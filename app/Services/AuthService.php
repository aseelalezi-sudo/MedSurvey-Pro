<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function login(string $username, string $password): ?array
    {
        $user = User::query()
            ->where('username', $username)
            ->where('isActive', true)
            ->first();

        if (! $user || ! password_verify($password, $user->password)) {
            return null;
        }

        $user->forceFill(['lastLogin' => now()])->save();

        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->createRefreshToken($user);

        return [
            'token' => $token,
            'user' => $this->serializeUser($user),
            'refreshToken' => $refreshToken,
        ];
    }

    public function refresh(?string $refreshToken): ?array
    {
        if (! $refreshToken) {
            return null;
        }

        $storedToken = RefreshToken::query()
            ->where('token', hash('sha256', $refreshToken))
            ->where('expiresAt', '>', now())
            ->first();

        if (! $storedToken) {
            return null;
        }

        $user = User::query()->whereKey($storedToken->userId)->where('isActive', true)->first();

        if (! $user) {
            $storedToken->delete();

            return null;
        }

        $storedToken->delete();

        $newRefreshToken = $this->createRefreshToken($user);
        $token = JWTAuth::fromUser($user);

        return [
            'token' => $token,
            'refreshToken' => $newRefreshToken,
            'user' => $this->serializeUser($user),
        ];
    }

    public function logout(?string $refreshToken): void
    {
        if ($refreshToken) {
            RefreshToken::query()->where('token', hash('sha256', $refreshToken))->delete();
        }

        if (JWTAuth::getToken()) {
            auth('api')->logout();
        }
    }

    public function serializeUser(User $user): array
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
