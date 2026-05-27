<?php

namespace App\Http\Controllers\Api;

use App\Models\Settings;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $settings = $tenantId
            ? Settings::query()->where('tenantId', $tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        return response()->json($settings?->data ?? $this->defaults());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->all();
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

    private function resolveTenantId(Request $request): ?string
    {
        $user = auth('api')->user();
        if ($user?->tenantId) {
            return $user->tenantId;
        }

        $configuredTenantId = trim((string) env('PUBLIC_TENANT_ID', ''));
        if ($configuredTenantId !== '') {
            return $configuredTenantId;
        }

        $requestedTenantId = $request->query('tenantId');

        return is_string($requestedTenantId) && trim($requestedTenantId) !== ''
            ? trim($requestedTenantId)
            : null;
    }
}
