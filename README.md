# 🏥 MedSurvey Pro | نظام قياس رضا المرضى المتكامل

[![CI Status](https://github.com/aseelalezi-sudo/MedSurvey-Pro/actions/workflows/ci.yml/badge.svg)](https://github.com/aseelalezi-sudo/MedSurvey-Pro/actions)
[![React Version](https://img.shields.io/badge/react-19.2.6-blue.svg)](https://react.dev/)
[![Express Version](https://img.shields.io/badge/express-5.1.0-green.svg)](https://expressjs.com/)
[![Prisma Version](https://img.shields.io/badge/prisma-%5E6.8.2-purple.svg)](https://www.prisma.io/)
[![MySQL Version](https://img.shields.io/badge/mysql-8.0-orange.svg)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/tailwind-v4-38bdf8.svg)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/license-MIT-lightgrey.svg)](#)

---

## 🌍 نظرة عامة | Overview

### [العربية]
**MedSurvey Pro** هو نظام متكامل ومتقدم لقياس وإدارة رضا المرضى في المنشآت الطبية والمستشفيات. يهدف النظام إلى تمكين المستشفيات من تصميم استبيانات ديناميكية، وجمع آراء المرضى بلغات متعددة (العربية والإنجليزية)، وتحليل النتائج لحظياً عبر لوحات تحليلات ذكية ورسوم بيانية تفاعلية. كما يضم النظام نظاماً تنبؤياً ذكياً مدعوماً بمحاكاة ذكاء اصطناعي، ونظام تذاكر وشكاوى فوري للتعامل مع الحالات الحرجة وتحسين جودة الرعاية الطبية وفقاً لأعلى معايير الأمن والأداء.

### [English]
**MedSurvey Pro** is an enterprise-grade patient satisfaction survey and analytics platform tailored for medical centers and hospitals. The system empowers healthcare providers to design dynamic surveys, capture patient feedback in multi-language interfaces (Arabic/English), and analyze outcomes in real-time using rich interactive dashboards. It also features a simulated predictive AI page, a smart ticketing system to handle negative feedback proactively, and robust security measures aligned with modern web application standards.

---

## ✨ المميزات الرئيسية | Key Features

| الميزة | Feature | الوصف / Description |
| :--- | :--- | :--- |
| **منشئ الاستبيانات الديناميكي** | **Dynamic Survey Builder** | واجهة مرنة لتصميم الأسئلة والأقسام ودعم خيارات إدخال متعددة (نجوم، وجوه تعبيرية، مقياس NPS، أسئلة نصية واختيار من متعدد). |
| **بوابة استبيان تفاعلية** | **Interactive Survey Portal** | واجهة سلسة وسريعة للمرضى مع ميزة الاحتفاظ بالوقت والمزامنة المحلية المستمرة للأقسام لضمان عدم ضياع الإجابات. |
| **لوحة تحليلات تفاعلية** | **Real-Time Analytics Dashboard** | لوحة تحكم ذكية تعرض رسوم بيانية تفاعلية متقدمة (Recharts) لمعدلات الرضا، ومستويات الأداء للأقسام والأطباء. |
| **لوحة الشرف والتميز** | **Hall of Fame & Honor Board** | تكريم الأقسام والموظفين الأعلى تقييماً بناءً على آراء المرضى الحقيقية لتحفيز الكادر الطبي. |
| **نظام التذاكر والشكاوى الذكي** | **Automated Ticketing System** | توليد تذاكر دعم تلقائية فور رصد تقييم منخفض أو شكوى حادة، مع إسنادها للقسم المختص ومتابعة حالة الحل. |
| **الذكاء الاصطناعي التنبؤي** | **Predictive AI Simulation** | شاشة تحليلات مخصصة توضح المسارات المتوقعة لرضا المرضى وأزمنة حل التذاكر استناداً إلى بيانات تاريخية ومحاكاة ذكية. |
| **تصدير التقارير الذكي** | **Advanced Reporting & Exports** | تصدير فوري لنتائج الاستبيانات والتقارير الدورية إلى ملفات **Excel** و **PDF** بجداول متناسقة. |
| **لوحة الرصد والتشغيل** | **Observability & Health Checks** | تكامل تام مع **Sentry** لتتبع الأخطاء، ونظام تدوين مهيكل باستخدام **Winston Logger** مع شاشة فحص سلامة النظام لحظياً. |
| **إدارة الصلاحيات والتدقيق** | **RBAC & Audit Logging** | نظام تحكم مرن بالصلاحيات (Super Admin, Admin, Manager, Staff) وسجل كامل لتدقيق كافة تحركات الإدارة لضمان النزاهة. |
| **أرشفة وحفظ البيانات** | **Data Archiving & Retention** | آلية أوتوماتيكية لأرشفة الاستبيانات القديمة وسجلات التدقيق للحفاظ على ذروة أداء قاعدة البيانات. |

---

## 🛠️ البنية التقنية | Technology Stack

### الواجهة الأمامية (Frontend)
*   **React 19.2.6**: أحدث بيئة عمل لبناء واجهات تفاعلية سريعة ومكونات قابلة لإعادة الاستخدام.
*   **Vite 7.3.2**: أداة البناء فائقة السرعة لإدارة وتجميع الأصول البرمجية.
*   **Tailwind CSS v4**: أحدث إصدار من إطار عمل التنسيق لتصميم عصري ومستجيب بالكامل.
*   **Zustand 5.0.13**: إدارة خفيفة وسريعة ومستمرة لحالة التطبيق بدون تعقيد.
*   **Recharts 3.8.1**: مكتبة رسوم بيانية تفاعلية لعرض تحليلات رضا المرضى بجاذبية فائقة.
*   **i18next**: توطين كامل للنظام باللغتين العربية والإنجليزية مع كشف تلقائي للغة المتصفح.

### الواجهة الخلفية وقاعدة البيانات (Backend & Database)
*   **Node.js & Express 5.1.0**: خادم API متطور يعتمد على بنية RESTful المعيارية.
*   **Prisma ORM 6.8.2**: محرك تعامل مع قاعدة البيانات يوفر أماناً كاملاً للأنواع (Type-Safe).
*   **MySQL 8.0**: قاعدة البيانات العلاجية الأساسية للمشروع لضمان سلامة وسلامة البيانات الطبية.
*   **Redis (ioredis)**: ذاكرة كاش وسيطة وإدارة الجلسات لتسريع الاستجابة والحد من استهلاك الموارد.
*   **JWT & Bcryptjs**: توقيع الجلسات والمصادقة الآمنة مع تشفير كلمات المرور بأعلى خوارزميات الحماية.

---

## 🔒 أمن وحماية النظام | Security & Hardening

لضمان حماية بيانات المرضى والمنشأة الطبية، تم تدعيم النظام بالآليات الأمنية التالية:
1.  **حماية العناوين (HTTP Hardening)**: استخدام مكتبة **Helmet** لتطبيق ترويسات أمان معيارية ومنع هجمات الاختطاف (Clickjacking) والتحقين.
2.  **تنظيف البيانات التلقائي (Recursive XSS Sanitization)**: فلترة كافة البيانات المدخلة بشكل متكرر لمنع حقن السكريبتات الخبيثة (Cross-Site Scripting).
3.  **إدارة جلسات فائقة الأمان**: تداول رموز الوصول الموقعة رقمياً (JWT Access) ورموز التحديث المستمرة (Refresh Tokens) عبر ملفات تعريف ارتباط مؤمنة بالكامل (`httpOnly`, `secure`, `sameSite`).
4.  **تحديد معدل الطلبات (Rate Limiting)**: تطبيق حماية برمجية للحد من الهجمات التكرارية (Brute-Force) وضمان استقرار الخادم.
5.  **سجلات تدقيق غير قابلة للتعديل (Audit Logs)**: تسجيل كل عملية حساسة يقوم بها المشرفون في قاعدة البيانات للرجوع إليها عند الحاجة.

---

## 📂 هيكل المجلدات | Project Structure

```text
MedSurvey-Pro/
├── .github/workflows/    # إعدادات سلسلة CI/CD للتحقق التلقائي واختبار الأكواد
├── public/               # الأصول الثابتة والصور العامة لواجهة المستخدم
├── scripts/              # سكريبتات تهيئة النظام وعمليات التحقق والتشغيل الذكي
├── server/               # الواجهة الخلفية (Backend)
│   ├── prisma/           # مخطط قاعدة البيانات (Schema) ومجلد الهجرات (Migrations)
│   └── src/
│       ├── controllers/  # منطق التحكم بطلبات API
│       ├── middleware/   # برمجيات الحماية والتحقق (Auth, XSS, Rate Limit)
│       ├── routes/       # مسارات نقاط الوصول وخريطة الـ API
│       ├── utils/        # الدوال المساعدة والتدوين (Winston Logging, Seeder)
│       └── index.ts      # نقطة دخول الخادم الأساسية
├── src/                  # الواجهة الأمامية (Frontend)
│   ├── api/              # قنوات الاتصال بالخادم (Axios config & API requests)
│   ├── components/       # شاشات وواجهات التطبيق (Dashboard, Survey, Tickets, etc.)
│   ├── store/            # مخازن الحالات العامة للتطبيق (Zustand Stores)
│   ├── locales/          # ملفات ترجمة الواجهات للعربية والإنجليزية (i18n)
│   └── main.tsx          # نقطة انطلاق تطبيق الـ React
├── Dockerfile            # ملف تعبئة الواجهة الأمامية في حاوية دكر
├── docker-compose.yml    # إعداد بيئة التشغيل المتكاملة (App, MySQL, Redis)
└── package.json          # الملف التعريفي والاعتمادات البرمجية للمشروع
```

---

## 🚀 التشغيل المحلي والتطوير | Local Development

### المتطلبات الأساسية (Prerequisites)
*   تثبيت **Node.js** (إصدار 18 أو أحدث).
*   تثبيت قاعدة بيانات **MySQL** (إصدار 8.0 أو أحدث).
*   تثبيت خادم **Redis** (اختياري للتطوير المحلي، وإلزامي للإنتاج).

### خطوات الإعداد والتثبيت
1.  **استنساخ المشروع (Clone the Repository):**
    ```bash
    git clone https://github.com/aseelalezi-sudo/MedSurvey-Pro.git
    cd MedSurvey-Pro
    ```

2.  **تثبيت الاعتمادات البرمجية (Install Dependencies):**
    قم بتثبيت الحزم للمشروع الرئيسي والخادم:
    ```bash
    npm install
    cd server && npm install
    cd ..
    ```

3.  **إعداد متغيرات البيئة (Configure Environment):**
    *   قم بنسخ ملف `.env.example` في المجلد الرئيسي إلى `.env` وعدّل القيم لتناسب بيئتك.
    *   قم بنسخ ملف `server/.env.example` إلى `server/.env` وضع إعدادات الاتصال بقاعدة البيانات المحلية.
    ```bash
    cp .env.example .env
    cp server/.env.example server/.env
    ```

4.  **تهيئة قاعدة البيانات وتغذيتها (Database Setup & Seed):**
    نفّذ الأوامر التالية لإنشاء الجداول وحقن بيانات المشرف الافتراضي والبيانات التجريبية:
    ```bash
    cd server
    npx prisma db push
    npx prisma db seed
    cd ..
    ```

5.  **بدء تشغيل خادم التطوير (Start Development Server):**
    يحتوي المشروع على نظام تشغيل ذكي ينسق تشغيل السيرفر والواجهة الأمامية معاً بطلبات صحة آلية:
    ```bash
    npm run dev
    # أو
    npm run dev:all
    ```
    *سيتم تشغيل الواجهة الخلفية على المنفذ `4001` والواجهة الأمامية على المنفذ `5173` (أو منفذ متاح آخر).*

---

## 🐳 التشغيل والإنتاج عبر دكر | Docker & Production

يوفر النظام بيئة إنتاج متكاملة ومعزولة بالكامل تعمل بلمسة واحدة باستخدام Docker Compose:

```bash
# تشغيل خادم التطبيق، قاعدة بيانات MySQL، وخادم Redis خلف شبكة معزولة
docker-compose up -d --build
```

### خدمات الحاويات المتاحة:
*   **الواجهة الأمامية والخلفية**: متصلة تلقائياً وتعمل على منفذ الويب الافتراضي أو المنفذ المخصص في ملف `.env`.
*   **قاعدة البيانات (MySQL)**: تدار بياناتها بشكل مستمر في حجم دكر خارجي (Volume) لحماية البيانات من الفقدان.
*   **خدمة الكاش (Redis)**: تضمن تشغيل جلسات معزولة وسريعة للغاية.

---

## 🧪 جودة الأكواد والاختبارات | Testing & QA

يلتزم المشروع بأعلى معايير جودة الكود البرمجي عبر أدوات الفحص الآلي والاختبارات المستمرة:

*   **الاختبارات الأحادية واختبارات المكونات (Unit & Component Tests)**:
    ```bash
    npm run test
    ```
*   **اختبارات نقط الوصول للواجهة الخلفية (Backend API Tests)**:
    ```bash
    npm run test:api
    ```
*   **الاختبارات الشاملة للمسارات (Playwright E2E Tests)**:
    ```bash
    npm run test:e2e
    ```
*   **التدقيق البرمجي وتنسيق الأكواد (Linting & Formatting)**:
    ```bash
    npm run lint          # كشف التحذيرات والمشاكل الهيكلية
    npm run format:fix    # تنسيق الأكواد تلقائياً باستخدام Prettier
    ```

---

## 📝 ترخيص المشروع | License

هذا المشروع مرخص بموجب رخصة **MIT**. يمكنك استخدامه وتعديله وتوزيعه بحرية تامة للأغراض الشخصية والتجارية في منشأتك الطبية.
