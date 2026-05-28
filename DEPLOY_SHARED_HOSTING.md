# Shared Hosting Deployment / دليل الرفع على الاستضافة العادية

## العربية

يمكن تشغيل هذا المشروع على استضافة عادية أو Business Hosting عند تعطيل البث اللحظي `Realtime Broadcasting`.

### متطلبات الاستضافة

- PHP 8.2 أو أحدث
- MySQL
- Apache مع دعم `mod_rewrite`
- إمكانية توجيه الدومين إلى مجلد `public`
- صلاحية الكتابة على مجلدي `storage` و `bootstrap/cache`
- يفضل توفر SSH و Composer

### ملف بيئة الإنتاج

استخدم الملف التالي كقالب لملف `.env` الخاص بالإنتاج:

```text
deploy/shared-hosting.env.example
```

أهم القيم المناسبة للاستضافة العادية:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
VITE_ENABLE_BROADCASTING=false
CACHE_STORE=file
SESSION_DRIVER=file
```

بعد رفع المشروع أو من داخل الاستضافة، أنشئ مفاتيح الإنتاج:

```bash
php artisan key:generate
php artisan jwt:secret
```

### بناء الواجهة قبل الرفع

شغل الأوامر التالية محليًا قبل رفع المشروع:

```bash
composer install --no-dev --optimize-autoloader
npm ci --legacy-peer-deps
npm run build
```

أمر `npm run build` ينشئ ملفات الواجهة الجاهزة داخل:

```text
public/build
```

### الملفات التي يجب رفعها

ارفع ملفات ومجلدات المشروع الأساسية، ومنها:

- `app`
- `bootstrap`
- `config`
- `database`
- `public`
- `resources`
- `routes`
- `storage`
- `vendor`
- `.env`
- `artisan`
- `composer.json`
- `composer.lock`

### ملفات لا يفضل رفعها

- `node_modules`
- `.git`
- `tests`
- `tests-e2e`
- `playwright-report`
- `test-results`
- ملفات البيئة المحلية أو الأسرار غير المستخدمة

### بعد الرفع

من SSH على الاستضافة، شغل:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### ملاحظة مهمة حول `public_html`

الأفضل أن يكون Document Root للدومين هو مجلد:

```text
public
```

إذا كانت الاستضافة لا تسمح بتوجيه الدومين إلى `public` وتستخدم فقط `public_html`، يحتاج المشروع إلى ترتيب خاص قبل الرفع حتى لا تظهر ملفات Laravel الحساسة للعامة.

---

## English

This project can run on regular shared or business hosting when realtime broadcasting is disabled.

### Hosting Requirements

- PHP 8.2 or newer
- MySQL
- Apache with `mod_rewrite`
- Ability to point the domain document root to `public`
- Writable `storage` and `bootstrap/cache`
- SSH and Composer are recommended

### Production Environment

Use this file as the production `.env` template:

```text
deploy/shared-hosting.env.example
```

Important shared-hosting values:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
VITE_ENABLE_BROADCASTING=false
CACHE_STORE=file
SESSION_DRIVER=file
```

Generate production secrets on the hosting account:

```bash
php artisan key:generate
php artisan jwt:secret
```

### Build Before Upload

Run locally before uploading:

```bash
composer install --no-dev --optimize-autoloader
npm ci --legacy-peer-deps
npm run build
```

The `npm run build` command generates the production frontend assets in:

```text
public/build
```

### Files To Upload

Upload the main project files and directories, including:

- `app`
- `bootstrap`
- `config`
- `database`
- `public`
- `resources`
- `routes`
- `storage`
- `vendor`
- `.env`
- `artisan`
- `composer.json`
- `composer.lock`

### Files You Should Not Upload

- `node_modules`
- `.git`
- `tests`
- `tests-e2e`
- `playwright-report`
- `test-results`
- local environment files or unused secrets

### After Upload

From SSH on the hosting account, run:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Important Note About `public_html`

The best setup is to make the domain document root point to:

```text
public
```

If the host cannot point the domain to `public` and only supports `public_html`, the project needs a special layout adjustment before upload so sensitive Laravel files are not publicly exposed.
