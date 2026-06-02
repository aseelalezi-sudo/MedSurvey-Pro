# تقرير الفحص الفني الشامل - MedSurvey Pro

تاريخ الفحص: 2026-06-01  
النطاق: بنية Laravel، قاعدة البيانات، الأداء مع كبر البيانات، الأمن، الصلاحيات، الواجهة، الاختبارات، وقابلية التشغيل.

## الخلاصة التنفيذية

النظام في حالته الحالية قابل للعمل وفيه أساس جيد: اختبارات Laravel وVitest والبناء نجحت، الصلاحيات الأساسية للوحة موجودة، وعمليات الاستبيانات والتذاكر والنسخ الاحتياطي عليها اختبارات فعلية.

لكن توجد مخاطر مهمة قبل الاعتماد على النظام مع بيانات كبيرة أو بيئة إنتاج حقيقية:

- قابلية التوسع متوسطة حاليًا؛ بعض التقارير والتصدير والتحليل التنبؤي تسحب مجموعات بيانات كاملة إلى الذاكرة.
- توجد مشكلة فعلية في تعدد المستأجرين داخل تقارير التذاكر: الكود يفلتر `tickets.tenantId` رغم أن جدول `tickets` لا يحتوي هذا العمود.
- استعادة النسخ الاحتياطية من الويب عالية الخطورة، ومفتاح التعطيل `DB_BACKUP_RESTORE_ENABLED` موجود لكنه غير مستخدم في الكنترولر.
- أمر lint للواجهة يفشل بسبب اعتماد ناقص في إعداد ESLint.
- صفحة لوحة التحكم تجمع حسابات كثيرة في طلب واحد، وستصبح أبطأ مع زيادة البيانات.

تقديري العام:

- الجاهزية الوظيفية: جيدة.
- الجاهزية للأمان الإنتاجي: متوسطة وتحتاج إغلاق نقاط النسخ الاحتياطي وتسجيل الدخول.
- الجاهزية للبيانات الكبيرة: متوسطة إلى ضعيفة في التقارير والتصدير والتحليل.
- جودة الاختبارات: جيدة في المسارات الأساسية، وتحتاج اختبارات تحميل/تعدد مستأجرين/أذونات النسخ الاحتياطي.

## ما تم تشغيله أثناء الفحص الأولي

- `php artisan test`: نجح، 67 اختبارًا، 333 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `composer pint:test`: نجح.
- `npm run lint`: فشل بسبب نقص `eslint-plugin-react-hooks`.
- `php artisan route:list`: نجح، 63 route.
- `php artisan about`: نجح، البيئة local، debug off، cache file، queue database، views cached، storage link غير موجود.

## النتائج الحرجة

### 1. خطأ محتمل في التقارير مع تعدد المستأجرين

المكان: `app/Http/Controllers/Web/DashboardController.php` داخل `reports()`.

الكود يبني استعلام التذاكر بهذا الشكل:

```php
Ticket::query()
    ->when($user?->tenantId, fn ($q) => $q->where('tenantId', $user->tenantId))
```

لكن جدول `tickets` في migration لا يحتوي عمود `tenantId`. عند وجود مستخدم لديه `tenantId`، سيؤدي ذلك غالبًا إلى خطأ SQL مثل `Unknown column tenantId`.

الأثر:

- صفحة التقارير قد تتعطل للمستأجرين.
- حتى لو لم تتعطل في بيئة الاختبار بسبب `tenantId = null`، فهي مخاطرة إنتاجية مباشرة.

التوصية:

- إما إضافة `tenantId` إلى جدول `tickets` وتعبئته عند إنشاء التذكرة.
- أو فلترة التذاكر عبر العلاقة:

```php
->when($user?->tenantId, fn ($q) => $q->whereHas('response', fn ($r) => $r->where('tenantId', $user->tenantId)))
```

ثم إضافة اختبار Feature لمستخدم لديه `tenantId` يدخل صفحة التقارير.

### 2. استعادة قاعدة البيانات متاحة من الويب رغم وجود مفتاح تعطيل

المكان:

- `config/medsurvey.php`
- `app/Services/BackupService.php`
- `app/Http/Controllers/Web/DashboardController.php`

يوجد إعداد:

```php
'restore_enabled' => (bool) env('DB_BACKUP_RESTORE_ENABLED', false)
```

وتوجد دالة:

