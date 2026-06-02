import { execFileSync } from 'node:child_process';

export default function globalSetup() {
  if (process.env.E2E_SKIP_CACHE_CLEAR !== 'true') {
    execFileSync('php', ['artisan', 'cache:clear'], { stdio: 'inherit' });
  }

  if (process.env.E2E_SKIP_DB_SEED !== 'true') {
    execFileSync('php', ['artisan', 'db:seed', '--class=E2ePredictiveSeeder', '--force'], {
      stdio: 'inherit',
    });
  }
}
