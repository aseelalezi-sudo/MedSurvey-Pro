# MedSurvey Pro — LicenseHub SDK

تتحكم هذه الحزمة بترخيص التثبيت *ككل* عبر خادم **LicenseHub**. توفر تسجيلاً عبر الإنترنت، وتوكنات موقّعة للعمل **بلا اتصال**، وكشف فوري للإلغاء، وقراءة ميزات مضمّنة في التوكن.

> **الإعداد المطلوب**: قاعدة `license_states` عبر `php artisan migrate`، ثم قيم في `.env` (انظر أسفل الملف).

---

## خطوات الإعداد

```bash
php artisan migrate                       # ينشئ جدول license_states
```

ضبط `.env`:

```ini
LICENSING_ENABLED=true
LICENSING_SERVER_URL=https://license.yourdomain.com
LICENSING_PRODUCT=medsurvey-pro
LICENSING_LICENSE_KEY=LHB-XXXX-XXXX-XXXX-XXXX
LICENSING_DEVICE_HASH_SECRET=<أعطِه نفس LICENSE_DEVICE_HASH_SECRET في حالة LicenseHub>
LICENSING_HEARTBEAT_INTERVAL=60
```

- `LICENSING_DEVICE_HASH_SECRET` **يجب** أن يطابق سر خادم LicenseHub تماماً؛ به يثبت التوكن لهذا التثبيت.
- لإيقاف الفحص مؤقتاً أثناء التطوير اجعل `LICENSING_ENABLED=false`.

---

## الاستخدام

### 1) حماية مجموعة مسارات (الموصى بها)

أضف الوسيط `license` لأي مجموعة مسارات تريد حمايتها (مثلاً لوحة التحكم):

```php
Route::middleware(['auth', 'license'])->prefix('dashboard')->group(function () {
    // ... مسارات مضمّنة
});
```

- المطلوب صالح → استمر. غير صالح → استجابة **402** (JSON لمسارات API، صفحة `errors.license` للويب).
- عند `LICENSING_ENABLED=false` يمر الطلب دائماً.

### 2) بوابة ميزة (Feature gating)

```php
use App\Licensing\Support\LicenseCheck;

public function isAllowed(): bool
{
    /** @var LicenseCheck $l */
    $l = app(\App\Licensing\Services\LicenseService::class)->boot();

    return $l->valid() && $l->hasFeature('crm-sync');
}
```

أو عبر الـ Facade:

```php
use App\Licensing\Facades\License;

if (License::boot()->hasFeature('advanced-reports')) {
    // show report
}
```

### 3) أوامر artisan

| الأمر | الوصف |
|---|---|
| `php artisan license:status --verbose` | الحالة الحالية + تفاصيل التوكن/المفاتيح |
| `php artisan license:activate --key=LHB-...` | تفعيل (أول مرة أو بعد نقل الخادم) |
| `php artisan license:validate` | إعادة تحقق عبر الإنترنت وتحديث التوكن |
| `php artisan license:heartbeat` | نبضة دورية لرصد الإلغاء |
| `php artisan license:refresh-keys` | تحديث المفاتيح العامة |
| `php artisan license:deactivate` | تحرير مقعد الجهاز ومسح الحالة المحلية |

### 4) جدولة النبضة

أضف في `routes/console.php` أو Kernel scheduling:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('license:heartbeat')->hourly();
```

> عند تعذّر الوصول للخادم تعيدها النبضة بحالة "error" ولا تكسر التطبيق — يستمر حتى انتهاء صلاحية التوكن بلا اتصال.

---

## كيف يعمل (باختصار)

```
أول تشغيل:
  install fingerprint ──activate──▶ LicenseHub ──▶ token موقّع (JWT EdDSA) + مفاتيح عامة
                                                      │
  تشغيل لاحق (كل طلب):
  boot() ──فحص محلي للتوكن (توقيع + منتج + exp + devf)──▶ صالح ➜ شغّل بلا اتصال
                                    │ غير صالح
                                    ▼
                     validate/heartbeat عبر الإنترنت (رصد الإلغاء/الانتهاء)
```

- **بلا اتصال**: طالما التوكن سليمياً وغير منته (مدة `LICENSE_TOKEN_TTL_DAYS` في الخادم) يبقى النظام يعمل.
- **الإلغاء الفوري**: يُكشف عبر النبضة؛ عند التجاوب `403` تتحول الحالة إلى `revoked` ويُمنع الوصول.
- **الأمان**: المفتاح والتوكن والبصمة مخزّنة مشفّرة (`encrypted` cast)، والمفاتيح العامة تُجلب وتُخزّن، والتوثيق يُتحقق منه بـ `sodium` مباشرة.

---

## حالات `LicenseStatus`

| الحالة | المعنى |
|---|---|
| `valid` | توكن سليم غير منته |
| `not_activated` | لا يوجد مفتاح/تفعيل بعد |
| `invalid` | المنتج في التوكن لا يطابق التثبيت |
| `invalid_token` | توقيع/بهود/مفتاح تالف |
| `revoked` | أُلغي أو عُلّق على الخادم |
| `expired` | انتهت فترة الصلاحية (وانتهت نافذة بلا اتصال) |
| `offline` | لا اتصال ولا توكن صالح |
| `activation_failed` | رفض الخادم (مفتاح/مقاعد) |
| `error` | خطأ نقل/إعدادات |

---

## الاختبارات

```bash
php vendor/bin/phpunit --testsuite Licensing
```

تشمل اختبارات بلا اتصال، وتفشيل إلكتروني عبر `FakeLicenseClient`، وإلغاء، ورفض توكن جهاز آخر.