```php
public function restoreEnabled(): bool
```

لكن مسارات الاستعادة لا تتحقق منها قبل تنفيذ restore.

الأثر:

- أي admin يملك صلاحية صفحة النسخ الاحتياطية يستطيع محاولة استعادة قاعدة البيانات من الواجهة.
- هذه من أعلى العمليات خطورة لأنها قد تستبدل بيانات الإنتاج.

التوصية:

- قبل `restoreBackup`, `uploadBackup`, `uploadRestoreAjax`, `restoreExternalAjax` أضف شرطًا يمنع العملية إذا كان `restoreEnabled()` false.
- اجعل الاستعادة حصرًا على `super_admin` أو gate منفصل مثل `manage-backups-super`.
- أضف تأكيدًا ثانيًا أو كلمة مرور المستخدم قبل الاستعادة.

### 3. عدادات التذاكر غير مقيّدة بالمستأجر أو القسم في بعض الأماكن

الأماكن:

- `app/View/Composers/DashboardLayoutComposer.php`
- `app/Http/Controllers/Web/DashboardController.php` داخل `index()`.

أمثلة:

```php
Ticket::where('status', 'open')->count()
```

و:

```php
Ticket::query()->where('status', 'open')->count()
```

الأثر:

- مستخدم في مستأجر معين أو رئيس قسم قد يرى رقم تذاكر لا يخصه.
- هذا تسريب معلومات إحصائية حتى لو لم يرَ التفاصيل.

التوصية:

- توحيد دالة scoped tickets query، وتطبيقها على كل العدادات والقوائم.
- إضافة اختبارات تؤكد أن head_of_department والمستأجر لا يرى أرقام الآخرين.

## الأداء وقابلية التوسع

### 4. التصدير CSV يحمّل كل النتائج في الذاكرة

المكان: `DashboardController::exportResponses()`.

الكود يستخدم:

```php
$responses = $filter->applySorting($query->with('survey'))->get();
```

ثم يبث CSV. البث جيد، لكن التحميل الكامل قبل البث يلغي فائدته.

الأثر عند كبر البيانات:

- استهلاك ذاكرة مرتفع.
- بطء أو فشل التصدير عند عشرات/مئات الآلاف من السجلات.

التوصية:

- استخدم `cursor()` أو `lazyById()` أو `chunkById()` مع كتابة الصفوف تدريجيًا.
- ضع حدًا واضحًا للتصدير أو اجعله job في queue مع ملف جاهز للتحميل.

### 5. طباعة الردود تسحب كل النتائج وتحسب NPS في الذاكرة

المكان: `DashboardController::responses()` عند `export=print`.

الكود:

```php
$allResponses = $filter->applySorting($queryForPrint->with('survey'))->get();
```

الأثر:

- صفحة الطباعة قد تتعطل مع بيانات كبيرة.
- حساب NPS يمر على كل الردود.

التوصية:

- فرض حد أقصى للطباعة، مثل 1000 رد.
- للتقارير الكبيرة استخدم تصدير خلفي أو PDF/CSV مجدول.
- احسب NPS باستعلام SQL aggregate بدل تحميل كل الردود.

### 6. التحليل التنبؤي يسحب آخر 30 يومًا بالكامل

المكان: `app/Services/PredictiveService.php::getAlerts()`.

الكود يجلب كل ردود آخر 30 يومًا ثم يعمل `groupBy` و `filter` في PHP.

الأثر:

- مناسب للبيانات الصغيرة والمتوسطة.
- عند آلاف الردود يوميًا سيصبح مكلفًا في الذاكرة والزمن.

التوصية:

- نقل المتوسطات والحسابات الأساسية إلى SQL عبر `GROUP BY department`.
- استخدام نافذتين زمنيتين منفصلتين باستعلامين aggregate بدل تحميل كل الصفوف.
- اجعل `keyDriver` يحسب فقط للأقسام المرشحة للخطر.
- تخزين نتائج predictive مؤقتًا أو في جدول snapshots.

### 7. لوحة التحكم تنفذ حسابات كثيرة في طلب واحد

المكان: `DashboardController::index()` و `PredictiveService::getStats()`.

الطلب الواحد يحسب:

- إجمالي الردود.
- المتوسط.
- NPS.
- توزيع الرضا.
- إحصاءات الساعات والأيام.
- الاتجاهات.
- الإنذار التنبؤي.
- أحدث الردود.

