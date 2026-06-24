<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Support\Cuid;
use App\Support\DashboardAnalyticsCache;
use App\Support\Privacy;
use App\Traits\FiltersResponses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponseService
{
    use FiltersResponses;

    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 200;

    private const EXPORT_LIMIT = 5000;

    private const TEXT_ANSWER_MAX_LENGTH = 1000;

    private const ANSWER_REASON_MAX_LENGTH = 1000;

    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function store(array $payload): SurveyResponse
    {
        $normalizedAnswers = [];
        foreach ($payload['answers'] as $key => $item) {
            if (is_array($item) && isset($item['questionId'])) {
                $normalizedAnswers[$item['questionId']] = $item['value'] ?? null;
            } else {
                $normalizedAnswers[$key] = $item;
            }
        }
        $payload['answers'] = $normalizedAnswers;

        $isPublicSubmission = array_key_exists('_publicTenantId', $payload);
        $publicTenantId = $payload['_publicTenantId'] ?? null;
        unset($payload['_publicTenantId']);

        $surveyQuery = Survey::query()
            ->with(['sections.questions'])
            ->whereKey($payload['surveyId'])
            ->where('isActive', true);

        if ($isPublicSubmission) {
            $surveyQuery->where(function ($query) use ($publicTenantId): void {
                if (is_string($publicTenantId) && trim($publicTenantId) !== '') {
                    $tenantId = trim($publicTenantId);
                    $query->where('tenantId', $tenantId)->orWhereNull('tenantId');

                    return;
                }

                $query->whereNull('tenantId');
            });
        }

        $survey = $surveyQuery->first();

        if (! $survey) {
            throw ValidationException::withMessages([
                'surveyId' => ['الاستبيان غير موجود أو غير نشط'],
            ]);
        }

        $this->validateDepartment($payload['department'], $survey->tenantId);

        $surveySettings = $this->surveySettingsFor($survey->tenantId);
        $this->validatePatientInfo($survey, $payload['patientInfo'] ?? [], $surveySettings);
        $payload['answers'] = $this->validateAndNormalizeAnswers($survey, $payload['answers']);
        $this->validateRequiredQuestions($survey, $payload['answers']);

        $patientInfo = $payload['patientInfo'] ?? [];
        $overallScore = $this->calculateOverallScore($survey, $payload['answers']);

        $response = DB::transaction(function () use ($survey, $payload, $patientInfo, $overallScore): SurveyResponse {
            $response = SurveyResponse::query()->create([
                'surveyId' => $survey->id,
                'answers' => $payload['answers'],
                'patientName' => $patientInfo['name'] ?? null,
                'patientPhone' => $patientInfo['phone'] ?? null,
                'ageGroup' => $patientInfo['ageGroup'] ?? null,
                'gender' => $patientInfo['gender'] ?? null,
                'visitType' => $patientInfo['visitType'] ?? null,
                'department' => $payload['department'],
                'overallScore' => $overallScore,
                'tenantId' => $survey->tenantId,
                'collectorId' => $payload['collectorId'] ?? null,
            ]);

            $this->storeAnswers($survey, $response->id, $payload['answers']);
            $this->createLowScoreTicketIfNeeded($response, $overallScore, $payload);

            return $response;
        });

        DashboardAnalyticsCache::bump();

        return $response;
    }

    public function buildResponseQuery(Request $request, $user, bool $includeSearchFilters = true): Builder
    {
        return $this->buildBaseFilteredResponsesQuery($request, $user)
            ->when(
                $user?->role === 'staff',
                fn ($query) => $query->where('submittedAt', '>=', now()->startOfDay())
            )
            ->when($includeSearchFilters && $request->query('search'), function ($query) use ($request): void {
                $search = addcslashes($request->query('search'), '%_');
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('department', 'like', "%{$search}%")
                        ->orWhere('patientName', 'like', "%{$search}%")
                        ->orWhere('patientPhone', 'like', "%{$search}%");
                });
            })
            ->when($includeSearchFilters && $request->query('score') && $request->query('score') !== 'all', function ($query) use ($request): void {
                match ($request->query('score')) {
                    'excellent' => $query->where('overallScore', '>=', 85),
                    'good' => $query->whereBetween('overallScore', [70, 84]),
                    'average' => $query->whereBetween('overallScore', [50, 69]),
                    'poor' => $query->where('overallScore', '<', 50),
                    default => null,
                };
            })
            ->when($includeSearchFilters && $request->query('gender') && $request->query('gender') !== 'all', fn ($query) => $query->where('gender', $request->query('gender')))
            ->when($includeSearchFilters && $request->query('hasName') === 'true', fn ($query) => $query->whereNotNull('patientName')->where('patientName', '<>', ''))
            ->when($includeSearchFilters && $request->query('hasPhone') === 'true', fn ($query) => $query->whereNotNull('patientPhone')->where('patientPhone', '<>', ''));
    }

    public function calculateOverallScore(Survey $survey, array $answers): int
    {
        $totalScore = 0;
        $maxScore = 0;

        foreach ($survey->sections as $section) {
            foreach ($section->questions as $question) {
                $value = $answers[$question->id] ?? null;

                if ($question->type === 'nps' && is_numeric($value)) {
                    $totalScore += min(10, max(0, (float) $value));
                    $maxScore += 10;

                    continue;
                }

                if (in_array($question->type, ['stars', 'emoji', 'rating'], true) && is_numeric($value)) {
                    $totalScore += min(5, max(0, (float) $value));
                    $maxScore += 5;

                    continue;
                }

                if ($question->type === 'yes_no') {
                    $isPositive = $value === true || (is_string($value) && in_array($value, ['true', 'yes', '1', '5'], true));
                    $isNegative = $value === false || (is_string($value) && in_array($value, ['false', 'no', '0'], true));

                    if ($isPositive || $isNegative) {
                        $totalScore += $isPositive ? 5 : 0;
                        $maxScore += 5;
                    }
                }
            }
        }

        if ($maxScore === 0) {
            return 0;
        }

        return (int) min(100, max(0, round(($totalScore / $maxScore) * 100)));
    }

    public function transformResponse(SurveyResponse $response): array
    {
        return [
            'id' => $response->id,
            'surveyId' => $response->surveyId,
            'answers' => $response->answers,
            'patientInfo' => [
                'name' => $response->patientName ?? '',
                'phone' => request()->user()?->can('responses.view-contact') ? $response->patientPhone : Privacy::maskPhone($response->patientPhone),
                'ageGroup' => $response->ageGroup ?? '',
                'gender' => $response->gender ?? '',
                'visitType' => $response->visitType ?? '',
                'department' => $response->department,
            ],
            'submittedAt' => optional($response->submittedAt)->toISOString(),
            'department' => $response->department,
            'overallScore' => $response->overallScore,
        ];
    }

    // ─── Private Helpers ───

    private function validateAndNormalizeAnswers(Survey $survey, array $answers): array
    {
        $questions = $survey->sections
            ->flatMap(fn ($section) => $section->questions)
            ->keyBy('id');

        $normalized = [];
        $errors = [];

        foreach ($answers as $questionId => $value) {
            $key = (string) $questionId;

            if (str_ends_with($key, '_reason')) {
                $baseQuestionId = substr($key, 0, -7);

                if (! $questions->has($baseQuestionId)) {
                    continue;
                }

                $reason = $this->normalizeTextAnswer($value, self::ANSWER_REASON_MAX_LENGTH, $errors, "answers.{$key}");

                if ($reason !== null && $reason !== '') {
                    $normalized[$key] = $reason;
                }

                continue;
            }

            $question = $questions->get($key);

            if (! $question) {
                continue;
            }

            $field = "answers.{$key}";
            $normalizedValue = match ($question->type) {
                'nps' => $this->normalizeIntegerRange($value, 0, 10, $field, $errors),
                'stars', 'emoji', 'rating' => $this->normalizeIntegerRange($value, 1, 5, $field, $errors),
                'yes_no' => $this->normalizeYesNo($value, $field, $errors),
                'multiple_choice' => $this->normalizeChoiceAnswer($question->options ?? [], $value, $field, $errors),
                'text' => $this->normalizeTextAnswer($value, self::TEXT_ANSWER_MAX_LENGTH, $errors, $field),
                default => null,
            };

            if ($normalizedValue !== null && $normalizedValue !== '' && ! (is_array($normalizedValue) && $normalizedValue === [])) {
                $normalized[$key] = $normalizedValue;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function normalizeIntegerRange(mixed $value, int $min, int $max, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value) || ! is_numeric($value)) {
            $errors[$field] = ["Answer must be a number between {$min} and {$max}"];

            return null;
        }

        $number = (int) $value;

        if ((string) $number !== trim((string) $value) && (float) $number !== (float) $value) {
            $errors[$field] = ["Answer must be a whole number between {$min} and {$max}"];

            return null;
        }

        if ($number < $min || $number > $max) {
            $errors[$field] = ["Answer must be between {$min} and {$max}"];

            return null;
        }

        return $number;
    }

    private function normalizeYesNo(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === true) {
            return 'yes';
        }

        if ($value === false) {
            return 'no';
        }

        $normalized = strtolower(trim((string) $value));
        $yesValues = ['yes', 'true', '1', '5'];
        $noValues = ['no', 'false', '0'];

        if (in_array($normalized, $yesValues, true)) {
            return 'yes';
        }

        if (in_array($normalized, $noValues, true)) {
            return 'no';
        }

        $errors[$field] = ['Answer must be yes or no'];

        return null;
    }

    private function normalizeChoiceAnswer(array $options, mixed $value, string $field, array &$errors): array|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $allowedValues = $this->allowedOptionValues($options);
        $submittedValues = is_array($value) ? array_values($value) : [$value];
        $normalizedValues = [];

        foreach ($submittedValues as $item) {
            if (is_array($item) || is_object($item)) {
                $errors[$field] = ['Choice answer contains an invalid value'];

                return null;
            }

            $choice = trim((string) $item);

            if ($choice === '') {
                continue;
            }

            if ($allowedValues !== [] && ! in_array($choice, $allowedValues, true)) {
                $errors[$field] = ['Selected choice is not allowed for this question'];

                return null;
            }

            $normalizedValues[] = $choice;
        }

        $normalizedValues = array_values(array_unique($normalizedValues));

        if ($normalizedValues === []) {
            return null;
        }

        return is_array($value) ? $normalizedValues : $normalizedValues[0];
    }

    private function allowedOptionValues(array $options): array
    {
        return collect($options)
            ->map(function ($option): ?string {
                if (is_array($option)) {
                    $value = $option['value'] ?? $option['label'] ?? null;
                } else {
                    $value = $option;
                }

                if ($value === null || is_array($value) || is_object($value)) {
                    return null;
                }

                $value = trim((string) $value);

                return $value === '' ? null : $value;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeTextAnswer(mixed $value, int $maxLength, array &$errors, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $errors[$field] = ['Answer must be text'];

            return null;
        }

        $text = trim((string) $value);

        if (mb_strlen($text) > $maxLength) {
            $errors[$field] = ["Answer must not exceed {$maxLength} characters"];

            return null;
        }

        return $text;
    }

    private function validateDepartment(string $department, ?string $tenantId): void
    {
        $settings = $this->settingsService->resolve($tenantId);

        $departments = collect($settings?->data['departments'] ?? [])
            ->filter(fn ($item) => ($item['isActive'] ?? false) === true)
            ->pluck('name')
            ->filter()
            ->values();

        if ($departments->isNotEmpty() && ! $departments->contains($department)) {
            throw ValidationException::withMessages([
                'department' => ['القسم المحدد غير موجود في قائمة الأقسام النشطة'],
            ]);
        }
    }

    private function validateRequiredQuestions(Survey $survey, array $answers): void
    {
        $surveySettings = $this->surveySettingsFor($survey->tenantId);
        $requireAllQuestions = (bool) ($surveySettings['requireAllQuestions'] ?? false);

        $requiredQuestions = $survey->sections
            ->flatMap(fn ($section) => $section->questions)
            ->filter(fn ($question) => $requireAllQuestions || $question->required);

        $missingRequired = $requiredQuestions->filter(function ($question) use ($answers): bool {
            $value = $answers[$question->id] ?? null;

            return $value === null || $value === '' || (is_array($value) && count($value) === 0);
        });

        if ($missingRequired->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['يرجى الإجابة على جميع الأسئلة المطلوبة'],
            ]);
        }
    }

    private function validatePatientInfo(Survey $survey, array $patientInfo, array $surveySettings): void
    {
        $allowAnonymous = (bool) ($surveySettings['allowAnonymous'] ?? true);
        $requireName = ! $allowAnonymous && ((bool) ($surveySettings['requireName'] ?? false) || (bool) $survey->requireName);
        $requirePhone = ! $allowAnonymous && ((bool) ($surveySettings['requirePhone'] ?? false) || (bool) $survey->requirePhone);
        $errors = [];

        if ($requireName && trim((string) ($patientInfo['name'] ?? '')) === '') {
            $errors['patientInfo.name'] = ['Name is required'];
        }

        if ($requirePhone) {
            $phone = preg_replace('/\D+/', '', (string) ($patientInfo['phone'] ?? ''));
            if ($phone === '' || strlen($phone) !== 9 || ! str_starts_with($phone, '7')) {
                $errors['patientInfo.phone'] = ['Phone number is required and must start with 7 with 9 digits'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function surveySettingsFor(?string $tenantId): array
    {
        $settings = $this->settingsService->resolve($tenantId);

        return $settings?->data['surveySettings'] ?? [];
    }

    private function storeAnswers(Survey $survey, string $responseId, array $answers): void
    {
        $validQuestionIds = $survey->sections
            ->flatMap(fn ($section) => $section->questions)
            ->pluck('id')
            ->all();

        $validQuestionLookup = array_flip($validQuestionIds);
        $rows = [];

        foreach ($answers as $questionId => $value) {
            if (! isset($validQuestionLookup[$questionId])) {
                continue;
            }

            $rows[] = [
                'id' => Cuid::make(),
                'responseId' => $responseId,
                'questionId' => $questionId,
                'value' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value,
            ];
        }

        if ($rows === []) {
            return;
        }

        SurveyAnswer::query()->insert($rows);
    }

    private function createLowScoreTicketIfNeeded(SurveyResponse $response, int $overallScore, array $payload): void
    {
        if ($overallScore >= 50) {
            return;
        }

        $patientInfo = $payload['patientInfo'] ?? [];

        Ticket::query()->create([
            'responseId' => $response->id,
            'department' => $payload['department'],
            'patientName' => $patientInfo['name'] ?? __('default_patient_name'),
            'patientPhone' => $patientInfo['phone'] ?? null,
            'tenantId' => $response->tenantId,
            'priority' => $overallScore < 30 ? 'high' : 'medium',
            'status' => 'open',
            'description' => $this->lowScoreTicketDescription($overallScore, $payload['department']),
        ]);
    }

    private function lowScoreTicketDescription(int $overallScore, string $department): string
    {
        return (string) __('auto_ticket_description', ['score' => $overallScore, 'department' => $department]);
    }
}
