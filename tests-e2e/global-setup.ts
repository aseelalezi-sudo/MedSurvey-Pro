import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import { closeSync, mkdirSync, openSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const serverHost = '127.0.0.1:9100';
const serverUrl = `http://${serverHost}`;
const pidFile = resolve('.e2e-server.pid');
const serverLogFile = resolve('storage/logs/e2e-server.log');

/**
 * Tail of the isolated PHP server output. Appended to setup failures so a server
 * stuck on a 5xx response (unsafe route, fatal error, port already in use) can be
 * diagnosed from the Playwright error alone.
 */
function formatServerOutput(): string {
  try {
    const output = readFileSync(serverLogFile, 'utf8').trim();

    if (output === '') return '';

    const tail = output.length > 4000 ? output.slice(-4000) : output;

    return `\n\nPHP server output (${serverLogFile}):\n${tail}`;
  } catch {
    return '';
  }
}

async function waitForServer(server: ChildProcess): Promise<void> {
  const deadline = Date.now() + 30_000;
  let lastStatus = 'no response';
  let lastError = 'no response';

  while (Date.now() < deadline) {
    if (server.exitCode !== null) {
      throw new Error(
        `E2E server exited with code ${server.exitCode} before becoming ready at ${serverUrl}.${formatServerOutput()}`,
      );
    }

    try {
      const response = await fetch(serverUrl);
      lastStatus = String(response.status);
      if (response.status < 500) return;
    } catch (error) {
      lastError = error instanceof Error ? error.message : String(error);
    }

    await new Promise((resolveDelay) => setTimeout(resolveDelay, 250));
  }

  throw new Error(
    `E2E server did not become ready at ${serverUrl} (last status: ${lastStatus}, last error: ${lastError}).${formatServerOutput()}`,
  );
}

export default async function globalSetup() {
  const database = process.env.DB_DATABASE ?? '';

  if (process.env.APP_ENV !== 'e2e' || !database.endsWith('_e2e')) {
    throw new Error(
      `Refusing to prepare E2E data outside an isolated *_e2e database (APP_ENV=${process.env.APP_ENV}, DB_DATABASE=${database}).`,
    );
  }

  if (process.env.E2E_SKIP_CACHE_CLEAR !== 'true') {
    execFileSync('php', ['artisan', 'cache:clear'], { stdio: 'inherit' });
  }

  if (process.env.E2E_SKIP_DB_SEED !== 'true') {
    execFileSync('php', ['artisan', 'migrate:fresh', '--force'], { stdio: 'inherit' });
    execFileSync('php', ['artisan', 'db:seed', '--class=E2ePredictiveSeeder', '--force'], {
      stdio: 'inherit',
    });
  }

  if (process.env.SKIP_PLAYWRIGHT_WEBSERVER === 'true') return;

  try {
    await fetch(serverUrl);
    throw new Error(`Refusing to reuse an existing server at ${serverUrl}.`);
  } catch (error) {
    if (error instanceof Error && error.message.startsWith('Refusing')) throw error;
  }

  mkdirSync(dirname(serverLogFile), { recursive: true });
  writeFileSync(serverLogFile, `# php -S ${serverHost} -t public server.php\n`);

  const serverStdout = openSync(serverLogFile, 'a');
  const serverStderr = openSync(serverLogFile, 'a');

  const server = spawn('php', ['-S', serverHost, '-t', 'public', 'server.php'], {
    cwd: process.cwd(),
    env: process.env,
    stdio: ['ignore', serverStdout, serverStderr],
  });

  // The child keeps its own descriptors; the parent must not hold duplicates open.
  closeSync(serverStdout);
  closeSync(serverStderr);

  if (!server.pid) throw new Error('Failed to start the isolated E2E server.');

  mkdirSync(dirname(pidFile), { recursive: true });
  writeFileSync(pidFile, String(server.pid));
  await waitForServer(server);
}
