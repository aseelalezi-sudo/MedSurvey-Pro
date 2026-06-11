<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Support\Cuid;
use App\Support\DashboardAnalyticsCache;
use App\Support\DateFilterBounds;
use App\Support\Privacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponseService
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 200;

    private const EXPORT_LIMIT = 5000;

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

        $survey = Survey::query()
            ->with(['sections.questions'])
            ->whereKey($payload['surveyId'])
            ->where('isActive', true)
            ->first();

        if (! $survey) {
            throw ValidationException::withMessages([
                'surveyId' => ['الاستبيان غير موجود أو غير نشط'],
            ]);
        }

        $this->validateDepartment($payload['department'], $survey->tenantId);

        $surveySettings = $this->surveySettingsFor($survey->tenantId);
        $this->validatePatientInfo($survey, $payload['patientInfo'] ?? [], $surveySettings);
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
        return SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department),
                fn ($query) => $query->when(
                    $request->query('department') && $request->query('department') !== 'all',
                    fn ($q) => $q->where('department', $request->query('department'))
                ),
            )
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
            ->when($includeSearchFilters && $request->query('hasPhone') === 'true', fn ($query) => $query->whereNotNull('patientPhone')->where('patientPhone', '<>', ''))
            ->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($query) use ($request): void {
                if ($request->query('dateFilter') === 'today') {
                    $query->where('submittedAt', '>=', now()->startOfDay());
                } elseif ($request->query('dateFilter') === 'week') {
                    $query->where('submittedAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $query->where('submittedAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === 'custom') {
                    if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                        $query->where('submittedAt', '>=', $startDate);
                    }
                    if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                        $query->where('submittedAt', '<=', $endDate);
                    }
                }
            })
            ->when(! $request->query('dateFilter') || $request->query('dateFilter') === 'all', function ($query) use ($request): void {
                if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                    $query->where('submittedAt', '>=', $startDate);
                }
                if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                    $query->where('submittedAt', '<=', $endDate);
                }
            });
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
                'phone' => Privacy::maskPhone($response->patientPhone),
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
            'patientName' => $patientInfo['name'] ?? 'زائر',
            'patientPhone' => $patientInfo['phone'] ?? null,
            'tenantId' => $response->tenantId,
            'priority' => $overallScore < 30 ? 'high' : 'medium',
            'status' => 'open',
            'description' => $this->lowScoreTicketDescription($overallScore, $payload['department']),
        ]);
    }

    private function lowScoreTicketDescription(int $overallScore, string $department): string
    {
        return "تنبيه آلي: تقييم منخفض جداً ({$overallScore}%). المراجع أبدى عدم رضاه عن الخدمة في قسم {$department}. يرجى المتابعة الفورية.";
    }
}
