<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user whose tenant was deactivated, so switching a
 * tenant off takes effect immediately instead of at the next login.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenantId && $user->tenant && ! $user->tenant->isActive) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'تم إيقاف حساب المنشأة.', 'code' => 'TENANT_INACTIVE'], 403);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'تم إيقاف حساب هذه المنشأة. تواصل مع مدير النظام.',
            ]);
        }

        return $next($request);
    }
}
