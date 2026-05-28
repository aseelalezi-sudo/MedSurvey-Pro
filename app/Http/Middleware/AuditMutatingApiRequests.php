<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\AuditRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuditMutatingApiRequests
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();
        $userId = $this->authenticatedUserId($bearerToken);
        $response = $next($request);

        if ($this->shouldRecord($request, $response, $userId)) {
            AuditLog::query()->create([
                'userId' => $userId,
                'action' => $this->actionFor($request),
                'details' => json_encode([
                    'messageKey' => 'audit.details.api_change',
                    'params' => [
                        'method' => $request->method(),
                        'path' => $this->apiPath($request),
                        'status' => $response->getStatusCode(),
                        'target' => $this->targetFor($request),
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ipAddress' => AuditRequestContext::ipAddress($request),
                'userAgent' => AuditRequestContext::userAgent($request),
                'deviceName' => AuditRequestContext::deviceName($request),
            ]);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response, ?string $userId): bool
    {
        if ($this->matches($request, 'audit/*')) {
            return false;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return (bool) $userId;
    }

    private function authenticatedUserId(?string $bearerToken): ?string
    {
        $user = auth('api')->user();
        if ($user) {
            return $user->id;
        }

        try {
            $payload = $bearerToken
                ? JWTAuth::setToken($bearerToken)->getPayload()
                : JWTAuth::parseToken()->getPayload();

            return (string) $payload->get('sub');
        } catch (Throwable) {
            return null;
        }
    }

    private function actionFor(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        return match (true) {
            $method === 'POST' && $this->matches($request, 'users') => 'create_user',
            $method === 'PUT' && $this->matches($request, 'users/*') => 'update_user',
            $method === 'PATCH' && $this->matches($request, 'users/*/password') => 'change_user_password',
            $method === 'PATCH' && $this->matches($request, 'users/*/toggle') => 'update_user',
            $method === 'DELETE' && $this->matches($request, 'users/*') => 'delete_user',
            $method === 'POST' && $this->matches($request, 'surveys') => 'create_survey',
            $method === 'PUT' && $this->matches($request, 'surveys/*') => 'update_survey',
            $method === 'DELETE' && $this->matches($request, 'surveys/*') => 'delete_survey',
            $method === 'PUT' && $this->matches($request, 'settings') => 'update_settings',
            $method === 'PATCH' && $this->matches($request, 'tickets/*') => 'update_ticket',
            $method === 'DELETE' && $this->matches($request, 'tickets/*') => 'delete_ticket',
            $method === 'POST' && $this->matches($request, 'backups/*/restore') => 'restore_backup',
            $method === 'POST' && $this->matches($request, 'backups/restore-external') => 'restore_backup',
            $method === 'POST' && $this->matches($request, 'backups/upload-restore') => 'restore_backup',
            $method === 'POST' && $this->matches($request, 'backups') => 'create_backup',
            $method === 'DELETE' && $this->matches($request, 'backups/*') => 'delete_backup',
            default => 'api_change',
        };
    }

    private function targetFor(Request $request): string
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();

        if (is_string($routeParameter) && $routeParameter !== '') {
            return '#'.strtoupper(substr($routeParameter, -8));
        }

        return $this->apiPath($request);
    }

    private function matches(Request $request, string $pattern): bool
    {
        return $request->is($pattern) || $request->is('api/'.$pattern);
    }

    private function apiPath(Request $request): string
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'api/')
            ? '/'.$path
            : '/api/'.$path;
    }
}
