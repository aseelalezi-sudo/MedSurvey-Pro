<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->selectOne('select 1');
            $ready = true;
        } catch (\Throwable) {
            $ready = false;
        }

        return response()->json([
            'status' => $ready ? 'ok' : 'unavailable',
            'timestamp' => now()->toISOString(),
            'service' => 'MedSurvey Pro',
        ], $ready ? 200 : 503);
    }
}
