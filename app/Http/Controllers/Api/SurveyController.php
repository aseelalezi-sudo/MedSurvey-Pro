<?php

namespace App\Http\Controllers\Api;

use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController
{
    public function __construct(private readonly SurveyService $surveyService) {}

    public function index(Request $request): JsonResponse
    {
        $surveys = $this->surveyService->indexForApp($request->user()->tenantId);

        return response()->json([
            'surveys' => $surveys
                ->map(fn ($survey) => $this->surveyService->transformSurvey($survey))
                ->values(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $survey = $this->surveyService->findPublicActive($request->user()->tenantId, $id);

        return response()->json([
            'survey' => $this->surveyService->transformSurvey($survey),
        ]);
    }
}
