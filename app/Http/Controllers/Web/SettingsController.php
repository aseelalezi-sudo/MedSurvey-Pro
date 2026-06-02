<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Services\SettingsService;
use App\Support\DashboardBadgeCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function settings(Request $request): View
    {
        $user = $request->user();
        $settings = $this->settingsService->getAll($user?->tenantId);

        return view('dashboard.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'hospital' => ['nullable', 'array'],
            'hospital.name' => ['nullable', 'string', 'max:200'],
            'hospital.shortName' => ['nullable', 'string', 'max:100'],
            'hospital.logo' => ['nullable', 'string', 'max:500000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                if ($this->isSupportedLogoReference($value)) {
                    return;
                }

                $fail('Hospital logo must be a PNG, JPEG, or WebP image.');
            }],
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
            'appearance.showLanguageToggle' => ['nullable', 'boolean'],

            'backupSettings' => ['nullable', 'array'],
            'backupSettings.schedule' => ['nullable', 'string', 'max:10'],
            'backupSettings.retentionDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'backupSettings.compressGzip' => ['nullable', 'boolean'],
            'backupSettings.backupDir' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        if (isset($payload['hospital']['logo'])) {
            $payload['hospital']['logo'] = $this->storeHospitalLogoIfEmbedded($payload['hospital']['logo'], $user);
        }

        $this->settingsService->update($payload, $user);

        DashboardBadgeCache::forgetPredictive($request->user());

        return redirect()->back()->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    public function usageCheck(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', 'in:department,ageGroup,visitType'],
            'value' => ['required', 'string'],
        ]);

        $user = $request->user();
        $result = $this->settingsService->checkUsage(
            $payload['type'],
            $payload['value'],
            $user?->tenantId
        );

        return response()->json($result);
    }

    private function isSupportedLogoReference(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return true;
        }

        if (preg_match('/^\/?storage\/settings\/logos\/[A-Za-z0-9_\-\/]+\.(?:png|jpe?g|webp)$/i', $value)) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) && str_starts_with(strtolower($value), 'https://')) {
            $path = parse_url($value, PHP_URL_PATH) ?: '';

            return (bool) preg_match('/\.(?:png|jpe?g|webp)$/i', $path);
        }

        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches)) {
            return false;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);

        if ($binary === false) {
            return false;
        }

        $info = @getimagesizefromstring($binary);

        return in_array($info['mime'] ?? null, ['image/png', 'image/jpeg', 'image/webp'], true);
    }

    private function storeHospitalLogoIfEmbedded(string $value, ?User $user): string
    {
        $value = trim($value);

        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,([A-Za-z0-9+\/=\r\n]+)$/', $value, $matches)) {
            return $value;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        if ($binary === false) {
            return '';
        }

        $extension = match (strtolower($matches[1])) {
            'jpeg', 'jpg' => 'jpg',
            'webp' => 'webp',
            default => 'png',
        };
        $scope = $user?->tenantId ?: 'global';
        $path = sprintf('settings/logos/%s/%s.%s', $scope, bin2hex(random_bytes(16)), $extension);

        Storage::disk('public')->put($path, $binary);

        return Storage::url($path);
    }
}
