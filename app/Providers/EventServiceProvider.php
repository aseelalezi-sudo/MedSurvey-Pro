<?php

namespace App\Providers;

use App\Events\SurveySubmitted;
use App\Listeners\UpdatePredictiveAnalysis;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SurveySubmitted::class => [
            UpdatePredictiveAnalysis::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
