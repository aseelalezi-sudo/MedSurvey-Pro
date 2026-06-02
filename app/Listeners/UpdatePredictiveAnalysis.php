<?php

namespace App\Listeners;

use App\Events\SurveySubmitted;
use App\Support\DashboardAnalyticsCache;

class UpdatePredictiveAnalysis
{
    public function handle(SurveySubmitted $event): void
    {
        DashboardAnalyticsCache::bump();
    }
}
