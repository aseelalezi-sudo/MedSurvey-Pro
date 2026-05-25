# Laravel API Installation Requirements

Install these tools before running the Laravel backend locally:

## Required

1. PHP 8.2 or newer
2. Composer 2.x
3. PHP extensions:
   - openssl
   - pdo
   - pdo_mysql
   - mbstring
   - tokenizer
   - xml
   - ctype
   - json
   - bcmath
   - curl
   - fileinfo
4. MySQL 8.0 or compatible

## Optional

1. Docker Desktop
2. Redis

Redis is optional for the Laravel migration path. The current Laravel scaffold uses file/database cache so it can run on shared hosting more easily.

## Commands After Installing PHP and Composer

```bash
cd laravel-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

## XAMPP Notes On Windows

This project has been tested locally with:

```text
C:\xampp\php\php.exe
C:\ProgramData\ComposerSetup\bin\composer.phar
```

If `php` or `composer` are not available directly from PowerShell, either add these folders to PATH:

```text
C:\xampp\php
C:\ProgramData\ComposerSetup\bin
```

or run commands with the full paths:

```powershell
C:\xampp\php\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Composer requires the PHP `zip` extension. In XAMPP, enable it in:

```text
C:\xampp\php\php.ini
```

Make sure this line is not commented:

```ini
extension=zip
```

Then update the React environment if needed:

```text
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

## Current Migration Status

Implemented as an initial Laravel API scaffold:

- health
- auth
- settings
- surveys
- responses
- tickets
- users
- audit
- error logs
- monitoring

Pending production-grade completion:

- full dashboard statistics parity
- full predictive analysis parity
- production backup/restore implementation
- full audit-log parity
- request rate limiting
- final frontend compatibility test
