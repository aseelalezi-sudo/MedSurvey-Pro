import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Testing Configuration
 */
export default defineConfig({
  testDir: './tests-e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:9000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
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
