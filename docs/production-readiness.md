# Production Readiness - MedSurvey Pro

## Current Stable Baseline

The current `main` branch has passed:

- Laravel Pint.
- Full Laravel test suite.
- Frontend Vitest suite.
- Production frontend build.
- GitHub Actions CI.
- E2E browser smoke tests.

Recent completed improvements include:

- Hardened external backup restore.
- Safe archive chunking with `chunkById`.
- Tenant-aware survey department validation.
- Cleaned corrupted analytics localization labels.
- Enforced LF line endings using `.gitattributes`.
- Aggregated monthly report trends.
- Restored monthly NPS trend calculation.
- Aggregated department report trends.
- Split CI into backend, frontend, and E2E jobs.

---

## Recommended Production Target

Recommended first production environment:

- Ubuntu Server LTS.
- Nginx.
- PHP-FPM.
- MySQL 8.
- Composer.
- Node.js for frontend build only.
- Supervisor for queue workers.
- Cron for Laravel scheduler.
- Let's Encrypt SSL.
- Off-server backups.

Docker can be introduced later after the direct deployment path is verified.

---

## Required Production Environment Values

Production `.env` must include:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Aden

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=20
SESSION_EXPIRE_ON_CLOSE=true
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medsurvey
DB_USERNAME=medsurvey
DB_PASSWORD=CHANGE_ME

JWT_SECRET=CHANGE_ME
SUPER_ADMIN_USERNAME=admin
SUPER_ADMIN_PASSWORD=CHANGE_ME_STRONG_PASSWORD

DB_BACKUP_RESTORE_ENABLED=false
HASH_VERIFY=true
```
