<?php

namespace App\Http\Controllers\Web;

use App\Events\SurveySubmitted;
use App\Http\Requests\SubmitSurveyResponseRequest;
use App\Models\Survey;
use App\Services\ResponseService;
use App\Services\SettingsService;
use App\Services\SurveyService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicSurveyController
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SurveyService $surveyService,
    ) {}

    public function selection(Request $request): View|RedirectResponse
    {
        $tenantId = $this->surveyService->resolvePublicTenantId($request->query('tenantId'));
        $settings = $this->settingsService->getPublic($tenantId);

        // Check if login is required for survey access
        if ($redirect = $this->enforceLoginRequirement($settings)) {
            return $redirect;
        }

        $surveys = $this->surveyService->indexPublic($tenantId)
            ->loadCount('responses');

        return view('survey.selection', compact('surveys', 'tenantId', 'settings'));
    }

    public function info(): RedirectResponse
    {
        return redirect()->route('survey.selection');
    }

    public function take(Request $request): View|RedirectResponse
    {
        $surveyId = $request->query('surveyId');
        if (! $surveyId) {
            return redirect()->route('survey.selection');
        }

        $tenantId = $this->surveyService->resolvePublicTenantId($request->query('tenantId'));
        $survey = $this->surveyService->findPublicActive($tenantId, $surveyId);

        $settings = $this->settingsService->getPublic($survey->tenantId);

        // Check if login is required for survey access
        if ($redirect = $this->enforceLoginRequirement($settings)) {
            return $redirect;
        }

        $timingToken = Crypt::encryptString(json_encode([
            'surveyId' => $survey->id,
            'startedAt' => microtime(true),
        ], JSON_THROW_ON_ERROR));

        return view('survey.take', compact('survey', 'settings', 'tenantId', 'timingToken'));
    }

    /**
     * Enforce login verification for survey access.
     * Returns a redirect response if blocked, or null if allowed through.
     */
    private function enforceLoginRequirement(array $settings): ?RedirectResponse
    {
        $requireLogin = (bool) ($settings['surveySettings']['requireLogin'] ?? false);

        if (! $requireLogin) {
            return null;
        }

        if (! auth()->check()) {
            session()->flash('login_required_message', __('survey_login_required'));

            return redirect()->route('login');
        }

        if (! auth()->user()?->can('surveys.submit')) {
            session()->flash('survey_permission_denied', __('survey_no_permission'));

            return redirect()->route('home');
        }

        return null; // Authenticated and has permission — allow through
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

        // Timing-based anti-bot protection uses a server-issued token so a
        // patient's incorrect device clock can never discard a real response.
        if ($this->isSuspiciouslyFastSubmission($request)) {
            return response()->json(['id' => 'ok', 'message' => 'Response recorded'], 201);
        }

        $payload = $request->validated();
        $payload['_publicTenantId'] = $this->surveyService->resolvePublicTenantId($request->input('tenantId', $request->query('tenantId')));
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

    private function isSuspiciouslyFastSubmission(Request $request): bool
    {
        $timingToken = $request->input('_timingToken');

        if (is_string($timingToken) && $timingToken !== '') {
            try {
                $timing = json_decode(Crypt::decryptString($timingToken), true, 8, JSON_THROW_ON_ERROR);
                $startedAt = $timing['startedAt'] ?? null;
                $surveyId = $timing['surveyId'] ?? null;

                if (is_numeric($startedAt) && hash_equals((string) $surveyId, (string) $request->input('surveyId'))) {
                    $elapsedSeconds = microtime(true) - (float) $startedAt;

                    return $elapsedSeconds >= 0 && $elapsedSeconds < 5;
                }
            } catch (DecryptException|\JsonException) {
                // Invalid or stale tokens do not silently discard submissions.
            }

            return false;
        }

        // Backwards compatibility for older cached PWA clients. A timestamp in
        // the future indicates clock skew and must never be treated as a bot.
        $legacyStartedAt = $request->input('_startedAt');
        if (! is_numeric($legacyStartedAt)) {
            return false;
        }

        $elapsedMs = (int) (microtime(true) * 1000) - (int) $legacyStartedAt;

        return $elapsedMs >= 0 && $elapsedMs < 5000;
    }
}
