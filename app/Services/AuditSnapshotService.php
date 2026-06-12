<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class AuditSnapshotService
{
    /**
     * @return array<int, array{path: string, label: string, before: mixed, after: mixed}>
     */
    public function settingsChanges(array $before, array $after): array
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
    public function recordChanges(array $before, array $after, string $prefix = ''): array
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
    public function userSnapshot(User $user): array
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
    public function userPayloadSnapshot(Request $request): array
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
    public function surveySnapshot(Survey $survey): array
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
    public function surveyPayloadSnapshot(Request $request): array
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
    public function ticketSnapshot(Ticket $ticket): array
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
    public function freshUserSnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $user = $routeParameter ? User::find($routeParameter) : null;

        return $user ? $this->userSnapshot($user) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function freshSurveySnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $survey = $routeParameter ? Survey::query()->with(['sections.questions'])->find($routeParameter) : null;

        return $survey ? $this->surveySnapshot($survey) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function freshTicketSnapshot(Request $request): array
    {
        $routeParameter = collect($request->route()?->parameters() ?? [])->first();
        $ticket = $routeParameter ? Ticket::find($routeParameter) : null;

        return $ticket ? $this->ticketSnapshot($ticket) : [];
    }

    public function fieldLabel(string $path): string
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
}
