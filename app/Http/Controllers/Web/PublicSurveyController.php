<?php

namespace App\Http\Controllers\Web;

use App\Events\SurveySubmitted;
use App\Http\Requests\SubmitSurveyResponseRequest;
use App\Models\Survey;
use App\Services\ResponseService;
use App\Services\SettingsService;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicSurveyController
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SurveyService $surveyService,
    ) {}

    public function selection(Request $request): View
    {
        $tenantId = $this->surveyService->resolvePublicTenantId($request->query('tenantId'));
        $surveys = $this->surveyService->indexPublic($tenantId)
            ->loadCount('responses');

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

        $tenantId = $this->surveyService->resolvePublicTenantId($request->query('tenantId'));
        $survey = $this->surveyService->findPublicActive($tenantId, $surveyId);

        $settings = $this->settingsService->getPublic($survey->tenantId);

        return view('survey.take', compact('survey', 'settings'));
    }

    public function thanks(): View
    {
        $medicalTip = session()->pull('medicalTip');
        $overallScore = (int) session()->pull('overallScore', 0);
        $thankYouMessage = session()->pull('thankYouMessage');

        return view('survey.thanks', compact('medicalTip', 'overallScore', 'thankYouMessage'));
    }

    public function store(SubmitSurveyResponseRequest $request, ResponseService $responseService): JsonResponse
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

        $payload = $request->validated();
        if (auth()->check()) {
            $payload['collectorId'] = auth()->id();
        }

        $response = $responseService->store($payload);

        try {
            $survey = Survey::find($payload['surveyId']);
            $settings = $survey ? $this->settingsService->getPublic($survey->tenantId) : [];
            $surveySettings = $settings['surveySettings'] ?? [];
            $enableThankYouPage = (bool) ($surveySettings['enableThankYouPage'] ?? true);
            if ($survey && ! empty($survey->tips) && is_array($survey->tips)) {
                $randomTip = $survey->tips[array_rand($survey->tips)];
                session()->put('medicalTip', $randomTip);
            }
            if ($enableThankYouPage && ! empty($surveySettings['thankYouMessage'])) {
                session()->put('thankYouMessage', $surveySettings['thankYouMessage']);
            }
            // Store overall score for the thanks page
            session()->put('overallScore', $response->overallScore ?? 0);
        } catch (\Throwable $e) {
            // Ignore error
        }

        try {
            event(new SurveySubmitted($response));
        } catch (\Throwable $e) {
            Log::warning('Broadcasting SurveySubmitted event failed: '.$e->getMessage());
        }

        $settings = $this->settingsService->getPublic($response->survey?->tenantId);
        $enableThankYouPage = (bool) (($settings['surveySettings']['enableThankYouPage'] ?? true));

        return response()->json([
            ...$responseService->transformResponse($response),
            'redirectUrl' => $enableThankYouPage ? route('survey.thanks') : route('home'),
        ], 201);
    }
}
