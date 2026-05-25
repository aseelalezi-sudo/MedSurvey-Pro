<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ])->cookie(
            'medsurvey_refresh_token',
            $token,
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
        $token = $request->cookie('medsurvey_refresh_token') ?: JWTAuth::getToken();
        if (! $token) {
            return response()->json(['error' => 'رمز التحديث مفقود', 'code' => 'TOKEN_MISSING'], 401);
        }

        JWTAuth::setToken($token);
        $newToken = JWTAuth::refresh();

        return response()->json([
            'token' => $newToken,
        ])->cookie(
            'medsurvey_refresh_token',
            $newToken,
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
        ];
    }
}
