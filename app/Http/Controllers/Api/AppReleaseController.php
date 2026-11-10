<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * Tells the Android app which build the hospital currently distributes.
 *
 * The values live in configuration (environment) rather than a database row so
 * publishing a release does not require a dashboard login: update
 * MOBILE_LATEST_VERSION / MOBILE_LATEST_BUILD / MOBILE_DOWNLOAD_URL, refresh
 * the config cache, and collectors see the notice on their next check.
 */
class AppReleaseController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'version' => (string) config('medsurvey.mobile.latest_version'),
            'build' => (int) config('medsurvey.mobile.latest_build'),
            'downloadUrl' => (string) config('medsurvey.mobile.download_url'),
            'notes' => (string) config('medsurvey.mobile.notes'),
            'minBuild' => (int) config('medsurvey.mobile.min_build'),
        ]);
    }
}
