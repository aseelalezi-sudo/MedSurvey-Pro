import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import process from 'node:process';

const isWindows = process.platform === 'win32';
const npxCommand = isWindows ? 'npx.cmd' : 'npx';
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:3000';
const pidFile = '.tmp/dev-server-pids.json';

function requestHealth(url) {
  return new Promise(resolve => {
    const req = http.get(url, res => {
      res.resume();
      resolve(res.statusCode && res.statusCode >= 200 && res.statusCode < 500);
    });
    req.on('error', () => resolve(false));
    req.setTimeout(1000, () => {
      req.destroy();
      resolve(false);
    });
  });
}

async function waitForFrontend(timeoutMs = 120000) {
  const startedAt = Date.now();
  while (Date.now() - startedAt < timeoutMs) {
    if (await requestHealth(baseUrl)) return true;
    await new Promise(resolve => setTimeout(resolve, 750));
  }
  return false;
}

function killPid(pid) {
  if (!pid || pid === process.pid) return;
  if (isWindows) {
    spawnSync('taskkill', ['/pid', String(pid), '/T', '/F'], { stdio: 'ignore' });
    return;
  }
  try {
    process.kill(pid, 'SIGTERM');
  } catch {
    // Already exited.
  }
}

function stopDevServer(devServer) {
  if (fs.existsSync(pidFile)) {
    try {
      const pids = Object.values(JSON.parse(fs.readFileSync(pidFile, 'utf8')));
      for (const pid of pids) {
        if (typeof pid === 'number') killPid(pid);
      }
    } catch {
      // Fall back to the direct child below.
    }
  }
  killPid(devServer.pid);
}

const devServer = spawn(process.execPath, ['scripts/dev.mjs'], {
  stdio: ['ignore', 'inherit', 'inherit'],
  env: process.env,
});

const ready = await waitForFrontend();
if (!ready) {
  stopDevServer(devServer);
  console.error(`[e2e] Frontend did not become ready within 120 seconds: ${baseUrl}`);
  process.exit(1);
}

const testArgs = ['playwright', 'test', ...process.argv.slice(2)];
const result = spawnSync(npxCommand, testArgs, {
  stdio: 'inherit',
  env: { ...process.env, SKIP_PLAYWRIGHT_WEBSERVER: '1' },
  shell: isWindows,
});

stopDevServer(devServer);
if (result.error) {
  console.error('[e2e] Failed to start Playwright:', result.error);
}
process.exit(result.status ?? 1);
