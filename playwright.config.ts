import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Testing Configuration
 */
export default defineConfig({
  testDir: './tests-e2e',
  globalTeardown: './tests-e2e/global-teardown.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:9000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    locale: 'ar-SA',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],
  // Start the dev server and backend before running tests
  // Note: For E2E tests with real backend, ensure MySQL and Redis are running
  // Or use npm run test:e2e:static for UI-only tests without backend
  webServer: process.env.SKIP_PLAYWRIGHT_WEBSERVER
    ? undefined
    : process.env.CI
    ? {
        command: 'npm run build && php artisan serve --host=127.0.0.1 --port=9000',
        url: 'http://127.0.0.1:9000',
        reuseExistingServer: false,
        timeout: 120 * 1000,
      }
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=9000',
        url: 'http://127.0.0.1:9000',
        reuseExistingServer: !process.env.CI,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 500 },
        timeout: 120 * 1000,
      },
});
