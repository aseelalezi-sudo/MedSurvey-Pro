# MedSurvey Pro Laravel API

This directory is the new Laravel backend target for MedSurvey Pro.

The existing Node/Express backend in `../server` should remain untouched until this API reaches feature parity.

## Local Setup

PHP and Composer are required:

```bash
cd laravel-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

The React frontend can continue to run from the project root.

See `INSTALL_REQUIREMENTS.md` for the full list of required tools and PHP extensions.
