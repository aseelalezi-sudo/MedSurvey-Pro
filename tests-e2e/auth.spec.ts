import { test, expect } from '@playwright/test';

test('should show login page correctly', async ({ page }) => {
  await page.goto('/#/login', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'MedSurvey Pro' })).toBeVisible();
  await expect(page.getByTestId('login-title')).toBeVisible();
  await expect(page.getByTestId('login-username')).toBeVisible();
  await expect(page.getByTestId('login-password')).toBeVisible();
});

test('should login with valid credentials', async ({ page }) => {
  await page.goto('/#/login', { waitUntil: 'domcontentloaded' });

  await page.getByTestId('login-username').fill(process.env.TEST_ADMIN_USERNAME || 'admin');
  await page.getByTestId('login-password').fill(process.env.TEST_ADMIN_PASSWORD || process.env.SEED_ADMIN_PW || 'admin123');
  await page.getByTestId('login-submit').click();

  await expect(page).toHaveURL(/\/dashboard/, { timeout: 20000 });
});

test('should show error for invalid credentials', async ({ page }) => {
  await page.goto('/#/login', { waitUntil: 'domcontentloaded' });

  await page.getByTestId('login-username').fill('wronguser');
  await page.getByTestId('login-password').fill('wrongpass');
  await page.getByTestId('login-submit').click();

  await expect(page.getByTestId('login-error')).toBeVisible({ timeout: 15000 });
});
