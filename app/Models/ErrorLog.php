<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use UsesCuid;

    protected $table = 'error_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $appends = [
        'translatedMessage',
    ];

    protected $fillable = [
        'id',
        'level',
        'message',
        'stack',
        'source',
        'metadata',
        'status',
        'resolutionNotes',
        'count',
        'createdAt',
        'resolvedAt',
        'userId',
        'tenantId',
    ];

    protected $casts = [
        'metadata' => 'array',
        'createdAt' => 'datetime',
        'resolvedAt' => 'datetime',
    ];

    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user?->role === 'super_admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            if ($user?->tenantId) {
                $q->where($q->qualifyColumn('tenantId'), $user->tenantId);

                return;
            }

            $q->whereNull($q->qualifyColumn('tenantId'));
        });
    }

    public function getTranslatedMessageAttribute(): string
    {
        $message = (string) $this->message;

        if (app()->getLocale() !== 'ar' || $message === '') {
            return $message;
        }

        return $this->translateMessageToArabic($message);
    }

    private function translateMessageToArabic(string $message): string
    {
        $patterns = [
            '/^Command "([^"]+)" is not defined\.$/i' => 'الأمر "$1" غير معرّف في Artisan.',
            '/^There are no commands defined in the "([^"]+)" namespace\.$/i' => 'لا توجد أوامر معرّفة ضمن نطاق "$1".',
            '/^Class "([^"]+)" not found$/i' => 'الصنف "$1" غير موجود.',
            '/^Target class \[([^\]]+)\] does not exist\.$/i' => 'الصنف أو الخدمة المطلوبة "$1" غير موجودة.',
            '/^Method ([^\s]+) does not exist\.$/i' => 'الدالة "$1" غير موجودة.',
            '/^Call to undefined method ([^\s]+)$/i' => 'تم استدعاء دالة غير معرّفة: $1.',
            '/^Call to undefined function ([^\s]+)\(\)$/i' => 'تم استدعاء دالة غير معرّفة: $1().',
            '/^Undefined variable \$(.+)$/i' => 'المتغير "$$1" غير معرّف.',
            '/^Undefined array key "([^"]+)"$/i' => 'مفتاح المصفوفة "$1" غير موجود.',
            '/^Attempt to read property "([^"]+)" on null$/i' => 'تمت محاولة قراءة الخاصية "$1" من قيمة فارغة.',
            '/^Trying to access array offset on null$/i' => 'تمت محاولة الوصول إلى عنصر في مصفوفة فارغة.',
            '/^SQLSTATE\[(.+?)\]: (.+)$/is' => 'خطأ في قاعدة البيانات [$1]: $2',
            '/^Database connection failed$/i' => 'فشل الاتصال بقاعدة البيانات.',
            '/^Cache connection failed$/i' => 'فشل الاتصال بخدمة التخزين المؤقت.',
            '/^Connection refused$/i' => 'تم رفض الاتصال بالخدمة المطلوبة.',
            '/^Connection timed out$/i' => 'انتهت مهلة الاتصال بالخدمة المطلوبة.',
            '/^cURL error ([0-9]+): (.+)$/is' => 'خطأ اتصال cURL رقم $1: $2',
            '/^The ([\w. -]+) field is required\.$/i' => 'الحقل "$1" مطلوب.',
            '/^The selected ([\w. -]+) is invalid\.$/i' => 'القيمة المحددة للحقل "$1" غير صالحة.',
            '/^The ([\w. -]+) must be a valid email address\.$/i' => 'يجب أن يكون الحقل "$1" بريدًا إلكترونيًا صالحًا.',
            '/^The ([\w. -]+) has already been taken\.$/i' => 'قيمة الحقل "$1" مستخدمة مسبقًا.',
            '/^The given data was invalid\.$/i' => 'البيانات المُدخلة غير صالحة.',
            '/^This action is unauthorized\.$/i' => 'غير مصرح بتنفيذ هذا الإجراء.',
            '/^Unauthenticated\.$/i' => 'المستخدم غير مسجل الدخول.',
            '/^Unauthorized$/i' => 'غير مصرح.',
            '/^Forbidden$/i' => 'ممنوع الوصول.',
            '/^Not Found$/i' => 'العنصر المطلوب غير موجود.',
            '/^Page Expired$/i' => 'انتهت صلاحية الصفحة أو الجلسة.',
            '/^Too Many Requests$/i' => 'عدد كبير جدًا من الطلبات.',
            '/^Server Error$/i' => 'خطأ داخلي في الخادم.',
            '/^Service Unavailable$/i' => 'الخدمة غير متاحة حاليًا.',
            '/^Backup failed: (.+)$/is' => 'فشل إنشاء النسخة الاحتياطية: $1',
            '/^Backup command failed: (.+)$/is' => 'فشل تنفيذ أمر النسخ الاحتياطي: $1',
            '/^No application encryption key has been specified\.$/i' => 'لم يتم تحديد مفتاح تشفير التطبيق.',
            '/^The stream or file "([^"]+)" could not be opened in append mode: (.+)$/is' => 'تعذر فتح الملف "$1" للإضافة: $2',
            '/^file_put_contents\(([^)]+)\): Failed to open stream: (.+)$/is' => 'فشل الكتابة إلى الملف "$1": $2',
        ];

        foreach ($patterns as $pattern => $translation) {
            if (preg_match($pattern, $message)) {
                return preg_replace($pattern, $translation, $message) ?: $message;
            }
        }

        return strtr($message, [
            'Command' => 'الأمر',
            'is not defined' => 'غير معرّف',
            'not found' => 'غير موجود',
            'failed' => 'فشل',
            'Failed' => 'فشل',
            'error' => 'خطأ',
            'Error' => 'خطأ',
            'exception' => 'استثناء',
            'Exception' => 'استثناء',
            'database' => 'قاعدة البيانات',
            'Database' => 'قاعدة البيانات',
            'connection' => 'الاتصال',
            'Connection' => 'الاتصال',
            'permission denied' => 'تم رفض الصلاحية',
            'Permission denied' => 'تم رفض الصلاحية',
        ]);
    }
}
