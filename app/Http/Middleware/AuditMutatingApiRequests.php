<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SettingsService;
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
        if ($action === 'update_settings') {
            try {
                $user = auth('api')->user() ?: auth('web')->user() ?: auth()->user();
                $details['settings_before'] = app(SettingsService::class)->getAll($user?->tenantId);
            } catch (Throwable) {
                $details['settings_before'] = [];
            }
        }

        if (! $routeParameter) {
            return $details;
        }

        try {
            if (str_contains($action, 'user') && class_exists(User::class)) {
                $user = User::find($routeParameter);
                if ($user) {
                    $details['user_name'] = $user->name;
                    $details['user_username'] = $user->username;
                    $details['user_role'] = $user->role;
                    $details['user_is_active'] = $user->isActive;
                    $details['user_before'] = $this->userSnapshot($user);
                }
            } elseif (str_contains($action, 'survey') && class_exists(Survey::class)) {
                $survey = Survey::query()->with(['sections.questions'])->find($routeParameter);
                if ($survey) {
                    $details['survey_title'] = $survey->title;
                    $details['survey_before'] = $this->surveySnapshot($survey);
                }
            } elseif (str_contains($action, 'ticket') && class_exists(Ticket::class)) {
                $ticket = Ticket::find($routeParameter);
                if ($ticket) {
                    $details['ticket_id'] = $ticket->id;
                    $details['ticket_status'] = $ticket->status;
                    $details['ticket_before'] = $this->ticketSnapshot($ticket);
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
                $changes = $this->recordChanges([], $this->userPayloadSnapshot($request), 'user');
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'update_user':
                $messageKey = 'audit.details.update_user';
                $params['name'] = $request->input('name') ?? $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $request->input('username') ?? $preTargetDetails['user_username'] ?? 'unknown';
                $userAfter = $this->freshUserSnapshot($request);
                $changes = $this->recordChanges(
                    $preTargetDetails['user_before'] ?? [],
                    $userAfter,
                    'user'
                );
                if ($request->filled('password')) {
                    $changes[] = [
                        'path' => 'user.password',
                        'label' => $this->fieldLabel('user.password'),
                        'before' => '[protected]',
                        'after' => '[changed]',
                    ];
                }
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'change_user_password':
                $messageKey = 'audit.details.change_user_password';
                $currentUser = auth('api')->user() ?: auth('web')->user() ?: auth()->user();
                $params['name'] = $preTargetDetails['user_name'] ?? $currentUser?->name ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? $currentUser?->username ?? 'unknown';
                $params['changeCount'] = 1;
                $params['changes'] = [[
                    'path' => 'user.password',
                    'label' => $this->fieldLabel('user.password'),
                    'before' => '[protected]',
                    'after' => '[changed]',
                ]];
                break;

            case 'delete_user':
                $messageKey = 'audit.details.delete_user';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                $changes = $this->recordChanges($preTargetDetails['user_before'] ?? [], [], 'user');
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'toggle_user_status':
                $isActiveBefore = $preTargetDetails['user_is_active'] ?? false;
                $messageKey = $isActiveBefore ? 'audit.details.deactivate_user' : 'audit.details.activate_user';
                $params['name'] = $preTargetDetails['user_name'] ?? 'unknown';
                $params['username'] = $preTargetDetails['user_username'] ?? 'unknown';
                $changes = $this->recordChanges(
                    $preTargetDetails['user_before'] ?? [],
                    $this->freshUserSnapshot($request),
                    'user'
                );
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
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
                $changes = $this->recordChanges([], $this->surveyPayloadSnapshot($request), 'survey');
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'update_survey':
                $messageKey = 'audit.details.update_survey';
                $params['title'] = $request->input('title') ?? $preTargetDetails['survey_title'] ?? 'Untitled';
                $changes = $this->recordChanges(
                    $preTargetDetails['survey_before'] ?? [],
                    $this->freshSurveySnapshot($request),
                    'survey'
                );
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'delete_survey':
                $messageKey = 'audit.details.delete_survey';
                $params['title'] = $preTargetDetails['survey_title'] ?? 'Untitled';
                $changes = $this->recordChanges($preTargetDetails['survey_before'] ?? [], [], 'survey');
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'update_settings':
                $messageKey = 'audit.details.update_settings';
                $params['tenant'] = $request->input('hospital.name') ?? 'النظام';
                break;

            case 'update_ticket':
                $messageKey = 'audit.details.update_ticket';
                $params['ticketCode'] = $preTargetDetails['ticket_id'] ? '#'.strtoupper(substr($preTargetDetails['ticket_id'], -8)) : $params['target'];
                $params['status'] = $request->input('status') ?? $preTargetDetails['ticket_status'] ?? 'unknown';
                $changes = $this->recordChanges(
                    $preTargetDetails['ticket_before'] ?? [],
                    $this->freshTicketSnapshot($request),
                    'ticket'
                );
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
                break;

            case 'delete_ticket':
                $messageKey = 'audit.details.delete_ticket';
                $params['ticketCode'] = ($preTargetDetails['ticket_id'] ?? null) ? '#'.strtoupper(substr($preTargetDetails['ticket_id'], -8)) : $params['target'];
                $changes = $this->recordChanges($preTargetDetails['ticket_before'] ?? [], [], 'ticket');
                $params['changeCount'] = count($changes);
                $params['changes'] = $changes;
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

        if ($action === 'update_settings') {
            $afterSettings = [];
            try {
                $user = auth('api')->user() ?: auth('web')->user() ?: auth()->user();
                $afterSettings = app(SettingsService::class)->getAll($user?->tenantId);
            } catch (Throwable) {
                $afterSettings = [];
            }

            $changes = $this->settingsChanges(
                $preTargetDetails['settings_before'] ?? [],
                $afterSettings
            );
            $params['changeCount'] = count($changes);
            $params['settingsChanges'] = $changes;
            $params['changes'] = $changes;
        }

        return [
            'messageKey' => $messageKey,
            'params' => $params,
        ];
    }

    /**
     * @return array<int, array{path: string, label: string, before: mixed, after: mixed}>
     */
    private function settingsChanges(array $before, array $after): array
    {
        $flatBefore = $this->flattenAuditData($before);
        $flatAfter = $this->flattenAuditData($after);
        $paths = array_unique(array_merge(array_keys($flatBefore), array_keys($flatAfter)));
        sort($paths);

        $changes = [];
        foreach ($paths as $path) {
            $old = $flatBefore[$path] ?? null;
            $new = $flatAfter[$path] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'label' => $this->fieldLabel($path),
                'before' => $this->auditValue($old, $path),
                'after' => $this->auditValue($new, $path),
            ];
        }

        return array_slice($changes, 0, 200);
    }

    /**
     * @return array<int, array{path: string, label: string, before: mixed, after: mixed}>
     */
    private function recordChanges(array $before, array $after, string $prefix = ''): array
    {
        $flatBefore = $this->flattenAuditData($before, $prefix);
        $flatAfter = $this->flattenAuditData($after, $prefix);
        $paths = array_unique(array_merge(array_keys($flatBefore), array_keys($flatAfter)));
        sort($paths);

        $changes = [];
        foreach ($paths as $path) {
            $old = $flatBefore[$path] ?? null;
            $new = $flatAfter[$path] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'label' => $this->fieldLabel($path),
                'before' => $this->auditValue($old, $path),
                'after' => $this->auditValue($new, $path),
            ];
        }

        return array_slice($changes, 0, 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenAuditData(array $value, string $prefix = ''): array
    {
        $flat = [];

        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($item) && $this->isAssoc($item)) {
                $flat += $this->flattenAuditData($item, $path);
                continue;
            }

            if (is_array($item) && $this->isListOfObjects($item)) {
                foreach ($item as $index => $listItem) {
                    $identifier = $this->listItemIdentifier($listItem, $index);
                    foreach ($listItem as $field => $fieldValue) {
                        $fieldPath = $path.'.'.$identifier.'.'.$field;
                        if (is_array($fieldValue)) {
                            $flat += $this->flattenAuditData($fieldValue, $fieldPath);
                            continue;
                        }

                        $flat[$fieldPath] = $fieldValue;
                    }
                }
                continue;
            }

            $flat[$path] = is_array($item) ? $this->normalizeListForAudit($item) : $item;
        }

        return $flat;
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function isListOfObjects(array $value): bool
    {
        if ($value === [] || $this->isAssoc($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item) || ! $this->isAssoc($item)) {
                return false;
            }
        }

        return true;
    }

    private function listItemIdentifier(array $item, int $index): string
    {
        $identifier = $item['id'] ?? $item['name'] ?? $item['label'] ?? 'item-'.$index;

        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', (string) $identifier) ?: 'item-'.$index;
    }

    private function normalizeListForAudit(array $items): string
    {
        return collect($items)
            ->map(function ($item): string {
                if (! is_array($item)) {
                    return (string) $item;
                }

                $name = $item['name'] ?? $item['label'] ?? $item['id'] ?? 'item';
                $active = array_key_exists('isActive', $item)
                    ? ((bool) $item['isActive'] ? 'active' : 'inactive')
                    : null;
                $color = $item['color'] ?? null;

                return implode(' | ', array_filter([
                    (string) $name,
                    $active,
                    $color ? 'color: '.$color : null,
                ]));
            })
            ->values()
            ->implode('; ');
    }

    private function auditValue(mixed $value, string $path): mixed
    {
        if (str_contains($path, 'logo') && is_string($value) && $value !== '') {
            return str_starts_with($value, 'data:image/') ? '[embedded image]' : '[stored image]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function userSnapshot(User $user): array
    {
        return [
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'isActive' => (bool) $user->isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayloadSnapshot(Request $request): array
    {
        $role = $request->input('role');

        return [
            'username' => $request->input('username'),
            'name' => $request->input('name'),
            'email' => $request->input('email', ''),
            'role' => $role,
            'department' => $role === 'head_of_department' ? $request->input('department') : null,
            'isActive' => $request->boolean('isActive', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function surveySnapshot(Survey $survey): array
    {
        $survey->loadMissing(['sections.questions']);

        return [
            'title' => $survey->title,
            'description' => $survey->description,
            'isActive' => (bool) $survey->isActive,
            'requireName' => (bool) $survey->requireName,
            'requirePhone' => (bool) $survey->requirePhone,
            'assignedDepartments' => $survey->assignedDepartments ?? [],
            'tips' => $survey->tips ?? [],
            'sections' => $survey->sections
                ->map(fn (SurveySection $section): array => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'icon' => $section->icon,
                    'sortOrder' => $section->sortOrder,
                    'questions' => $section->questions
                        ->map(fn (SurveyQuestion $question): array => [
                            'id' => $question->id,
                            'type' => $question->type,
                            'title' => $question->title,
                            'description' => $question->description,
                            'required' => (bool) $question->required,
                            'category' => $question->category,
                            'options' => $question->options ?? [],
                            'followUp' => $question->followUp ?? [],
                            'sortOrder' => $question->sortOrder,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyPayloadSnapshot(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'description' => $request->input('description', ''),
            'isActive' => $request->boolean('isActive', true),
            'requireName' => $request->boolean('requireName', false),
            'requirePhone' => $request->boolean('requirePhone', false),
            'assignedDepartments' => array_values(array_unique((array) $request->input('assignedDepartments', []))),
            'tips' => array_values(array_filter((array) $request->input('tips', []), fn ($tip) => ! is_null($tip) && trim((string) $tip) !== '')),
            'sections' => collect((array) $request->input('sections', []))
                ->map(fn ($section, $sectionIndex): array => [
                    'id' => $section['id'] ?? 'section-'.$sectionIndex,
                    'title' => $section['title'] ?? '',
                    'description' => $section['description'] ?? '',
                    'icon' => $section['icon'] ?? 'clipboard-check',
                    'sortOrder' => $sectionIndex,
                    'questions' => collect($section['questions'] ?? [])
                        ->map(fn ($question, $questionIndex): array => [
                            'id' => $question['id'] ?? 'question-'.$questionIndex,
                            'type' => $question['type'] ?? 'stars',
                            'title' => $question['title'] ?? '',
                            'description' => $question['description'] ?? null,
                            'required' => (bool) ($question['required'] ?? false),
                            'category' => $question['category'] ?? '',
                            'options' => $question['options'] ?? [],
                            'followUp' => $question['followUp'] ?? [],
                            'sortOrder' => $questionIndex,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSnapshot(Ticket $ticket): array
    {
        return [
            'status' => $ticket->status,
            'resolutionNotes' => $ticket->resolutionNotes,
            'assignedTo' => $ticket->assignedTo,
            'resolvedAt' => optional($ticket->resolvedAt)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freshUserSnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $user = $routeParameter ? User::find($routeParameter) : null;

        return $user ? $this->userSnapshot($user) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function freshSurveySnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $survey = $routeParameter ? Survey::query()->with(['sections.questions'])->find($routeParameter) : null;

        return $survey ? $this->surveySnapshot($survey) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function freshTicketSnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $ticket = $routeParameter ? Ticket::find($routeParameter) : null;

        return $ticket ? $this->ticketSnapshot($ticket) : [];
    }

    private function fieldLabel(string $path): string
    {
        return match ($path) {
            'user.username' => 'Username',
            'user.name' => 'Name',
            'user.email' => 'Email',
            'user.role' => 'Role',
            'user.department' => 'Department',
            'user.isActive' => 'Status',
            'user.password' => 'Password',
            'survey.title' => 'Survey title',
            'survey.description' => 'Survey description',
            'survey.isActive' => 'Survey status',
            'survey.requireName' => 'Require name',
            'survey.requirePhone' => 'Require phone',
            'survey.assignedDepartments' => 'Assigned departments',
            'survey.tips' => 'Tips',
            'survey.sections' => 'Survey sections',
            'survey.questions' => 'Survey questions',
            'ticket.status' => 'Ticket status',
            'ticket.resolutionNotes' => 'Resolution notes',
            'ticket.assignedTo' => 'Assigned to',
            'ticket.resolvedAt' => 'Resolved at',
            'hospital.name' => 'Hospital name',
            'hospital.shortName' => 'Hospital short name',
            'hospital.logo' => 'Hospital logo',
            'hospital.address' => 'Hospital address',
            'hospital.phone' => 'Hospital phone',
            'hospital.email' => 'Hospital email',
            'hospital.website' => 'Hospital website',
            'hospital.description' => 'Hospital description',
            'hospital.workingHours' => 'Working hours',
            'hospital.operatingTitle' => 'Operating title',
            'hospital.welcomeMessage' => 'Welcome message',
            'departments' => 'Departments',
            'ageGroups' => 'Age groups',
            'visitTypes' => 'Visit types',
            'surveySettings.allowAnonymous' => 'Allow anonymous responses',
            'surveySettings.requireAllQuestions' => 'Require all questions',
            'surveySettings.requireName' => 'Require patient name',
            'surveySettings.requirePhone' => 'Require patient phone',
            'surveySettings.showProgressBar' => 'Show progress bar',
            'surveySettings.enableThankYouPage' => 'Enable thank-you page',
            'surveySettings.thankYouMessage' => 'Thank-you message',
            'appearance.primaryColor' => 'Primary color',
            'appearance.secondaryColor' => 'Secondary color',
            'appearance.fontFamily' => 'Font family',
            'appearance.showLanguageToggle' => 'Show language toggle',
            'backupSettings.schedule' => 'Backup schedule',
            'backupSettings.retentionDays' => 'Backup retention days',
            'backupSettings.compressGzip' => 'Compress backups',
            'backupSettings.backupDir' => 'Backup directory',
            'archiveSettings.enabled' => 'Automatic archiving',
            'archiveSettings.schedule' => 'Archive schedule',
            'archiveSettings.retentionYears' => 'Data retention years',
            default => str_replace('.', ' / ', $path),
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
