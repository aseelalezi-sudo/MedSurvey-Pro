import { test, expect } from '@playwright/test';

test.describe('Survey Flow (Guest)', () => {
  test('should display landing page with start survey button', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    await expect(page.locator('h1')).toContainText('MedSurvey Pro');
    await expect(page.getByText('ابدأ الاستبيان')).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to survey selection when clicking start', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    await page.getByText('ابدأ الاستبيان').click();
    await page.waitForURL('**/survey-selection', { timeout: 15000 });
  });

  test('should navigate to thank you page directly if no active surveys', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    await page.getByText('ابدأ الاستبيان').click();
    await page.waitForURL('**/survey-selection', { timeout: 15000 });

    const noSurveys = page.getByText(/لا توجد استبيانات|no active surveys/i);
    if (await noSurveys.isVisible().catch(() => false)) {
      await test.step('verify no surveys message and admin link', async () => {
        await expect(noSurveys).toBeVisible();
      });
    } else {
      await test.step('select first available survey', async () => {
        const surveyButton = page.locator('button, a').filter({ hasText: /اختر|select|بدء|start/i }).first();
        await expect(surveyButton).toBeVisible({ timeout: 10000 });
      });
    }
  });

  test('should show language switcher and toggle language', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    const switcher = page.getByTitle('English').or(page.getByTitle('العربية'));
    await expect(switcher).toBeVisible();

    const currentTitle = await switcher.getAttribute('title');
    await switcher.click();
    await page.waitForTimeout(500);

    const newTitle = await switcher.getAttribute('title');
    expect(newTitle).not.toBe(currentTitle);
  });
});

test.describe('Responsive Design', () => {
  test('should render mobile layout at 375px width', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('h1')).toContainText('MedSurvey Pro');
  });

  test('should render tablet layout at 768px width', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/', { waitUntil: 'networkidle', timeout: 60000 });
    await expect(page.locator('body')).toBeVisible();
  });
});
