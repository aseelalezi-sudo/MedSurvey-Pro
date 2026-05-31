<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthSessionController
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt([...$credentials, 'isActive' => true], $remember)) {
            $attemptedUserId = User::query()
                ->where('username', $credentials['username'])
                ->value('id');

            // Record failed login attempt for security auditing
            \App\Models\AuditLog::query()->create([
                'userId' => $attemptedUserId,
                'action' => 'login_failed',
                'details' => json_encode([
                    'messageKey' => 'audit.details.login_failed',
                    'params' => [
                        'username' => $credentials['username'],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ipAddress' => \App\Support\AuditRequestContext::ipAddress($request),
                'userAgent' => \App\Support\AuditRequestContext::userAgent($request),
                'deviceName' => \App\Support\AuditRequestContext::deviceName($request),
            ]);

            throw ValidationException::withMessages([
                'username' => __('بيانات الدخول غير صحيحة أو أن الحساب غير نشط.'),
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['lastLogin' => now()])->save();

        // Record successful login for security auditing
        \App\Models\AuditLog::query()->create([
            'userId' => $user->id,
            'action' => 'login',
            'details' => json_encode([
                'messageKey' => 'audit.details.login',
                'params' => [
                    'username' => $user->username,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ipAddress' => \App\Support\AuditRequestContext::ipAddress($request),
            'userAgent' => \App\Support\AuditRequestContext::userAgent($request),
            'deviceName' => \App\Support\AuditRequestContext::deviceName($request),
        ]);

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            // Record successful logout for security auditing
            \App\Models\AuditLog::query()->create([
                'userId' => $user->id,
                'action' => 'logout',
                'details' => json_encode([
                    'messageKey' => 'audit.details.logout',
                    'params' => [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ipAddress' => \App\Support\AuditRequestContext::ipAddress($request),
                'userAgent' => \App\Support\AuditRequestContext::userAgent($request),
                'deviceName' => \App\Support\AuditRequestContext::deviceName($request),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
