<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\SettingsService;
use App\Traits\ResolvesAuditTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class UserManagementController
{
    use ResolvesAuditTarget;

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
                $escaped = addcslashes($search, '%_');
                $query->where(function ($nested) use ($escaped): void {
                    $nested->where('name', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhere('email', 'like', "%{$escaped}%")
                        ->orWhere('department', 'like', "%{$escaped}%");
                });
            })
            ->orderByDesc('createdAt')
            ->paginate(20)
            ->withQueryString();

        // Single aggregated query instead of 3 separate COUNTs
        $statsRow = User::query()
            ->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN isActive = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw("SUM(CASE WHEN role IN ('super_admin', 'admin') THEN 1 ELSE 0 END) as admins")
            ->first();

        $userStats = [
            'total' => (int) ($statsRow?->total ?? 0),
            'active' => (int) ($statsRow?->active ?? 0),
            'admins' => (int) ($statsRow?->admins ?? 0),
        ];

        $settings = $this->settingsService->getAll($currentUser?->tenantId);
        $departments = collect($settings['departments'] ?? [])
            ->filter(fn ($department) => $department['isActive'] ?? true)
            ->pluck('name')
            ->values()
            ->all();

        $allPermissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        // Load direct permissions for users
        $users->load('permissions');

        return view('dashboard.users', compact('users', 'userStats', 'departments', 'allPermissions'));
    }

    public function storeUser(StoreUserRequest $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

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

        $createdUser->assignRole($payload['role']);

        if ($request->has('permissions')) {
            $createdUser->syncPermissions($request->input('permissions', []));
        }

        if ($json = $this->jsonSuccessIfRequested($request, ['user' => $createdUser])) {
            return $json;
        }

        return redirect()->back()->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    public function updateUser(string $id, UpdateUserRequest $request): JsonResponse|RedirectResponse
    {
        $targetUser = $this->findScopedUser($id, $request->user());
        if (! $targetUser) {
            return redirect()->back()->with('error', 'المستخدم غير موجود');
        }

        if (Gate::denies('manage-super-admin-users') && $targetUser->role === 'super_admin') {
            return redirect()->back()->with('error', 'ليس لديك صلاحية تعديل مدير عام');
        }

        $payload = $request->validated();

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

        $targetUser->syncRoles([$payload['role']]);

        if ($request->has('permissions')) {
            $targetUser->syncPermissions($request->input('permissions', []));
        } else {
            $targetUser->syncPermissions([]); // Clear direct permissions if none selected
        }

        if ($json = $this->jsonSuccessIfRequested($request, ['user' => $targetUser->fresh(['permissions'])])) {
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
        return $this->resolveAuditTarget(request(), 'audit_pre_target_user', function () use ($id, $currentUser) {
            $query = User::query();

            if ($currentUser && $currentUser->tenantId) {
                $query->where('tenantId', $currentUser->tenantId);
            }

            return $query->find($id);
        });
    }

    private function jsonSuccessIfRequested(Request $request, array $payload = []): ?JsonResponse
    {
        if (! $request->wantsJson() && ! $request->ajax()) {
            return null;
        }

        return response()->json(['success' => true, ...$payload]);
    }
}
