<?php

use App\Http\Middleware\AuditMutatingApiRequests;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireWebRole;
use App\Http\Middleware\SetLocale;
use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', ''),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->api(prepend: [
            ThrottleRequests::class.':api',
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));

        $middleware->alias([
            'audit.mutations' => AuditMutatingApiRequests::class,
            'role' => RequireRole::class,
            'web.role' => RequireWebRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $exception): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return redirect()->guest(route('login'));
            }

            return response()->json([
                'error' => 'Unauthenticated',
                'code' => 'TOKEN_MISSING',
            ], 401);
        });

        $exceptions->report(function (Throwable $e) {
            try {
                if ($e instanceof AuthenticationException ||
                    $e instanceof NotFoundHttpException ||
                    $e instanceof ValidationException) {
                    return;
                }

                $rawMessage = $e->getMessage() ?: class_basename($e);
                $translatedMessage = null;

                $translations = [
                    'SQLSTATE[HY000] [2002]' => 'فشل الاتصال بقاعدة البيانات (تأكد من تشغيل خادم MySQL أو Docker)',
                    'Connection refused' => 'فشل الاتصال بقاعدة البيانات (تأكد من تشغيل خادم MySQL أو Docker)',
                    'No connection could be made' => 'فشل الاتصال بقاعدة البيانات (تأكد من تشغيل خادم MySQL أو Docker)',
                    'Column not found' => 'يوجد عمود مفقود في قاعدة البيانات (تأكد من تنفيذ أمر php artisan migrate)',
                    'Base table or view not found' => 'يوجد جدول مفقود في قاعدة البيانات (تأكد من تنفيذ أمر php artisan migrate)',
                    'Undefined variable' => 'متغير برمجي غير معرّف (تأكد من تمرير البيانات للواجهة)',
                    'syntax error' => 'خطأ إملائي في كتابة الكود البرمجي (Syntax Error)',
                    'Call to undefined method' => 'محاولة استدعاء دالة غير موجودة في النظام',
                    'View not found' => 'ملف الواجهة (View) المطلوب غير موجود',
                    'Route not defined' => 'المسار (Route) المطلوب غير معرّف',
                    'CSRF token mismatch' => 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة',
                    'Maximum execution time' => 'تم تجاوز وقت التنفيذ الأقصى (Time out)',
                    'Allowed memory size' => 'تم تجاوز حجم الذاكرة المسموح (Memory limit)',
                ];

                foreach ($translations as $en => $ar) {
                    if (stripos($rawMessage, $en) !== false) {
                        $translatedMessage = $ar;
                        break;
                    }
                }

                $finalMessage = $translatedMessage ? ($translatedMessage.' | التفاصيل: '.$rawMessage) : $rawMessage;

                ErrorLog::create([
                    'level' => 'error',
                    'message' => substr($finalMessage, 0, 500),
                    'stack' => substr($e->getTraceAsString(), 0, 5000),
                    'source' => substr(str_replace(base_path(), '', $e->getFile()).':'.$e->getLine(), 0, 255),
                    'metadata' => [
                        'class' => get_class($e),
                        'url' => request()->fullUrl(),
                        'method' => request()->method(),
                        'ip' => request()->ip(),
                        'user_id' => auth()->id(),
                    ],
                    'status' => 'new',
                    'count' => 1,
                    'createdAt' => now(),
                    'userId' => auth()->id(),
                ]);
            } catch (Throwable $loggingException) {
                // Silently fallback to default logging if the DB is down or log insertion fails
            }
        });
    })->create();