الأثر:

- مع زيادة البيانات تصبح الصفحة الرئيسية أثقل صفحة في النظام.

التوصية:

- Cache لمدة 1-5 دقائق للإحصاءات الثقيلة حسب المستأجر والقسم والفلاتر.
- فصل الإحصاءات الثقيلة إلى endpoints AJAX تُحمّل تدريجيًا.
- إنشاء جدول materialized daily stats أو job دوري.

### 8. البحث بـ LIKE مع wildcard في البداية لا يستخدم الفهارس بكفاءة

الأماكن:

- `ResponseFilterQuery`
- `TicketFilterQuery`
- audit/error logs search.

النمط:

```php
LIKE "%term%"
```

الأثر:

- بطيء مع الجداول الكبيرة.

التوصية:

- للبحث الحر استخدم FULLTEXT indexes في MySQL إن أمكن.
- للهواتف استخدم بحث prefix أو عمود normalized phone.
- ضع حدًا أدنى لطول البحث مثل 3 أحرف.

## الأمن والصلاحيات

### 9. login rate limiter معرّف لكنه غير مطبق على Route

المكان:

- `AppServiceProvider` يعرف `RateLimiter::for('login')`.
- `routes/web.php` لا يضع `throttle:login` على `POST /login`.

الأثر:

- قابلية أعلى لمحاولات brute force.

التوصية:

```php
Route::post('/login', [AuthSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');
```

مع اختبار يحاكي أكثر من 5 محاولات.

### 10. TRUSTED_PROXIES الافتراضي `*`

المكان: `bootstrap/app.php`.

الكود:

```php
$middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'), ...)
```

الأثر:

- إذا لم يكن التطبيق خلف proxy موثوق مضبوط، يمكن أن تتأثر قراءات IP وscheme وhost من headers.

التوصية:

- في الإنتاج اضبط `TRUSTED_PROXIES` على عنوان proxy الفعلي فقط.
- لا تترك `*` إلا إذا كنت على منصة موثوقة تتحكم بهذه الرؤوس.

### 11. endpoint تسجيل الأحداث يقبل action من المستخدم

المكان: `DashboardController::recordEvent()`.

الأثر:

- أي مستخدم مصادق يستطيع إنشاء audit log باسم action يختاره.
- هذا يضعف موثوقية السجل إذا تم الاعتماد عليه كمرجع تدقيقي صارم.

التوصية:

- حصر `action` في قائمة مسموحة.
- أو فصل client-side analytics عن security audit.

### 12. رفع شعار المستشفى داخل settings يحتاج ضبط نوع المحتوى

المكان: `DashboardController::updateSettings()`.

الحقل:

```php
'hospital.logo' => ['nullable', 'string', 'max:500000']
```

الأثر:

- يتم تخزين شعار كـ string كبيرة في الإعدادات.
- قد يكبر حجم سجل settings ويؤثر على كل الصفحات لأن settings مشتركة عبر View composer.

التوصية:

- تخزين الملف في storage مع تحقق MIME وحجم.
- قبول `image/png`, `image/jpeg`, `image/webp` فقط.
- تجنب تخزين base64 ضخم داخل DB.

## قاعدة البيانات

نقاط جيدة:

- فهارس موجودة على `survey_responses.department`, `submittedAt`, `overallScore`, `surveyId`, و `department/submittedAt`.
- فهارس موجودة على tickets status/department/createdAt/priority.
- العلاقات الأساسية موجودة.

نقاط تحتاج تحسين:

- `tickets` لا يحتوي `tenantId` رغم احتياج النظام المتعدد المستأجرين له في التقارير والعدادات.
- `audit_logs.userId` أصبح nullable عبر migration لاحق، وهذا جيد لفشل تسجيل الدخول، لكن يجب التأكد من عدم وجود foreign key قديم يمنع null.
- الجداول المؤرشفة لا تحتوي `tenantId`، وهذا قد يصعب التقارير التاريخية متعددة المستأجرين.

توصيات:

- إضافة `tenantId` إلى `tickets` و `archived_survey_responses` و `archived_audit_logs` إذا كانت الأرشفة ستستخدم تقارير متعددة المستأجرين.
- إضافة فهرس مركب متكرر الاستخدام:
  - `survey_responses(tenantId, submittedAt)`
  - `survey_responses(tenantId, department, submittedAt)`
  - `tickets(department, status, createdAt)`
  - إذا أضيف `tenantId`: `tickets(tenantId, status, createdAt)`.

