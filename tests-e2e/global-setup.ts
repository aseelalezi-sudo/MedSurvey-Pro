import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const serverUrl = 'http://127.0.0.1:9100';
const pidFile = resolve('.e2e-server.pid');

async function waitForServer(): Promise<void> {
  const deadline = Date.now() + 30_000;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(serverUrl);
      if (response.status < 500) return;
    } catch {
      // The server may still be starting.
    }

    await new Promise((resolveDelay) => setTimeout(resolveDelay, 250));
  }

  throw new Error(`E2E server did not become ready at ${serverUrl}.`);
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

  const server = spawn('php', ['-S', '127.0.0.1:9100', '-t', 'public', 'server.php'], {
    cwd: process.cwd(),
    env: process.env,
    stdio: 'ignore',
  });

  if (!server.pid) throw new Error('Failed to start the isolated E2E server.');

  mkdirSync(dirname(pidFile), { recursive: true });
  writeFileSync(pidFile, String(server.pid));
  await waitForServer();
}
