<?php

namespace App\Services;

use App\Models\Settings;
use App\Models\SurveyResponse;

class SettingsService
{
    /**
     * Resolve a Settings record following the tenant → global fallback chain.
     * This is the single source of truth for settings lookup across all services.
     */
    public function resolve(?string $tenantId): ?Settings
    {
        if ($tenantId) {
            $settings = Settings::query()->where('tenantId', $tenantId)->first();
            if ($settings) {
                return $settings;
            }
        }

        // Fall back to global settings
        return Settings::query()->where('id', 'global')->first()
            ?? Settings::query()->whereNull('tenantId')->first();
    }

    public function getPublic(?string $tenantId): array
    {
        $settings = $this->resolve($tenantId);
        $data = $settings?->data ?? $this->defaults();

        return [
            'hospital' => $data['hospital'] ?? [],
            'departments' => $data['departments'] ?? [],
            'ageGroups' => $data['ageGroups'] ?? [],
            'visitTypes' => $data['visitTypes'] ?? [],
            'surveySettings' => $data['surveySettings'] ?? [],
            'appearance' => $data['appearance'] ?? [],
        ];
    }

    public function getAll(?string $tenantId): array
    {
        return $this->resolve($tenantId)?->data ?? $this->defaults();
    }

    public function update(array $payload, $user): array
    {
        $tenantId = $user?->tenantId;

        $settings = $tenantId
            ? Settings::query()->where('tenantId', $tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        // Non-super_admin cannot modify globally managed lists or the backup directory,
        // even when this is the tenant's first settings row.
        if ($user?->role !== 'super_admin') {
            $currentData = $settings?->data ?? $this->defaults();
            if (array_key_exists('departments', $payload)) {
                $payload['departments'] = $currentData['departments'] ?? [];
            }
            if (array_key_exists('ageGroups', $payload)) {
                $payload['ageGroups'] = $currentData['ageGroups'] ?? [];
            }
            if (array_key_exists('visitTypes', $payload)) {
                $payload['visitTypes'] = $currentData['visitTypes'] ?? [];
            }
            if (isset($payload['backupSettings']['backupDir'])) {
                $payload['backupSettings']['backupDir'] = $currentData['backupSettings']['backupDir'] ?? 'storage/app/backups';
            }
        }

        // Preserve backupSettings.backupDir from existing if not provided in payload
        if (isset($payload['backupSettings']) && $settings) {
            $existing = $settings->data['backupSettings'] ?? [];
            if (empty($payload['backupSettings']['backupDir'])) {
                $payload['backupSettings']['backupDir'] = $existing['backupDir'] ?? 'storage/app/backups';
            }
        }

        if (! $settings) {
            $settings = Settings::query()->create([
                'id' => $tenantId ? null : 'global',
                'tenantId' => $tenantId,
                'data' => $payload,
            ]);
        } else {
            $data = array_replace_recursive($settings->data ?? [], $payload);
            foreach (['departments', 'ageGroups', 'visitTypes', 'activatedPredictivePlans'] as $listKey) {
                if (array_key_exists($listKey, $payload)) {
                    $data[$listKey] = $payload[$listKey];
                }
            }

            $settings->data = $data;
            $settings->save();
        }

        return $settings->data;
    }

    public function checkUsage(string $type, string $value, ?string $tenantId): array
    {
        $query = SurveyResponse::query()
            ->when($tenantId, fn ($query) => $query->where('tenantId', $tenantId));

        match ($type) {
            'department' => $query->where('department', $value),
            'ageGroup' => $query->where('ageGroup', $value),
            'visitType' => $query->where('visitType', $value),
        };

        $count = $query->count();

        return [
            'inUse' => $count > 0,
            'count' => $count,
        ];
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
            'archiveSettings' => [
                'enabled' => true,
                'schedule' => '02:30',
                'retentionYears' => 3,
            ],
        ];
    }
}
