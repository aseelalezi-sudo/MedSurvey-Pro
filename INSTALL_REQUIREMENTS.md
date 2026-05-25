# MedSurvey Pro Installation Requirements

Install these tools before running MedSurvey Pro locally:

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
5. Node.js 20 or newer
6. npm

## Optional

1. Docker Desktop
2. Redis

Redis is optional. The current application can run with file/database cache so it works more easily on shared hosting.

## Commands After Installing PHP and Composer

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

For frontend hot reload during development, run this in a second terminal:

```bash
npm run dev
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

## Current Project Status

The project is now a Laravel application at the repository root. The React frontend lives in `resources/js` and is served through the Laravel Blade entry view in `resources/views/app.blade.php`.

Implemented application areas:

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
- backups
