<?php

namespace App\View\Composers;

use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PredictiveService;
use App\Services\SettingsService;
use App\Support\DashboardBadgeCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

final class DashboardLayoutComposer
{
    public function __construct(
        private readonly PredictiveService $predictiveService,
        private readonly SettingsService $settingsService,
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $openTicketsCount = 0;
        $predictiveCount = 0;

        if ($user instanceof User) {
            // Cached open tickets count
            $openTicketsCount = Cache::remember(
                DashboardBadgeCache::openTicketsKey($user),
                60,
                fn () => Ticket::query()
                    ->where('status', 'open')
                    ->when($user->tenantId, fn ($query) => $query->whereHas('response', fn ($response) => $response->where('tenantId', $user->tenantId)))
                    ->when($user->role === 'head_of_department' && $user->department, fn ($query) => $query->where('department', $user->department))
                    ->count(),
            );

            // Predictive count only for non-staff users
            if ($user->role !== 'staff') {
                $predictiveCount = Cache::remember(
                    DashboardBadgeCache::predictiveKey($user),
                    60,
                    function () use ($user) {
                        try {
                            $predictiveData = $this->predictiveService->getAlerts(
                                SurveyResponse::query()
                                    ->when($user->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
                                    ->when($user->role === 'head_of_department' && $user->department, fn ($query) => $query->where('department', $user->department))
                            );
                            $settings = $this->settingsService->getAll(Auth::user()?->tenantId);
                            $activatedPlans = $settings['activatedPredictivePlans'] ?? [];

                            return collect($predictiveData['alerts'] ?? [])
                                ->filter(fn ($alert) => ! in_array($alert['department'], $activatedPlans))
                                ->count();
                        } catch (Throwable) {
                            return 0;
                        }
                    },
                );
            }
        }

        $view->with([
            'openTicketsCount' => $openTicketsCount,
            'predictiveCount' => $predictiveCount,
        ]);
    }
}
