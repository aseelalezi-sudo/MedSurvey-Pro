<?php

namespace App\Http\Controllers\Web;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant (facility) management. Only a super administrator who is not bound to
 * a tenant may use it: a tenant-bound administrator must never see, create or
 * remove other tenants.
 */
class TenantController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobalAdmin($request);

        $tenants = Tenant::query()
            ->withCount(['users', 'surveys', 'responses', 'tickets'])
            ->when($request->query('q'), function ($query, string $search): void {
                $query->where('name', 'like', '%'.addcslashes($search, '%_').'%');
            })
            ->orderByDesc('createdAt')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.tenants', [
            'tenants' => $tenants,
            'totalTenants' => Tenant::query()->count(),
            'activeTenants' => Tenant::query()->where('isActive', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tenants', 'name')],
        ]);

        Tenant::query()->create(['name' => trim($payload['name']), 'isActive' => true]);

        return redirect()->route('dashboard.tenants')->with('success', 'تم إنشاء المنشأة بنجاح');
    }

    public function update(string $id, Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);
        $tenant = Tenant::query()->findOrFail($id);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tenants', 'name')->ignore($tenant->id)],
        ]);

        $tenant->update(['name' => trim($payload['name'])]);

        return redirect()->route('dashboard.tenants')->with('success', 'تم تحديث المنشأة بنجاح');
    }

    public function toggle(string $id, Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);
        $tenant = Tenant::query()->findOrFail($id);

        $tenant->update(['isActive' => ! $tenant->isActive]);

        if (! $tenant->isActive) {
            // Mobile sessions end now; web sessions end on their next request.
            PersonalAccessToken::query()
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $tenant->users()->select('id'))
                ->delete();
        }

        return redirect()->route('dashboard.tenants')->with('success', 'تم تغيير حالة المنشأة بنجاح');
    }

    public function destroy(string $id, Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);
        $tenant = Tenant::query()->findOrFail($id);

        if ($tenant->hasData()) {
            return redirect()->route('dashboard.tenants')
                ->with('error', 'لا يمكن حذف منشأة لديها مستخدمون أو استبيانات أو استجابات. أوقفها بدلًا من ذلك.');
        }

        $tenant->delete();

        return redirect()->route('dashboard.tenants')->with('success', 'تم حذف المنشأة بنجاح');
    }

    /**
     * Creates the first (or an additional) administrator for a tenant, so the
     * facility can sign in and manage its own users and surveys.
     */
    public function storeAdmin(string $id, Request $request): RedirectResponse
    {
        $this->authorizeGlobalAdmin($request);
        $tenant = Tenant::query()->findOrFail($id);

        $payload = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($payload, $tenant): void {
            $admin = User::query()->create([
                'username' => $payload['username'],
                'password' => Hash::make($payload['password']),
                'name' => $payload['name'],
                'email' => $payload['email'] ?? '',
                'role' => 'admin',
                'department' => null,
                'tenantId' => $tenant->id,
                'isActive' => true,
            ]);

            $admin->assignRole('admin');
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('dashboard.tenants')->with('success', 'تم إنشاء مدير المنشأة بنجاح');
    }

    private function authorizeGlobalAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->role === 'super_admin' && $user->tenantId === null, 403);
    }
}
