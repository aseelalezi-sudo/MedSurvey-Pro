# سجل التغييرات | CHANGELOG

جميع التغييرات المهمة في مشروع **MedSurvey Pro** سيتم توثيقها في هذا الملف.
التنسيق معتمد على [Keep a Changelog](https://keepachangelog.com/ar/1.0.0/)، ويلتزم هذا المشروع بـ [Semantic Versioning](https://semver.org/).

All notable changes to the **MedSurvey Pro** project will be documented in this file.
This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2026-05-19

### تم الإضافة | Added
- **سلسلة CI/CD (GitHub Actions)**: إعداد خط إنتاج متكامل للاختبار والتحقق التلقائي في ملف `.github/workflows/ci.yml` يعمل مع كل Push أو Pull Request ويقوم ببناء واجهة المستخدم والتحقق من اختبارات API واختبارات E2E مع MySQL و Redis.
- **سلسلة CI/CD (GitHub Actions)**: Setup standard, production-ready continuous integration workflow at `.github/workflows/ci.yml` to automatically run unit tests, database migrations, backend API tests, and Playwright E2E tests against live MySQL & Redis services.
- **ملف سجل التغييرات**: إطلاق ملف `CHANGELOG.md` لتوثيق تاريخ إصدارات المشروع باللغتين العربية والإنجليزية بشكل احترافي.
- **Changelog Creation**: Added a comprehensive bilingual `CHANGELOG.md` to document the project's milestones, additions, and updates clearly.

### تم التعديل | Changed
- **تحديث الإصدار (Semantic Versioning)**: تحديث إصدار المشروع في ملف `package.json` الرئيسي من `0.0.0` إلى `1.0.0` لإصدار رسمي مستقر ومطابق لمعايير الحوكمة البرمجية.
- **Upgrade Project Version**: Bumped the root project version to stable `1.0.0` to match standard software packaging and governance specifications.
- **تحديث Docker Compose**: إزالة حقل `version: '3.8'` المُهمل في الإصدارات الحديثة من Docker Compose لضمان التوافقية ومنع التحذيرات البرمجية أثناء التشغيل.
- **Docker Compose Modernization**: Removed deprecated `version: '3.8'` declaration from `docker-compose.yml` to adhere to modern Docker specifications.

---

## [0.9.0] - 2026-05-18

### تم الإضافة | Added
- **مؤقت استبيان مستمر (Persistent Survey Timer)**: إضافة ميزة حفظ حالة وتوقيت الأقسام عند الانتقال بين الصفحات أو إعادة تحميل المتصفح باستخدام `localStorage` ومزامنتها مع Zustand Store.
- **Persistent Survey Timer**: Implemented section-level survey timer persistence across page navigation and router reloads using local storage synchronization.

### تم الإصلاح | Fixed
- **منع تكرار أقسام الاستبيانات**: تعديل منطق واجهة البرمجة (PUT `/api/surveys/:id`) لمعالجة وتحديث الأقسام الموجودة مباشرة بدلاً من تكرارها عشوائياً في قاعدة البيانات أثناء حفظ التعديلات.
- **Section Reconciliation**: Prevented duplication of survey sections during editing by introducing in-place database record reconciliation instead of blanket inserts.

---

## [0.8.0] - 2026-05-17

### تم الإضافة | Added
- **إدارة كلمات المرور الشخصية**: إضافة واجهة `ChangePasswordModal` للمستخدمين العاديين تتيح لهم تحديث كلمات مرورهم ذاتياً وأماناً عبر نقطة الوصول `/api/users/:id/password`.
- **Self-Service Password Management**: Created `ChangePasswordModal` component and backend endpoint allowing all authenticated users to securely update their passwords.

### تم التعديل | Changed
- **الهوية البصرية وتصميم الواجهة الرئيسية**: إعادة تحسين وتنسيق ترويسة الصفحة الرئيسية واستعادة شعار المستشفى العريق "خير من يعتني وأكثر من يهتم" بتناسق بصري وتصميم مستجيب وأنيق.
- **Branding Excellence**: Restored the hospital slogan "خير من يعتني وأكثر من يهتم" and refined landing page layouts to project a premium, clinical brand.

### تم الإصلاح | Fixed
- **أخطاء لوحة التحكم (RBAC)**: إصلاح مشاكل في صياغة JSX وأوسمة الإغلاق غير الصحيحة في لوحة التحكم بصلاحيات المستخدمين `UserManagement.tsx`.
- **RBAC UI Dashboard**: Fixed syntax issues and unclosed JSX tags in the Role-Based Access Control configuration view.

---

## [0.7.0] - 2026-05-16

### تم الإضافة | Added
- **نظام المراقبة والرصد (Observability)**: دمج مكتبة Winston للتدوين المهيكل (JSON Logs)، وتوفير نقطة فحص سلامة النظام `/health` وتكامل مع Sentry لتعقب الأخطاء تلقائياً.
- **Observability Stack**: Integrated Winston structured JSON logging, system `/health` endpoint, APM middleware, and Sentry automated error reporting.

### تم الإصلاح | Fixed
- **حلقة التكرار اللانهائي بالصفحة**: إصلاح مشكلة إعادة التحميل المتكررة في الواجهة الأمامية عبر تحويل التنقل الإجباري إلى Hash-based navigation والتكامل مع مرحلة تهيئة الحساب الذاتية (`/auth/me`).
- **Infinite Reload Loop**: Prevented app initialization redirection cycles by implementing hash-based routing and silent authentication verification.
- **فشل التحقق من صحة الاستبيانات**: حل أخطاء Zod البرمجية عند تقديم الاستبيان عبر مواءمة بنية الحزمة المرسلة من العميل مع متطلبات خادم التحقق.
- **Survey Submission Validation**: Rectified payload mismatch in the frontend client logic to satisfy strict schema constraints on submission.

---

## [0.6.0] - 2026-05-15

### تم الإضافة | Added
- **لوحة التحليلات الذكية**: بناء رسوم بيانية تفاعلية (Recharts)، وتصميم خدمة تصدير البيانات إلى Excel و PDF للتقارير الطبية.
- **Analytics & Exports**: Designed real-time visual charts and robust server-side utilities to download survey reports in Excel and PDF formats.
- **الذكاء الاصطناعي التنبؤي (Predictive AI)**: واجهة تحليلات مخصصة تعرض توقعات مستويات رضا المرضى وتنبؤات زمن الحل استناداً إلى خوارزميات محاكاة.
- **Predictive AI UI**: Designed predictive client indicators showcasing simulated patient satisfaction trajectories and ticket resolution patterns.
- **توثيق المستخدمين وأمن النظام**: تطبيق نظام الجلسات الآمنة باستخدام رموز JWT الموقعة، وكلمات المرور المشفرة بـ Bcrypt.
- **Authentication & Security**: Integrated cryptographically secure JWT authentication, session cookies, and bcrypt hashing for user credentials.

---

## [0.5.0] - 2026-05-14

### تم الإضافة | Added
- **النسخة التجريبية الأولى**:
  - نظام الاستبيانات الأساسي وتصميم نماذج الاستبيانات الديناميكية.
  - دعم كامل للتوطين واللغتين العربية والإنجليزية (i18next).
  - خادم Express مدمج مع قاعدة بيانات MySQL ونظام Prisma ORM لإدارة قواعد البيانات.
- **Core MVP Release**:
  - Core dynamic survey engine and designer.
  - Multi-language localization support (Arabic/English) using i18next.
  - Production-grade Express Server utilizing Prisma ORM with MySQL.

[1.0.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v1.0.0
[0.9.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v0.9.0
[0.8.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v0.8.0
[0.7.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v0.7.0
[0.6.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v0.6.0
[0.5.0]: https://github.com/aseelalezi-sudo/MedSurvey-Pro/releases/tag/v0.5.0
