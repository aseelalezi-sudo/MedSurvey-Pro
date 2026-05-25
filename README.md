# 🏥 MedSurvey Pro | نظام قياس رضا المرضى المتكامل

[![CI Status](https://github.com/aseelalezi-sudo/MedSurvey-Pro/actions/workflows/ci.yml/badge.svg)](https://github.com/aseelalezi-sudo/MedSurvey-Pro/actions)
[![React Version](https://img.shields.io/badge/react-19.2.6-blue.svg)](https://react.dev/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-red.svg)](https://laravel.com/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)](https://php.net/)
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
| **نظام التذاكر والشكاوى الذكي** | **Automated Ticketing System** | توليد تذاكر دعم تلقائية فور رصد تقييم منخفض أو شكوى حادة، مع إسنادها للقسم المختص ومتابعة حالة الحل عبر WebSockets. |
| **الذكاء الاصطناعي التنبؤي** | **Predictive AI Simulation** | شاشة تحليلات مخصصة توضح المسارات المتوقعة لرضا المرضى وأزمنة حل التذاكر استناداً إلى بيانات تاريخية ومحاكاة ذكية. |
| **تصدير التقارير الذكي** | **Advanced Reporting & Exports** | تصدير فوري لنتائج الاستبيانات والتقارير الدورية إلى ملفات **CSV** و **Excel** و **PDF**. |
| **لوحة الرصد والتشغيل** | **Observability & Health Checks** | نظام تدوين مهيكل للأخطاء مع شاشة فحص سلامة النظام لحظياً (System Health Monitoring). |
| **النسخ الاحتياطي التلقائي** | **Automated DB Backups** | إدارة تفاعلية بالكامل لجدولة النسخ الاحتياطية لقاعدة البيانات وحذفها برمجياً مع دعم ضغط GZIP. |
| **إدارة الصلاحيات والتدقيق** | **RBAC & Audit Logging** | نظام تحكم مرن بالصلاحيات وسجل كامل لتدقيق كافة تحركات الإدارة لضمان النزاهة والشفافية. |

---

## 🛠️ البنية التقنية | Technology Stack

### الواجهة الأمامية (Frontend)
*   **React 19**: أحدث بيئة عمل لبناء واجهات تفاعلية سريعة ومكونات قابلة لإعادة الاستخدام.
*   **Vite 7**: أداة البناء فائقة السرعة لإدارة وتجميع الأصول البرمجية وتكاملها مع لارافل (laravel-vite-plugin).
*   **Tailwind CSS v4**: أحدث إصدار من إطار عمل التنسيق لتصميم عصري ومستجيب بالكامل.
*   **Zustand 5**: إدارة خفيفة وسريعة ومستمرة لحالة التطبيق.
*   **Recharts**: مكتبة رسوم بيانية تفاعلية لعرض تحليلات رضا المرضى بجاذبية فائقة.
*   **i18next**: توطين كامل للنظام باللغتين العربية والإنجليزية.

### الواجهة الخلفية وقاعدة البيانات (Backend & Database)
*   **Laravel 12 (PHP 8.2+)**: إطار عمل بي إتش بي المتطور لبناء واجهة خلفية قوية ومعيارية (RESTful API).
*   **MySQL 8.0 / MariaDB**: قاعدة البيانات العلاجية الأساسية للمشروع.
*   **Laravel Reverb**: محرك WebSockets مدمج ومفتوح المصدر لإدارة التحديثات اللحظية والتنبيهات.
*   **JWT (tymon/jwt-auth)**: توقيع الجلسات والمصادقة الآمنة عبر واجهة برمجة التطبيقات (API).

---

## 🔒 أمن وحماية النظام | Security & Hardening

لضمان حماية بيانات المرضى والمنشأة الطبية، تم تدعيم النظام بالآليات الأمنية التالية:
1.  **حماية الطلبات (Rate Limiting & Throttling)**: تطبيق حماية برمجية للحد من الهجمات التكرارية (Brute-Force) وضمان استقرار الخادم عبر `RateLimiter` الخاص بلارافل.
2.  **تنظيف البيانات (Sanitization & Validation)**: التحقق الصارم من كافة البيانات المدخلة في استمارات الاستبيان أو لوحة التحكم للوقاية من هجمات XSS وحقن SQL.
3.  **المصادقة الآمنة**: تشفير كلمات المرور باستخدام `Bcrypt` وتأمين جلسات واجهة برمجة التطبيقات عبر JWT.
4.  **سجلات تدقيق غير قابلة للتعديل (Audit Logs)**: تسجيل كل عملية حساسة يقوم بها المشرفون.
5.  **أمان المزامنة في الوقت الفعلي (WebSocket Security)**: التحقق من التراخيص لضمان عدم اطلاع غير المصرح لهم على البيانات اللحظية المتبادلة عبر `Reverb`.

---

## 📂 هيكل المجلدات | Project Structure

```text
MedSurvey-Pro/
├── .github/workflows/    # إعدادات سلسلة CI/CD للتحقق التلقائي واختبار الأكواد
├── app/                  # منطق الواجهة الخلفية (Controllers, Models, Middleware)
│   ├── Console/Commands/ # أوامر النظام المجدولة (مثل أمر النسخ الاحتياطي التلقائي)
│   ├── Events/           # أحداث البث اللحظي (WebSockets)
│   └── Http/Controllers/ # متحكمات API لمعالجة الطلبات
├── database/             # مخطط قاعدة البيانات (Migrations) والبيانات التجريبية (Seeders)
├── public/               # الأصول الثابتة وملفات النظام
├── resources/
│   └── js/               # مجلد الواجهة الأمامية (Frontend - React)
│       ├── api/          # قنوات الاتصال بالخادم (Axios config & endpoints)
│       ├── components/   # شاشات وواجهات التطبيق (Dashboard, Survey, Settings, etc.)
│       ├── store/        # مخازن الحالات العامة للتطبيق (Zustand)
│       └── main.tsx      # نقطة انطلاق تطبيق الـ React
├── routes/               # مسارات الـ API والـ WebSockets والمهام المجدولة (Console)
├── tests/                # ملفات اختبار الواجهة الخلفية (PHPUnit / Pest)
└── vite.config.ts        # إعدادات حزمة Vite وتكاملها مع لارافل
```

---

## 🚀 التشغيل المحلي والتطوير | Local Development

### المتطلبات الأساسية (Prerequisites)
*   تثبيت **PHP** (إصدار 8.2 أو أحدث).
*   تثبيت **Composer**.
*   تثبيت **Node.js** (إصدار 20 أو أحدث) و **npm**.
*   تثبيت قاعدة بيانات **MySQL** أو **MariaDB**.

### خطوات الإعداد والتثبيت
1.  **استنساخ المشروع (Clone the Repository):**
    ```bash
    git clone https://github.com/aseelalezi-sudo/MedSurvey-Pro.git
    cd MedSurvey-Pro
    ```

2.  **تثبيت الاعتمادات البرمجية (Install Dependencies):**
    ```bash
    composer install
    npm install
    ```

3.  **إعداد متغيرات البيئة (Configure Environment):**
    قم بنسخ ملف `.env.example` إلى `.env` وعدّل القيم لتناسب بيانات الاتصال بقاعدة البيانات المحلية.
    ```bash
    cp .env.example .env
    php artisan key:generate
    php artisan jwt:secret
    ```

4.  **تهيئة قاعدة البيانات وتغذيتها (Database Setup & Seed):**
    يُنشئ هذا الأمر الجداول في قاعدة البيانات ويحقن الحسابات الافتراضية والبيانات التجريبية.
    ```bash
    php artisan migrate --seed
    ```
    > **بيانات دخول لوحة التحكم الافتراضية:** 
    > اسم المستخدم: `admin` | كلمة المرور: `ChangeMeLocalOnly!123`

5.  **تجميع ملفات الواجهة الأمامية (Build Frontend Assets):**
    ```bash
    npm run build
    ```

6.  **بدء تشغيل النظام (Start Servers):**
    لتشغيل النظام محلياً مع دعم المزامنة اللحظية (WebSockets)، يجب تشغيل أمرين في نافذتين منفصلتين (Terminals):

    *النافذة الأولى (الخادم الأساسي):*
    ```bash
    php artisan serve --port=8200
    ```
    *النافذة الثانية (خادم WebSockets):*
    ```bash
    php artisan reverb:start --port=8280
    ```

---

## 📅 الجدولة والمهام الآلية (Automated Jobs)

يحتوي النظام على مهام تعمل في الخلفية مثل (النسخ الاحتياطي اليومي، وحذف النسخ القديمة). لتشغيلها بشكل سليم على خوادم الإنتاج (Production)، يجب إضافة الأمر التالي لمجدول المهام (Cron Job) في الخادم ليتم تنفيذه كل دقيقة:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🧪 جودة الأكواد والاختبارات | Testing & QA

يلتزم المشروع بمعايير جودة الكود البرمجي عبر أدوات الفحص الآلي:

*   **اختبارات الواجهة الخلفية (Backend Tests - PHPUnit):**
    ```bash
    php artisan test
    ```
*   **الاختبارات الشاملة (E2E) واختبارات المكونات:**
    ```bash
    npm test
    npx playwright test
    ```

---

## 📝 ترخيص المشروع | License

هذا المشروع مرخص بموجب رخصة **MIT**. يمكنك استخدامه وتعديله وتوزيعه بحرية تامة للأغراض الشخصية والتجارية في منشأتك الطبية.
