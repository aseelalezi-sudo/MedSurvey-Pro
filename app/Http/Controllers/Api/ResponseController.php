<?php

namespace App\Http\Controllers\Api;

use App\Events\SurveySubmitted;
use App\Http\Requests\SubmitSurveyResponseRequest;
use App\Models\SurveyResponse;
use App\Services\ResponseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResponseController
{
    public function __construct(private readonly ResponseService $responseService) {}

    public function store(SubmitSurveyResponseRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();
        $clientResponseId = trim((string) ($payload['clientResponseId'] ?? ''));

        if ($clientResponseId === '') {
            throw ValidationException::withMessages([
                'clientResponseId' => ['معرّف الاستجابة المحلي مطلوب لمزامنة الاستبيانات دون اتصال.'],
            ]);
        }

        $existing = SurveyResponse::query()
            ->where('clientResponseId', $clientResponseId)
            ->first();

        if ($existing) {
            return $this->duplicateOrConflict($existing, $user->id);
        }

        // The app is authenticated, so the tenant comes from the account and
        // never from a client-supplied value. The bootstrap endpoint sends this
        // tenant's department/patient-info settings to the app, so validation
        // must use the same settings even when the survey itself is global.
        $payload['_publicTenantId'] = $user->tenantId;
        $payload['_settingsTenantId'] = $user->tenantId;
        $payload['collectorId'] = $user->id;

        // Anti-bot web protections do not apply to the authenticated app.
        unset($payload['_timingToken'], $payload['_startedAt'], $payload['_website']);

        try {
            $response = $this->responseService->store($payload);
        } catch (UniqueConstraintViolationException $exception) {
            // Two retries of the same offline response arrived at the same
            // time. The transaction was rolled back, so return the winner.
            $existing = SurveyResponse::query()
                ->where('clientResponseId', $clientResponseId)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->duplicateOrConflict($existing, $user->id);
        }

        try {
            event(new SurveySubmitted($response));
        } catch (\Throwable $e) {
            Log::warning('Broadcasting SurveySubmitted event failed: '.$e->getMessage());
        }

        return response()->json($this->responsePayload($response, false), 201);
    }

    private function duplicateOrConflict(SurveyResponse $response, string $collectorId): JsonResponse
    {
        if ($response->collectorId !== $collectorId) {
            return response()->json([
                'message' => 'معرّف الاستجابة مستخدم مسبقًا. يرجى إعادة المحاولة بمعرّف جديد.',
                'code' => 'CLIENT_RESPONSE_ID_CONFLICT',
            ], 409);
        }

        return response()->json($this->responsePayload($response, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(SurveyResponse $response, bool $duplicate): array
    {
        return [
            'id' => $response->id,
            'clientResponseId' => $response->clientResponseId,
            'surveyId' => $response->surveyId,
            'department' => $response->department,
            'overallScore' => $response->overallScore,
            'submittedAt' => optional($response->submittedAt)->toISOString(),
            'duplicate' => $duplicate,
        ];
    }
}
