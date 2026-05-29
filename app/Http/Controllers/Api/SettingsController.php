<?php

namespace App\Http\Controllers\Api;

use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    /**
     * Public-facing endpoint — returns only non-sensitive settings.
     */
    public function showPublic(Request $request): JsonResponse
    {
        $tenantId = $this->resolvePublicTenantId($request);
        $data = $this->settingsService->getPublic($tenantId);

        return response()->json($data);
    }

    /**
     * Authenticated endpoint — returns all settings including sensitive ones.
     */
    public function show(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->settingsService->getAll($user?->tenantId);

        return response()->json($data);
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'hospital' => ['nullable', 'array'],
            'hospital.name' => ['nullable', 'string', 'max:200'],
            'hospital.shortName' => ['nullable', 'string', 'max:100'],
            'hospital.logo' => ['nullable', 'string', 'max:5000000'],
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
        ]);

        $user = auth('api')->user();
        $data = $this->settingsService->update($payload, $user);

        return response()->json($data);
    }

    public function usageCheck(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'in:department,ageGroup,visitType'],
            'value' => ['required', 'string'],
        ]);

        $user = auth('api')->user();
        $result = $this->settingsService->checkUsage(
            $payload['type'],
            $payload['value'],
            $user?->tenantId
        );

        return response()->json($result);
    }

    public function defaults(): array
    {
        return $this->settingsService->defaults();
    }

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
