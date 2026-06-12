# Playwright E2E Testing

## Prerequisites

- PHP 8.2+ with all extensions
- Node.js + npm
- MySQL/MariaDB database running

## Local Run Sequence

```bash
# 1. Prepare database with demo data
php artisan migrate:fresh --seed

# 2. Build frontend assets
npm run build

# 3. Install Chromium browser (one-time)
npx playwright install chromium

# 4. Run E2E tests (headless)
npm run test:e2e
```

## Running Tests

```bash
# Headless mode (default)
npm run test:e2e

# With visible browser window
npx playwright test -c e2e.config.ts --headed
```

## Smoke Tests Included

- `Desktop Smoke Tests (1440×900)`:
  - home page loads
  - login page loads (form fields visible)
  - dashboard login with demo `super_admin` credentials

- `Mobile Smoke Tests (390×844)`:
  - public survey selection page loads
  - mobile dashboard after login
  - mobile responses page after login
  - mobile tickets page after login

## Demo Credentials

| Role          | Username         | Password       |
| ------------- | ---------------- | -------------- |
| Super Admin   | `super_admin`    | `Password123!` |
| Admin         | `admin`          | `Password123!` |
| Unit Manager  | `unit_manager`   | `Password123!` |
| HOD Emergency | `head_emergency` | `Password123!` |
| Staff         | `staff`          | `Password123!` |

## Notes

- Tests run against Chromium only (Firefox/WebKit require separate `npx playwright install`).
- The E2E config starts `php artisan serve` automatically on port 9000.
- Ensure database is seeded before running tests.
- Setting `SKIP_PLAYWRIGHT_WEBSERVER=true` skips the built-in web server (use your own).
