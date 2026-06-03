# Foundation Audit - MedSurvey Pro

**Date:** 2026-06-03  
**Branch:** chore/foundation-audit  
**Purpose:** Technical foundation audit before starting cleanup, performance, and production-hardening work.

---

## 1. Official Stack Detected

The current official project stack is:

- Laravel 12 backend/web application.
- PHP 8.2+ runtime.
- Vite frontend bundling.
- Blade views.
- Alpine.js for frontend interactivity.
- TailwindCSS for styling.
- MySQL database.
- Laravel Reverb is present for realtime/broadcasting support.
- JWT Auth and Sanctum are present in Composer dependencies.

The active root project is not a Node/Express application.

---

## 2. Active Runtime Path

The production path should be treated as:

- Laravel application in the repository root.
- Routes are mainly managed through `routes/web.php`.
- `routes/api.php` currently contains only the health endpoint.
- Frontend assets are built through Vite from `resources/js` and `resources/css`.
- Docker runtime is based on PHP Apache, not Node Express.

Conclusion:

> Laravel is the official runtime path. Node/Express must not be treated as the current production backend.

---

## 3. Legacy / Suspicious Items

The following items are legacy candidates and should not be used as part of the active production path:

- `.kilo/worktrees/wide-jargon/`
- `.kilo/worktrees/wide-jargon/server/`
- `.kilo/worktrees/wide-jargon/package.json`
- `.kilo/worktrees/wide-jargon/docker-compose.yml`
- `.kilo/worktrees/wide-jargon/Dockerfile`
- `.kilo/worktrees/wide-jargon/start.bat`
- `.kilo/worktrees/wide-jargon/vite.config.ts`
- `.kilo/worktrees/wide-jargon/nginx.conf`
- `.kilo/worktrees/wide-jargon/playwright.config.ts`

These appear to represent an older React + Node/Express worktree and should remain outside the official Laravel production path.

No deletion is required in this step.

---

## 4. CI Consistency Notes

The current CI should be reviewed later for consistency between:

- Seeder-created users.
- `SUPER_ADMIN_USERNAME`
- `SUPER_ADMIN_PASSWORD`
- `TEST_ADMIN_USERNAME`
- `TEST_ADMIN_PASSWORD`
- Playwright E2E login data.

Potential issue:

> E2E credentials may not match the seeded admin credentials. This should be verified and fixed in a later step.

No CI changes are required in this step.

---

## 5. Technical Decision

The official project direction is:

> MedSurvey Pro will continue as a Laravel 12 Web Application with Vite, Blade, Alpine.js, TailwindCSS, MySQL, and optional Reverb support.

Legacy Node/React/Express worktree content should not be used in the current production path.

---

## 6. Do Not Change Yet

No source code was changed in this step.

The following were not modified:

- `composer.json`
- `package.json`
- `.github/workflows/ci.yml`
- `.gitignore`
- `.env.example`
- Docker files
- Laravel controllers
- Laravel routes
- Database migrations
- `.kilo/`
- backup files

Only this documentation file was created/updated:

- `docs/foundation-audit.md`
