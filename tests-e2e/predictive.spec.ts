import { expect, test } from '@playwright/test';

async function loginAsAdmin(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', 'super_admin');
  await page.fill('input[name="password"]', 'Password123!');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
}

test.describe('Predictive warning actions', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('take action opens and closes the action plan modal', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/dashboard/predictive');
    await expect(page.locator('body')).not.toContainText('Server Error');

    const actionButton = page.locator('[data-predictive-action-button]').first();
    const actionButtonCount = await actionButton.count();

    test.skip(actionButtonCount === 0, 'No active predictive alert exists in the seeded data.');

    await actionButton.click();

    const modal = page.locator('#predictive-action-modal');
    const panel = page.locator('[data-predictive-action-panel]');
    await expect(modal).toBeVisible();
    await expect(modal).toHaveAttribute('aria-hidden', 'false');
    await expect(panel).toBeVisible();
    await expect(page.locator('[data-predictive-plan-input="department"]')).not.toHaveValue('');

    await page.locator('[data-predictive-action-close]').first().click();
    await expect(modal).toHaveAttribute('aria-hidden', 'true');
  });
});
