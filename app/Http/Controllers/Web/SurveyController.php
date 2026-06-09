<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\StoreSurveyRequest;
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

        $surveysJson = $surveys->getCollection();

        return view('dashboard.surveys.index', compact('surveys', 'departments', 'surveysJson'));
    }

    public function storeSurvey(StoreSurveyRequest $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validatedPayload();
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

    public function updateSurvey(string $id, StoreSurveyRequest $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validatedPayload();
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
}
