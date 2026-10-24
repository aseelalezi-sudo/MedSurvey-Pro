# تكامل تطبيق الأندرويد | MedSurvey Pro API

توثيق واجهات الـAPI التي يستخدمها تطبيق Flutter، وآلية المزامنة الآمنة للاستبيانات التي تُعبأ دون اتصال.

## نظرة عامة

| العنصر | القيمة |
| --- | --- |
| بادئة المسارات | `/api/v1` |
| المصادقة | Laravel Sanctum (Bearer Token) |
| صلاحية التوكن | 30 يومًا |
| تنسيق البيانات | JSON / UTF-8 |
| معرّف الاستجابة الفريد | `clientResponseId` (UUID من التطبيق) |
| حفظ الاستجابة | مرة واحدة فقط لكل `clientResponseId` |

## تشغيل التحديثات على الخادم

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

> لا تستخدم `php artisan down` لمجرد إضافة جداول؛ قد يبقى الموقع متوقفًا إذا فشل أمر لاحق. الترحيلات هنا آمنة وقابلة للتكرار.

المهاجرات المضافة:

- `2026_10_22_000000_create_personal_access_tokens_table.php`
  - جدول Sanctum. صُمم ليدعم مفاتيح المستخدمين النصية (CUID) بدل الأعداد الصحيحة.
- `2026_10_22_000001_add_client_response_id_to_survey_responses_table.php`
  - عمود `clientResponseId` فريد (nullable) في `survey_responses`.
- `2026_10_23_000000_create_queue_tables.php`
  - جداول `jobs` و`job_batches` و`failed_jobs`. مطلوبة لأن `QUEUE_CONNECTION=database`، وبدونها كان إرسال حدث `SurveySubmitted` يفشل برسالة `Table 'jobs' doesn't exist`.

> إذا كان جدول `personal_access_tokens` موجودًا مسبقًا في بيئة الإنتاج، فلن تنشئ المهاجرة جدولًا جديدًا. تأكد فقط أن عمود `tokenable_id` من نوع `string` وليس `bigint` لأن مستخدمي النظام يستخدمون CUID نصيًا.

### عمليات دائمة التشغيل (حسب بيئة الخادم)

**الاستضافة العادية (الإعداد المعتمد في `deploy/shared-hosting.env.example`):**

```env
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
```

لا تحتاج إلى عامل طابور ولا خادم بث في هذه الحالة، وتُنفَّذ الأحداث مباشرةً دون جداول طابور. تظهر رسالة `Table 'jobs' doesn't exist` فقط إذا كان `QUEUE_CONNECTION=database` بدون تشغيل المهاجرة الجديدة.

**خادم كامل مع بث مباشر (Reverb):**

```bash
# عامل الطابور (يفضَّل عبر Supervisor أو systemd)
php artisan queue:work --tries=3 --sleep=2

# خادم البث المباشر على المنفذ المُعد في .env
php artisan reverb:start --host=127.0.0.1 --port=8280
```

يجب إعادة تشغيل العمّال بعد كل نشر جديد:

```bash
php artisan queue:restart
```

للتحقق من عدم وجود وظائف عالقة أو فاشلة:

```bash
php artisan queue:failed
php artisan queue:monitor default:100
```

## المصادقة

### تسجيل الدخول

`POST /api/v1/auth/login`

```json
{
  "username": "staff",
  "password": "Password123!",
  "deviceName": "android-app"
}
```

الاستجابة `200`:

```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "tokenType": "Bearer",
  "expiresAt": "2026-11-21T12:00:00.000000Z",
  "user": {
    "id": "c...",
    "username": "staff",
    "name": "جامع الاستبيانات",
    "role": "staff",
    "department": "الطوارئ",
    "tenantId": null
  }
}
```

- الحساب يجب أن يكون `isActive = true`.
- الخطأ `422` يعيد رسالة عربية داخل `errors.username`.
- الطلبات محدودة بـ10 محاولات في الدقيقة لكل IP.

### بيانات المستخدم الحالي

`GET /api/v1/auth/me`  
رأس الطلب: `Authorization: Bearer <token>`

### تسجيل الخروج

`POST /api/v1/auth/logout`  
يحذف التوكن الحالي فقط. الاستبيانات غير المرفوعة تبقى على الجهاز.

## البيانات اللازمة للنموذج

`GET /api/v1/bootstrap`

استجابة مختصرة:

```json
{
  "user": { "id": "...", "name": "...", "role": "staff", "tenantId": null },
  "surveys": [
    {
      "id": "survey-1",
      "title": "استبيان رضا المرضى",
      "description": "...",
      "isActive": true,
      "requireName": false,
      "requirePhone": false,
      "assignedDepartments": ["الطوارئ"],
      "sections": [
        {
          "id": "section-1",
          "title": "القسم الأول",
          "description": "",
          "questions": [
            {
              "id": "q1",
              "type": "stars",
              "title": "ما تقييمك للخدمة؟",
              "description": null,
              "required": true,
              "category": "",
              "options": null,
              "followUp": null
            }
          ]
        }
      ]
    }
  ],
  "settings": {
    "hospital": { "name": "المستشفى", "logo": "", "phone": "", "address": "" },
    "departments": [{ "id": "d1", "name": "الطوارئ", "color": "#EF4444" }],
    "ageGroups": [{ "id": "a1", "label": "18 - 30 سنة" }],
    "visitTypes": [{ "id": "v1", "label": "زيارة طارئة" }],
    "surveySettings": {
      "allowAnonymous": true,
      "requireAllQuestions": false,
      "requireName": false,
      "requirePhone": false,
      "showProgressBar": true,
      "enableThankYouPage": true,
      "thankYouMessage": "..."
    },
    "appearance": {}
  },
  "serverTime": "2026-10-22T12:00:00.000000Z"
}
```

