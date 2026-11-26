<?php

namespace App\Http\Controllers\Web;

use App\Services\SettingsService;
use App\Services\SurveyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(Request $request, SurveyService $surveys, SettingsService $settingsService): View
    {
        $tenantId = $surveys->resolvePublicTenantId($request->query('tenantId'), $request->user()?->tenantId);
        $settings = $settingsService->getPublic($tenantId);

        return view('pages.home', compact('tenantId', 'settings'));
    }
}
