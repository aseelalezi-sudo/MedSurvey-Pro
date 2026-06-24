<?php

namespace App\Http\Controllers\Web;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Queries\ResponseFilterQuery;
use App\Services\PredictiveService;
use App\Services\ResponseService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseController
{
    private const PRINT_EXPORT_LIMIT = 1000;

    private const CSV_EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly PredictiveService $predictiveService,
        private readonly ResponseService $responseService,
        private readonly SettingsService $settingsService,
    ) {}

    public function responses(Request $request)
    {
        if ($request->query('export') === 'csv') {
            return $this->exportResponses($request);
        }

        $user = $request->user();
        $filter = ResponseFilterQuery::make($request, $user);
        $query = $filter->builder();

        if ($request->query('count_only') === '1') {
            return response()->json(['count' => $query->count()]);
        }

        if ($request->query('export') === 'print') {
            $queryForPrint = clone $query;
            $allResponses = $filter->applySorting($queryForPrint->with('survey'))
                ->limit(self::PRINT_EXPORT_LIMIT)
                ->get();
            $averageScore = $allResponses->avg('overallScore') ?? 0;

            $settings = $this->settingsService->getAll($user?->tenantId);
            $hospitalName = $settings['hospital']['name'] ?? 'MedSurvey Pro';
            $hospitalNameAr = $settings['hospital']['nameAr'] ?? $hospitalName;
            $npsScore = $this->predictiveService->getNpsScoreForResponses($allResponses);

            $hasDateFilter = $request->query('dateFilter') && $request->query('dateFilter') !== 'all';
            $baseQueryForRate = $this->responseRateBaseQuery($request);

            if ($hasDateFilter) {
                $currentStartDate = $allResponses->min('submittedAt');
                $currentEndDate = $allResponses->max('submittedAt');
                $dateDiff = $currentStartDate && $currentEndDate ? $currentEndDate->diffInDays($currentStartDate) : 30;
                $periodDays = max($dateDiff + 1, 1);

                $previousCount = (clone $baseQueryForRate)
                    ->whereBetween('submittedAt', [
                        $currentStartDate ? $currentStartDate->copy()->subDays($periodDays) : now()->subDays(60),
                        $currentStartDate ?: now()->subDays(30),
                    ])
                    ->count();
                $currentCount = $allResponses->count();
            } else {
                $currentCount = (clone $baseQueryForRate)
                    ->where('submittedAt', '>=', now()->subDays(30))
                    ->count();
                $previousCount = (clone $baseQueryForRate)
                    ->whereBetween('submittedAt', [now()->subDays(60), now()->subDays(30)])
                    ->count();
            }

            if ($previousCount > 0) {
                $responseRate = (int) round(($currentCount / $previousCount) * 100);
            } elseif ($currentCount > 0) {
                $responseRate = 100;
            } else {
                $responseRate = 0;
            }

            return view('dashboard.responses-print', [
                'responses' => $allResponses,
                'averageScore' => $averageScore,
                'isAr' => app()->getLocale() === 'ar',
                'hospitalName' => app()->getLocale() === 'ar' ? $hospitalNameAr : $hospitalName,
                'hospitalLogo' => $settings['hospital']['logo'] ?? '',
                'npsScore' => $npsScore,
                'responseRate' => $responseRate,
            ]);
        }

        $responses = $filter->applySorting($query->with('survey'))
            ->paginate(20)
            ->withQueryString();

        $averageScore = (clone $query)->avg('overallScore') ?? 0;
        $departments = SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->select('department')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('dashboard.responses', compact('responses', 'departments', 'averageScore'));
    }

    public function filterResponses(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = ResponseFilterQuery::make($request, $user);
        $query = $filter->builder();

        $responses = $filter->applySorting($query->with('survey'))
            ->paginate(20)
            ->withQueryString();
        $averageScore = (clone $query)->avg('overallScore') ?? 0;

        $isAr = app()->getLocale() === 'ar';
        $isRtl = $isAr;
        $html = view('dashboard.partials._response-cards', compact('responses', 'isAr', 'isRtl'))->render();
        $pagination = $responses->links()->toHtml();

        return response()->json([
            'html' => $html,
            'pagination' => $pagination,
            'total' => $responses->total(),
            'averageScore' => round((float) $averageScore, 1),
        ]);
    }

    public function exportResponses(Request $request)
    {
        abort_unless($request->user()?->can('responses.export') === true, 403);

        $user = $request->user();
        $filter = ResponseFilterQuery::make($request, $user);
        $query = $filter->builder();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=responses_export_'.now()->format('Y_m_d_H_i').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($filter, $query): void {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['ID', 'اسم المريض', 'رقم الجوال', 'العمر', 'الجنس', 'القسم', 'نوع الزيارة', 'معدل الرضا', 'تاريخ التقديم']);

            $filter->applySorting($query)->limit(self::CSV_EXPORT_LIMIT)->cursor()->each(function (SurveyResponse $response) use ($file): void {
                fputcsv($file, [
                    $response->id,
                    $response->patientName ?: 'غير محدد',
                    $response->patientPhone ?: 'غير محدد',
                    $response->ageGroup ?: 'غير محدد',
                    $response->gender ?: 'غير محدد',
                    $response->department ?: 'غير محدد',
                    $response->visitType ?: 'غير محدد',
                    $response->overallScore.'%',
                    $response->submittedAt ? $response->submittedAt->format('Y-m-d H:i:s') : 'غير محدد',
                ]);
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function showResponseJson(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $response = SurveyResponse::query()
            ->forUserAccess($user)
            ->find($id);

        if (! $response) {
            return response()->json(['error' => 'الاستجابة غير موجودة'], 404);
        }

        $survey = Survey::with(['sections.questions'])->find($response->surveyId);

        return response()->json([
            'response' => $this->responseService->transformResponse($response),
            'survey' => $survey,
        ]);
    }

    private function responseRateBaseQuery(Request $request)
    {
        $user = $request->user();

        return SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department)
            )
            ->when(
                $user?->role === 'staff',
                fn ($query) => $query->where('submittedAt', '>=', now()->startOfDay())
            )
            ->when($request->query('department'), fn ($query) => $query->where('department', $request->query('department')))
            ->when($request->query('score'), function ($query, $score): void {
                if ($score === 'excellent') {
                    $query->where('overallScore', '>=', 85);
                } elseif ($score === 'good') {
                    $query->whereBetween('overallScore', [70, 84]);
                } elseif ($score === 'average') {
                    $query->whereBetween('overallScore', [50, 69]);
                } elseif ($score === 'poor') {
                    $query->where('overallScore', '<', 50);
                }
            })
            ->when($request->query('hasName') === '1', fn ($query) => $query->whereNotNull('patientName')->where('patientName', '<>', ''))
            ->when($request->query('hasPhone') === '1', fn ($query) => $query->whereNotNull('patientPhone')->where('patientPhone', '<>', ''))
            ->when($request->query('gender') && $request->query('gender') !== 'all', function ($query) use ($request): void {
                $this->applyGenderFilter($query, (string) $request->query('gender'));
            });
    }

    private function applyGenderFilter($query, string $gender): void
    {
        $normalized = strtolower(trim($gender));

        if ($normalized === 'male') {
            $query->where(function ($nested): void {
                $nested->whereRaw('LOWER(gender) = ?', ['male'])
                    ->orWhereIn('gender', ['ذكر']);
            });

            return;
        }

        if ($normalized === 'female') {
            $query->where(function ($nested): void {
                $nested->whereRaw('LOWER(gender) = ?', ['female'])
                    ->orWhereIn('gender', ['أنثى', 'انثى']);
            });

            return;
        }

        $query->where('gender', $gender);
    }
}