- تُعاد الاستبيانات النشطة الخاصة بمستأجر المستخدم + الاستبيانات العامة (`tenantId = null`).
- تُفلتر الأقسام والفئات وأنواع الزيارة إلى العناصر النشطة فقط.
- يمكن استخدام `GET /api/v1/surveys` أو `GET /api/v1/surveys/{id}` لتحديث الاستبيانات منفردة.

## إرسال استجابة (مع دعم إعادة المحاولة)

`POST /api/v1/responses`

```json
{
  "clientResponseId": "6f1c2b8e-4d3a-4c8e-9f31-2a7b6d5e4c10",
  "surveyId": "survey-1",
  "department": "الطوارئ",
  "answers": [
    { "questionId": "q1", "value": 5 },
    { "questionId": "q2", "value": "yes" },
    { "questionId": "q3", "value": "ملاحظة نصية" },
    { "questionId": "q4_reason", "value": "سبب التقييم المنخفض" }
  ],
  "patientInfo": {
    "name": "اسم المريض",
    "phone": "712345678",
    "ageGroup": "18 - 30 سنة",
    "gender": "ذكر",
    "visitType": "زيارة طارئة"
  }
}
```

### الاستجابة الناجحة `201`

```json
{
  "id": "c...",
  "clientResponseId": "6f1c2b8e-4d3a-4c8e-9f31-2a7b6d5e4c10",
  "surveyId": "survey-1",
  "department": "الطوارئ",
  "overallScore": 92,
  "submittedAt": "2026-10-22T12:00:00.000000Z",
  "duplicate": false
}
```

### إعادة إرسال نفس المعرّف

إذا وصل الطلب نفسه مرة أخرى (بسبب انقطاع الاتصال بعد نجاح الحفظ مثلًا):

- تعيد `200` مع `"duplicate": true` ونفس `id`.
- **لا** تُنشأ استجابة ثانية.
- **لا** يُنشأ بلاغ ثانٍ للتقييم المنخفض.
- المعرّف مرتبط بالحساب؛ إذا أرسل حساب آخر نفس المعرّف يعيد الخادم `409`.

### ملاحظات التحقق

- `clientResponseId`: مطلوب، 8–64 حرفًا، أحرف/أرقام/`.`/`_`/`-`.
- `answers`: مصفوفة من `{questionId, value}` أو كائن مفاتيحه معرّفات الأسئلة.
- `department`: يجب أن يكون ضمن الأقسام النشطة إن كانت القائمة معرّفة.
- الأسئلة المطلوبة تُتحقق حسب `required` أو `requireAllQuestions`.
- `stars/rating/emoji`: 1–5، `nps`: 0–10، `yes_no`: `yes/no`.
- `phone`: عند إلزامه يجب أن يكون 9 أرقام ويبدأ بـ7.
- حماية anti-bot الخاصة بالويب (التوقيت/الهوني بوت) لا تُطبق على طلبات التطبيق المصادق عليها.

## الأمان والعزل

- المستأجر يُؤخذ من `user.tenantId` ولا يُقبل من العميل.
- لا يمكن للمستخدم إرسال استجابة لاستبيان مستأجر آخر؛ يعيد الخادم `422`.
- `collectorId` يُسجَّل تلقائيًا من التوكن.
- التوكنات تُحذف عند تسجيل الخروج، وتنتهي بعد 30 يومًا.
- حدود الطلبات: 180 طلبًا/دقيقة لكل مستخدم/IP للـAPI، و120/دقيقة لمسار الاستجابات.

## اختبار سريع بـcURL

```bash
curl -X POST https://survey.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"staff","password":"Password123!","deviceName":"curl"}'

curl -X GET https://survey.example.com/api/v1/bootstrap \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

## استكشاف الأخطاء

| مشكلة | سبب محتمل | حل |
| --- | --- | --- |
| `401 Unauthenticated` | توكن منتهٍ أو محذوف | تسجيل الدخول مجددًا |
| `419 CSRF` | استخدام مسار ويب بدل API | استخدم `/api/v1/...` |
| `422` عند الإرسال | بيانات ناقصة أو قسم/سؤال غير صالح | اقرأ رسالة `errors` و صحح النموذج |
| `409` | `clientResponseId` مستخدم لحساب آخر | احذف الاستجابة المعلقة وأعد الإدخال |
| «انتهت مهلة الاتصال» مع خادم محلي | العنوان كتب بلا `http://` فافترض التطبيق `https://`، أو الخادم يستمع على localhost، أو الهاتف على شبكة أخرى | استخدم `http://IP:PORT`، وشغّل `php artisan serve --host=0.0.0.0 --port=8080`، وتأكد من نفس شبكة Wi-Fi ومن جدار الحماية |
| فشل الاتصال من جهاز حقيقي | الخادم يستمع على localhost فقط | `php artisan serve --host=0.0.0.0` واستخدم IP الجهاز |
| فشل الاتصال عبر HTTP في الإنتاج | Android يمنع cleartext | استخدم HTTPS |
| لا تعمل المزامنة في الخلفية | قيود تحسين البطارية على بعض الأجهزة | اسمح للتطبيق بالعمل في الخلفية |

## الملفات ذات الصلة

- `routes/api.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/BootstrapController.php`
- `app/Http/Controllers/Api/SurveyController.php`
- `app/Http/Controllers/Api/ResponseController.php`
- `app/Http/Middleware/EnsureApiUserIsActive.php`
- `mobile/` — تطبيق Flutter
