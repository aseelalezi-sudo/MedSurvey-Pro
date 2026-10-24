<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $deviceName = trim((string) ($credentials['deviceName'] ?? '')) ?: 'android-app';

        $user = User::query()
            ->where('username', $credentials['username'])
            ->where('isActive', true)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit($request, $user, 'login_failed', [
                'messageKey' => 'audit.details.login_failed',
                'params' => ['username' => $credentials['username']],
                'channel' => 'android',
            ]);

            throw ValidationException::withMessages([
                'username' => ['اسم المستخدم أو كلمة المرور غير صحيحة، أو أن الحساب غير نشط.'],
            ]);
        }

        $user->forceFill(['lastLogin' => now()])->save();

        $expiresAt = now()->addDays(30);
        $token = $user->createToken($deviceName, ['app'], $expiresAt);

        $this->audit($request, $user, 'login', [
            'messageKey' => 'audit.details.login',
            'params' => ['username' => $user->username],
            'channel' => 'android',
            'deviceName' => $deviceName,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->toISOString(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessToken = $user?->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        $this->audit($request, $user, 'logout', [
            'messageKey' => 'audit.details.logout',
            'params' => [],
            'channel' => 'android',
        ]);

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'department' => $user->department,
            'tenantId' => $user->tenantId,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(Request $request, ?User $user, string $action, array $details): void
    {
        if (! $user) {
            return;
        }

        try {
            AuditLog::query()->create([
                'userId' => $user->id,
                'tenantId' => $user->tenantId,
                'action' => $action,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ipAddress' => AuditRequestContext::ipAddress($request),
                'userAgent' => AuditRequestContext::userAgent($request),
                'deviceName' => AuditRequestContext::deviceName($request),
            ]);
        } catch (\Throwable) {
            // A failed audit insert must never block authentication.
        }
    }
}
