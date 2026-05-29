<?php

namespace App\Http\Controllers\Api;

use App\Services\SurveyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SurveyController
{
    public function __construct(
        private readonly SurveyService $surveyService
    ) {}

    public function indexPublic(Request $request): JsonResponse
    {
        $surveys = $this->surveyService->indexPublic($request);

        return response()->json(
            $surveys->map(fn ($survey) => $this->surveyService->transformSurvey($survey))
        );
    }

    public function index(Request $request): JsonResponse
    {
        $surveys = $this->surveyService->index($request);

        return response()->json(
            $surveys->map(fn ($survey) => $this->surveyService->transformSurvey($survey))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateSurvey($request);
        $user = auth("api")->user();

        $survey = $this->surveyService->store($payload, $user);

        return ApiResponse::created(
            $this->surveyService->transformSurvey($survey)
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $this->validateSurvey($request);
        $user = auth("api")->user();

        try {
            $survey = $this->surveyService->update($id, $payload, $user);
            return response()->json($this->surveyService->transformSurvey($survey));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $user = auth("api")->user();

        try {
            $this->surveyService->destroy($id, $user);
            return ApiResponse::deleted("تم حذف الاستبيان بنجاح");
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    private function validateSurvey(Request $request): array
    {
        return $request->validate([
            "title" => ["required", "string"],
            "description" => ["nullable", "string"],
            "isActive" => ["sometimes", "boolean"],
            "requireName" => ["sometimes", "boolean"],
            "requirePhone" => ["sometimes", "boolean"],
            "assignedDepartments" => ["nullable", "array"],
            "assignedDepartments.*" => ["string"],
            "tips" => ["nullable", "array"],
            "sections" => ["nullable", "array"],
            "sections.*.id" => ["nullable", "string"],
            "sections.*.title" => ["nullable", "string"],
            "sections.*.description" => ["nullable", "string"],
            "sections.*.icon" => ["nullable", "string"],
            "sections.*.questions" => ["nullable", "array"],
            "sections.*.questions.*.type" => ["nullable", Rule::in(["rating", "stars", "emoji", "text", "multiple_choice", "yes_no", "nps"])],
            "sections.*.questions.*.title" => ["nullable", "string"],
            "sections.*.questions.*.description" => ["nullable", "string"],
            "sections.*.questions.*.required" => ["nullable", "boolean"],
            "sections.*.questions.*.category" => ["nullable", "string"],
            "sections.*.questions.*.options" => ["nullable"],
            "sections.*.questions.*.followUp" => ["nullable"],
        ]);
    }
}
