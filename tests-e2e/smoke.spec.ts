import { test, expect } from '@playwright/test';

/**
 * Login helper using demo credentials from DemoDataSeeder.
 * All demo users share the same password: Password123!
 * Uses flexible selectors matching the Laravel Blade login form.
 */
async function loginAsAdmin(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', 'super_admin');
  await page.fill('input[name="password"]', 'Password123!');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
}

test.describe('Desktop Smoke Tests (1440×900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('home page loads', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
  });

  test('login page loads', async ({ page }) => {
    const response = await page.goto('/login');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('dashboard login with demo super_admin credentials', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="username"]', 'super_admin');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('Mobile Smoke Tests (390×844)', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('public survey selection page loads', async ({ page }) => {
    const response = await page.goto('/survey-selection');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
  });

  test('mobile dashboard page loads after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/dashboard');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Server Error');
  });

  test('mobile responses page loads after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/dashboard/responses');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Server Error');
  });

  test('mobile tickets page loads after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/dashboard/tickets');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Server Error');
  });
});
