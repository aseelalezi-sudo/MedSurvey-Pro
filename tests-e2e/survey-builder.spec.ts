import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Login directly without helper to avoid session issues between tests.
 */
async function loginDirect(page: import('@playwright/test').Page) {
  execFileSync('php', ['artisan', 'cache:clear'], { stdio: 'inherit' });

  await page.goto('/login');

  await page.fill('input[name="username"]', process.env.TEST_ADMIN_USERNAME ?? 'super_admin');
  await page.fill('input[name="password"]', process.env.TEST_ADMIN_PASSWORD ?? 'Password123!');

  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 10000 });
}

async function openSurveysPage(page: import('@playwright/test').Page) {
  await page.goto('/dashboard/surveys');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toBeVisible();
  await expect(page).toHaveURL(/\/dashboard\/surveys/, { timeout: 10000 });
  await expect(page.locator('body')).not.toContainText('Server Error');
}

function createSurveyButton(page: import('@playwright/test').Page) {
  return page.getByRole('button', { name: /إضافة استبيان جديد|Create New Survey/ }).first();
}

const receptionTemplateName = /رضا الاستقبال|Reception Satisfaction|Reception/;
const fullTemplateName = /استبيان شامل|Comprehensive Survey|Full Survey/;

test.describe('Survey Builder - Desktop (1440×900)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('surveys management page loads', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await expect(page.locator('body')).toContainText(/إدارة وتصميم الاستبيانات|Surveys Management/, {
      timeout: 10000,
    });
  });

  test('open create survey modal', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await createSurveyButton(page).click();

    await expect(page.locator('body')).toContainText(/إنشاء استبيان جديد|Create New Survey/, {
      timeout: 10000,
    });
  });

  test('survey builder has template buttons when no sections', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await createSurveyButton(page).click();

    await expect(page.locator('body')).toContainText(/قوالب جاهزة|Templates/, {
      timeout: 10000,
    });

    await expect(page.locator('body')).toContainText(receptionTemplateName);
    await expect(page.locator('body')).toContainText(fullTemplateName);
  });

  test('load reception template from survey builder', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await createSurveyButton(page).click();

    await expect(page.locator('body')).toContainText(/إنشاء استبيان جديد|Create New Survey/, {
      timeout: 10000,
    });

    await page.getByRole('button', { name: receptionTemplateName }).first().click();

    await expect(page.locator('body')).toContainText(/تقييم خدمة الاستقبال|Reception Service/, {
      timeout: 5000,
    });
  });

  test('load full survey template then preview it', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await createSurveyButton(page).click();

    await expect(page.locator('body')).toContainText(/إنشاء استبيان جديد|Create New Survey/, {
      timeout: 10000,
    });

    await page.getByRole('button', { name: fullTemplateName }).first().click();

    await expect(page.locator('body')).toContainText(/خدمة الاستقبال|Reception Service|Reception/, {
      timeout: 5000,
    });

    await page
      .getByRole('button', { name: /معاينة|Preview/ })
      .first()
      .click();

    await expect(page.locator('body')).toContainText(/معاينة الاستبيان|Survey Preview/, {
      timeout: 5000,
    });

    await page.getByRole('button', { name: /إغلاق المعاينة|Close Preview/ }).click();
  });
});

test.describe('Survey Builder - Mobile (390×844)', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('surveys page loads on mobile', async ({ page }) => {
    await loginDirect(page);
    await openSurveysPage(page);

    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('Server Error');
  });
});
