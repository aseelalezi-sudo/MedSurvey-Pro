<?php

namespace App\Listeners;

use App\Events\SurveySubmitted;
use App\Services\PredictiveService;
use Illuminate\Support\Facades\Cache;

class UpdatePredictiveAnalysis
{
    public function __construct(
        private readonly PredictiveService $predictiveService
    ) {}

    public function handle(SurveySubmitted $event): void
    {
        // Clear cached stats that include this response
        $cacheKey = "response_stats_*";
        // Invalidate the general stats cache
        Cache::flush();
    }
}
