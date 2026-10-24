<?php

namespace App\Http\Controllers\Api;

use App\Services\SettingsService;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController
{
    public function __construct(
        private readonly SurveyService $surveyService,
        private readonly SettingsService $settingsService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->settingsService->getPublic($user->tenantId);
        $surveys = $this->surveyService->indexForApp($user->tenantId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'role' => $user->role,
                'department' => $user->department,
                'tenantId' => $user->tenantId,
            ],
            'surveys' => $surveys
                ->map(fn ($survey) => $this->surveyService->transformSurvey($survey))
                ->values(),
            'settings' => [
                'hospital' => [
                    'name' => $settings['hospital']['name'] ?? '',
                    'logo' => $settings['hospital']['logo'] ?? '',
                    'phone' => $settings['hospital']['phone'] ?? '',
                    'address' => $settings['hospital']['address'] ?? '',
                    'description' => $settings['hospital']['description'] ?? '',
                    'welcomeMessage' => $settings['hospital']['welcomeMessage'] ?? '',
                ],
                'departments' => $this->activeDepartments($settings['departments'] ?? []),
                'ageGroups' => $this->activeLabels($settings['ageGroups'] ?? []),
                'visitTypes' => $this->activeLabels($settings['visitTypes'] ?? []),
                'surveySettings' => $settings['surveySettings'] ?? [],
                'appearance' => $settings['appearance'] ?? [],
            ],
            'serverTime' => now()->toISOString(),
        ]);
    }

    /**
     * @param  array<int, mixed>  $departments
     * @return array<int, array<string, string>>
     */
    private function activeDepartments(array $departments): array
    {
        return collect($departments)
            ->filter(fn ($item) => is_array($item)
                && filter_var($item['isActive'] ?? true, FILTER_VALIDATE_BOOL)
                && trim((string) ($item['name'] ?? '')) !== '')
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? $item['name']),
                'name' => (string) $item['name'],
                'color' => (string) ($item['color'] ?? '#0d9488'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, string>>
     */
    private function activeLabels(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item)
                && filter_var($item['isActive'] ?? true, FILTER_VALIDATE_BOOL)
                && trim((string) ($item['label'] ?? '')) !== '')
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? $item['label']),
                'label' => (string) $item['label'],
            ])
            ->values()
            ->all();
    }
}
