<?php

namespace App\Http\Controllers\Api;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('username', $credentials['username'])
            ->where('isActive', true)
            ->first();

        if (! $user || ! password_verify($credentials['password'], $user->password)) {
            return response()->json(['error' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }

        $user->forceFill(['lastLogin' => now()])->save();

        return response()->json([
            'token' => JWTAuth::fromUser($user),
            'user' => $this->serializeUser($user),
        ])->cookie(
            'medsurvey_refresh_token',
            $this->createRefreshToken($user),
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
        $refreshToken = $request->cookie('medsurvey_refresh_token') ?: $request->input('refreshToken');
        if (! $refreshToken) {
            return response()->json(['error' => 'رمز التحديث مفقود', 'code' => 'TOKEN_MISSING'], 401);
        }

        $storedToken = RefreshToken::query()
            ->where('token', hash('sha256', $refreshToken))
            ->where('expiresAt', '>', now())
            ->first();

        $user = $storedToken
            ? User::query()->whereKey($storedToken->userId)->where('isActive', true)->first()
            : null;

        if (! $storedToken || ! $user) {
            return response()->json(['error' => 'رمز التحديث غير صالح', 'code' => 'TOKEN_INVALID'], 401)
                ->withoutCookie('medsurvey_refresh_token');
        }

        $storedToken->delete();

        return response()->json([
            'token' => JWTAuth::fromUser($user),
        ])->cookie(
            'medsurvey_refresh_token',
            $this->createRefreshToken($user),
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
        if ($refreshToken) {
            RefreshToken::query()->where('token', hash('sha256', $refreshToken))->delete();
        }

        if (JWTAuth::getToken()) {
            auth('api')->logout();
        }

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح'])
            ->withoutCookie('medsurvey_refresh_token');
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'code' => 'TOKEN_MISSING'], 401);
        }

        return response()->json($this->serializeUser($user));
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'createdAt' => optional($user->createdAt)->toISOString(),
            'lastLogin' => optional($user->lastLogin)->toISOString(),
            'isActive' => $user->isActive,
            'avatar' => $user->avatar,
            'tenantId' => $user->tenantId,
        ];
    }

    private function createRefreshToken(User $user): string
    {
        $plainToken = Str::random(80);

        RefreshToken::query()->create([
            'token' => hash('sha256', $plainToken),
            'userId' => $user->id,
            'expiresAt' => now()->addDays(7),
        ]);

        return $plainToken;
    }
}
