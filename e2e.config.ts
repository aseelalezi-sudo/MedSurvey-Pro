import { defineConfig, devices } from '@playwright/test';

const e2eDatabase = process.env.E2E_DB_DATABASE ?? 'medsurvey_pro_e2e';

if (!e2eDatabase.endsWith('_e2e')) {
  throw new Error(`Refusing to run E2E tests against non-isolated database: ${e2eDatabase}`);
}

const e2eEnvironment = {
  ...process.env,
  APP_ENV: 'e2e',
  DB_DATABASE: e2eDatabase,
  LICENSING_ENABLED: 'false',
};

Object.assign(process.env, e2eEnvironment);

/**
 * Playwright E2E Testing Configuration
 */
export default defineConfig({
  testDir: './tests-e2e',
  globalSetup: './tests-e2e/global-setup.ts',
  globalTeardown: './tests-e2e/global-teardown.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: process.env.CI ? 'html' : 'list',
  use: {
    baseURL: 'http://127.0.0.1:9100',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
