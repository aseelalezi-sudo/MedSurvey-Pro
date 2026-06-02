# MedSurvey Pro | نظام قياس رضا المرضى

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net/)
[![Vite](https://img.shields.io/badge/Vite-7.x-646cff.svg)](https://vite.dev/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.x-38bdf8.svg)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-MIT-lightgrey.svg)](#license--الترخيص)

---

## نظرة عامة | Overview

### العربية

**MedSurvey Pro** هو نظام ويب متكامل لإدارة استبيانات رضا المرضى داخل المنشآت الصحية. يتيح للفرق الإدارية إنشاء الاستبيانات، استقبال إجابات المرضى، متابعة التقييمات المنخفضة، إصدار البلاغات، تحليل النتائج، وإدارة النسخ الاحتياطي من لوحة تحكم آمنة تدعم العربية والإنجليزية.

يركز المشروع على تجربة استخدام واضحة، صلاحيات إدارية دقيقة، دعم الاتجاهين RTL/LTR، وإمكانية العمل كتطبيق قابل للتثبيت من المتصفح عبر PWA.

### English

**MedSurvey Pro** is a healthcare-focused patient satisfaction platform. It enables teams to manage surveys, collect patient responses, monitor low-score feedback, generate tickets, analyze performance, and manage backups from a secure bilingual dashboard.

The application supports Arabic and English, RTL/LTR layouts, role-based access, audit logging, and browser installation through Progressive Web App support.

---

## المميزات الرئيسية | Key Features

| العربية | English |
| --- | --- |
| إنشاء وإدارة الاستبيانات والأقسام والأسئلة | Survey, section, and question management |
| استقبال استجابات المرضى من واجهة عامة | Public patient survey flow |
| دعم العربية والإنجليزية مع اتجاه RTL/LTR | Arabic and English with RTL/LTR support |
| لوحة تحكم تعرض المؤشرات والتحليلات | Dashboard analytics and operational indicators |
| شاشة الاستجابات مع تفاصيل الأسئلة وأسباب التقييم المنخفض | Response details with low-score reasons |
| توليد بلاغات تلقائية عند انخفاض التقييم | Automatic ticket creation for low satisfaction |
| شاشة الإنذار المبكر وخطط الإجراءات | Predictive early-warning screen and action plans |
| التقارير والرسوم البيانية والتصدير | Reports, charts, and export support |
| لوحة الشرف للأقسام أو الجهات الأعلى أداءً | Hall of fame for high-performing teams |
| إدارة المستخدمين والصلاحيات | User and role management |
| سجل تدقيق للعمليات الحساسة | Audit logging for sensitive actions |
| إدارة النسخ الاحتياطي والاستعادة والتحقق | Backup, restore, and verification tools |
| دعم التثبيت كتطبيق من المتصفح | Progressive Web App installation support |

---

## البنية التقنية | Technology Stack

### Backend

- **Laravel 12**
- **PHP 8.2+**
- **Laravel Sanctum**
- **JWT Auth**
- **Laravel Reverb** للتحديثات اللحظية عند الحاجة
- **MySQL / MariaDB**

### Frontend

- **Blade Templates**
- **Alpine.js**
- **TypeScript**
- **Vite 7**
- **Tailwind CSS 4**
- **ApexCharts**
- **Lucide Icons**
- **local @fontsource/cairo fonts**

### Testing & Quality

- **PHPUnit**
- **Vitest**
- **Playwright**
- **ESLint**
- **Prettier**
- **Laravel Pint**

---

## هيكل المشروع | Project Structure

```text
MedSurvey Pro/
├── app/
│   ├── Http/Controllers/Web/   # صفحات لوحة التحكم، الاستبيانات، البلاغات، النسخ الاحتياطي
│   ├── Models/                 # نماذج قاعدة البيانات
│   └── Services/               # منطق الخدمات المشتركة
├── database/
│   ├── migrations/             # مخطط قاعدة البيانات
│   ├── seeders/                # البيانات التجريبية
│   └── factories/              # بيانات الاختبارات
├── resources/
│   ├── views/                  # واجهات Blade
│   ├── js/                     # TypeScript و Alpine helpers
│   ├── css/                    # Tailwind وملفات التصميم
│   └── lang/                   # ملفات الترجمة العربية والإنجليزية
├── routes/
│   ├── web.php                 # مسارات الموقع ولوحة التحكم
│   └── console.php             # المهام المجدولة
├── tests/
│   └── Feature/                # اختبارات الخصائص والأمان
└── docs/                       # التوثيق وبيانات العرض والتقارير الفنية
```

---

## متطلبات التشغيل | Requirements

- PHP 8.2 أو أحدث
- Composer
- Node.js 20 أو أحدث
- npm
- MySQL أو MariaDB
- خادم محلي مثل Laravel Serve أو XAMPP أو Laragon

---

## التشغيل المحلي | Local Development

### 1. تثبيت الاعتمادات

```bash
composer install
npm install
```

### 2. إعداد ملف البيئة

```bash
cp .env.example .env
php artisan key:generate
```

بعد ذلك قم بتعديل إعدادات قاعدة البيانات داخل ملف `.env`.

### 3. إنشاء قاعدة البيانات والبيانات التجريبية

```bash
php artisan migrate --seed
```

بيانات الحسابات التجريبية موثقة في:

```text
docs/demo-data.md
```

### 4. تشغيل الواجهة أثناء التطوير

```bash
npm run dev
```

### 5. تشغيل خادم Laravel

```bash
php artisan serve
```

### 6. تشغيل Reverb عند الحاجة

```bash
php artisan reverb:start
```

---

## أوامر البناء والفحص | Build & QA

### بناء ملفات الإنتاج

```bash
npm run build
```

### اختبارات Laravel

```bash
php artisan test
```

### اختبارات الواجهة

```bash
npm test
```

### اختبارات E2E

```bash
npm run test:e2e
```

### فحص JavaScript و TypeScript

```bash
npm run lint
```

### فحص تنسيق PHP

```bash
composer pint:test
```

---

## التثبيت كتطبيق | Progressive Web App

يدعم المشروع التثبيت كتطبيق من المتصفح عند توفر الشروط التالية:

- فتح الموقع عبر `localhost` أو HTTPS.
- تنفيذ `npm run build`.
- وجود الملفات `public/sw.js` و `public/build/manifest.webmanifest`.
- حذف Service Worker القديم من المتصفح عند وجود نسخة مخزنة قديمة.

---

## الأمان | Security Notes

- لا ترفع ملف `.env` إلى Git.
- غيّر كلمات مرور البيانات التجريبية قبل استخدام النظام خارج البيئة المحلية.
- اجعل صلاحيات النسخ الاحتياطي والاستعادة والحذف للمستخدمين الموثوقين فقط.
- راجع سجل التدقيق بعد العمليات الحساسة.
- استخدم HTTPS في بيئة الإنتاج.
- عطّل `APP_DEBUG` في الإنتاج.

---

## النشر | Deployment Checklist

- ضبط `APP_ENV=production`.
- ضبط `APP_DEBUG=false`.
- إعداد قاعدة البيانات والبريد والجلسات والكاش.
- تنفيذ:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate
```

- توجيه الخادم إلى مجلد `public`.
- تفعيل المجدول:

```bash
php artisan schedule:run
```

- تشغيل Queue Workers إذا تم تفعيل المهام المؤجلة.
- اختبار تسجيل الدخول والصلاحيات والنسخ الاحتياطي قبل فتح النظام للمستخدمين.

---

## التوثيق | Documentation

- [Demo Data](docs/demo-data.md)
- [E2E Testing](docs/e2e-testing.md)
- [Technical Audit](docs/technical-audit-2026-06-01.md)

---

## License | الترخيص

This project is released under the MIT license.

هذا المشروع مرخص بموجب رخصة MIT.
