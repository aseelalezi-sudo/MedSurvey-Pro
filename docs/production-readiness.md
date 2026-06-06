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

---

## Deployment Commands

### 1. Server Preparation

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql \
  php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath \
  composer nodejs npm supervisor cron
```

### 2. Application Setup

```bash
cd /var/www
sudo git clone https://github.com/aseelalezi-sudo/MedSurvey-Pro.git medsurvey
cd medsurvey
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
# Edit .env with production values (see section above)
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\E2ePredictiveSeeder --force
```

### 3. Permission Hardening

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache
```

### 4. Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.example;

    ssl_certificate     /etc/letsencrypt/live/your-domain.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.example/privkey.pem;

    root /var/www/medsurvey/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 5. Finalise

```bash
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

---

## Health Check

After deployment, verify the application is running:

```bash
# Application health endpoint
curl -s https://your-domain.example/health | jq .

# Expected response (200 OK):
# {"status":"ok","timestamp":"..."}

# PHP-FPM status
sudo systemctl status php8.3-fpm --no-pager

# Nginx status
sudo systemctl status nginx --no-pager

# Database connection
php artisan tinker --execute="DB::connection()->getPdo();"
```

---

## Queue Worker Setup

### Supervisor Configuration

Create `/etc/supervisor/conf.d/medsurvey-worker.conf`:

```ini
[program:medsurvey-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/medsurvey/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/medsurvey-worker.log
stopwaitsecs=3600
```

### Start Worker

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start medsurvey-worker:*
```

---

## Scheduler Setup

Add the following cron entry for the `www-data` user:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/medsurvey && php artisan schedule:run >> /dev/null 2>&1
```

---

## Backup Safety Rules

1. **Backup directory** must be outside the web root (configured in `.env`).
2. **mysqldump binary** must be installed on the server for backup creation.
3. **Off-server backups** are recommended (rsync / S3 / SFTP).
4. **Restore is disabled by default** (`DB_BACKUP_RESTORE_ENABLED=false`). Only enable temporarily during verified recovery.
5. **External restore** is guarded by the `admin` middleware.
6. **Filename validation** rejects dangerous paths (directory traversal, null bytes).
7. Do **not** expose backup download links publicly.
8. Consider encrypting backup archives before transferring off-server.

---

## Post-Deployment Checklist

- [ ] `.env` contains production values (APP_ENV, APP_DEBUG, APP_URL, DB, JWT).
- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] `SESSION_SECURE_COOKIE=true` and HTTPS is enforced.
- [ ] Database migrated and seeded (`php artisan migrate --force`).
- [ ] Storage linked (`php artisan storage:link`).
- [ ] Application key generated (`php artisan key:generate`).
- [ ] Queue worker running (`supervisorctl status`).
- [ ] Cron entry added for scheduler (`crontab -l`).
- [ ] Health endpoint returns `{"status":"ok"}`.
- [ ] Let's Encrypt SSL certificate active.
- [ ] Firewall allows only ports 80, 443, and SSH.
- [ ] File permissions: `storage/` and `bootstrap/cache/` owned by `www-data`.
- [ ] Production frontend assets built (`npm run build`).
- [ ] JWT secret changed from default.
- [ ] Super admin password changed from default.

---

## Rollback Plan

If a deployment introduces issues:

```bash
# 1. Identify the previous working commit
git log --oneline -5

# 2. Revert to the previous tag or commit
git reset --hard <previous-stable-hash>

# 3. Rebuild assets
npm ci && npm run build

# 4. Re-migrate if necessary (restore previous schema)
php artisan migrate:rollback --force
php artisan migrate --force

# 5. Reload services
sudo supervisorctl restart medsurvey-worker:*
sudo systemctl reload nginx

# 6. Restore database from backup if data corruption occurred
#    (Only if DB_BACKUP_RESTORE_ENABLED=true temporarily)
php artisan backup:restore --filename=<backup-file>
```

---

## Windows Local Testing Note

This application is developed on Windows. Local testing uses:

- **Laravel Herd**, **XAMPP**, or **Laragon** for PHP/MySQL.
- **CMD** (`&&` chaining is not supported — use `;` or run commands individually).
- **PowerShell** (`npm run test`, `php artisan test`).

Before pushing:

- Run `composer run pint:test` — Laravel Pint style check.
- Run `php artisan test` — Full Laravel test suite.
- Run `npm run test` — Frontend Vitest suite.
- Run `npm run build` — Production frontend build.

Ensure file permissions and path separators are tested on the target Linux server, not locally.
