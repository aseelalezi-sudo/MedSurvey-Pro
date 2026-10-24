<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive) {
            return response()->json([
                'message' => 'الحساب غير نشط أو انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 401);
        }

        if ($user->tenantId && $user->tenant && ! $user->tenant->isActive) {
            return response()->json([
                'message' => 'تم إيقاف حساب المنشأة. تواصل مع مدير النظام.',
                'code' => 'TENANT_INACTIVE',
            ], 401);
        }

        return $next($request);
    }
}
