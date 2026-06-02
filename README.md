# MedSurvey Pro

MedSurvey Pro is a Laravel-based patient satisfaction platform for healthcare teams. It helps hospitals and medical centers publish surveys, collect patient feedback, monitor low-score responses, manage tickets, and review operational analytics from a secure administrative dashboard.

The project currently uses a server-rendered Laravel Blade interface enhanced with Alpine.js, Vite, Tailwind CSS, ApexCharts, Lucide icons, Laravel Reverb, and a Progressive Web App setup.

## Key Features

- Patient-facing survey flow with Arabic and English support.
- Dynamic survey management with sections, questions, assignments, activation, duplication, and protected deletion rules.
- Response dashboard with filters, exports, detailed patient questionnaire views, and low-score reason visibility.
- Automatic ticket creation for low satisfaction responses, with status and priority tracking.
- Predictive early-warning screen with action-plan modal support.
- Reports and analytics dashboards with charts and department-level indicators.
- Hall of fame view for high-performing departments and staff.
- Role-based dashboard access for super admins, admins, unit managers, department heads, and staff.
- Audit logging for sensitive dashboard actions.
- Backup management with create, verify, download, upload, restore, external scan, and safety checks.
- Monitoring, error-log management, and operational health views.
- Progressive Web App support for browser installation on localhost or HTTPS.
- Local font and icon bundling to reduce dependence on external network resources.

## Technology Stack

| Area | Stack |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12, Laravel Sanctum, JWT Auth |
| Realtime | Laravel Reverb, Laravel Echo, Pusher protocol |
| Frontend | Blade, Alpine.js, TypeScript, Vite 7, Tailwind CSS 4 |
| Charts and UI | ApexCharts, Lucide icons |
| Data export | ExcelJS, jsPDF, jsPDF AutoTable, FileSaver |
| Testing | PHPUnit, Vitest, Playwright |
| Code quality | ESLint, Prettier, Laravel Pint |

## Project Structure

```text
app/
  Http/Controllers/Web/     Dashboard, survey, analytics, tickets, backups, users
  Models/                   Application domain models
  Services/                 Shared business and infrastructure services

database/
  migrations/               Database schema changes
  seeders/                  Demo and baseline data
  factories/                Test data factories

resources/
  views/                    Blade pages and layouts
  js/                       TypeScript entrypoint and browser helpers
  css/                      Tailwind and application styles
  lang/                     Arabic and English translation files

routes/
  web.php                   Public survey and dashboard routes
  api.php                   API routes, if used by integrations
  console.php               Scheduled jobs and console wiring

tests/
  Feature/                  Laravel feature and security tests
  Unit/                     Unit tests

docs/
  demo-data.md              Demo accounts and seeded data reference
  e2e-testing.md            End-to-end testing notes
  technical-audit-*.md      Technical audit reports and remediation notes
```

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 20 or newer
- npm
- MySQL or MariaDB
- A local web server such as Laravel's built-in server, XAMPP, Laragon, or a production-ready PHP stack

## Local Setup

Clone the repository and install dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure the database connection in `.env`, then run migrations and seeders:

```bash
php artisan migrate --seed
```

Build frontend assets:

```bash
npm run build
```

Start the Laravel server:

```bash
php artisan serve
```

For frontend development with hot reload, run Vite in a separate terminal:

```bash
npm run dev
```

If realtime features are enabled in your environment, start Reverb in another terminal:

```bash
php artisan reverb:start
```

Demo accounts and seeded sample data are documented in [docs/demo-data.md](docs/demo-data.md).

## Testing and Quality Checks

Run the Laravel feature test suite:

```bash
php artisan test
```

Run frontend/unit tests:

```bash
npm test
```

Run end-to-end tests:

```bash
npm run test:e2e
```

Run JavaScript and TypeScript linting:

```bash
npm run lint
```

Run a production build:

```bash
npm run build
```

Optional PHP formatting checks:

```bash
composer pint:test
```

## Progressive Web App

The application includes PWA support through `vite-plugin-pwa`.

To make the browser installation option appear:

- Open the site on `localhost` or through HTTPS.
- Ensure `npm run build` has generated `public/sw.js` and `public/build/manifest.webmanifest`.
- Clear old service worker registrations if the browser previously cached an older build.
- Reload the page after the service worker becomes active.

## Security Notes

- Keep `.env` out of version control.
- Rotate demo passwords before using the project outside a local environment.
- Restrict backup restore, deletion, and external scan actions to trusted administrative users.
- Review audit logs after sensitive administrative actions.
- Use HTTPS in production, especially for authentication, PWA installation, and realtime features.

## Deployment Checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure database, mail, queue, cache, session, Reverb, and backup settings.
- Run `composer install --no-dev --optimize-autoloader`.
- Run `npm ci` and `npm run build`.
- Run migrations with the intended production strategy.
- Configure the web server to serve the Laravel `public` directory.
- Configure `php artisan schedule:run` through cron or the hosting scheduler.
- Configure queue workers if queued jobs are enabled.
- Confirm role access, backup permissions, and audit logging before opening the dashboard to users.

## License

This project is released under the MIT license.
