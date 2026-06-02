<?php

namespace App\Http\Controllers\Web;

use App\Events\SurveySubmitted;
use App\Models\Survey;
use App\Services\ResponseService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSurveyController
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    public function selection(): View
    {
        $surveys = Survey::query()
            ->where('isActive', true)
            ->with(['sections.questions'])
            ->withCount('responses')
            ->orderByDesc('createdAt')
            ->get();

        return view('survey.selection', compact('surveys'));
    }

    public function info(): RedirectResponse
    {
        return redirect()->route('survey.selection');
    }

    public function take(Request $request)
    {
        $surveyId = $request->query('surveyId');
        if (! $surveyId) {
            return redirect()->route('survey.selection');
        }

        $survey = Survey::query()
            ->where('isActive', true)
            ->with(['sections' => fn ($q) => $q->orderBy('sortOrder'), 'sections.questions' => fn ($q) => $q->orderBy('sortOrder')])
            ->findOrFail($surveyId);

        $settings = $this->settingsService->getPublic($survey->tenantId);

        return view('survey.take', compact('survey', 'settings'));
    }

    public function thanks(): View
    {
        $medicalTip = session()->pull('medicalTip');
        $overallScore = (int) session()->pull('overallScore', 0);

        return view('survey.thanks', compact('medicalTip', 'overallScore'));
    }

    public function store(Request $request, ResponseService $responseService): JsonResponse
    {
        // Honeypot anti-bot protection: bots auto-fill hidden fields
        if ($request->filled('_website')) {
            // Silently accept but don't store — bots think it succeeded
            return response()->json(['id' => 'ok', 'message' => 'Response recorded'], 201);
        }

        // Timing-based anti-bot: reject submissions faster than 5 seconds
        $startedAt = $request->input('_startedAt');
        if ($startedAt && is_numeric($startedAt)) {
            $elapsedMs = (int) (microtime(true) * 1000) - (int) $startedAt;
            if ($elapsedMs < 5000) {
                return response()->json(['id' => 'ok', 'message' => 'Response recorded'], 201);
            }
        }

        $payload = $request->validate([
            'surveyId' => ['required', 'string'],
            'answers' => ['required', 'array', 'max:300'],
            'department' => ['required', 'string', 'max:120'],
            'patientInfo' => ['nullable', 'array'],
            'patientInfo.name' => ['nullable', 'string', 'max:120'],
            'patientInfo.phone' => ['nullable', 'string', 'max:40'],
            'patientInfo.ageGroup' => ['nullable', 'string', 'max:80'],
            'patientInfo.gender' => ['nullable', 'string', 'max:40'],
            'patientInfo.visitType' => ['nullable', 'string', 'max:80'],
        ]);

        $response = $responseService->store($payload);

        try {
            $survey = Survey::find($payload['surveyId']);
            if ($survey && ! empty($survey->tips) && is_array($survey->tips)) {
                $randomTip = $survey->tips[array_rand($survey->tips)];
                session()->put('medicalTip', $randomTip);
            }
            // Store overall score for the thanks page
            if (isset($response->overallScore) || method_exists($response, 'getOverallScore')) {
                session()->put('overallScore', $response->overallScore ?? $response->getOverallScore());
            }
        } catch (\Throwable $e) {
            // Ignore error
        }

        try {
            event(new SurveySubmitted($response));
        } catch (\Throwable $e) {
            \Log::warning('Broadcasting SurveySubmitted event failed: '.$e->getMessage());
        }

        return response()->json(
            $responseService->transformResponse($response),
            201
        );
    }
}
