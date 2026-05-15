import { test, expect } from '@playwright/test';

test('should show login page correctly', async ({ page }) => {
  // Increase timeout and wait for network to be idle
  await page.goto('/login', { waitUntil: 'networkidle', timeout: 60000 });
  
  // Wait for any element to appear to ensure page is rendering
  await page.waitForSelector('h1');
  
  // The system title is in an H1
  await expect(page.locator('h1')).toContainText('MedSurvey Pro');
  
  // The login card title (Wait for it specifically)
  const loginTitle = page.getByText('تسجيل الدخول');
  await expect(loginTitle).toBeVisible({ timeout: 15000 });
});

test('should login with valid credentials', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });
  
  // Fill credentials
  await page.locator('#username').fill('admin');
  await page.locator('#password').fill('admin123');
  
  // Click login button
  await page.locator('button[type="submit"]').click();
  
  // Wait for navigation or dashboard content
  await expect(page).toHaveURL('/', { timeout: 20000 });
  
  // Verify dashboard by looking for the AI status text
  // Using a substring to be safe with translations/rendering
  await expect(page.getByText('نظام التنبؤ')).toBeVisible({ timeout: 15000 });
});

test('should show error for invalid credentials', async ({ page }) => {
  await page.goto('/login', { waitUntil: 'networkidle' });
  
  await page.locator('#username').fill('wronguser');
  await page.locator('#password').fill('wrongpass');
  await page.locator('button[type="submit"]').click();
  
  // Should show error message
  await expect(page.locator('text=اسم المستخدم أو كلمة المرور غير صحيحة')).toBeVisible({ timeout: 15000 });
});
