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
                fn () => Ticket::where('status', 'open')->count(),
            );

            // Predictive count only for non-staff users
            if ($user->role !== 'staff') {
                $predictiveCount = Cache::remember(
                    DashboardBadgeCache::predictiveKey($user),
                    60,
                    function () {
                        try {
                            $predictiveData = $this->predictiveService->getAlerts(SurveyResponse::query());
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
