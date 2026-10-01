<?php

namespace App\Licensing\Middleware;

use App\Licensing\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route group (e.g. the dashboard) behind a valid license.
 *
 * Apply it via the `license` middleware alias. When licensing is disabled in
 * config the request always passes, so the group is easy to develop against.
 */
class RequiresValidLicense
{
    public function __construct(private readonly LicenseService $licensing) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('licensing.enabled', true)) {
            return $next($request);
        }

        $check = $this->licensing->boot();

        if ($check->valid()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => $this->friendlyMessage($check->status->value),
                'code' => 'LICENSE_'.strtoupper(str_replace('-', '_', $check->status->value)),
            ], 402);
        }

        return response()
            ->view('errors.license', ['check' => $check], 402)
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    private function friendlyMessage(string $status): string
    {
        return match ($status) {
            'expired' => 'The license has expired. Please renew it to continue.',
            'revoked' => 'The license has been revoked. Contact your provider.',
            'not_activated' => 'This installation is not activated. Provide a license key.',
            default => 'This installation is not licensed for this feature.',
        };
    }
}
