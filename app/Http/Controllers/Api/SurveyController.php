<?php

namespace App\Http\Controllers\Api;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController
{
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->query('active') === 'true';
        $tenantId = $this->resolvePublicTenantId($request);

        $surveys = Survey::query()
            ->when($activeOnly, fn ($query) => $query->where('isActive', true))
            ->when($tenantId, fn ($query) => $query->where('tenantId', $tenantId))
            ->with(['sections.questions'])
            ->withCount('responses')
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn (Survey $survey) => $this->transformSurvey($survey));

        return response()->json($surveys);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateSurvey($request);
        $user = auth('api')->user();

        $survey = DB::transaction(function () use ($payload, $user): Survey {
            $survey = Survey::query()->create([
                'title' => $payload['title'],
                'description' => $payload['description'] ?? '',
                'isActive' => $payload['isActive'] ?? true,
                'requireName' => $payload['requireName'] ?? false,
                'requirePhone' => $payload['requirePhone'] ?? false,
                'assignedDepartments' => isset($payload['assignedDepartments'])
                    ? array_values(array_unique($payload['assignedDepartments']))
                    : null,
                'tips' => $payload['tips'] ?? null,
                'tenantId' => $user?->tenantId,
            ]);

            foreach (($payload['sections'] ?? []) as $sectionIndex => $sectionPayload) {
                $section = SurveySection::query()->create([
                    'surveyId' => $survey->id,
                    'title' => $sectionPayload['title'] ?? '',
                    'description' => $sectionPayload['description'] ?? '',
                    'icon' => $sectionPayload['icon'] ?? 'clipboard-check',
                    'sortOrder' => $sectionIndex,
                ]);

                foreach (($sectionPayload['questions'] ?? []) as $questionIndex => $questionPayload) {
                    SurveyQuestion::query()->create([
                        'sectionId' => $section->id,
                        'type' => $questionPayload['type'] ?? 'stars',
                        'title' => $questionPayload['title'] ?? '',
                        'description' => $questionPayload['description'] ?? null,
                        'required' => $questionPayload['required'] ?? false,
                        'category' => $questionPayload['category'] ?? '',
                        'options' => $questionPayload['options'] ?? null,
                        'followUp' => $questionPayload['followUp'] ?? null,
                        'sortOrder' => $questionIndex,
                    ]);
                }
            }

            return $survey->load(['sections.questions'])->loadCount('responses');
        });

        return response()->json($this->transformSurvey($survey), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $this->validateSurvey($request);
        $user = auth('api')->user();

        $survey = Survey::query()->find($id);
        if (! $survey || ($user?->tenantId && $survey->tenantId !== $user->tenantId)) {
            return response()->json(['error' => 'الاستبيان غير موجود'], 404);
        }

        $survey = DB::transaction(function () use ($survey, $payload): Survey {
            $existingSections = SurveySection::query()
                ->where('surveyId', $survey->id)
                ->with(['questions.surveyAnswers' => fn ($query) => $query->limit(1)])
                ->get();

            $protectedSectionIds = $existingSections
                ->filter(fn (SurveySection $section) => $section->questions->contains(fn ($question) => $question->surveyAnswers->isNotEmpty()))
                ->pluck('id')
                ->all();

            foreach ($existingSections as $section) {
                if (! in_array($section->id, $protectedSectionIds, true)) {
                    SurveyQuestion::query()->where('sectionId', $section->id)->delete();
                    $section->delete();
                }
            }

            $survey->update([
                'title' => $payload['title'],
                'description' => $payload['description'] ?? '',
                'isActive' => $payload['isActive'] ?? true,
                'requireName' => $payload['requireName'] ?? false,
                'requirePhone' => $payload['requirePhone'] ?? false,
                'assignedDepartments' => isset($payload['assignedDepartments'])
                    ? array_values(array_unique($payload['assignedDepartments']))
                    : null,
                'tips' => $payload['tips'] ?? null,
            ]);

            foreach (($payload['sections'] ?? []) as $sectionIndex => $sectionPayload) {
                $sectionId = $sectionPayload['id'] ?? null;

                if ($sectionId && in_array($sectionId, $protectedSectionIds, true)) {
                    SurveySection::query()->whereKey($sectionId)->update([
                        'title' => $sectionPayload['title'] ?? '',
                        'description' => $sectionPayload['description'] ?? '',
                        'icon' => $sectionPayload['icon'] ?? 'clipboard-check',
                        'sortOrder' => $sectionIndex,
                    ]);
                    continue;
                }

                $section = SurveySection::query()->create([
                    'id' => $sectionId && ! str_starts_with($sectionId, 'section-') ? $sectionId : null,
                    'surveyId' => $survey->id,
                    'title' => $sectionPayload['title'] ?? '',
                    'description' => $sectionPayload['description'] ?? '',
                    'icon' => $sectionPayload['icon'] ?? 'clipboard-check',
                    'sortOrder' => $sectionIndex,
                ]);

                foreach (($sectionPayload['questions'] ?? []) as $questionIndex => $questionPayload) {
                    SurveyQuestion::query()->create([
                        'sectionId' => $section->id,
                        'type' => $questionPayload['type'] ?? 'stars',
                        'title' => $questionPayload['title'] ?? '',
                        'description' => $questionPayload['description'] ?? null,
                        'required' => $questionPayload['required'] ?? false,
                        'category' => $questionPayload['category'] ?? '',
                        'options' => $questionPayload['options'] ?? null,
                        'followUp' => $questionPayload['followUp'] ?? null,
                        'sortOrder' => $questionIndex,
                    ]);
                }
            }

            return $survey->fresh(['sections.questions'])->loadCount('responses');
        });

        return response()->json($this->transformSurvey($survey));
    }

    public function destroy(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $survey = Survey::query()->find($id);

        if (! $survey || ($user?->tenantId && $survey->tenantId !== $user->tenantId)) {
            return response()->json(['error' => 'الاستبيان غير موجود'], 404);
        }

        $survey->delete();

        return response()->json(['message' => 'تم حذف الاستبيان بنجاح']);
    }

    private function validateSurvey(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'requireName' => ['sometimes', 'boolean'],
            'requirePhone' => ['sometimes', 'boolean'],
            'assignedDepartments' => ['nullable', 'array'],
            'assignedDepartments.*' => ['string'],
            'tips' => ['nullable', 'array'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'string'],
            'sections.*.title' => ['nullable', 'string'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.icon' => ['nullable', 'string'],
            'sections.*.questions' => ['nullable', 'array'],
            'sections.*.questions.*.type' => ['nullable', 'string'],
            'sections.*.questions.*.title' => ['nullable', 'string'],
            'sections.*.questions.*.description' => ['nullable', 'string'],
            'sections.*.questions.*.required' => ['nullable', 'boolean'],
            'sections.*.questions.*.category' => ['nullable', 'string'],
            'sections.*.questions.*.options' => ['nullable'],
            'sections.*.questions.*.followUp' => ['nullable'],
        ]);
    }

    private function resolvePublicTenantId(Request $request): ?string
    {
        $configuredTenantId = trim((string) env('PUBLIC_TENANT_ID', ''));
        if ($configuredTenantId !== '') {
            return $configuredTenantId;
        }

        $requestedTenantId = $request->query('tenantId');
        if (is_string($requestedTenantId) && trim($requestedTenantId) !== '') {
            return trim($requestedTenantId);
        }

        return null;
    }

    private function transformSurvey(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->description,
            'isActive' => $survey->isActive,
            'requireName' => $survey->requireName,
            'requirePhone' => $survey->requirePhone,
            'assignedDepartments' => $survey->assignedDepartments,
            'tips' => $survey->tips,
            'createdAt' => optional($survey->createdAt)->toISOString(),
            'responseCount' => $survey->responses_count ?? 0,
            'sections' => $survey->sections->map(fn (SurveySection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'icon' => $section->icon,
                'questions' => $section->questions->map(fn (SurveyQuestion $question) => [
                    'id' => $question->id,
                    'type' => $question->type,
                    'title' => $question->title,
                    'description' => $question->description,
                    'required' => $question->required,
                    'category' => $question->category,
                    'options' => $question->options,
                    'followUp' => $question->followUp,
                ])->values(),
            ])->values(),
        ];
    }
}

