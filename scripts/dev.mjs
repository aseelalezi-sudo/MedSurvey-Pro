import { spawn } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import process from 'node:process';

const isWindows = process.platform === 'win32';
const npmCommand = isWindows ? 'npm.cmd' : 'npm';
const apiPort = process.env.VITE_API_PORT || process.env.PORT || '4001';
const apiHealthUrl = `http://127.0.0.1:${apiPort}/api/health`;

let backendProcess;
let frontendProcess;
let shuttingDown = false;
const pidFile = '.tmp/dev-server-pids.json';

function writePidFile() {
  fs.mkdirSync('.tmp', { recursive: true });
  fs.writeFileSync(pidFile, JSON.stringify({
    parent: process.pid,
    backend: backendProcess?.pid || null,
    frontend: frontendProcess?.pid || null,
  }));
}

function log(scope, message) {
  process.stdout.write(`[${scope}] ${message}`);
}

function spawnNpm(scope, args, options = {}) {
  const command = isWindows ? (process.env.ComSpec || 'cmd.exe') : npmCommand;
  const commandArgs = isWindows ? ['/d', '/s', '/c', [npmCommand, ...args].join(' ')] : args;
  const child = spawn(command, commandArgs, {
    stdio: ['inherit', 'pipe', 'pipe'],
    shell: false,
    ...options,
  });

  child.stdout.on('data', data => log(scope, data.toString()));
  child.stderr.on('data', data => log(scope, data.toString()));
  writePidFile();

  return child;
}

function requestHealth() {
  return new Promise(resolve => {
    const req = http.get(apiHealthUrl, res => {
      res.resume();
      resolve(res.statusCode && res.statusCode >= 200 && res.statusCode < 300);
    });

    req.on('error', () => resolve(false));
    req.setTimeout(1000, () => {
      req.destroy();
      resolve(false);
    });
  });
}

async function waitForBackend(timeoutMs = 60000) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (await requestHealth()) return true;
    await new Promise(resolve => setTimeout(resolve, 750));
  }

  return false;
}

function stopProcess(child) {
  if (child && !child.killed) {
    if (isWindows) {
      spawn('taskkill', ['/pid', String(child.pid), '/T', '/F'], {
        stdio: 'ignore',
        shell: false,
      });
      return;
    }

    child.kill('SIGTERM');
  }
}

function shutdown(code = 0) {
  if (shuttingDown) return;
  shuttingDown = true;
  stopProcess(frontendProcess);
  stopProcess(backendProcess);
  process.exit(code);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

const backendAlreadyHealthy = await requestHealth();

if (backendAlreadyHealthy) {
  console.log(`[dev] Backend API is already healthy at ${apiHealthUrl}`);
  writePidFile();
} else {
  console.log(`[dev] Starting backend API and waiting for ${apiHealthUrl}`);
  backendProcess = spawnNpm('backend', ['run', 'dev'], { cwd: 'server' });

  backendProcess.on('exit', code => {
    if (!shuttingDown) {
      console.error(`[dev] Backend stopped before the frontend session ended. Exit code: ${code}`);
      shutdown(code || 1);
    }
  });

  const ready = await waitForBackend();
  if (!ready) {
    console.error(`[dev] Backend did not become healthy within 60 seconds: ${apiHealthUrl}`);
    shutdown(1);
  }
}

console.log('[dev] Backend is healthy. Starting Vite frontend...');
frontendProcess = spawnNpm('frontend', ['run', 'dev:frontend']);
writePidFile();

frontendProcess.on('exit', code => {
  if (!shuttingDown) shutdown(code || 0);
});
