# MedSurvey Pro

MedSurvey Pro is a Laravel 12 application with a React/TypeScript SPA bundled through Vite.

## Stack

- Laravel API backend
- React 19 frontend in `resources/js`
- Vite with `laravel-vite-plugin`
- MySQL-compatible database
- PHPUnit, Vitest, and Playwright tests

## Local Setup

PHP, Composer, Node.js, and npm are required.

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

For frontend development with hot reload, run Vite in a second terminal:

```bash
npm run dev
```

## Tests

```bash
php artisan test
npm test
npx playwright test
```

See `INSTALL_REQUIREMENTS.md` for Windows/XAMPP notes and required PHP extensions.
