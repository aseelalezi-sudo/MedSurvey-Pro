<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function users(Request $request): View
    {
        $currentUser = $request->user();

        $users = User::query()
            ->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))
            ->when($request->query('role'), fn ($query) => $query->where('role', $request->query('role')))
            ->when($request->query('status') === 'active', fn ($query) => $query->where('isActive', true))
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('isActive', false))
            ->when($request->query('q'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('createdAt')
            ->paginate(20)
            ->withQueryString();

        $userStats = [
            'total' => User::query()->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))->count(),
            'active' => User::query()->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))->where('isActive', true)->count(),
            'admins' => User::query()->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))->whereIn('role', ['super_admin', 'admin'])->count(),
        ];

        $settings = $this->settingsService->getAll($currentUser?->tenantId);
        $departments = collect($settings['departments'] ?? [])
            ->filter(fn ($department) => $department['isActive'] ?? true)
            ->pluck('name')
            ->values()
            ->all();

        return view('dashboard.users', compact('users', 'userStats', 'departments'));
    }

    public function storeUser(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', Password::min(6)],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string', 'max:200'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if (Gate::denies('manage-super-admin-users') && $payload['role'] === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لإنشاء مدير عام')->withInput();
        }

        $createdUser = User::query()->create([
            'username' => $payload['username'],
            'password' => Hash::make($payload['password']),
            'name' => $payload['name'],
            'email' => $payload['email'] ?? '',
            'role' => $payload['role'],
            'department' => $payload['role'] === 'head_of_department' ? ($payload['department'] ?? null) : null,
            'tenantId' => $request->user()?->tenantId,
            'isActive' => (bool) ($payload['isActive'] ?? true),
        ]);

        if ($json = $this->jsonSuccessIfRequested($request, ['user' => $createdUser])) {
            return $json;
        }

        return redirect()->back()->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    public function updateUser(string $id, Request $request): JsonResponse|RedirectResponse
    {
        $targetUser = $this->findScopedUser($id, $request->user());
        if (! $targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود');
        }

        if (Gate::denies('manage-super-admin-users') && $targetUser->role === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل مدير عام');
        }

        $payload = $request->validate([
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'password' => ['nullable', 'string', Password::min(6)],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff'])],
            'department' => ['nullable', 'string', 'max:200'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        if ($targetUser->id === $request->user()?->id && $payload['role'] !== $targetUser->role) {
            return redirect()->back()->with('error', 'لا يمكنك تغيير دور حسابك الحالي');
        }

        if (Gate::denies('manage-super-admin-users') && $payload['role'] === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية ترقية المستخدم إلى مدير عام');
        }

        $update = [
            'username' => $payload['username'],
            'name' => $payload['name'],
            'email' => $payload['email'] ?? '',
            'role' => $payload['role'],
            'department' => $payload['role'] === 'head_of_department' ? ($payload['department'] ?? null) : null,
        ];

        if (! empty($payload['password'])) {
            $update['password'] = Hash::make($payload['password']);
        }

        $targetUser->update($update);

        if ($json = $this->jsonSuccessIfRequested($request, ['user' => $targetUser->fresh()])) {
            return $json;
        }

        return redirect()->back()->with('success', 'تم تحديث المستخدم بنجاح');
    }

    public function toggleUser(string $id, Request $request): JsonResponse|RedirectResponse
    {
        if ($id === $request->user()?->id) {
            return redirect()->back()->with('error', 'لا يمكنك تعطيل حسابك الحالي');
        }

        $targetUser = $this->findScopedUser($id, $request->user());
        if (! $targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود');
        }

        if (Gate::denies('manage-super-admin-users') && $targetUser->role === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية تعطيل مدير عام');
        }

        $targetUser->update(['isActive' => ! $targetUser->isActive]);

        if ($json = $this->jsonSuccessIfRequested($request, ['user' => $targetUser->fresh()])) {
            return $json;
        }

        return redirect()->back()->with('success', 'تم تغيير حالة المستخدم بنجاح');
    }

    public function destroyUser(string $id, Request $request): JsonResponse|RedirectResponse
    {
        if ($id === $request->user()?->id) {
            return redirect()->back()->with('error', 'لا يمكنك حذف حسابك الحالي');
        }

        $targetUser = $this->findScopedUser($id, $request->user());
        if (! $targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود');
        }

        if (Gate::denies('manage-super-admin-users') && $targetUser->role === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية حذف مدير عام');
        }

        $targetUser->delete();

        if ($json = $this->jsonSuccessIfRequested($request)) {
            return $json;
        }

        return redirect()->back()->with('success', 'تم حذف المستخدم بنجاح');
    }

    private function findScopedUser(string $id, ?User $currentUser): ?User
    {
        return User::query()
            ->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))
            ->find($id);
    }

    private function jsonSuccessIfRequested(Request $request, array $payload = []): ?JsonResponse
    {
        if (! $request->wantsJson() && ! $request->ajax()) {
            return null;
        }

        return response()->json(['success' => true, ...$payload]);
    }

    private function jsonErrorIfRequested(Request $request, string $message, int $status = 400): ?JsonResponse
    {
        if (! $request->wantsJson() && ! $request->ajax()) {
            return null;
        }

        return response()->json(['success' => false, 'error' => $message], $status);
    }
}
