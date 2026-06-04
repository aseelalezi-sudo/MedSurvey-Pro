<?php

namespace App\Http\Controllers\Web;

use App\Models\Survey;
use App\Services\SettingsService;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SurveyController
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly SurveyService $surveyService,
    ) {}

    public function surveys(Request $request): View
    {
        $user = $request->user();

        $surveys = Survey::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->with(['sections.questions'])
            ->withCount('responses')
            ->orderByDesc('createdAt')
            ->paginate(15);

        $settings = $this->settingsService->getAll($user?->tenantId);
        $departments = collect($settings['departments'] ?? [])
            ->filter(fn ($department) => $department['isActive'] ?? true)
            ->pluck('name')
            ->values()
            ->all();

        return view('dashboard.surveys', compact('surveys', 'departments'));
    }

    public function storeSurvey(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedSurveyPayload($request);
        $user = $request->user();
        $survey = $this->surveyService->store($payload, $user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'survey' => $survey]);
        }

        return redirect()->back()->with('success', 'تم إنشاء الاستبيان بنجاح');
    }

    public function duplicateSurvey(string $id, Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $original = Survey::with(['sections.questions'])->find($id);

        if (! $original || ($user?->tenantId && $original->tenantId !== $user->tenantId)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Survey not found.'], 404);
            }

            return redirect()->back()->with('error', 'الاستبيان غير موجود.');
        }

        $payload = $original->toArray();
        $payload['title'] = $payload['title'].' - نسخة';

        unset($payload['id'], $payload['createdAt'], $payload['updatedAt']);

        if (! empty($payload['sections'])) {
            foreach ($payload['sections'] as &$section) {
                unset($section['id'], $section['surveyId'], $section['createdAt'], $section['updatedAt']);
                if (! empty($section['questions'])) {
                    foreach ($section['questions'] as &$question) {
                        unset($question['id'], $question['sectionId'], $question['createdAt'], $question['updatedAt']);
                    }
                }
            }
        }

        try {
            $survey = $this->surveyService->store($payload, $user);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'survey' => $survey]);
            }

            return redirect()->back()->with('success', 'تم تكرار الاستبيان بنجاح.');
        } catch (Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء تكرار الاستبيان: '.$e->getMessage());
        }
    }

    public function updateSurvey(string $id, Request $request): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedSurveyPayload($request);
        $user = $request->user();

        try {
            $survey = $this->surveyService->update($id, $payload, $user);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'survey' => $survey]);
            }

            return redirect()->back()->with('success', 'تم تعديل الاستبيان بنجاح');
        } catch (Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroySurvey(string $id, Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            $this->surveyService->destroy($id, $user);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', 'تم حذف الاستبيان بنجاح');
        } catch (Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            }

            return redirect()->back()->with('error', 'فشل حذف الاستبيان: '.$e->getMessage());
        }
    }

    public function toggleSurvey(string $id, Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $survey = Survey::query()->findOrFail($id);

        if ($user?->tenantId && $survey->tenantId !== $user->tenantId) {
            abort(404);
        }

        $survey->update(['isActive' => ! $survey->isActive]);
        $updated = $survey->fresh(['sections.questions'])->loadCount('responses');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'survey' => $updated]);
        }

        return redirect()->back()->with('success', 'تم تعديل حالة الاستبيان بنجاح');
    }

    private function validatedSurveyPayload(Request $request): array
    {
        $payload = $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'requireName' => ['sometimes', 'boolean'],
            'requirePhone' => ['sometimes', 'boolean'],
            'assignedDepartments' => ['nullable', 'array'],
            'assignedDepartments.*' => ['string'],
            'tips' => ['nullable', 'array'],
            'tips.*' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'string'],
            'sections.*.title' => ['nullable', 'string'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.icon' => ['nullable', 'string'],
            'sections.*.questions' => ['nullable', 'array'],
            'sections.*.questions.*.id' => ['nullable', 'string'],
            'sections.*.questions.*.type' => ['required', 'string'],
            'sections.*.questions.*.title' => ['required', 'string'],
            'sections.*.questions.*.description' => ['nullable', 'string'],
            'sections.*.questions.*.required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.category' => ['nullable', 'string'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.followUp' => ['nullable', 'array'],
        ]);

        if (isset($payload['tips'])) {
            $payload['tips'] = array_values(array_filter($payload['tips'], fn ($tip) => ! is_null($tip) && trim($tip) !== ''));
        }

        return $payload;
    }
}
