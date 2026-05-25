<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('api')->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'], 403);
        }

        return $next($request);
    }
}
