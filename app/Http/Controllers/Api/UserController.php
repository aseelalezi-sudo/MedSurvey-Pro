<?php

namespace App\Http\Controllers\Api;

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController
{
    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        $users = User::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn (User $item) => $this->serializeUser($item));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = auth('api')->user();
        $payload = $request->validate([
            'username' => ['required', 'string', 'unique:users,username'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'name' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if ($currentUser?->role !== 'super_admin' && $payload['role'] === 'super_admin') {
            return ApiResponse::error('ليس لديك صلاحية لإنشاء مستخدم بصلاحية مدير عام', 403);
        }

        $created = User::query()->create([
            'username' => $payload['username'],
            'password' => Hash::make($payload['password']),
            'name' => $payload['name'],
            'email' => $payload['email'] ?? '',
            'role' => $payload['role'],
            'department' => $payload['role'] === 'head_of_department' ? ($payload['department'] ?? null) : null,
            'tenantId' => $currentUser?->tenantId,
            'isActive' => $payload['isActive'] ?? true,
        ]);

        return ApiResponse::created($this->serializeUser($created));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth('api')->user();
        $targetUser = User::query()->find($id);

        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return ApiResponse::error('المستخدم غير موجود', 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return ApiResponse::error('ليس لديك صلاحية لتعديل حساب مدير عام', 403);
        }

        $payload = $request->validate([
            'username' => ['sometimes', 'string', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'name' => ['sometimes', 'string'],
            'email' => ['nullable', 'email'],
            'role' => ['sometimes', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'avatar' => ['nullable', 'string'],
        ]);

        if (array_key_exists('role', $payload)) {
            if ($id === $currentUser?->id && $payload['role'] !== $currentUser->role) {
                return ApiResponse::error('لا يمكنك تغيير الدور الخاص بك لتجنب فقدان الصلاحيات', 400);
            }
            if ($currentUser?->role !== 'super_admin' && $payload['role'] === 'super_admin') {
                return ApiResponse::error('ليس لديك صلاحية لترقية مستخدم إلى مدير عام', 403);
            }
        }

        $effectiveRole = $payload['role'] ?? $targetUser->role;
        $update = collect($payload)->except(['password', 'department'])->all();
        if (array_key_exists('password', $payload) && $payload['password']) {
            $update['password'] = Hash::make($payload['password']);
        }
        if (array_key_exists('department', $payload) || array_key_exists('role', $payload)) {
            $update['department'] = $effectiveRole === 'head_of_department' ? ($payload['department'] ?? null) : null;
        }

        $targetUser->update($update);

        return response()->json($this->serializeUser($targetUser->fresh()));
    }

    public function changePassword(Request $request, string $id): JsonResponse
    {
        $currentUser = auth('api')->user();
        $payload = $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'currentPassword' => ['nullable', 'string'],
        ]);

        if (! in_array($currentUser?->role, ['super_admin', 'admin']) && $currentUser?->id !== $id) {
            return ApiResponse::error('error_unauthorized_password_change', 403);
        }

        $targetUser = User::query()->find($id);
        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return ApiResponse::error('المستخدم غير موجود', 404);
        }

        if ($currentUser?->role === 'admin' && $targetUser->role === 'super_admin') {
            return ApiResponse::error('error_unauthorized_super_admin_change', 403);
        }

        if ($currentUser?->id === $id && ! Hash::check($payload['currentPassword'] ?? '', $targetUser->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['error_current_password_invalid'],
            ]);
        }

        if (in_array($currentUser?->role, ['super_admin', 'admin']) && $currentUser?->id !== $id) {
            $providedCurrentPassword = $payload['currentPassword'] ?? null;
            if (! $providedCurrentPassword || ! Hash::check($providedCurrentPassword, $currentUser->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['error_admin_password_required'],
                ]);
            }
        }

        $targetUser->update(['password' => Hash::make($payload['password'])]);
        RefreshToken::query()->where('userId', $id)->delete();

        return response()->json($this->serializeUser($targetUser->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth('api')->user();

        if ($id === $currentUser?->id) {
            return ApiResponse::error('لا يمكنك حذف حسابك الخاص', 400);
        }

        $targetUser = User::query()->find($id);
        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return ApiResponse::error('المستخدم غير موجود', 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return ApiResponse::error('ليس لديك صلاحية لحذف حساب مدير عام', 403);
        }

        $targetUser->delete();

        return ApiResponse::deleted('تم حذف المستخدم بنجاح');
    }

    public function toggle(string $id): JsonResponse
    {
        $currentUser = auth('api')->user();
        $targetUser = User::query()->find($id);

        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return ApiResponse::error('المستخدم غير موجود', 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return ApiResponse::error('ليس لديك صلاحية لتعطيل حساب مدير عام', 403);
        }

        $targetUser->update(['isActive' => ! $targetUser->isActive]);

        return response()->json($this->serializeUser($targetUser->fresh()));
    }

    private function isOutsideTenantScope(?User $currentUser, ?string $tenantId): bool
    {
        return (bool) $currentUser?->tenantId && $tenantId !== $currentUser->tenantId;
    }

    private function serializeUser(User $user): array
    {
        return $user->toFormattedArray();
    }
}