## جودة الكود والصيانة

### DashboardController كبير جدًا

الحجم: حوالي 65KB ويحتوي مسؤوليات كثيرة:

- Dashboard
- responses
- exports
- tickets
- users
- audit
- error logs
- reports
- predictive
- settings
- backups
- monitoring

الأثر:

- صعوبة اختبار وتعديل السلوك.
- سهولة إدخال أخطاء جانبية.

التوصية:

- تقسيمه إلى Controllers أصغر:
  - `DashboardHomeController`
  - `ResponseController`
  - `TicketController`
  - `UserManagementController`
  - `AuditController`
  - `BackupController`
  - `ReportController`
  - `PredictiveController`

### تكرار منطق الفلاتر

يوجد `ResponseFilterQuery` جيد، لكن هناك منطق مشابه مكرر في التقارير والطباعة.

التوصية:

- توحيد كل فلاتر الردود في `ResponseFilterQuery`.
- إضافة methods مثل `baseScopedQuery()`, `applyDateFilter()`, `applySearch()`.

### إعداد ESLint غير متوافق مع dependencies

المكان: `eslint.config.js`.

يستورد:

- `eslint-plugin-react-hooks`
- `eslint-plugin-react-refresh`

لكنها ليست في `package.json`.

التوصية:

- إما تثبيت الحزمتين إن كان React سيعود.
- أو إزالة إعدادات React من ESLint لأن الواجهة الحالية TypeScript/Alpine.

## الواجهة وتجربة المستخدم التقنية

نقاط جيدة:

- Vite build يعمل.
- Alpine أصبح محليًا داخل bundle بدل CDN.
- PWA مفعلة.

نقاط انتباه:

- PWA قد تُبقي ملفات JS قديمة في المتصفح، وهذا ظهر عمليًا في زر الإنذار المبكر.
- `public/storage` غير مربوط حسب `php artisan about`.

التوصيات:

- أضف آلية أوضح لتحديث service worker بعد deploy.
- نفذ `php artisan storage:link` في بيئة الإنتاج إذا كانت الملفات العامة تعتمد عليه.
- اختبر المسارات الحساسة عبر Playwright بعد البناء، خصوصًا: تسجيل الدخول، الإنذار المبكر، التصدير، النسخ الاحتياطية.

## الاختبارات

الموجود جيد ويغطي:

- صلاحيات صفحات اللوحة.
- الفلاتر AJAX.
- تصدير الردود.
- النسخ الاحتياطي safety.
- تدفق الاستبيان العام.
- إنشاء تذاكر التقييم المنخفض.
- localization.
- PredictiveService جزئيًا.

الناقص المقترح:

- اختبار `reports()` مع مستخدم لديه `tenantId`.
- اختبار أن عدادات التذاكر scoped حسب المستأجر والقسم.
- اختبار أن restore backup ممنوع عندما `DB_BACKUP_RESTORE_ENABLED=false`.
- اختبار throttle على login.
- اختبار حجم كبير نسبيًا للتصدير أو على الأقل استخدام cursor/chunk.
- اختبار Playwright لزر `اتخاذ إجراء` في predictive modal.

## خطة إصلاح مقترحة حسب الأولوية

## تحديث التنفيذ - 2026-06-01

تمت معالجة عناصر P0 التالية:

- إصلاح فلترة التذاكر في التقارير والعدادات عبر نطاق المستخدم بدل الاعتماد على عمود `tickets.tenantId` غير الموجود.
- منع مسارات استعادة النسخ الاحتياطية عندما يكون `DB_BACKUP_RESTORE_ENABLED=false`.
- تطبيق `throttle:login` على مسار تسجيل الدخول.
- إضافة اختبارات تغطي منع الاستعادة، rate limit لتسجيل الدخول، وتقرير مستخدم متعدد المستأجرين.

نتيجة التحقق بعد الإصلاح:

- `php artisan test`: نجح، 70 اختبارًا، 353 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة عناصر P1 التالية:

