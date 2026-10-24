<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies baseline security headers to every response (web + api).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content-Security-Policy is limited to the directives that do not
        // depend on how scripts load (the dashboard relies on Alpine.js and
        // inline handlers). They still block framing, <base> tag injection,
        // form hijacking and plugin content. A full script policy, and HSTS by
        // default, are left to the edge (Cloudflare); HSTS can be enabled here
        // with SECURITY_HSTS=true when there is no edge.
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'",
        );

        if (config('medsurvey.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
