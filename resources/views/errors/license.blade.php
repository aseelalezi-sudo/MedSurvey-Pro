@php
use App\Licensing\Enums\LicenseStatus;
$messages = [
    LicenseStatus::Expired->value => ['Licencia expirada', 'انتهت صلاحية الترخيص'],
    LicenseStatus::Revoked->value => ['Licencia revocada', 'تم إلغاء الترخيص'],
    LicenseStatus::NotActivated->value => ['Instalación sin activar', 'لم يتم تفعيل هذا التثبيت'],
    LicenseStatus::Offline->value => ['Sin conexión y sin token válido', 'لا يوجد اتصال ولا توكن ساري'],
];
[$title, $subtitle] = $messages[$check->status->value] ?? ['Licencia no válida', 'الترخيص غير صالح'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f7fb; color: #333; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 48px 40px; max-width: 460px; width: 100%; text-align: center; }
        h1 { font-size: 22px; margin: 0 0 10px; }
        p { color: #64748b; line-height: 1.7; margin: 0 0 24px; }
        .status { display: inline-block; font-size: 12px; letter-spacing: .04em; padding: 6px 12px; border-radius: 999px; background: #fee2e2; color: #b91c1c; margin-bottom: 16px; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="status">{{ $check->status->value }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $subtitle }}</p>
        <p><small>{{ $check->reason }}</small></p>
    </div>
</body>
</html>