- إصلاح `npm run lint` بإزالة إعدادات React غير المستخدمة من ESLint.
- تحويل CSV export إلى streaming عبر `cursor()` بدل تحميل جميع الردود في الذاكرة.
- وضع حد آمن لطباعة الردود الكبيرة عند 1000 رد لتجنب استهلاك الذاكرة.
- إزالة سجل debug من `count_only`.
- إضافة اختبار يؤكد حد الطباعة للنتائج الكبيرة.

نتيجة التحقق بعد إصلاحات P1:

- `php artisan test`: نجح، 71 اختبارًا، 356 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة تحسينات إضافية من P1:

- إعادة بناء `PredictiveService::getAlerts()` ليبدأ بتجميع SQL حسب القسم بدل تحميل كل ردود آخر 30 يومًا إلى الذاكرة.
- جعل حساب trend الأسبوعي يستخدم استعلامات aggregate لكل أسبوع بدل تحميل آخر 84 يومًا كاملة.
- تحويل NPS السابق إلى subquery بدل `pluck()` لكل IDs السابقة.
- إضافة cache قصير لإحصاءات الصفحة الرئيسية الثقيلة والإنذارات التنبؤية.
- استبدال `Cache::flush()` عند استقبال استبيان جديد بنظام version خاص بإحصاءات اللوحة حتى لا يمسح كاش التطبيق كله.
- إضافة اختبارات للحالات الفارغة واكتشاف الانخفاض التنبؤي.

نتيجة التحقق بعد تحسين predictive/cache:

- `php artisan test`: نجح، 73 اختبارًا، 366 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة عناصر P2 عالية العائد التالية:

- إضافة migration لفهارس مركبة تخدم أكثر استعلامات اللوحة والتقارير تكرارًا:
  - `survey_responses(tenantId, submittedAt)`
  - `survey_responses(tenantId, department, submittedAt)`
  - `tickets(department, status, createdAt)`
- تشديد التحقق من شعار المستشفى في الإعدادات ليقبل فقط data URLs من نوع PNG أو JPEG أو WebP، ويرفض SVG أو أي نوع غير مدعوم.
- إضافة اختبارات Feature تؤكد رفض شعار SVG وقبول شعار PNG.

نتيجة التحقق بعد إصلاحات P2:

- `php artisan test`: نجح، 75 اختبارًا، 373 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة تحسين إضافي من P2:

- نقل شعارات المستشفى المرفوعة كـ base64 من قاعدة البيانات إلى `storage/app/public/settings/logos`.
- حفظ مسار قصير مثل `/storage/settings/logos/...png` داخل الإعدادات بدل النص الكامل للصورة.
- التحقق الفعلي من محتوى الصورة قبل الحفظ باستخدام `getimagesizefromstring`، وليس فقط التحقق من بادئة النص.
- السماح فقط بـ PNG وJPEG وWebP، مع دعم رابط HTTPS مباشر لهذه الأنواع عند الحاجة.
- تحديث واجهة الإعدادات لتمنع اختيار SVG من البداية وتعرض رسالة واضحة عند نوع غير مدعوم.
- إنشاء رابط `public/storage` محليًا عبر `php artisan storage:link` حتى تظهر الملفات العامة.

نتيجة التحقق بعد نقل الشعار إلى storage:

- `php artisan test`: نجح، 75 اختبارًا، 377 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة بند أمني إضافي:

- تقييد endpoint تسجيل أحداث الواجهة `dashboard.audit.events` على actions معروفة فقط: `print_report` و`export_report`.
- إضافة حدود طول على `messageKey` وقيم `params` المقبولة حتى لا يتحول endpoint إلى قناة إدخال مفتوحة لسجل التدقيق.
- إضافة اختبار يؤكد رفض action غير معروف وعدم تسجيله في `audit_logs`.
- تجاهل `public/storage` وملفات `storage/app/public` المرفوعة في git لأنها ملفات تشغيلية/محلية وليست كودًا.

نتيجة التحقق بعد حماية أحداث التدقيق:

- `php artisan test`: نجح، 77 اختبارًا، 382 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تم بدء معالجة بند تفكيك `DashboardController`:

- تشغيل المشروع محليًا:
  - Laravel: `http://127.0.0.1:49152`
  - Vite: `http://127.0.0.1:5173`
- فصل إعدادات النظام إلى `App\Http\Controllers\Web\SettingsController`.
- فصل النسخ الاحتياطي إلى `App\Http\Controllers\Web\BackupController`.
- إبقاء أسماء routes كما هي حتى لا تتغير روابط الواجهة أو الاختبارات.
- التأكد من أن routes الإعدادات والنسخ الاحتياطي أصبحت تشير إلى controllers الجديدة.
- تقليل مسؤوليات `DashboardController` بإزالة دوال الإعدادات والنسخ الاحتياطي منه.

