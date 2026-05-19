import { test, expect } from '@playwright/test';

test.describe('Survey Flow (Guest)', () => {
  test('should display landing page with start survey button', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'MedSurvey Pro' })).toBeVisible();
    await expect(page.getByTestId('start-survey')).toBeVisible();
  });

  test('should navigate to survey selection when clicking start', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await page.getByTestId('start-survey').click();
    await page.waitForURL(/\/survey-selection|\/survey\/info/, { timeout: 15000 });
  });

  test('should show a usable survey entry point', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await page.getByTestId('start-survey').click();
    await page.waitForURL(/\/survey-selection|\/survey\/info/, { timeout: 15000 });
    await expect(page.locator('body')).toBeVisible();
  });

  test('should show language switcher and toggle language', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const switcher = page.getByTestId('language-switcher');
    await expect(switcher).toBeVisible();

    const currentTitle = await switcher.getAttribute('title');
    await switcher.click();
    await expect.poll(() => switcher.getAttribute('title')).not.toBe(currentTitle);
  });
});

test.describe('Responsive Design', () => {
  test('should render mobile layout at 375px width', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('body')).toBeVisible();
    await expect(page.getByTestId('start-survey')).toBeVisible();
  });

  test('should render tablet layout at 768px width', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('body')).toBeVisible();
    await expect(page.getByTestId('start-survey')).toBeVisible();
  });
});
