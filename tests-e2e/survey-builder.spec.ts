import { test, expect } from '@playwright/test';

/**
 * Login directly without helper to avoid session issues between tests.
 */
async function loginDirect(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', 'super_admin');
  await page.fill('input[name="password"]', 'Password123!');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle', { timeout: 30000 });
  await page.waitForTimeout(1000);
}

test.describe('Survey Builder - Desktop (1440×900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('surveys management page loads', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('text=إدارة وتصميم الاستبيانات')).toBeVisible({ timeout: 10000 });
  });

  test('open create survey modal', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await page.locator('button:has-text("إضافة استبيان جديد")').first().click();
    await expect(page.locator('text=إنشاء استبيان جديد')).toBeVisible({ timeout: 10000 });
  });

  test('survey builder has template buttons when no sections', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await page.locator('button:has-text("إضافة استبيان جديد")').first().click();
    await expect(page.locator('text=إنشاء استبيان جديد')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=قوالب جاهزة')).toBeVisible();
    await expect(page.locator('text=رضا الاستقبال')).toBeVisible();
    await expect(page.locator('text=استبيان شامل')).toBeVisible();
  });

  test('load reception template from survey builder', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await page.locator('button:has-text("إضافة استبيان جديد")').first().click();
    await expect(page.locator('text=إنشاء استبيان جديد')).toBeVisible({ timeout: 10000 });
    await page.locator('button:has-text("رضا الاستقبال")').first().click();
    await expect(page.locator('text=تقييم خدمة الاستقبال').first()).toBeVisible({ timeout: 5000 });
  });

  test('load full survey template then preview it', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await page.locator('button:has-text("إضافة استبيان جديد")').first().click();
    await expect(page.locator('text=إنشاء استبيان جديد')).toBeVisible({ timeout: 10000 });
    await page.locator('button:has-text("استبيان شامل")').first().click();
    await expect(page.locator('text=خدمة الاستقبال').first()).toBeVisible({ timeout: 5000 });
    await page.locator('button:has-text("معاينة")').first().click();
    await expect(page.locator('text=معاينة الاستبيان').first()).toBeVisible({ timeout: 5000 });
    await page.locator('button:has-text("إغلاق المعاينة")').click();
  });
});

test.describe('Survey Builder - Mobile (390×844)', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('surveys page loads on mobile', async ({ page }) => {
    await loginDirect(page);
    await page.goto('/dashboard/surveys');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Server Error');
  });
});
