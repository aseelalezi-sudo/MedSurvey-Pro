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
        
        // 1. Gather target details BEFORE executing the request (in case models are deleted/modified)
        $preTargetDetails = $this->gatherPreTargetDetails($request);
        
        $response = $next($request);

        if ($this->shouldRecord($request, $response, $userId)) {
            $action = $this->actionFor($request);
            $details = $this->buildDetailedParams($request, $response, $action, $preTargetDetails);

            // Dynamically rewrite action string based on toggle state before saving to DB
            if ($action === 'toggle_user_status') {
                $isActiveBefore = $preTargetDetails['user_is_active'] ?? false;
                $action = $isActiveBefore ? 'deactivate_user' : 'activate_user';
            }

            AuditLog::query()->create([
                'userId' => $userId,
                'action' => $action,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        if ($this->matches($request, 'backups/scan-external') || $this->matches($request, 'backups/verify-external')) {
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
        $user = auth('api')->user() ?: auth('web')->user() ?: auth()->user();
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
        $method = $request->method();

        return match (true) {
            $method === 'POST' && $this->matches($request, 'users') => 'create_user',
            $method === 'PUT' && $this->matches($request, 'users/*') => 'update_user',
            $method === 'PATCH' && $this->matches($request, 'users/*/password') => 'change_user_password',
            $method === 'POST' && $this->matches($request, 'change-password') => 'change_user_password',
            $method === 'PATCH' && $this->matches($request, 'users/*/toggle') => 'toggle_user_status',
            $method === 'DELETE' && $this->matches($request, 'users/*') => 'delete_user',
            $method === 'POST' && $this->matches($request, 'surveys') => 'create_survey',
            $method === 'POST' && $this->matches($request, 'surveys/*/duplicate') => 'create_survey',
            $method === 'PUT' && $this->matches($request, 'surveys/*') => 'update_survey',
            $method === 'DELETE' && $this->matches($request, 'surveys/*') => 'delete_survey',
            $method === 'PATCH' && $this->matches($request, 'surveys/*/toggle') => 'update_survey',
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

    private function messageKeyFor(string $action): string
    {
        return match ($action) {
            'create_user' => 'audit.details.create_user_auto',
            'update_user' => 'audit.details.update_user_auto',
            'change_user_password' => 'audit.details.change_user_password_auto',
            'delete_user' => 'audit.details.delete_user_auto',
            'toggle_user_status' => 'audit.details.update_user_auto',
            'create_survey' => 'audit.details.create_survey_auto',
            'update_survey' => 'audit.details.update_survey_auto',
            'delete_survey' => 'audit.details.delete_survey_auto',
            'update_settings' => 'audit.details.update_settings_auto',
            'update_ticket' => 'audit.details.update_ticket_auto',
            'delete_ticket' => 'audit.details.delete_ticket_auto',
            'create_backup' => 'audit.details.create_backup_auto',
            'restore_backup' => 'audit.details.restore_backup_auto',
            'delete_backup' => 'audit.details.delete_backup_auto',
            default => 'audit.details.api_change',
        };
    }

    private function gatherPreTargetDetails(Request $request): array
    {
        $action = $this->actionFor($request);
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        
        $details = [];
        if (!$routeParameter) {
            return $details;
        }

        try {
            if (str_contains($action, 'user') && class_exists(\App\Models\User::class)) {
                $user = \App\Models\User::find($routeParameter);
                if ($user) {
                    $details['user_name'] = $user->name;
                    $details['user_username'] = $user->username;
                    $details['user_role'] = $user->role;
                    $details['user_is_active'] = $user->isActive;
                }
            } elseif (str_contains($action, 'survey') && class_exists(\App\Models\Survey::class)) {
                $survey = \App\Models\Survey::find($routeParameter);
                if ($survey) {
                    $details['survey_title'] = $survey->title;
                }
            } elseif (str_contains($action, 'ticket') && class_exists(\App\Models\Ticket::class)) {
                $ticket = \App\Models\Ticket::find($routeParameter);
                if ($ticket) {
                    $details['ticket_id'] = $ticket->id;
                    $details['ticket_status'] = $ticket->status;
                }
            }
        } catch (Throwable $e) {
            // Silently fail if DB error or model doesn't exist
        }

        return $details;
    }

    private function buildDetailedParams(Request $request, Response $response, string $action, array $preTargetDetails): array
    {
        $params = [
            'method' => $request->method(),
            'path' => $this->apiPath($request),
            'status' => $response->getStatusCode(),
            'target' => $this->targetFor($request),
        ];

        $messageKey = $this->messageKeyFor($action);

        // Enhance parameters based on the action type
        switch ($action) {
            case 'create_user':
                $messageKey = 'audit.details.create_user';
                $params['name'] = $request->input('name') ?? $request->input('username');
                $params['username'] = $request->input('username');
                $params['role'] = $request->input('role');
                break;
                
            case 'update_user':
                $messageKey = 'audit.details.update_user';
                $params['name'] = $request->input('name') ?? $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $request->input('username') ?? $preTargetDetails['user_username'] ?? 'unknown';
                break;

            case 'change_user_password':
                $messageKey = 'audit.details.change_user_password';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                break;
                
            case 'delete_user':
                $messageKey = 'audit.details.delete_user';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                break;

            case 'toggle_user_status':
                $isActiveBefore = $preTargetDetails['user_is_active'] ?? false;
                $messageKey = $isActiveBefore ? 'audit.details.deactivate_user' : 'audit.details.activate_user';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                break;

            case 'activate_user':
            case 'deactivate_user':
                $messageKey = $action === 'activate_user' ? 'audit.details.activate_user' : 'audit.details.deactivate_user';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                break;
                
            case 'create_survey':
                $messageKey = 'audit.details.create_survey';
                $params['title'] = $request->input('title') ?? 'Untitled';
                break;

            case 'update_survey':
                $messageKey = 'audit.details.update_survey';
                $params['title'] = $request->input('title') ?? $preTargetDetails['survey_title'] ?? 'Untitled';
                break;

            case 'delete_survey':
                $messageKey = 'audit.details.delete_survey';
                $params['title'] = $preTargetDetails['survey_title'] ?? 'Untitled';
                break;
                
            case 'update_settings':
                $messageKey = 'audit.details.update_settings';
                $params['tenant'] = $request->input('hospital.name') ?? 'النظام';
                break;
                
            case 'update_ticket':
                $messageKey = 'audit.details.update_ticket';
                $params['ticketCode'] = $preTargetDetails['ticket_id'] ? '#' . strtoupper(substr($preTargetDetails['ticket_id'], -8)) : $params['target'];
                $params['status'] = $request->input('status') ?? $preTargetDetails['ticket_status'] ?? 'unknown';
                break;
                
            case 'delete_ticket':
                $messageKey = 'audit.details.delete_ticket';
                $params['ticketCode'] = $preTargetDetails['ticket_id'] ? '#' . strtoupper(substr($preTargetDetails['ticket_id'], -8)) : $params['target'];
                break;

            case 'create_backup':
                $messageKey = 'audit.details.create_backup';
                $filename = 'unknown';
                if ($response->getContent()) {
                    $data = json_decode($response->getContent(), true);
                    if ($data && isset($data['verification']['filename'])) {
                        $filename = $data['verification']['filename'];
                    } elseif ($data && isset($data['file'])) {
                        $filename = basename($data['file']);
                    }
                }
                $params['filename'] = $filename;
                break;

            case 'restore_backup':
                $messageKey = 'audit.details.restore_backup';
                $filename = 'unknown';
                if ($request->input('filename')) {
                    $filename = $request->input('filename');
                } elseif ($request->input('filepath')) {
                    $filename = basename($request->input('filepath'));
                } else {
                    $routeParameter = collect($request->route()?->parameters() ?? [])->first();
                    if ($routeParameter) {
                        $filename = basename($routeParameter);
                    }
                }
                $params['filename'] = $filename;
                break;

            case 'delete_backup':
                $messageKey = 'audit.details.delete_backup';
                $routeParameter = collect($request->route()?->parameters() ?? [])->first();
                $params['filename'] = $routeParameter ? basename($routeParameter) : 'unknown';
                break;
        }

        return [
            'messageKey' => $messageKey,
            'params' => $params,
        ];
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
        return $request->is($pattern) || $request->is('api/'.$pattern) || $request->is('dashboard/'.$pattern);
    }

    private function apiPath(Request $request): string
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'api/')
            ? '/'.$path
            : '/api/'.$path;
    }
}
