# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth.spec.ts >> should login with valid credentials
- Location: tests-e2e\auth.spec.ts:18:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.fill: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('#username')

```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | test('should show login page correctly', async ({ page }) => {
  4  |   // Increase timeout and wait for network to be idle
  5  |   await page.goto('/login', { waitUntil: 'networkidle', timeout: 60000 });
  6  |   
  7  |   // Wait for any element to appear to ensure page is rendering
  8  |   await page.waitForSelector('h1');
  9  |   
  10 |   // The system title is in an H1
  11 |   await expect(page.locator('h1')).toContainText('MedSurvey Pro');
  12 |   
  13 |   // The login card title (Wait for it specifically)
  14 |   const loginTitle = page.getByText('تسجيل الدخول');
  15 |   await expect(loginTitle).toBeVisible({ timeout: 15000 });
  16 | });
  17 | 
  18 | test('should login with valid credentials', async ({ page }) => {
  19 |   await page.goto('/login', { waitUntil: 'networkidle' });
  20 |   
  21 |   // Fill credentials
> 22 |   await page.locator('#username').fill('admin');
     |                                   ^ Error: locator.fill: Test timeout of 30000ms exceeded.
  23 |   await page.locator('#password').fill('admin123');
  24 |   
  25 |   // Click login button
  26 |   await page.locator('button[type="submit"]').click();
  27 |   
  28 |   // Wait for navigation or dashboard content
  29 |   await expect(page).toHaveURL('/', { timeout: 20000 });
  30 |   
  31 |   // Verify dashboard by looking for the AI status text
  32 |   // Using a substring to be safe with translations/rendering
  33 |   await expect(page.getByText('نظام التنبؤ')).toBeVisible({ timeout: 15000 });
  34 | });
  35 | 
  36 | test('should show error for invalid credentials', async ({ page }) => {
  37 |   await page.goto('/login', { waitUntil: 'networkidle' });
  38 |   
  39 |   await page.locator('#username').fill('wronguser');
  40 |   await page.locator('#password').fill('wrongpass');
  41 |   await page.locator('button[type="submit"]').click();
  42 |   
  43 |   // Should show error message
  44 |   await expect(page.locator('text=اسم المستخدم أو كلمة المرور غير صحيحة')).toBeVisible({ timeout: 15000 });
  45 | });
  46 | 
```