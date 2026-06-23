<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController
{
    public function changePassword(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = $request->user();
        $payload = $request->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
        ]);

        $targetId = $payload['user_id'] ?? $currentUser->id;

        if ((string) $targetId !== (string) $currentUser->id) {
            if (Gate::denies('manage-users')) {
                return redirect()->back()->withErrors(['currentPassword' => 'ليس لديك صلاحية تغيير كلمة مرور مستخدم آخر'])->withInput();
            }

            $targetUser = $this->findScopedUser((string) $targetId, $currentUser);
            if (! $targetUser) {
                return redirect()->back()->withErrors(['user_id' => 'المستخدم غير موجود'])->withInput();
            }

            if (Gate::denies('manage-super-admin-users') && $targetUser->role === 'super_admin') {
                return redirect()->back()->withErrors(['currentPassword' => 'ليس لديك صلاحية تغيير كلمة مرور مدير عام'])->withInput();
            }

            if (! Hash::check($payload['currentPassword'], $currentUser->password)) {
                return redirect()->back()->withErrors(['currentPassword' => 'كلمة المرور الحالية غير صحيحة'])->withInput();
            }

            $targetUser->update(['password' => Hash::make($payload['password'])]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', 'تم تغيير كلمة المرور بنجاح');
        }

        if (! Hash::check($payload['currentPassword'], $currentUser->password)) {
            return redirect()->back()->withErrors(['currentPassword' => 'كلمة المرور الحالية غير صحيحة'])->withInput();
        }

        $currentUser->update([
            'password' => Hash::make($payload['password']),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'تم تغيير كلمة المرور بنجاح');
    }

    private function findScopedUser(string $id, ?User $currentUser): ?User
    {
        return User::query()
            ->when($currentUser?->tenantId, fn ($query) => $query->where('tenantId', $currentUser->tenantId))
            ->find($id);
    }
}
