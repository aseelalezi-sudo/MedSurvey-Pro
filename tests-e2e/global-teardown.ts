import { spawnSync } from 'node:child_process';
import fs from 'node:fs';

export default async function globalTeardown() {
  const pidFile = '.tmp/dev-server-pids.json';
  if (!fs.existsSync(pidFile)) return;

  const raw = fs.readFileSync(pidFile, 'utf8');
  const pids = Object.values(JSON.parse(raw) as Record<string, number | null>)
    .filter((pid): pid is number => typeof pid === 'number' && pid > 0 && pid !== process.pid);

  for (const pid of pids) {
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/pid', String(pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
      try {
        process.kill(pid, 'SIGTERM');
      } catch {
        // Process already exited.
      }
    }
  }
}
