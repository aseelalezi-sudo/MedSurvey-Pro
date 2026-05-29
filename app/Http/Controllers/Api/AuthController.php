<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Support\AuditRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->authService->login($credentials['username'], $credentials['password']);

        if (! $result) {
            // Record failed login attempt for security auditing
            AuditLog::query()->create([
                'userId' => null,
                'action' => 'login_failed',
                'details' => json_encode([
                    'messageKey' => 'audit.details.login_failed',
                    'params' => [
                        'username' => $credentials['username'],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ipAddress' => AuditRequestContext::ipAddress($request),
                'userAgent' => AuditRequestContext::userAgent($request),
                'deviceName' => AuditRequestContext::deviceName($request),
            ]);

            return ApiResponse::error('اسم المستخدم أو كلمة المرور غير صحيحة', 401);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => $result['user'],
        ])->cookie(
            'medsurvey_refresh_token',
            $result['refreshToken'],
            60 * 24 * 7,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'strict',
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('medsurvey_refresh_token');

        $result = $this->authService->refresh($refreshToken);

        if (! $result) {
            return ApiResponse::error('رمز التحديث غير صالح', 401, 'TOKEN_INVALID')
                ->withoutCookie('medsurvey_refresh_token');
        }

        return response()->json([
            'token' => $result['token'],
            'user' => $result['user'],
        ])->cookie(
            'medsurvey_refresh_token',
            $result['refreshToken'],
            60 * 24 * 7,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'strict',
        );
    }

    public function logout(): JsonResponse
    {
        $refreshToken = request()->cookie('medsurvey_refresh_token');
        $this->authService->logout($refreshToken);

        return ApiResponse::deleted('تم تسجيل الخروج بنجاح')
            ->withoutCookie('medsurvey_refresh_token');
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401, 'TOKEN_MISSING');
        }

        return response()->json($this->authService->serializeUser($user));
    }
}
