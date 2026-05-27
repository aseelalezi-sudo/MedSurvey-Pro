<?php

namespace App\Http\Controllers\Api;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            'password' => ['required', 'string', 'min:8'],
            'name' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if ($currentUser?->role !== 'super_admin' && $payload['role'] === 'super_admin') {
            return response()->json(['error' => 'ليس لديك صلاحية لإنشاء مستخدم بصلاحية مدير عام'], 403);
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

        return response()->json($this->serializeUser($created), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $currentUser = auth('api')->user();
        $targetUser = User::query()->find($id);

        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return response()->json(['error' => 'ليس لديك صلاحية لتعديل حساب مدير عام'], 403);
        }

        $payload = $request->validate([
            'username' => ['sometimes', 'string', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'name' => ['sometimes', 'string'],
            'email' => ['nullable', 'email'],
            'role' => ['sometimes', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'avatar' => ['nullable', 'string'],
        ]);

        if (array_key_exists('role', $payload)) {
            if ($id === $currentUser?->id && $payload['role'] !== $currentUser->role) {
                return response()->json(['error' => 'لا يمكنك تغيير الدور الخاص بك لتجنب فقدان الصلاحيات'], 400);
            }
            if ($currentUser?->role !== 'super_admin' && $payload['role'] === 'super_admin') {
                return response()->json(['error' => 'ليس لديك صلاحية لترقية مستخدم إلى مدير عام'], 403);
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
            'password' => ['required', 'string', 'min:6'],
            'currentPassword' => ['nullable', 'string'],
        ]);

        if ($currentUser?->role !== 'super_admin' && $currentUser?->id !== $id) {
            return response()->json(['error' => 'ليس لديك صلاحية لتغيير كلمة المرور لهذا المستخدم'], 403);
        }

        $targetUser = User::query()->find($id);
        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }

        if ($currentUser?->id === $id && ! password_verify($payload['currentPassword'] ?? '', $targetUser->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['كلمة المرور الحالية غير صحيحة'],
            ]);
        }

        $targetUser->update(['password' => Hash::make($payload['password'])]);
        RefreshToken::query()->where('userId', $id)->delete();

        return response()->json($this->serializeUser($targetUser->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $currentUser = auth('api')->user();

        if ($id === $currentUser?->id) {
            return response()->json(['error' => 'لا يمكنك حذف حسابك الخاص'], 400);
        }

        $targetUser = User::query()->find($id);
        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return response()->json(['error' => 'ليس لديك صلاحية لحذف حساب مدير عام'], 403);
        }

        $targetUser->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }

    public function toggle(string $id): JsonResponse
    {
        $currentUser = auth('api')->user();
        $targetUser = User::query()->find($id);

        if (! $targetUser || $this->isOutsideTenantScope($currentUser, $targetUser->tenantId)) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }

        if ($currentUser?->role !== 'super_admin' && $targetUser->role === 'super_admin') {
            return response()->json(['error' => 'ليس لديك صلاحية لتعطيل حساب مدير عام'], 403);
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
}
