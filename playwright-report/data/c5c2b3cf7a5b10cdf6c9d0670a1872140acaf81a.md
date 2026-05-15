# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth.spec.ts >> should show error for invalid credentials
- Location: tests-e2e\auth.spec.ts:36:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: page.goto: Test timeout of 30000ms exceeded.
Call log:
  - navigating to "http://localhost:5173/login", waiting until "networkidle"

```

# Page snapshot

```yaml
- generic [ref=e4]:
  - banner [ref=e5]:
    - generic [ref=e7]:
      - generic [ref=e8]:
        - generic [ref=e9]:
          - img [ref=e11]
          - generic [ref=e15]:
            - heading "MedSurvey Pro" [level=1] [ref=e16]
            - generic [ref=e17]: نظام استبيانات رضا المرضى
        - generic [ref=e19]:
          - img [ref=e21]
          - generic [ref=e23]:
            - generic [ref=e24]: مستشفى الشفاء الطبي
            - generic [ref=e25]: المستشفى المشغل
      - generic [ref=e26]:
        - generic [ref=e27]:
          - button "العربية" [ref=e28] [cursor=pointer]:
            - img [ref=e29]
            - generic [ref=e32]: العربية
          - button "تفعيل الوضع المظلم" [ref=e33] [cursor=pointer]:
            - img [ref=e35]
        - button "لوحة التحكم" [ref=e37] [cursor=pointer]:
          - img [ref=e38]
          - generic [ref=e41]: لوحة التحكم
  - generic [ref=e48]:
    - generic [ref=e49]:
      - img [ref=e50]
      - generic [ref=e52]: خير من يعتني واكثر من يهتم
    - heading "رأيكم يصنع الفرق في تطوير خدماتنا" [level=2] [ref=e53]:
      - text: رأيكم يصنع
      - generic [ref=e54]: الفرق
      - text: في تطوير خدماتنا
    - paragraph [ref=e55]: شاركونا تجربتكم في المستشفى لنتمكن من تحسين وتطوير الخدمات الصحية المقدمة لكم. استبيان سري وآمن لا يتجاوز 3 دقائق.
    - button "ابدأ الاستبيان الآن" [ref=e57] [cursor=pointer]:
      - img [ref=e58]
      - text: ابدأ الاستبيان الآن
      - img [ref=e61]
    - generic [ref=e63]:
      - generic [ref=e64]:
        - img [ref=e65]
        - generic [ref=e68]: 3 دقائق فقط
      - generic [ref=e69]:
        - img [ref=e70]
        - generic [ref=e72]: مشفر وآمن 100%
  - generic [ref=e75]:
    - generic [ref=e76]:
      - img [ref=e78]
      - heading "استبيان شامل" [level=3] [ref=e81]
      - paragraph [ref=e82]: يغطي جميع جوانب الخدمة من الاستقبال حتى المغادرة
    - generic [ref=e83]:
      - img [ref=e85]
      - heading "خصوصية تامة" [level=3] [ref=e87]
      - paragraph [ref=e88]: بياناتكم محمية ومشفرة ولا يتم مشاركتها مع أي طرف
    - generic [ref=e89]:
      - img [ref=e91]
      - heading "تحسين مستمر" [level=3] [ref=e93]
      - paragraph [ref=e94]: نستخدم آراءكم لتطوير وتحسين جودة الخدمات باستمرار
  - contentinfo [ref=e95]:
    - generic [ref=e96]:
      - generic [ref=e97]:
        - generic [ref=e98]:
          - img [ref=e100]
          - generic [ref=e104]: MedSurvey Pro
        - generic [ref=e105]:
          - img [ref=e107]
          - generic [ref=e109]: مستشفى الشفاء الطبي
      - paragraph [ref=e110]: تم التطوير والتشغيل لصالح مستشفى الشفاء الطبي عبر نظام قياس وتحسين رضا المرضى MedSurvey Pro © 2026
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
  22 |   await page.locator('#username').fill('admin');
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
> 37 |   await page.goto('/login', { waitUntil: 'networkidle' });
     |              ^ Error: page.goto: Test timeout of 30000ms exceeded.
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