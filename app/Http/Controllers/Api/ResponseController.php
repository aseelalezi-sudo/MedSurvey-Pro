<?php

namespace App\Http\Controllers\Api;

use App\Events\SurveySubmitted;
use App\Models\SurveyResponse;
use App\Services\PredictiveService;
use App\Services\ResponseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseController
{
    public function __construct(
        private readonly ResponseService $responseService,
        private readonly PredictiveService $predictiveService,
    ) {}

    public function store(Request $request): JsonResponse
    {
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

        $response = $this->responseService->store($payload);

        event(new SurveySubmitted($response));

        return ApiResponse::created(
            $this->responseService->transformResponse($response)
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if ($request->query('exportAll') === 'true' && ! in_array($user->role, ['super_admin', 'admin', 'unit_manager', 'head_of_department'], true)) {
            return ApiResponse::error('ليس لديك صلاحية لتصدير البيانات', 403);
        }

        $query = $this->responseService->buildResponseQuery($request, $user);
        $sortBy = in_array($request->query('sortBy'), ['submittedAt', 'overallScore', 'department', 'patientName', 'patientPhone'], true)
            ? $request->query('sortBy')
            : 'submittedAt';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        if ($request->query('exportAll') === 'true') {
            $total = (clone $query)->count();
            if ($total > 5000) {
                return ApiResponse::error(
                    "حجم البيانات المطلوب تصديرها ضخم جداً ({$total} سجل). الحد الأقصى المسموح به هو 5000 سجل.",
                    400
                );
            }

            $responses = $query->orderBy($sortBy, $order)->get();

            return ApiResponse::paginated(
                $responses->map(fn (SurveyResponse $response) => $this->responseService->transformResponse($response))->values(),
                $responses->count(),
                1,
                $responses->count()
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $total = (clone $query)->count();
        $average = (clone $query)->avg('overallScore');

        $responses = $query
            ->orderBy($sortBy, $order)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $responses->map(fn (SurveyResponse $response) => $this->responseService->transformResponse($response))->values(),
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
            return ApiResponse::error('ليس لديك صلاحية لتصدير البيانات', 403);
        }

        $query = $this->responseService->buildResponseQuery($request, $user);

        $total = (clone $query)->count();
        if ($total > 5000) {
            return ApiResponse::error(
                "حجم البيانات المطلوب تصديرها ضخم جداً ({$total} سجل). الحد الأقصى المسموح به هو 5000 سجل.",
                400
            );
        }

        $fileName = 'responses_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'المعرف', 'القسم', 'اسم المريض', 'رقم الهاتف',
                'الفئة العمرية', 'الجنس', 'نوع الزيارة',
                'التقييم العام', 'تاريخ الإرسال',
            ]);

            $query->orderBy('submittedAt', 'desc')->chunk(500, function ($responses) use ($handle) {
                foreach ($responses as $response) {
                    fputcsv($handle, [
                        $response->id,
                        $response->department,
                        $response->patientName ?? 'غير محدد',
                        $response->patientPhone ?? 'غير محدد',
                        $response->ageGroup ?? 'غير محدد',
                        $response->gender === 'male' ? 'ذكر' : ($response->gender === 'female' ? 'أنثى' : 'غير محدد'),
                        $response->visitType === 'inpatient' ? 'تنويم' : ($response->visitType === 'outpatient' ? 'عيادات خارجية' : ($response->visitType === 'emergency' ? 'طوارئ' : 'غير محدد')),
                        $response->overallScore.'%',
                        $response->submittedAt->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $query = $this->responseService->buildResponseQuery($request, $user, includeSearchFilters: false);

        $data = $this->predictiveService->getStats($query);

        return response()->json($data);
    }

    public function predictive(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $query = $this->responseService->buildResponseQuery($request, $user, false);

        $result = $this->predictiveService->getAlerts($query);

        return response()->json($result);
    }

    public function show(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $response = SurveyResponse::query()->find($id);

        if (! $response || ($user?->tenantId && $response->tenantId !== $user->tenantId)) {
            return ApiResponse::error('الاستجابة غير موجودة', 404);
        }

        return response()->json($this->responseService->transformResponse($response));
    }
}