نتيجة التحقق بعد التفكيك الأول:

- `php artisan test`: نجح، 77 اختبارًا، 382 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت متابعة تفكيك `DashboardController`:

- فصل التذاكر إلى `App\Http\Controllers\Web\TicketController`.
- فصل إدارة المستخدمين إلى `App\Http\Controllers\Web\UserManagementController`.
- إبقاء `changePassword` مؤقتًا في `DashboardController` لأنه يخدم الحساب الحالي ويتداخل مع نطاق المستخدم الحالي.
- إبقاء أسماء routes كما هي:
  - `dashboard.tickets*`
  - `dashboard.users*`
- التأكد من أن routes التذاكر والمستخدمين أصبحت تشير إلى controllers الجديدة.

نتيجة التحقق بعد فصل التذاكر والمستخدمين:

- `php artisan test`: نجح، 77 اختبارًا، 382 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت متابعة تفكيك `DashboardController`:

- فصل إدارة الاستبيانات إلى `App\Http\Controllers\Web\SurveyController`.
- فصل الشاشات التشغيلية إلى `App\Http\Controllers\Web\OperationsController`:
  - سجل التدقيق.
  - أحداث التدقيق القادمة من الواجهة.
  - سجلات الأخطاء.
  - مراقبة صحة النظام.
- إبقاء أسماء routes كما هي:
  - `dashboard.surveys*`
  - `dashboard.audit*`
  - `dashboard.error-logs*`
  - `dashboard.monitoring`
- تنظيف imports القديمة من `DashboardController` بعد النقل.

نتيجة التحقق بعد فصل الاستبيانات والشاشات التشغيلية:

- `php artisan test`: نجح، 77 اختبارًا، 382 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت متابعة تفكيك `DashboardController` حتى أصبح Controller صغيرًا للصفحة الرئيسية:

- فصل الردود والتصدير إلى `App\Http\Controllers\Web\ResponseController`.
- فصل التقارير والإنذار التنبؤي وقاعة التميز إلى `App\Http\Controllers\Web\AnalyticsController`.
- فصل تغيير كلمة المرور إلى `App\Http\Controllers\Web\AccountController`.
- إزالة دوال غير مستخدمة مثل `placeholder`.
- تقليص `DashboardController` إلى 83 سطرًا تقريبًا بعد أن كان ملفًا ضخمًا يجمع معظم مسؤوليات اللوحة.
- إبقاء أسماء routes كما هي:
  - `dashboard.responses*`
  - `dashboard.reports`
  - `dashboard.predictive*`
  - `dashboard.hall-of-fame`
  - `dashboard.change-password`

نتيجة التحقق بعد التفكيك النهائي للوحة:

- `php artisan test`: نجح، 77 اختبارًا، 382 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت إضافة اختبارات أعمق للمسارات التي تم فصلها:

- إضافة `SurveyManagementControllerTest` لتغطية:
  - إنشاء استبيان مع أقسام وأسئلة.
  - تنظيف النصائح الفارغة وتوحيد الأقسام المكررة.
  - تعديل الاستبيان وأسئلته.
  - تفعيل/تعطيل الاستبيان.
  - تكرار الاستبيان.
  - حذف الاستبيان.
  - منع tenant admin من تكرار استبيان مستأجر آخر.
- إضافة `UserManagementControllerTest` لتغطية:
  - إنشاء مستخدم.
  - تعديل الدور والقسم وكلمة المرور.
  - تعطيل المستخدم.
  - حذف المستخدم.
  - منع admin من إنشاء أو تعديل super_admin.
  - منع المستخدم من تعطيل أو حذف حسابه الحالي.

نتيجة التحقق بعد الاختبارات الإضافية:

- `php artisan test`: نجح، 82 اختبارًا، 424 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت متابعة تحسين الأداء خارج الصفحة الرئيسية:

