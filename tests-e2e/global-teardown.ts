import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';

const pidFile = resolve('.e2e-server.pid');

export default function globalTeardown() {
  if (process.env.SKIP_PLAYWRIGHT_WEBSERVER === 'true' || !existsSync(pidFile)) return;

  const pid = Number.parseInt(readFileSync(pidFile, 'utf8').trim(), 10);

  try {
    if (Number.isInteger(pid) && pid > 0) {
      if (process.platform === 'win32') {
        try {
          execFileSync('taskkill', ['/PID', String(pid), '/T', '/F'], { stdio: 'ignore' });
        } catch {
          // The PHP server can already be gone when its parent setup process exits.
        }
      } else {
        process.kill(pid, 'SIGTERM');
      }
    }
  } finally {
    unlinkSync(pidFile);
  }
}
