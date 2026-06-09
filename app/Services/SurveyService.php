<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Support\DashboardAnalyticsCache;
use App\Support\DashboardBadgeCache;
use App\Traits\ResolvesAuditTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyService
{
    use ResolvesAuditTarget;

    public function index(Request $request): Collection
    {
        $user = auth('api')->user();
        $tenantId = $user?->tenantId;

        return Survey::query()
            ->when($tenantId, fn ($query) => $query->where('tenantId', $tenantId))
            ->with(['sections.questions'])
            ->withCount('responses')
            ->orderByDesc('createdAt')
            ->get();
    }

    public function indexPublic(Request $request): Collection
    {
        $tenantId = $this->resolvePublicTenantId($request);

        return Survey::query()
            ->where('isActive', true)
            ->when($tenantId, fn ($query) => $query->where('tenantId', $tenantId))
            ->with(['sections.questions'])
            ->orderByDesc('createdAt')
            ->get();
    }

    public function store(array $payload, $user): Survey
    {
        return DB::transaction(function () use ($payload, $user): Survey {
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

            $this->createSectionsAndQuestions($survey->id, $payload['sections'] ?? []);

            return $survey->load(['sections.questions'])->loadCount('responses');
        });
    }

    public function update(string $id, array $payload, $user): Survey
    {
        $survey = $this->resolveAuditTarget(request(), 'audit_pre_target_survey', fn () => Survey::query()->find($id));

        if (! $survey || ($user?->tenantId && $survey->tenantId !== $user->tenantId)) {
            throw new \RuntimeException('الاستبيان غير موجود');
        }

        return DB::transaction(function () use ($survey, $payload): Survey {
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

            $this->updateSectionsAndQuestions($survey->id, $payload['sections'] ?? [], $protectedSectionIds);

            return $survey->fresh(['sections.questions'])->loadCount('responses');
        });
    }

    public function destroy(string $id, $user): void
    {
        $survey = $this->resolveAuditTarget(request(), 'audit_pre_target_survey', fn () => Survey::query()->find($id));

        if (! $survey || ($user?->tenantId && $survey->tenantId !== $user->tenantId)) {
            throw new \RuntimeException('الاستبيان غير موجود');
        }

        $responseCount = $survey->responses()->count();
        if ($responseCount > 0 && $user?->role !== 'super_admin') {
            throw new \RuntimeException("لا يمكن حذف هذا الاستبيان لأنه مرتبط بـ {$responseCount} استجابة. الحذف متاح فقط للمدير العام.");
        }

        DB::transaction(function () use ($survey): void {
            $responseIds = $survey->responses()->pluck('id');
            if ($responseIds->isNotEmpty()) {
                SurveyAnswer::query()->whereIn('responseId', $responseIds)->delete();
                Ticket::query()->whereIn('responseId', $responseIds)->delete();
            }

            $survey->responses()->delete();

            $sectionIds = $survey->sections()->pluck('id');
            if ($sectionIds->isNotEmpty()) {
                SurveyQuestion::query()->whereIn('sectionId', $sectionIds)->delete();
            }

            $survey->sections()->delete();
            $survey->delete();
        });

        DashboardAnalyticsCache::bump();
        DashboardBadgeCache::forgetPredictive($user);
    }

    public function transformSurvey(Survey $survey): array
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

    public function findPublicActive(Request $request, string $surveyId): Survey
    {
        $tenantId = $this->resolvePublicTenantId($request);

        return Survey::query()
            ->where('isActive', true)
            ->when($tenantId, fn ($query) => $query->where('tenantId', $tenantId))
            ->with([
                'sections' => fn ($query) => $query->orderBy('sortOrder'),
                'sections.questions' => fn ($query) => $query->orderBy('sortOrder'),
            ])
            ->findOrFail($surveyId);
    }

    // ─── Private Helpers ───

    private function createSectionsAndQuestions(string $surveyId, array $sections): void
    {
        foreach ($sections as $sectionIndex => $sectionPayload) {
            $section = SurveySection::query()->create([
                'surveyId' => $surveyId,
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
    }

    private function updateSectionsAndQuestions(string $surveyId, array $sections, array $protectedSectionIds): void
    {
        foreach ($sections as $sectionIndex => $sectionPayload) {
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
                'surveyId' => $surveyId,
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
    }

    private function resolvePublicTenantId(Request $request): ?string
    {
        $configuredTenantId = trim((string) config('medsurvey.public_tenant_id', ''));
        if ($configuredTenantId !== '') {
            return $configuredTenantId;
        }

        $requestedTenantId = $request->query('tenantId');
        if (is_string($requestedTenantId) && trim($requestedTenantId) !== '') {
            return trim($requestedTenantId);
        }

        return null;
    }
}