- توسيع `DashboardAnalyticsCache` ليدعم بصمة ثابتة للفلاتر، حتى لا تختلط نتائج cache بين فلاتر التقارير المختلفة.
- إضافة cache قصير لإحصاءات صفحة التقارير حسب المستأجر/الدور/القسم وفلاتر التاريخ والقسم.
- إضافة cache قصير لقائمة تذاكر التقارير حسب نطاق المستخدم وفلتر القسم.
- إضافة cache قصير لإنذارات صفحة predictive نفسها، وليس فقط badge الصفحة الرئيسية.
- إضافة cache قصير لإحصاءات قاعة التميز حسب فلاتر التاريخ، مع إبقاء البحث النصي محليًا فوق النتائج المخزنة.
- إضافة اختبار Unit يؤكد أن مفاتيح cache تتغير عند اختلاف الفلاتر وتبقى ثابتة عند اختلاف ترتيبها.

نتيجة التحقق بعد توسيع cache:

- `php artisan test`: نجح، 83 اختبارًا، 426 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run build`: نجح.
- `npm run lint`: نجح.
- `composer pint:fix`: نجح.

تمت معالجة بند اختبارات Browser للإنذار المبكر:

- إضافة `E2ePredictiveSeeder` لتجهيز بيانات E2E ثابتة وغير مدمرة نسبيًا:
  - مستخدم `super_admin` بكلمة مرور demo.
  - استبيان وردود تحقق انخفاضًا واضحًا في قسم `E2E Emergency`.
  - تحديث version cache الخاص بتحليلات اللوحة حتى لا تقرأ الصفحة نتائج قديمة.
- إضافة اختبار Playwright في `tests-e2e/predictive.spec.ts` يغطي:
  - تسجيل الدخول.
  - فتح صفحة الإنذار المبكر.
  - الضغط على زر `اتخاذ إجراء`.
  - التأكد من ظهور modal خطة الإجراء.
  - التأكد من تعبئة حقل القسم المخفي قبل الإرسال.
  - إغلاق modal.
- إضافة `globalSetup` لاختبارات Playwright يمسح cache قبل الاختبار ويجهز بيانات E2E مرة واحدة.
- ضبط Playwright على worker واحد لأن اختبارات الدخول تستخدم نفس الحساب ونفس IP، ومع `throttle:login` قد يؤدي التشغيل المتوازي إلى `429 Too Many Requests`.
- أثناء تشغيل الاختبار ظهر أن migration جعل `audit_logs.userId` nullable كان يفترض اسم foreign key ثابتًا، وفشل على قاعدة MySQL المحلية. تم تعديل migration ليكتشف اسم القيد من `information_schema` قبل الحذف، ويتجنب إضافة قيود مكررة.
- تم تشغيل `php artisan migrate --force` محليًا بنجاح، وطبّق:
  - `2026_05_29_142003_make_user_id_nullable_in_audit_logs_table`
  - `2026_06_01_000001_add_dashboard_performance_indexes`

نتيجة التحقق بعد اختبار Browser:

- `php artisan test`: نجح، 83 اختبارًا، 426 assertion.
- `npm test`: نجح، 12 اختبارًا.
- `npm run lint`: نجح.
- `npm run build`: نجح.
- `composer pint:fix`: نجح.
- `npm run test:e2e -- predictive.spec.ts`: نجح، اختبار Playwright واحد.
- `npm run test:e2e`: نجح، 8 اختبارات Playwright.

### أولوية P0

1. إصلاح فلترة `tickets.tenantId` في التقارير.
2. تطبيق `restoreEnabled()` ومنع استعادة النسخ الاحتياطية افتراضيًا.
3. تطبيق `throttle:login`.

### أولوية P1

1. توحيد scoping للتذاكر في كل العدادات والقوائم.
2. تحويل CSV export والطباعة إلى streaming/chunking أو job.
3. تحسين predictive aggregates وتقليل التحميل في الذاكرة.
4. إصلاح ESLint dependencies أو إزالة إعداد React.

### أولوية P2

1. توسيع اختبارات Browser/Playwright لاحقًا لنماذج الاستبيانات والمستخدمين.

## الحكم النهائي

النظام مناسب كنسخة تشغيلية أولية أو داخلية، لكنه قبل الإنتاج على بيانات كبيرة يحتاج إغلاق ثلاث مناطق: تعدد المستأجرين في التذاكر، أمان استعادة النسخ الاحتياطية، وأداء التصدير والتحليل. بعد معالجة هذه النقاط، ستكون القاعدة التقنية أكثر ثباتًا وقابلة للنمو.
