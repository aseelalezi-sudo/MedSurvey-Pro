<?php

namespace App\Http\Controllers\Api;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponseController
{
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 200;
    private const EXPORT_LIMIT = 5000;

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'surveyId' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'department' => ['required', 'string'],
            'patientInfo' => ['nullable', 'array'],
            'patientInfo.name' => ['nullable', 'string'],
            'patientInfo.phone' => ['nullable', 'string'],
            'patientInfo.ageGroup' => ['nullable', 'string'],
            'patientInfo.gender' => ['nullable', 'string'],
            'patientInfo.visitType' => ['nullable', 'string'],
        ]);

        $this->validateDepartment($payload['department']);

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

        $answers = $payload['answers'];
        $requiredQuestions = $survey->sections
            ->flatMap(fn ($section) => $section->questions)
            ->filter(fn ($question) => $question->required);

        $missingRequired = $requiredQuestions->filter(function ($question) use ($answers): bool {
            $value = $answers[$question->id] ?? null;
            return $value === null || $value === '' || (is_array($value) && count($value) === 0);
        });

        if ($missingRequired->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['يرجى الإجابة على جميع الأسئلة المطلوبة'],
            ]);
        }

        $patientInfo = $payload['patientInfo'] ?? [];
        $overallScore = $this->calculateOverallScore($survey, $answers);

        $response = DB::transaction(function () use ($survey, $payload, $patientInfo, $answers, $overallScore): SurveyResponse {
            $response = SurveyResponse::query()->create([
                'surveyId' => $survey->id,
                'answers' => $answers,
                'patientName' => $patientInfo['name'] ?? null,
                'patientPhone' => $patientInfo['phone'] ?? null,
                'ageGroup' => $patientInfo['ageGroup'] ?? null,
                'gender' => $patientInfo['gender'] ?? null,
                'visitType' => $patientInfo['visitType'] ?? null,
                'department' => $payload['department'],
                'overallScore' => $overallScore,
                'tenantId' => $survey->tenantId,
            ]);

            $validQuestionIds = $survey->sections
                ->flatMap(fn ($section) => $section->questions)
                ->pluck('id')
                ->all();

            foreach ($answers as $questionId => $value) {
                if (! in_array($questionId, $validQuestionIds, true)) {
                    continue;
                }

                SurveyAnswer::query()->firstOrCreate(
                    ['responseId' => $response->id, 'questionId' => $questionId],
                    ['value' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value],
                );
            }

            if ($overallScore < 50) {
                Ticket::query()->create([
                    'responseId' => $response->id,
                    'department' => $payload['department'],
                    'patientName' => $patientInfo['name'] ?? 'زائر',
                    'patientPhone' => $patientInfo['phone'] ?? null,
                    'priority' => $overallScore < 30 ? 'high' : 'medium',
                    'status' => 'open',
                    'description' => 'تم إنشاء تذكرة تلقائية بسبب انخفاض تقييم الاستبيان.',
                ]);
            }

            return $response;
        });

        event(new \App\Events\SurveySubmitted($response));

        return response()->json($this->transformResponse($response), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if ($request->query('exportAll') === 'true' && ! in_array($user->role, ['super_admin', 'admin', 'unit_manager', 'head_of_department'], true)) {
            return response()->json(['error' => 'ليس لديك صلاحية لتصدير البيانات'], 403);
        }

        $query = $this->buildResponseQuery($request, $user);
        $sortBy = in_array($request->query('sortBy'), ['submittedAt', 'overallScore', 'department', 'patientName', 'patientPhone'], true)
            ? $request->query('sortBy')
            : 'submittedAt';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        if ($request->query('exportAll') === 'true') {
            $total = (clone $query)->count();
            if ($total > self::EXPORT_LIMIT) {
                return response()->json([
                    'error' => "حجم البيانات المطلوب تصديرها ضخم جداً ({$total} سجل). الحد الأقصى المسموح به هو ".self::EXPORT_LIMIT.' سجل.',
                ], 400);
            }

            $responses = $query->orderBy($sortBy, $order)->get();

            return response()->json([
                'data' => $responses->map(fn (SurveyResponse $response) => $this->transformResponse($response))->values(),
                'pagination' => [
                    'total' => $responses->count(),
                    'page' => 1,
                    'limit' => $responses->count(),
                    'totalPages' => 1,
                ],
            ]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query('limit', self::DEFAULT_PAGE_SIZE)));
        $total = (clone $query)->count();
        $average = (clone $query)->avg('overallScore');
        $responses = $query
            ->orderBy($sortBy, $order)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $responses->map(fn (SurveyResponse $response) => $this->transformResponse($response))->values(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
            'meta' => [
                'averageScore' => (int) round($average ?? 0),
                'filteredTotal' => $total,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $user = auth('api')->user();

        if (! in_array($user->role, ['super_admin', 'admin', 'unit_manager', 'head_of_department'], true)) {
            return response()->json(['error' => 'ليس لديك صلاحية لتصدير البيانات'], 403);
        }

        $query = $this->buildResponseQuery($request, $user);
        
        $total = (clone $query)->count();
        if ($total > self::EXPORT_LIMIT) {
            return response()->json([
                'error' => "حجم البيانات المطلوب تصديرها ضخم جداً ({$total} سجل). الحد الأقصى المسموح به هو ".self::EXPORT_LIMIT.' سجل.',
            ], 400);
        }

        $fileName = 'responses_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fputs($handle, "\xEF\xBB\xBF");
            
            // CSV Headers
            fputcsv($handle, [
                'المعرف', 'القسم', 'اسم المريض', 'رقم الهاتف', 
                'الفئة العمرية', 'الجنس', 'نوع الزيارة', 
                'التقييم العام', 'تاريخ الإرسال'
            ]);

            // Chunk the results to save memory
            $query->orderBy('submittedAt', 'desc')->chunk(500, function ($responses) use ($handle) {
                foreach ($responses as $response) {
                    fputcsv($handle, [
                        $response->id,
                        $response->department,
                        $response->patientName ?? 'غير محدد',
                        $response->patientPhone ?? 'غير محدد',
                        $response->patientAgeGroup ?? 'غير محدد',
                        $response->patientGender === 'male' ? 'ذكر' : ($response->patientGender === 'female' ? 'أنثى' : 'غير محدد'),
                        $response->visitType === 'inpatient' ? 'تنويم' : ($response->visitType === 'outpatient' ? 'عيادات خارجية' : ($response->visitType === 'emergency' ? 'طوارئ' : 'غير محدد')),
                        $response->overallScore . '%',
                        $response->submittedAt->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $cacheKey = 'response_stats_' . md5(json_encode($request->query()) . '_' . ($user?->id ?? 'guest'));

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(5), function () use ($request, $user) {
            $query = $this->buildResponseQuery($request, $user, includeSearchFilters: false);

            $totalResponses = (clone $query)->count();
            $averageScore = (int) round((clone $query)->avg('overallScore') ?? 0);

            // Calculate previous stats (e.g. older than 30 days for growth indicator)
            $previousQuery = $this->buildResponseQuery($request, $user, includeSearchFilters: false)
                ->where('submittedAt', '<', now()->subDays(30));
            $previousAverageScore = (int) round((clone $previousQuery)->avg('overallScore') ?? $averageScore);
            $previousNpsScore = $this->calculateNps((clone $previousQuery)->pluck('id')->all());

            $departmentScores = (clone $query)
                ->select('department', DB::raw('AVG(overallScore) as score'), DB::raw('COUNT(*) as count'))
                ->groupBy('department')
                ->orderByDesc('score')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->department,
                    'score' => (int) round($row->score ?? 0),
                    'count' => (int) $row->count,
                ]);

            $distribution = [
                ['level' => 'ممتاز', 'count' => (clone $query)->where('overallScore', '>=', 85)->count(), 'color' => '#10B981'],
                ['level' => 'جيد', 'count' => (clone $query)->whereBetween('overallScore', [70, 84])->count(), 'color' => '#3B82F6'],
                ['level' => 'متوسط', 'count' => (clone $query)->whereBetween('overallScore', [50, 69])->count(), 'color' => '#F59E0B'],
                ['level' => 'ضعيف', 'count' => (clone $query)->where('overallScore', '<', 50)->count(), 'color' => '#EF4444'],
            ];

            $responseIds = (clone $query)->pluck('id');

            return [
                'totalResponses' => $totalResponses,
                'averageScore' => $averageScore,
                'previousAverageScore' => $previousAverageScore,
                'npsScore' => $this->calculateNps($responseIds->all()),
                'previousNpsScore' => $previousNpsScore ?: 0,
                'departmentScores' => $departmentScores,
                'hourlyStats' => $this->hourlyStats(clone $query),
                'dayStats' => $this->dayStats(clone $query),
                'categoryScores' => $this->categoryScores($responseIds->all()),
                'trendData' => $this->trendData(clone $query),
                'satisfactionDistribution' => $distribution,
                'responseRate' => 100,
                'previousResponseRate' => 100,
            ];
        });

        return response()->json($data);
    }

    public function predictive(): JsonResponse
    {
        return response()->json([
            'alerts' => [],
            'stats' => [
                'totalDepts' => SurveyResponse::query()->distinct('department')->count('department'),
                'activeWarnings' => 0,
                'healthIndex' => (int) round(SurveyResponse::query()->avg('overallScore') ?? 100),
                'totalResponsesAnalyzed' => SurveyResponse::query()->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $response = SurveyResponse::query()->find($id);

        if (! $response || ($user?->tenantId && $response->tenantId !== $user->tenantId)) {
            return response()->json(['error' => 'الاستجابة غير موجودة'], 404);
        }

        return response()->json($this->transformResponse($response));
    }

    private function validateDepartment(string $department): void
    {
        $settings = Settings::query()->first();
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

    private function calculateOverallScore(Survey $survey, array $answers): int
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

                if ($question->type === 'yes_no' && is_bool($value)) {
                    $totalScore += $value ? 5 : 0;
                    $maxScore += 5;
                }
            }
        }

        if ($maxScore === 0) {
            return 0;
        }

        return (int) min(100, max(0, round(($totalScore / $maxScore) * 100)));
    }

    private function buildResponseQuery(Request $request, $user, bool $includeSearchFilters = true): Builder
    {
        return SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department),
                fn ($query) => $query->when($request->query('department') && $request->query('department') !== 'all', fn ($q) => $q->where('department', $request->query('department'))),
            )
            ->when($includeSearchFilters && $request->query('search'), function ($query) use ($request): void {
                $search = $request->query('search');
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
                    if ($request->query('startDate')) {
                        $query->where('submittedAt', '>=', $request->query('startDate'));
                    }
                    if ($request->query('endDate')) {
                        $query->where('submittedAt', '<=', Carbon::parse($request->query('endDate'))->endOfDay());
                    }
                }
            })
            ->when($request->query('startDate'), fn ($query) => $query->where('submittedAt', '>=', $request->query('startDate')))
            ->when($request->query('endDate'), fn ($query) => $query->where('submittedAt', '<=', Carbon::parse($request->query('endDate'))->endOfDay()));
    }

    private function transformResponse(SurveyResponse $response): array
    {
        return [
            'id' => $response->id,
            'surveyId' => $response->surveyId,
            'answers' => $response->answers,
            'patientInfo' => [
                'name' => $response->patientName ?? '',
                'phone' => $response->patientPhone ?? '',
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

    private function hourlyStats(Builder $query): array
    {
        $rows = $query
            ->selectRaw('HOUR(submittedAt) as hour_number, AVG(overallScore) as score, COUNT(*) as count')
            ->groupBy('hour_number')
            ->get()
            ->keyBy('hour_number');

        return collect(range(0, 23))
            ->map(fn ($hour) => [
                'hour' => "{$hour}:00",
                'score' => (int) round($rows[$hour]->score ?? 0),
                'count' => (int) ($rows[$hour]->count ?? 0),
            ])
            ->all();
    }

    private function dayStats(Builder $query): array
    {
        $rows = $query
            ->selectRaw('DAYOFWEEK(submittedAt) as day_number, AVG(overallScore) as score, COUNT(*) as count')
            ->groupBy('day_number')
            ->get()
            ->keyBy('day_number');

        return collect(['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'])
            ->map(fn ($day, $index) => [
                'day' => $day,
                'score' => (int) round($rows[$index + 1]->score ?? 0),
                'count' => (int) ($rows[$index + 1]->count ?? 0),
            ])
            ->all();
    }

    private function trendData(Builder $query): array
    {
        $responses = $query
            ->where('submittedAt', '>=', now()->subDays(84))
            ->get(['overallScore', 'submittedAt']);
        $now = now();

        return collect(range(11, 0))
            ->map(function ($weeksAgo) use ($responses, $now): array {
                $weekEnd = $now->copy()->subWeeks($weeksAgo);
                $weekStart = $weekEnd->copy()->subWeek();
                $weekResponses = $responses->filter(fn ($response) => $response->submittedAt >= $weekStart && $response->submittedAt < $weekEnd);

                return [
                    'date' => $weekEnd->format('j/n'),
                    'score' => $weekResponses->isNotEmpty() ? (int) round($weekResponses->avg('overallScore')) : 0,
                    'count' => $weekResponses->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function calculateNps(array $responseIds): int
    {
        if (empty($responseIds)) {
            return 0;
        }

        $answers = SurveyAnswer::query()
            ->whereIn('responseId', $responseIds)
            ->whereHas('question', fn ($query) => $query->where('type', 'nps'))
            ->get();

        if ($answers->isEmpty()) {
            return 0;
        }

        $promoters = 0;
        $detractors = 0;
        $total = $answers->count();

        foreach ($answers as $answer) {
            $value = (int) $answer->value;
            if ($value >= 9) {
                $promoters++;
            } elseif ($value <= 6) {
                $detractors++;
            }
        }

        return (int) round((($promoters - $detractors) / $total) * 100);
    }

    private function categoryScores(array $responseIds): array
    {
        if (count($responseIds) === 0) {
            return [];
        }

        $answers = SurveyAnswer::query()
            ->whereIn('responseId', $responseIds)
            ->whereHas('question', fn ($query) => $query->whereIn('type', ['stars', 'emoji', 'rating', 'yes_no']))
            ->with(['question.section'])
            ->get();

        $groups = [];
        foreach ($answers as $answer) {
            $question = $answer->question;
            if (! $question) {
                continue;
            }

            $category = $question->category ?: ($question->section?->title ?? null);
            if (! $category) {
                continue;
            }

            $rawValue = $answer->value;
            if ($question->type === 'yes_no') {
                $value = in_array($rawValue, ['true', 'yes', '1', '5'], true) ? 5 : 0;
            } elseif (is_numeric($rawValue)) {
                $value = (float) $rawValue;
            } else {
                continue;
            }

            $groups[$category] ??= ['sum' => 0, 'count' => 0];
            $groups[$category]['sum'] += min(5, max(0, $value));
            $groups[$category]['count']++;
        }

        return collect($groups)
            ->map(fn ($data, $category) => [
                'category' => $category,
                'score' => $data['count'] > 0 ? (int) round(($data['sum'] / ($data['count'] * 5)) * 100) : 0,
            ])
            ->values()
            ->all();
    }
}
