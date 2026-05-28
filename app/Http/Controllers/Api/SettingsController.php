<?php

namespace App\Http\Controllers\Api;

use App\Models\Settings;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    /**
     * Public-facing endpoint — returns only non-sensitive settings.
     */
    public function showPublic(Request $request): JsonResponse
    {
        $tenantId = $this->resolvePublicTenantId($request);
        $settings = $tenantId
            ? Settings::query()->where('tenantId', $tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        $data = $settings?->data ?? $this->defaults();

        // Return only public-safe fields
        return response()->json([
            'hospital' => $data['hospital'] ?? [],
            'departments' => $data['departments'] ?? [],
            'ageGroups' => $data['ageGroups'] ?? [],
            'visitTypes' => $data['visitTypes'] ?? [],
            'surveySettings' => $data['surveySettings'] ?? [],
            'appearance' => $data['appearance'] ?? [],
        ]);
    }

    /**
     * Authenticated endpoint — returns all settings including sensitive ones.
     */
    public function show(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $tenantId = $user?->tenantId;

        $settings = $tenantId
            ? Settings::query()->where('tenantId', $tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        return response()->json($settings?->data ?? $this->defaults());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'hospital' => ['nullable', 'array'],
            'hospital.name' => ['nullable', 'string', 'max:200'],
            'hospital.shortName' => ['nullable', 'string', 'max:100'],
            'hospital.logo' => ['nullable', 'string', 'max:5000'],
            'hospital.address' => ['nullable', 'string', 'max:500'],
            'hospital.phone' => ['nullable', 'string', 'max:50'],
            'hospital.email' => ['nullable', 'string', 'max:200'],
            'hospital.website' => ['nullable', 'string', 'max:200'],
            'hospital.description' => ['nullable', 'string', 'max:2000'],
            'hospital.workingHours' => ['nullable', 'string', 'max:200'],
            'hospital.operatingTitle' => ['nullable', 'string', 'max:200'],
            'hospital.welcomeMessage' => ['nullable', 'string', 'max:2000'],

            'departments' => ['nullable', 'array', 'max:100'],
            'departments.*.id' => ['required', 'string', 'max:50'],
            'departments.*.name' => ['required', 'string', 'max:120'],
            'departments.*.isActive' => ['required', 'boolean'],
            'departments.*.color' => ['nullable', 'string', 'max:20'],

            'ageGroups' => ['nullable', 'array', 'max:20'],
            'ageGroups.*.id' => ['required', 'string', 'max:50'],
            'ageGroups.*.label' => ['required', 'string', 'max:120'],
            'ageGroups.*.isActive' => ['required', 'boolean'],

            'visitTypes' => ['nullable', 'array', 'max:20'],
            'visitTypes.*.id' => ['required', 'string', 'max:50'],
            'visitTypes.*.label' => ['required', 'string', 'max:120'],
            'visitTypes.*.isActive' => ['required', 'boolean'],

            'surveySettings' => ['nullable', 'array'],
            'surveySettings.allowAnonymous' => ['nullable', 'boolean'],
            'surveySettings.requireAllQuestions' => ['nullable', 'boolean'],
            'surveySettings.requireName' => ['nullable', 'boolean'],
            'surveySettings.requirePhone' => ['nullable', 'boolean'],
            'surveySettings.showProgressBar' => ['nullable', 'boolean'],
            'surveySettings.enableThankYouPage' => ['nullable', 'boolean'],
            'surveySettings.thankYouMessage' => ['nullable', 'string', 'max:2000'],

            'appearance' => ['nullable', 'array'],
            'appearance.primaryColor' => ['nullable', 'string', 'max:20'],
            'appearance.secondaryColor' => ['nullable', 'string', 'max:20'],
            'appearance.fontFamily' => ['nullable', 'string', 'max:50'],

            'backupSettings' => ['nullable', 'array'],
            'backupSettings.schedule' => ['nullable', 'string', 'max:10'],
            'backupSettings.retentionDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'backupSettings.compressGzip' => ['nullable', 'boolean'],
            // backupDir is intentionally excluded — it must be set via .env only
        ]);

        $user = auth('api')->user();

        $settings = $user?->tenantId
            ? Settings::query()->where('tenantId', $user->tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        if ($user?->role !== 'super_admin' && $settings) {
            $currentData = $settings->data ?? $this->defaults();
            if (array_key_exists('departments', $payload)) {
                $payload['departments'] = $currentData['departments'] ?? [];
            }
            if (array_key_exists('ageGroups', $payload)) {
                $payload['ageGroups'] = $currentData['ageGroups'] ?? [];
            }
            if (array_key_exists('visitTypes', $payload)) {
                $payload['visitTypes'] = $currentData['visitTypes'] ?? [];
            }
        }

        // Preserve backupSettings.backupDir from existing settings (cannot be changed via API)
        if (isset($payload['backupSettings']) && $settings) {
            $existing = $settings->data['backupSettings'] ?? [];
            $payload['backupSettings']['backupDir'] = $existing['backupDir'] ?? 'storage/app/backups';
        }

        if (! $settings) {
            $settings = Settings::query()->create([
                'id' => $user?->tenantId ? null : 'global',
                'tenantId' => $user?->tenantId,
                'data' => $payload,
            ]);
        } else {
            $settings->data = $payload;
            $settings->save();
        }

        return response()->json($settings->data);
    }

    public function usageCheck(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'in:department,ageGroup,visitType'],
            'value' => ['required', 'string'],
        ]);

        $user = auth('api')->user();
        $query = SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId));

        match ($payload['type']) {
            'department' => $query->where('department', $payload['value']),
            'ageGroup' => $query->where('ageGroup', $payload['value']),
            'visitType' => $query->where('visitType', $payload['value']),
        };

        $count = $query->count();

        return response()->json([
            'inUse' => $count > 0,
            'count' => $count,
        ]);
    }

    public function defaults(): array
    {
        return [
            'hospital' => [
                'name' => '',
                'shortName' => '',
                'logo' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'website' => '',
                'description' => '',
                'workingHours' => '',
                'operatingTitle' => '',
                'welcomeMessage' => '',
            ],
            'departments' => [
                ['id' => 'dept-1', 'name' => 'الطوارئ', 'isActive' => true, 'color' => '#EF4444'],
                ['id' => 'dept-2', 'name' => 'العيادات الخارجية', 'isActive' => true, 'color' => '#3B82F6'],
                ['id' => 'dept-3', 'name' => 'الباطنية', 'isActive' => true, 'color' => '#10B981'],
            ],
            'ageGroups' => [
                ['id' => 'age-1', 'label' => 'أقل من 18 سنة', 'isActive' => true],
                ['id' => 'age-2', 'label' => '18 - 30 سنة', 'isActive' => true],
                ['id' => 'age-3', 'label' => '31 - 45 سنة', 'isActive' => true],
                ['id' => 'age-4', 'label' => '46 - 60 سنة', 'isActive' => true],
                ['id' => 'age-5', 'label' => 'أكثر من 60 سنة', 'isActive' => true],
            ],
            'visitTypes' => [
                ['id' => 'vt-1', 'label' => 'زيارة طارئة', 'isActive' => true],
                ['id' => 'vt-2', 'label' => 'موعد مسبق', 'isActive' => true],
                ['id' => 'vt-3', 'label' => 'تنويم', 'isActive' => true],
                ['id' => 'vt-4', 'label' => 'مراجعة', 'isActive' => true],
            ],
            'surveySettings' => [
                'allowAnonymous' => true,
                'requireAllQuestions' => false,
                'requireName' => false,
                'requirePhone' => false,
                'showProgressBar' => true,
                'enableThankYouPage' => true,
                'thankYouMessage' => 'شكراً لمشاركتكم! رأيكم يساعدنا في تحسين خدماتنا.',
            ],
            'appearance' => [
                'primaryColor' => '#0d9488',
                'secondaryColor' => '#10b981',
                'fontFamily' => 'Cairo',
            ],
            'backupSettings' => [
                'schedule' => '03:00',
                'retentionDays' => 30,
                'compressGzip' => true,
                'backupDir' => 'storage/app/backups',
            ],
        ];
    }

    /**
     * For unauthenticated access only — resolves tenant from env or query param.
     */
    private function resolvePublicTenantId(Request $request): ?string
    {
        $configuredTenantId = trim((string) config('medsurvey.public_tenant_id', ''));
        if ($configuredTenantId !== '') {
            return $configuredTenantId;
        }

        $requestedTenantId = $request->query('tenantId');

        return is_string($requestedTenantId) && trim($requestedTenantId) !== ''
            ? trim($requestedTenantId)
            : null;
    }
}
