@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'إدارة الاستبيانات' : 'Surveys Management') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isRtl = app()->getLocale() === 'ar';
    $isAr = $isRtl;
    $user = auth()->user();
    $isSuperAdmin = $user->role === 'super_admin';
    $compactCount = function (int $count): string {
        if ($count >= 1000000) {
            return rtrim(rtrim(number_format($count / 1000000, $count >= 10000000 ? 0 : 1), '0'), '.').'M';
        }

        if ($count >= 1000) {
            return rtrim(rtrim(number_format($count / 1000, $count >= 10000 ? 0 : 1), '0'), '.').'K';
        }

        return (string) $count;
    };
  @endphp

  <div x-data="surveyComponent()" class="space-y-6 animate-fade-in font-cairo text-start">
    <!-- Toast Notification -->
    <div x-show="toast.show" x-transition.opacity.duration.300ms class="fixed top-4 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-2xl shadow-xl border font-bold text-sm flex items-center gap-3 transition-all"
         :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:border-emerald-800' : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-800'" style="display: none;">
      <i data-lucide="check-circle-2" x-show="toast.type === 'success'" class="w-5 h-5"></i>
      <i data-lucide="alert-circle" x-show="toast.type === 'error'" class="w-5 h-5"></i>
      <span x-text="toast.message"></span>
    </div>

    <!-- Header -->
    <div class="max-w-7xl mx-auto py-6">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 border-b border-gray-100 dark:border-slate-800/80 pb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-linear-to-br from-teal-500 to-teal-600 dark:from-teal-600 dark:to-teal-800 rounded-xl flex items-center justify-center shadow-lg shadow-teal-100 dark:shadow-none">
            <i data-lucide="clipboard-list" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <h2 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white leading-tight">{{ $isAr ? 'إدارة وتصميم الاستبيانات' : 'Surveys Management' }}</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">{{ $isAr ? 'قم بإنشاء وتعديل استبيانات رضا المرضى وتخصيصها للأقسام الطبية' : 'Create and manage patient satisfaction surveys' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
              @click="openCreate()"
              class="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-3 bg-linear-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
            >
              <i data-lucide="plus" class="w-5 h-5"></i>
              {{ $isAr ? 'إضافة استبيان جديد' : 'Create New Survey' }}
            </button>
        </div>
      </div>

      <!-- Empty State -->
      @if($surveys->isEmpty())
        <div class="text-center py-20 bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm">
          <div class="w-20 h-20 bg-teal-50 dark:bg-teal-950/20 text-teal-600 dark:text-teal-400 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
            <i data-lucide="clipboard-list" class="w-10 h-10"></i>
          </div>
          <h3 class="text-lg font-black text-gray-800 dark:text-white mb-2">{{ $isAr ? 'لا توجد استبيانات مضافة حالياً' : 'No surveys added yet' }}</h3>
          <p class="text-gray-500 dark:text-slate-400 max-w-sm mx-auto mb-6 text-sm">{{ $isAr ? 'ابدأ بإنشاء استبيانك الأول لتتمكن من جمع وتقييم آراء المرضى وتطوير أداء عيادات المستشفى.' : 'Start creating your first survey to collect patient feedback.' }}</p>
          <button @click="openCreate()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-sm transition-colors cursor-pointer">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ $isAr ? 'إضافة استبيان جديد' : 'Create New Survey' }}
          </button>
        </div>
      @endif

      <!-- Grid List -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($surveys as $survey)
          @php
            $sectionsCount = $survey->sections->count();
            $questionsCount = $survey->sections->sum(fn($s) => $s->questions->count());
            $responsesCount = $survey->responses_count;
          @endphp
          <div class="group bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800/80 shadow-md hover:shadow-2xl hover:border-teal-500/20 dark:hover:border-teal-500/30 transition-all duration-300 overflow-hidden flex flex-col text-start animate-fade-in">
            <!-- Card Header -->
            <div class="p-6 text-white relative overflow-hidden text-start {{ $survey->isActive ? 'bg-linear-to-br from-teal-500 to-emerald-600' : 'bg-linear-to-br from-slate-400 to-slate-500 dark:from-slate-700 dark:to-slate-800' }}">
              <div class="absolute inset-0 opacity-10">
                <div class="absolute -top-10 -left-10 w-40 h-40 bg-white rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-32 h-32 bg-white rounded-full"></div>
              </div>

              <div class="relative">
                <div class="flex items-center justify-between mb-4">
                  <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i data-lucide="clipboard-list" class="w-6 h-6 text-white"></i>
                  </div>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black {{ $survey->isActive ? 'bg-emerald-500/20 border border-emerald-400/30 text-emerald-100' : 'bg-white/20 border border-white/25 text-white/90' }}">
                    {{ $survey->isActive ? ($isAr ? 'نشط' : 'Active') : ($isAr ? 'غير نشط' : 'Inactive') }}
                  </span>
                </div>

                <h3 class="text-lg font-black mb-1.5 leading-relaxed text-white line-clamp-1">{{ $survey->title }}</h3>
                <p class="text-xs line-clamp-2 min-h-8 {{ $survey->isActive ? 'text-teal-100' : 'text-slate-100/90' }}">
                  {{ $survey->description ?: ($isAr ? 'لا يوجد وصف لهذا الاستبيان.' : 'No description provided.') }}
                </p>
              </div>
            </div>

            <!-- Card Body -->
            <div class="p-5 flex-1 flex flex-col justify-between text-start">
              <!-- Stats -->
              <div class="grid grid-cols-[0.9fr_0.95fr_1.2fr_1.15fr] gap-1.5 text-[11px] font-bold text-gray-500 dark:text-slate-400 mb-4 sm:text-xs">
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ number_format($sectionsCount) }} {{ $isAr ? 'أقسام' : 'Sections' }}">{{ $compactCount($sectionsCount) }} {{ $isAr ? 'أقسام' : 'Sections' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="clipboard-check" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ number_format($questionsCount) }} {{ $isAr ? 'أسئلة' : 'Questions' }}">{{ $compactCount($questionsCount) }} {{ $isAr ? 'أسئلة' : 'Questions' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="users" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ number_format($responsesCount) }} {{ $isAr ? 'استجابة' : 'Responses' }}">{{ $compactCount($responsesCount) }} {{ $isAr ? 'استجابة' : 'Responses' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="calendar" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span>{{ $survey->createdAt ? $survey->createdAt->format($isAr ? 'Y/m/d' : 'M d, Y') : '' }}</span>
                </div>
              </div>

              <!-- Badges -->
              <div class="flex flex-wrap gap-1.5 mb-5 min-h-7">
                @if($survey->requireName)
                  <span class="bg-amber-50 dark:bg-amber-950/20 border border-amber-100/30 dark:border-amber-900/40 text-[10px] font-bold text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="user" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'الاسم مطلوب' : 'Name Required' }}</span>
                  </span>
                @endif
                @if($survey->requirePhone)
                  <span class="bg-orange-50 dark:bg-orange-950/20 border border-orange-100/30 dark:border-orange-900/40 text-[10px] font-bold text-orange-700 dark:text-orange-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="phone" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'الهاتف مطلوب' : 'Phone Required' }}</span>
                  </span>
                @endif

                @php $assignedDepts = $survey->assignedDepartments ?? []; @endphp
                @if(count($assignedDepts) > 0)
                  @foreach(array_slice($assignedDepts, 0, 2) as $dept)
                    <span class="bg-teal-50 dark:bg-teal-950/20 border border-teal-100/30 dark:border-teal-900/40 text-[10px] font-bold text-teal-700 dark:text-teal-400 px-2 py-0.5 rounded-md">
                      {{ $dept }}
                    </span>
                  @endforeach
                  @if(count($assignedDepts) > 2)
                    <span class="bg-gray-100 dark:bg-slate-800 text-[10px] font-bold text-gray-500 dark:text-slate-400 px-2 py-0.5 rounded-md">
                      +{{ count($assignedDepts) - 2 }}
                    </span>
                  @endif
                @else
                  <span class="bg-slate-50 dark:bg-slate-800/30 border border-slate-100/40 dark:border-slate-800 text-[10px] font-bold text-slate-400 px-2.5 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="building-2" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'غير مخصص لأي قسم' : 'Not assigned' }}</span>
                  </span>
                @endif
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 pt-4 border-t border-gray-50 dark:border-slate-800/80 mt-auto">
                <form method="POST" action="{{ route('dashboard.surveys.toggle', $survey->id) }}" class="flex-1">
                  @csrf @method('PATCH')
                  <button type="submit" class="w-full py-2 px-3 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 border border-gray-200/70 dark:border-slate-750 text-xs font-bold text-gray-600 dark:text-slate-300 transition-all cursor-pointer flex items-center justify-center gap-1">
                    <span>{{ $survey->isActive ? ($isAr ? 'إيقاف' : 'Deactivate') : ($isAr ? 'تفعيل' : 'Activate') }}</span>
                  </button>
                </form>
                <button @click="openEdit('{{ $survey->id }}')" class="flex-1 py-2 px-3 rounded-xl bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/30 dark:hover:bg-teal-950/50 border border-teal-100/30 dark:border-teal-900/40 text-xs font-bold text-teal-600 dark:text-teal-400 transition-all cursor-pointer flex items-center justify-center gap-1">
                  <i data-lucide="edit-3" class="w-4 h-4"></i>
                  <span>{{ $isAr ? 'تعديل' : 'Edit' }}</span>
                </button>
                <form method="POST" action="{{ route('dashboard.surveys.duplicate', $survey->id) }}" title="{{ $isAr ? 'تكرار الاستبيان' : 'Duplicate' }}">
                  @csrf
                  <button type="submit" class="py-2 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/20 dark:hover:bg-blue-950/30 border border-blue-100/30 dark:border-blue-900/40 text-xs font-bold text-blue-500 dark:text-blue-400 transition-all cursor-pointer flex items-center justify-center">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                  </button>
                </form>
                @if($isSuperAdmin || $survey->responses_count === 0)
                  <button
                    type="button"
                    @click="openDelete(@js($survey->id), @js($survey->title), @js(route('dashboard.surveys.destroy', $survey->id)), @js($survey->responses_count))"
                    class="py-2 px-2.5 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/30 border border-red-100/30 dark:border-red-900/40 text-xs font-bold text-red-500 dark:text-red-400 transition-all cursor-pointer flex items-center justify-center"
                  >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                  </button>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="mt-8">
        {{ $surveys->links() }}
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      x-show="deleteModal.show"
      x-transition.opacity.duration.200ms
      style="display: none;"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm"
      @keydown.escape.window="closeDelete()"
    >
      <div
        @click.away="closeDelete()"
        x-transition.scale.origin.center
        class="w-full max-w-md rounded-2xl border border-red-100 bg-white p-6 text-start shadow-2xl dark:border-red-950/50 dark:bg-slate-900"
      >
        <div class="mb-5 flex items-start gap-4">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400">
            <i data-lucide="trash-2" class="h-6 w-6"></i>
          </div>
          <div>
            <h3 class="text-lg font-black text-gray-900 dark:text-white">
              {{ $isAr ? 'تأكيد حذف الاستبيان' : 'Confirm survey deletion' }}
            </h3>
            <p class="mt-2 text-sm font-bold leading-7 text-gray-500 dark:text-slate-400">
              {{ $isAr ? 'سيتم حذف الاستبيان بالكامل مع أقسامه وأسئلته. هل تريد المتابعة؟' : 'This will permanently delete the survey with its sections and questions. Do you want to continue?' }}
            </p>
          </div>
        </div>

        <div class="mb-5 rounded-xl border border-red-100 bg-red-50/60 px-4 py-3 text-sm font-black text-red-700 dark:border-red-950/40 dark:bg-red-950/20 dark:text-red-300" x-text="deleteModal.title"></div>

        <div
          x-show="deleteModal.responseCount > 0"
          class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-7 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300"
        >
          <span>{{ $isAr ? 'تنبيه: هذا الاستبيان مرتبط بعدد' : 'Warning: this survey is linked to' }}</span>
          <span class="font-black" x-text="deleteModal.responseCount"></span>
          <span>{{ $isAr ? 'استجابة. الحذف متاح للمدير العام فقط وسيحذف الاستجابات المرتبطة وتذاكرها.' : 'responses. Only a super admin can delete it, and linked responses and tickets will be removed.' }}</span>
        </div>

        <div class="flex items-center justify-end gap-3">
          <button
            type="button"
            @click="closeDelete()"
            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50 dark:border-slate-750 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-850"
          >
            {{ $isAr ? 'إلغاء' : 'Cancel' }}
          </button>
          <form method="POST" :action="deleteModal.action">
            @csrf
            @method('DELETE')
            <button
              type="submit"
              class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-red-500/20 transition hover:bg-red-700"
            >
              {{ $isAr ? 'حذف نهائي' : 'Delete permanently' }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Survey Editor Modal -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-start justify-center p-4 overflow-y-auto">
      <div @click.away="!isSaving && closeModal()" class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-4xl w-full my-8 text-start shadow-2xl transition-all" x-transition.scale.origin.bottom>

        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
          <h2 class="text-xl font-black text-gray-800 dark:text-white" x-text="isEditing ? ('{{ $isAr ? 'تعديل' : 'Edit' }}: ' + (form.title || '{{ $isAr ? 'استبيان جديد' : 'New Survey' }}')) : '{{ $isAr ? 'إنشاء استبيان جديد' : 'Create New Survey' }}'"></h2>
          <div class="flex items-center gap-2">
            <!-- Preview Button -->
            <button type="button" @click="togglePreview()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-teal-50 hover:text-teal-600 dark:hover:bg-teal-950/30 dark:hover:text-teal-400 transition-all cursor-pointer">
              <i data-lucide="eye" class="w-4 h-4"></i>
              <span>{{ $isAr ? 'معاينة' : 'Preview' }}</span>
            </button>
            <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
              <i data-lucide="x" class="w-6 h-6"></i>
            </button>
          </div>
        </div>

        <div class="p-6 space-y-6">
          <div class="space-y-4">
            <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
              <i data-lucide="clipboard-list" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              {{ $isAr ? 'المعلومات الأساسية' : 'Basic Info' }}
              <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'قم بتعبئة المعلومات الأساسية للاستبيان مثل العنوان والوصف' : 'Fill in the basic survey information like title and description' }}">?</span>
            </h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'عنوان الاستبيان' : 'Survey Title' }} <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.title" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold" placeholder="{{ $isAr ? 'أدخل عنوان الاستبيان...' : 'Enter survey title...' }}">
              </div>
              <div>
                <label class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'الوصف (اختياري)' : 'Description (Optional)' }}</label>
                <textarea x-model="form.description" rows="2" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold"></textarea>
              </div>
            </div>

            <!-- Toggles -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حالة الاستبيان' : 'Survey Status' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1">{{ $isAr ? 'تفعيل ليظهر للمرضى' : 'Activate to show to patients' }}</p>
              </div>
              <button type="button" @click="form.isActive = !form.isActive" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.isActive ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.isActive ? '{{ $isRtl ? 'right-7' : 'right-7' }}' : '{{ $isRtl ? 'right-0.5' : 'right-0.5' }}'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requireName ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الاسم' : 'Name Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requireName ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requireName = !form.requireName" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requireName ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.requireName ? '{{ $isRtl ? 'right-7' : 'right-7' }}' : '{{ $isRtl ? 'right-0.5' : 'right-0.5' }}'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requirePhone ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الهاتف' : 'Phone Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requirePhone ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requirePhone = !form.requirePhone" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requirePhone ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.requirePhone ? '{{ $isRtl ? 'right-7' : 'right-7' }}' : '{{ $isRtl ? 'right-0.5' : 'right-0.5' }}'"></div>
              </button>
            </div>

            <!-- Tips -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
                <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
                  <i data-lucide="heart" class="w-5 h-5 text-red-500"></i>
                  {{ $isAr ? 'نصائح طبية للمرضى' : 'Medical Tips' }}
                  <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'أضف نصائح صحية تظهر للمريض بعد إكمال الاستبيان. سيتم اختيار نصيحة عشوائية في كل مرة.' : 'Add health tips that appear to patients after completing the survey. A random tip will be shown each time.' }}">?</span>
                </h3>
                <button type="button" @click="form.tips.push('')" class="text-xs font-bold text-teal-600 dark:text-teal-400 cursor-pointer">{{ $isAr ? '+ إضافة نصيحة' : '+ Add Tip' }}</button>
              </div>
              <div class="space-y-2">
                <template x-for="(tip, index) in form.tips" :key="index">
                  <div class="flex items-center gap-2">
                    <input type="text" x-model="form.tips[index]" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold">
                    <button type="button" @click="form.tips.splice(index, 1)" class="p-2.5 text-red-400 hover:text-red-500 cursor-pointer">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </div>
                </template>
              </div>
            </div>

            <!-- Assigned Departments -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
                <i data-lucide="building-2" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'تخصيص للأقسام' : 'Assigned Departments' }}
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'اختر الأقسام الطبية التي سيظهر لها هذا الاستبيان. إذا لم تختر أي قسم، سيظهر لجميع الأقسام.' : 'Select which medical departments this survey will be available for. If none selected, it will be available to all departments.' }}">?</span>
              </h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                <template x-for="dept in availableDepartments" :key="dept">
                  <button type="button" @click="toggleDepartment(dept)" class="p-3 rounded-xl border-2 text-sm font-bold transition-all cursor-pointer text-start" :class="form.assignedDepartments.includes(dept) ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-950 text-gray-700 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800'">
                    <div class="flex items-center gap-2">
                      <i data-lucide="check" class="w-4 h-4 text-teal-600 dark:text-teal-400" x-show="form.assignedDepartments.includes(dept)"></i>
                      <span x-text="dept"></span>
                    </div>
                  </button>
                </template>
              </div>
            </div>

            <!-- Templates Bar (NEW) -->
            <div x-show="form.sections.length === 0" class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-teal-50/50 dark:bg-teal-950/10 border border-teal-100 dark:border-teal-900/30 rounded-xl">
              <span class="text-xs font-bold text-teal-700 dark:text-teal-400 flex items-center gap-1">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                {{ $isAr ? 'قوالب جاهزة:' : 'Templates:' }}
              </span>
              <button type="button" @click="loadTemplate('reception')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'رضا الاستقبال' : 'Reception' }}</button>
              <button type="button" @click="loadTemplate('nursing')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'الخدمة التمريضية' : 'Nursing' }}</button>
              <button type="button" @click="loadTemplate('full')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'استبيان شامل' : 'Full Survey' }}</button>
              <button type="button" @click="loadTemplate('quick')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'سريع (سؤالين)' : 'Quick (2 Q)' }}</button>
            </div>

            <!-- Sections Builder - Enhanced with Icons, Collapse/Expand, Reordering -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
              <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
                <i data-lucide="file-text" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'أقسام الاستبيان' : 'Survey Sections' }} (<span x-text="form.sections.length"></span>)
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'الأقسام هي مجموعات من الأسئلة. يمكنك إضافة أقسام متعددة وتخصيص أيقونة لكل قسم.' : 'Sections are groups of questions. You can add multiple sections and customize an icon for each one.' }}">?</span>
              </h3>
                <button type="button" @click="addSection()" class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-bold hover:bg-teal-700 transition-colors cursor-pointer">
                  <i data-lucide="plus" class="w-4 h-4"></i>
                  {{ $isAr ? 'إضافة قسم' : 'Add Section' }}
                </button>
              </div>

              <!-- Empty Sections State -->
              <div x-show="form.sections.length === 0" class="text-center py-10 bg-gray-50 dark:bg-slate-800/40 rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-750">
                <i data-lucide="alert-circle" class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-3"></i>
                <p class="text-gray-500 dark:text-slate-400 font-bold">{{ $isAr ? 'لا توجد أقسام بعد. أضف قسماً جديداً للبدء.' : 'No sections yet. Add a new section to start building your survey.' }}</p>
              </div>

              <!-- Sections List -->
              <div class="space-y-4">
                <template x-for="(section, sIndex) in form.sections" :key="section.id || sIndex">
                  <div class="border border-gray-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                    <!-- Section Header (collapsible) -->
                    <div
                      class="bg-gray-50 dark:bg-slate-800/60 p-4 flex items-center gap-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
                      @click="toggleSection(sIndex)"
                      draggable="true"
                      @dragstart="handleSectionDragStart(sIndex, $event)"
                      @dragend="handleSectionDragEnd($event)"
                      @dragover="handleSectionDragOver(sIndex, $event)"
                      @drop="handleSectionDrop(sIndex, $event)"
                      :class="dragOverSectionIndex === sIndex ? 'border-2 border-teal-500' : ''"
                    >
                      <!-- Drag Handle -->
                      <div class="cursor-grab active:cursor-grabbing text-gray-300 dark:text-slate-600 hover:text-teal-500 transition-colors" @click.stop title="{{ $isAr ? 'اسحب لإعادة الترتيب' : 'Drag to reorder' }}">
                        <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                      </div>
                      <!-- Section Icon -->
                      <div class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-900 shadow-sm border border-gray-100 dark:border-slate-750 shrink-0" x-html="getSectionIconHtml(section.icon)"></div>

                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="font-bold text-gray-700 dark:text-white truncate" x-text="section.title || '{{ $isAr ? 'قسم' : 'Section' }} ' + (sIndex + 1)"></span>
                          <span class="text-xs font-bold text-gray-400 dark:text-slate-500 whitespace-nowrap" x-text="'(' + (section.questions ? section.questions.length : 0) + ' {{ $isAr ? 'أسئلة' : 'Q' }})'"></span>
                        </div>
                        <p x-show="section.description" class="text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5" x-text="section.description"></p>
                      </div>

                      <div class="flex items-center gap-1 shrink-0">
                        <!-- Move Up -->
                        <button type="button" @click.stop="moveSection(sIndex, -1)" :disabled="sIndex === 0" class="p-1.5 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="chevron-up" class="w-4 h-4"></i>
                        </button>
                        <!-- Move Down -->
                        <button type="button" @click.stop="moveSection(sIndex, 1)" :disabled="sIndex === form.sections.length - 1" class="p-1.5 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                        <!-- Delete Section -->
                        <button type="button" @click.stop="form.sections.splice(sIndex, 1)" class="p-1.5 text-gray-400 hover:text-red-500 cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                      </div>
                      <!-- Expand/Collapse Chevron -->
                      <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400 transition-transform duration-200" :class="expandedSections[sIndex] ? 'rotate-180' : ''"></i>
                    </div>

                    <!-- Section Body (collapsible content) -->
                    <div x-show="expandedSections[sIndex]" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="p-4 space-y-4 bg-white dark:bg-slate-900 border-t border-gray-150 dark:border-slate-800">

                      <!-- Section Title & Description -->
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1.5 relative group">
                            {{ $isAr ? 'عنوان القسم' : 'Section Title' }}
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-400 text-[8px] font-bold cursor-help ml-1 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors align-middle" title="{{ $isAr ? 'مثال: قسم الاستقبال، قسم الطبيب، قسم الخدمة التمريضية' : 'Example: Reception Section, Doctor Section, Nursing Section' }}">?</span>
                          </label>
                          <input type="text" x-model="section.title" class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'عنوان القسم...' : 'Section Title...' }}">
                        </div>
                        <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1.5">{{ $isAr ? 'وصف القسم (اختياري)' : 'Section Description' }}</label>
                          <input type="text" x-model="section.description" class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'وصف القسم...' : 'Section Description...' }}">
                        </div>
                      </div>

                      <!-- Section Icon Picker -->
                      <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2 relative group">
                            {{ $isAr ? 'اختيار أيقونة القسم' : 'Section Icon' }}
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-400 text-[8px] font-bold cursor-help ml-1 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors align-middle" title="{{ $isAr ? 'اختر أيقونة تعبر عن محتوى القسم لتظهر للمريض أثناء التعبئة' : 'Choose an icon that represents the section content to show to patients while filling the survey' }}">?</span>
                          </label>
                        <div class="flex flex-wrap gap-2">
                          <template x-for="si in sectionIcons" :key="si.id">
                            <button type="button" @click="section.icon = si.id" class="p-3 rounded-xl border-2 transition-all cursor-pointer" :class="section.icon === si.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/30' : 'border-gray-200 dark:border-slate-750 hover:border-gray-300 dark:hover:border-slate-650'">
                              <div x-html="getIconHtml(si.icon)" class="w-5 h-5" :class="section.icon === si.id ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-slate-400'"></div>
                            </button>
                          </template>
                        </div>
                      </div>

                      <!-- Questions List -->
                      <div class="space-y-3">
                        <div class="flex items-center justify-between">
                          <h4 class="font-bold text-gray-600 dark:text-slate-350 text-sm">{{ $isAr ? 'الأسئلة' : 'Questions' }} (<span x-text="section.questions ? section.questions.length : 0"></span>)</h4>
                          <button type="button" @click="addQuestion(sIndex)" class="flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                            <i data-lucide="plus" class="w-3 h-3"></i>
                            {{ $isAr ? 'إضافة سؤال' : 'Add Question' }}
                          </button>
                        </div>

                        <template x-for="(question, qIndex) in section.questions" :key="question.id || qIndex">
                          <div class="bg-gray-50 dark:bg-slate-900/60 border border-transparent dark:border-slate-800 rounded-xl p-4 space-y-3 relative group">
                            <div class="flex items-start gap-3">
                              <!-- Question Number -->
                              <div class="w-7 h-7 bg-teal-100 dark:bg-teal-950/30 text-teal-700 dark:text-teal-400 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 mt-0.5" x-text="qIndex + 1"></div>

                              <div class="flex-1 space-y-3">
                                <!-- Question Type Picker with Icons -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                  <template x-for="qt in questionTypes" :key="qt.id">
                                    <button type="button" @click="question.type = qt.id" class="p-2 rounded-lg border-2 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer" :class="question.type === qt.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-750 text-gray-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-600'">
                                      <div x-html="getQuestionTypeIcon(qt.id)" class="w-3.5 h-3.5 shrink-0"></div>
                                      <span x-text="qt.label"></span>
                                    </button>
                                  </template>
                                </div>

                                <!-- Question Title -->
                                <input type="text" x-model="question.title" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'نص السؤال...' : 'Question text...' }}">

                                <!-- Question Description (NEW) -->
                                <input type="text" x-model="question.description" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'وصف السؤال (اختياري)...' : 'Question description (optional)...' }}">

                                <!-- Options (only for multiple_choice) -->
                                <div x-show="question.type === 'multiple_choice'" class="space-y-2 mt-3 p-3 bg-gray-50 dark:bg-slate-800/40 rounded-xl">
                                  <label class="text-xs font-bold text-gray-500">{{ $isAr ? 'خيارات الإجابة:' : 'Options:' }}</label>
                                  <template x-for="(opt, optIndex) in question.options" :key="optIndex">
                                    <div class="flex gap-2">
                                      <input type="text" x-model="opt.label" @input="opt.value = opt.label" class="flex-1 px-3 py-1.5 text-xs font-bold bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-md outline-none focus:border-teal-500" placeholder="{{ $isAr ? 'الخيار...' : 'Option...' }}">
                                      <button type="button" @click="question.options.splice(optIndex, 1)" class="text-red-400 hover:text-red-500"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </div>
                                  </template>
                                  <button type="button" @click="if(!question.options) question.options = []; question.options.push({label:'', value:''})" class="text-xs font-bold text-teal-600 mt-1 cursor-pointer">{{ $isAr ? '+ إضافة خيار' : '+ Add Option' }}</button>
                                </div>

                                <!-- Required Toggle -->
                                <div class="flex items-center gap-3">
                                  <button type="button" @click="question.required = !question.required" class="w-10 h-5 rounded-full transition-all relative cursor-pointer" :class="question.required ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                                    <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow-sm transition-all" :class="question.required ? 'right-5' : 'right-0.5'"></div>
                                  </button>
                                  <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ $isAr ? 'إجابة مطلوبة' : 'Required' }}</span>
                                </div>
                              </div>

                              <!-- Question Actions (Move Up/Down/Delete) -->
                              <div class="flex flex-col items-center gap-1 shrink-0">
                                <button type="button" @click="moveQuestion(sIndex, qIndex, -1)" :disabled="qIndex === 0" class="p-1 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer" title="{{ $isAr ? 'تحريك لأعلى' : 'Move Up' }}">
                                  <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                </button>
                                <button type="button" @click="moveQuestion(sIndex, qIndex, 1)" :disabled="qIndex === section.questions.length - 1" class="p-1 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer" title="{{ $isAr ? 'تحريك لأسفل' : 'Move Down' }}">
                                  <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </button>
                                <button type="button" @click="section.questions.splice(qIndex, 1)" class="p-1 text-gray-400 hover:text-red-500 cursor-pointer" title="{{ $isAr ? 'حذف السؤال' : 'Delete Question' }}">
                                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                              </div>
                            </div>
                          </div>
                        </template>
                      </div>

                    </div>
                  </div>
                </template>
              </div>

            </div>

          </div>
        </div>

            <!-- Live Preview Modal -->
        <div x-show="showPreview" style="display: none;" class="fixed inset-0 z-[70] bg-slate-950/70 backdrop-blur-sm flex items-start justify-center p-2 overflow-y-auto" @keydown.escape.window="showPreview = false">
          <div @click.away="showPreview = false" class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-2xl w-full my-4 sm:my-8 text-start shadow-2xl transition-all animate-scale-in">
            <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
              <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                <i data-lucide="eye" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'معاينة الاستبيان' : 'Survey Preview' }}
              </h3>
              <button type="button" @click="showPreview = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
                <i data-lucide="x" class="w-5 h-5"></i>
              </button>
            </div>
            <div class="p-6 space-y-6">
              <!-- Survey Title in Preview -->
              <div class="text-center">
                <h2 class="text-2xl font-black text-gray-900 dark:text-white" x-text="form.title || '{{ $isAr ? '(بدون عنوان)' : '(Untitled)' }}'"></h2>
                <p x-show="form.description" class="text-sm text-gray-500 dark:text-slate-400 mt-2" x-text="form.description"></p>
              </div>

              <!-- Sections in Preview -->
              <template x-for="(section, sIdx) in form.sections" :key="sIdx">
                <div class="space-y-4">
                  <div class="flex items-center gap-3 border-b border-gray-100 dark:border-slate-800 pb-3">
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-teal-100 dark:bg-teal-950/30 text-teal-600 dark:text-teal-400" x-html="getSectionIconHtml(section.icon)"></div>
                    <div>
                      <h4 class="font-bold text-gray-800 dark:text-white" x-text="section.title || '{{ $isAr ? 'قسم' : 'Section' }} ' + (sIdx + 1)"></h4>
                      <p x-show="section.description" class="text-xs text-gray-500 dark:text-slate-400" x-text="section.description"></p>
                    </div>
                  </div>

                  <template x-for="(question, qIdx) in section.questions" :key="qIdx">
                    <div class="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-4 space-y-3">
                      <div class="flex items-start gap-2">
                        <span class="text-xs font-bold text-teal-600 dark:text-teal-400 shrink-0 mt-0.5" x-text="(qIdx + 1) + '.'"></span>
                        <div>
                          <p class="text-sm font-bold text-gray-800 dark:text-white" x-text="question.title"></p>
                          <p x-show="question.description" class="text-xs text-gray-500 dark:text-slate-400 mt-0.5" x-text="question.description"></p>
                        </div>
                      </div>
                      <!-- Star Rating Preview -->
                      <div x-show="question.type === 'stars'" class="flex gap-1" x-html="getPreviewStars(question)"></div>
                      <!-- Emoji Preview -->
                      <div x-show="question.type === 'emoji'" class="flex gap-2 text-xl">
                        <span class="opacity-50">😡</span> <span class="opacity-50">😕</span> <span class="opacity-100 scale-110">😐</span> <span class="opacity-50">😊</span> <span class="opacity-50">😍</span>
                      </div>
                      <!-- Yes/No Preview -->
                      <div x-show="question.type === 'yes_no'" class="flex gap-3">
                        <span class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-sm font-bold text-gray-600 dark:text-slate-300">{{ $isAr ? 'نعم' : 'Yes' }}</span>
                        <span class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-sm font-bold text-gray-600 dark:text-slate-300">{{ $isAr ? 'لا' : 'No' }}</span>
                      </div>
                      <!-- NPS Preview -->
                      <div x-show="question.type === 'nps'" class="flex gap-1">
                        <template x-for="n in 11">
                          <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold border border-gray-200 dark:border-slate-700 text-gray-500" x-text="n - 1"></span>
                        </template>
                      </div>
                      <!-- Text Preview -->
                      <div x-show="question.type === 'text'" class="border border-gray-200 dark:border-slate-700 rounded-lg p-3 text-sm text-gray-400 italic">
                        {{ $isAr ? '[مكان إدخال النص]' : '[Text input]' }}
                      </div>
                      <!-- Required Badge -->
                      <span x-show="question.required" class="text-[10px] text-red-500 font-bold">{{ $isAr ? '* مطلوب' : '* Required' }}</span>
                    </div>
                  </template>
                </div>
              </template>

              <!-- Empty State -->
              <div x-show="form.sections.length === 0" class="text-center py-10">
                <i data-lucide="file-text" class="w-16 h-16 text-gray-200 dark:text-slate-700 mx-auto mb-3"></i>
                <p class="text-gray-500 dark:text-slate-400 font-bold">{{ $isAr ? 'أضف أقساماً وأسئلة لرؤية المعاينة' : 'Add sections and questions to see the preview' }}</p>
              </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-slate-800 text-center">
              <button type="button" @click="showPreview = false" class="px-6 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm transition-all cursor-pointer">
                {{ $isAr ? 'إغلاق المعاينة' : 'Close Preview' }}
              </button>
            </div>
          </div>
        </div>

        <div class="p-6 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between sticky bottom-0 bg-white dark:bg-slate-900 rounded-b-2xl z-10">
          <div class="flex items-center gap-2">
            <button type="button" @click="closeModal()" class="px-6 py-3 rounded-xl text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:bg-slate-800 font-bold transition-all cursor-pointer">
              {{ $isAr ? 'إلغاء' : 'Cancel' }}
            </button>
          <button type="button" @click="saveSurvey()" :disabled="!form.title || isSaving" class="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white bg-teal-600 hover:bg-teal-700 disabled:opacity-50 transition-all cursor-pointer">
            <i data-lucide="save" class="w-5 h-5" x-show="!isSaving"></i>
            <i data-lucide="loader-2" class="w-5 h-5 animate-spin" x-show="isSaving"></i>
            <span x-text="isSaving ? '{{ $isAr ? 'جاري الحفظ...' : 'Saving...' }}' : '{{ $isAr ? 'حفظ الاستبيان' : 'Save Survey' }}'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  @php
    $surveysJson = $surveys->map(function($survey) {
        $data = $survey->toArray();
        // Decode fields if they are strings (JSON)
        $data['tips'] = is_string($survey->tips) ? json_decode($survey->tips, true) : ($survey->tips ?? []);
        $data['assignedDepartments'] = is_string($survey->assignedDepartments) ? json_decode($survey->assignedDepartments, true) : ($survey->assignedDepartments ?? []);

        $data['sections'] = $survey->sections->map(function($sec) {
            $secData = $sec->toArray();
            $secData['icon'] = $sec->icon ?? 'clipboard-check';
            $secData['questions'] = $sec->questions->map(function($q) {
                $qData = $q->toArray();
                $qData['options'] = is_string($q->options) ? json_decode($q->options, true) : ($q->options ?? []);
                $qData['description'] = $q->description ?? '';
                return $qData;
            });
            return $secData;
        });
        return $data;
    })->values();
  @endphp

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('surveyComponent', () => ({
        surveys: @json($surveysJson),
        availableDepartments: @json($departments),
        showModal: false,
        isEditing: false,
        isSaving: false,
        deleteModal: { show: false, id: null, title: '', action: '', responseCount: 0 },
        toast: { show: false, message: '', type: 'success' },
        expandedSections: {},
        showPreview: false,
        // Drag & Drop state
        dragSectionIndex: null,
        dragQuestionIndex: null,
        dragSourceSection: null,
        dragOverSectionIndex: null,
        dragOverQuestionIndex: null,
        dragOverTargetSection: null,
        sectionIcons: [
          { id: 'door-open', icon: 'DoorOpen' },
          { id: 'stethoscope', icon: 'Stethoscope' },
          { id: 'building', icon: 'Building2' },
          { id: 'pill', icon: 'Pill' },
          { id: 'clipboard-check', icon: 'ClipboardCheck' },
          { id: 'users', icon: 'Users' },
          { id: 'activity', icon: 'Activity' },
          { id: 'heart', icon: 'Heart' },
          { id: 'file-text', icon: 'FileText' },
        ],
        questionTypes: [
          { id: 'stars', label: '{{ $isAr ? 'نجوم' : 'Stars' }}', icon: 'Star' },
          { id: 'emoji', label: '{{ $isAr ? 'وجوه تعبيرية' : 'Emoji' }}', icon: 'Smile' },
          { id: 'nps', label: '{{ $isAr ? 'NPS' : 'NPS' }}', icon: 'Hash' },
          { id: 'yes_no', label: '{{ $isAr ? 'نعم/لا' : 'Yes/No' }}', icon: 'ToggleLeft' },
          { id: 'multiple_choice', label: '{{ $isAr ? 'خيارات' : 'Multiple Choice' }}', icon: 'CheckSquare' },
          { id: 'text', label: '{{ $isAr ? 'نص حر' : 'Text' }}', icon: 'MessageSquare' }
        ],
        form: {
          id: null,
          title: '',
          description: '',
          isActive: false,
          requireName: false,
          requirePhone: false,
          tips: [],
          assignedDepartments: [],
          sections: []
        },

        // SVG icons for section icons
        getIconHtml(iconName) {
          const icons = {
            DoorOpen: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>',
            Stethoscope: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.3.3 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>',
            Building2: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>',
            Pill: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
            ClipboardCheck: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
            Users: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            Activity: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
            Heart: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
            FileText: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
          };
          return icons[iconName] || icons.ClipboardCheck;
        },

        // SVG icons for question types
        getQuestionTypeIcon(typeId) {
          const icons = {
            stars: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            emoji: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>',
            nps: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/></svg>',
            yes_no: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><rect x="2" y="6" width="20" height="12" rx="6"/><circle cx="8" cy="12" r="2"/></svg>',
            multiple_choice: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            text: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
          };
          return icons[typeId] || icons.stars;
        },

        // Get section icon HTML for display
        getSectionIconHtml(iconId) {
          return this.getIconHtml(
            (this.sectionIcons.find(i => i.id === iconId) || this.sectionIcons[4]).icon
          );
        },

        showToastMsg(msg, type = 'success') {
          this.toast = { show: true, message: msg, type: type };
          setTimeout(() => { this.toast.show = false; }, 3000);
        },

        openCreate() {
          this.isEditing = false;
          this.form = {
            id: null, title: '', description: '', isActive: false,
            requireName: false, requirePhone: false, tips: [],
            assignedDepartments: [], sections: []
          };
          this.expandedSections = {};
          this.showModal = true;
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        openEdit(id) {
          const survey = this.surveys.find(s => String(s.id) === String(id));
          if(survey) {
            this.isEditing = true;
            this.form = JSON.parse(JSON.stringify(survey)); // Deep copy
            this.form.tips = this.form.tips || [];
            this.form.assignedDepartments = this.form.assignedDepartments || [];
            this.form.sections = this.form.sections || [];

            // Fix options stringification issue & ensure description field
            this.form.sections.forEach(s => {
                s.questions.forEach(q => {
                    if(typeof q.options === 'string') {
                        try { q.options = JSON.parse(q.options); } catch(e) { q.options = []; }
                    }
                    if(!q.options) q.options = [];
                    q.description = q.description || '';
                });
                // Ensure section has icon
                s.icon = s.icon || 'clipboard-check';
            });

            // Expand all sections when editing
            this.expandedSections = {};
            this.form.sections.forEach((_, i) => {
              this.expandedSections[i] = true;
            });

            this.showModal = true;
            this.$nextTick(() => window.lucide && lucide.createIcons());
          }
        },

        closeModal() {
          this.showModal = false;
        },

        openDelete(id, title, action, responseCount = 0) {
          this.deleteModal = { show: true, id: id, title: title, action: action, responseCount: Number(responseCount) || 0 };
          this.$nextTick(() => window.lucide?.createIcons());
        },

        closeDelete() {
          this.deleteModal.show = false;
        },

        toggleDepartment(dept) {
          if (this.form.assignedDepartments.includes(dept)) {
            this.form.assignedDepartments = this.form.assignedDepartments.filter(d => d !== dept);
          } else {
            this.form.assignedDepartments.push(dept);
          }
        },

        // Toggle section expand/collapse
        toggleSection(sIndex) {
          this.expandedSections[sIndex] = !this.expandedSections[sIndex];
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // Move section up or down
        moveSection(sIndex, direction) {
          const newIndex = sIndex + direction;
          if (newIndex < 0 || newIndex >= this.form.sections.length) return;

          // Swap sections
          const temp = this.form.sections[sIndex];
          this.form.sections[sIndex] = this.form.sections[newIndex];
          this.form.sections[newIndex] = temp;

          // Swap expanded state
          const expandedTemp = this.expandedSections[sIndex];
          this.expandedSections[sIndex] = this.expandedSections[newIndex];
          this.expandedSections[newIndex] = expandedTemp;

          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // Move question up or down
        moveQuestion(sIndex, qIndex, direction) {
          const newIndex = qIndex + direction;
          if (newIndex < 0 || newIndex >= this.form.sections[sIndex].questions.length) return;

          const questions = this.form.sections[sIndex].questions;
          const temp = questions[qIndex];
          questions[qIndex] = questions[newIndex];
          questions[newIndex] = temp;

          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // ===== Drag & Drop for Sections =====
        handleSectionDragStart(sIndex, event) {
          this.dragSectionIndex = sIndex;
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', sIndex);
          event.target.classList.add('opacity-50');
        },
        handleSectionDragEnd(event) {
          this.dragSectionIndex = null;
          this.dragOverSectionIndex = null;
          event.target.classList.remove('opacity-50', 'border-teal-500');
        },
        handleSectionDragOver(sIndex, event) {
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
          this.dragOverSectionIndex = sIndex;
        },
        handleSectionDrop(dropIndex, event) {
          event.preventDefault();
          const dragIndex = this.dragSectionIndex;
          if (dragIndex === null || dragIndex === dropIndex) return;

          // Swap sections
          const section = this.form.sections.splice(dragIndex, 1)[0];
          this.form.sections.splice(dropIndex, 0, section);

          // Fix expanded state
          const expandedEntries = Object.entries(this.expandedSections);
          // Rebuild expanded keys since indices shifted
          const newExpanded = {};
          this.form.sections.forEach((_, i) => {
            newExpanded[i] = false;
          });
          // Try to keep the moved section expanded
          newExpanded[dropIndex] = true;
          this.expandedSections = newExpanded;

          this.dragSectionIndex = null;
          this.dragOverSectionIndex = null;
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // ===== Drag & Drop for Questions =====
        handleQuestionDragStart(sIndex, qIndex, event) {
          this.dragQuestionIndex = qIndex;
          this.dragSourceSection = sIndex;
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', qIndex);
          event.target.closest('[data-question-card]')?.classList.add('opacity-50');
        },
        handleQuestionDragEnd(event) {
          this.dragQuestionIndex = null;
          this.dragSourceSection = null;
          this.dragOverQuestionIndex = null;
          this.dragOverTargetSection = null;
          event.target.closest('[data-question-card]')?.classList.remove('opacity-50', 'ring-2', 'ring-teal-500');
        },
        handleQuestionDragOver(sIndex, qIndex, event) {
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
          this.dragOverQuestionIndex = qIndex;
          this.dragOverTargetSection = sIndex;
        },
        handleQuestionDrop(sIndex, qIndex, event) {
          event.preventDefault();
          const fromSection = this.dragSourceSection;
          const fromQIndex = this.dragQuestionIndex;

          if (fromSection === null || fromQIndex === null) return;

          const questions = this.form.sections[fromSection].questions;

          if (fromSection === sIndex) {
            // Same section: reorder
            if (fromQIndex === qIndex) return;
            const q = questions.splice(fromQIndex, 1)[0];
            questions.splice(qIndex, 0, q);
          } else {
            // Different section: move question
            const q = questions.splice(fromQIndex, 1)[0];
            this.form.sections[sIndex].questions.splice(qIndex, 0, q);
          }

          this.dragQuestionIndex = null;
          this.dragSourceSection = null;
          this.dragOverQuestionIndex = null;
          this.dragOverTargetSection = null;
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // ===== Survey Templates =====
        loadTemplate(templateName) {
          const templates = {
            reception: {
              sections: [
                { id: 'sec-' + Date.now(), title: '{{ $isAr ? 'تقييم خدمة الاستقبال' : 'Reception Service' }}', description: '{{ $isAr ? 'تقييم تجربتك مع موظفي الاستقبال' : 'Rate your reception experience' }}', icon: 'door-open', questions: [
                  { id: 'q-' + Date.now() + '-1', type: 'stars', title: '{{ $isAr ? 'مدى ترحيب موظف الاستقبال' : 'Reception staff welcome' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-2', type: 'emoji', title: '{{ $isAr ? 'سرعة إنهاء إجراءات الدخول' : 'Check-in speed' }}', description: '', required: true, options: [] },
                ]}
              ]
            },
            nursing: {
              sections: [
                { id: 'sec-' + Date.now(), title: '{{ $isAr ? 'تقييم الخدمة التمريضية' : 'Nursing Care' }}', description: '{{ $isAr ? 'قيم مستوى الرعاية التمريضية' : 'Rate the nursing care level' }}', icon: 'heart', questions: [
                  { id: 'q-' + Date.now() + '-1', type: 'stars', title: '{{ $isAr ? 'تعامل الممرضين مع المرضى' : 'Nurses attitude towards patients' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-2', type: 'nps', title: '{{ $isAr ? 'مدى رضاك عن الرعاية التمريضية' : 'Nursing care satisfaction' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-3', type: 'yes_no', title: '{{ $isAr ? 'هل تم الاستجابة لطلبك بسرعة؟' : 'Was your request responded to quickly?' }}', description: '', required: false, options: [] },
                ]}
              ]
            },
            full: {
              sections: [
                { id: 'sec-' + Date.now() + '-1', title: '{{ $isAr ? 'خدمة الاستقبال' : 'Reception' }}', description: '{{ $isAr ? 'قيم تجربتك مع خدمة الاستقبال' : 'Rate your reception experience' }}', icon: 'door-open', questions: [
                  { id: 'q-' + Date.now() + '-1', type: 'stars', title: '{{ $isAr ? 'مدى ترحيب وتودد موظفي الاستقبال' : 'Reception staff friendliness' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-2', type: 'yes_no', title: '{{ $isAr ? 'هل كانت عملية التسجيل سريعة وسهلة؟' : 'Was registration quick and easy?' }}', description: '', required: true, options: [] },
                ]},
                { id: 'sec-' + Date.now() + '-2', title: '{{ $isAr ? 'خدمة الطبيب' : 'Doctor Service' }}', description: '{{ $isAr ? 'قيم تجربتك مع الطبيب المعالج' : 'Rate your doctor experience' }}', icon: 'stethoscope', questions: [
                  { id: 'q-' + Date.now() + '-3', type: 'stars', title: '{{ $isAr ? 'مستوى الشرح والتوضيح من الطبيب' : 'Doctor explanation clarity' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-4', type: 'emoji', title: '{{ $isAr ? 'شعورك بالراحة مع الطبيب' : 'Comfort level with doctor' }}', description: '', required: true, options: [] },
                ]},
                { id: 'sec-' + Date.now() + '-3', title: '{{ $isAr ? 'الخدمة التمريضية' : 'Nursing' }}', description: '{{ $isAr ? 'قيم مستوى الرعاية التمريضية' : 'Rate nursing care' }}', icon: 'heart', questions: [
                  { id: 'q-' + Date.now() + '-5', type: 'nps', title: '{{ $isAr ? 'مدى رضاك عن الرعاية التي تلقيتها' : 'Satisfaction with care received' }}', description: '', required: true, options: [] },
                ]}
              ]
            },
            quick: {
              sections: [
                { id: 'sec-' + Date.now(), title: '{{ $isAr ? 'تقييم سريع' : 'Quick Feedback' }}', description: '{{ $isAr ? 'سؤالين سريعين فقط' : 'Just 2 quick questions' }}', icon: 'clipboard-check', questions: [
                  { id: 'q-' + Date.now() + '-1', type: 'stars', title: '{{ $isAr ? 'التقييم العام للخدمة' : 'Overall service rating' }}', description: '', required: true, options: [] },
                  { id: 'q-' + Date.now() + '-2', type: 'yes_no', title: '{{ $isAr ? 'هل تنصح بزيارة المستشفى للآخرين؟' : 'Would you recommend us?' }}', description: '', required: true, options: [] },
                ]}
              ]
            }
          };

          this.form.sections = templates[templateName]?.sections || [];
          this.expandedSections = {};
          if (this.form.sections.length > 0) {
            this.form.sections.forEach((_, i) => { this.expandedSections[i] = true; });
          }
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // ===== Live Preview =====
        togglePreview() {
          this.showPreview = !this.showPreview;
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        // Get star rating HTML for preview
        getPreviewStars(question) {
          let stars = '';
          for (let i = 1; i <= 5; i++) {
            stars += `<svg class="w-8 h-8 inline-block ${i <= 3 ? 'text-amber-400 fill-amber-400' : 'text-gray-300'}" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;
          }
          return stars;
        },

        addSection() {
          const newId = 'section-' + Date.now();
          this.form.sections.push({
            id: newId,
            title: '',
            description: '',
            icon: 'clipboard-check',
            questions: []
          });
          // Auto-expand the new section
          this.expandedSections[this.form.sections.length - 1] = true;
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        addQuestion(sectionIndex) {
          if (!this.form.sections[sectionIndex].questions) {
            this.form.sections[sectionIndex].questions = [];
          }
          this.form.sections[sectionIndex].questions.push({
            id: 'question-' + Date.now(),
            type: 'stars',
            title: '',
            description: '',
            required: false,
            options: []
          });
          this.$nextTick(() => window.lucide && lucide.createIcons());
        },

        async saveSurvey() {
          this.isSaving = true;
          try {
            const url = this.isEditing ? `/dashboard/surveys/${this.form.id}` : '/dashboard/surveys';
            const method = this.isEditing ? 'PUT' : 'POST';

            // Clean up options: ensure value is same as label
            this.form.sections.forEach(sec => {
                sec.questions.forEach(q => {
                    if (q.type === 'multiple_choice' && q.options) {
                        q.options.forEach(opt => { opt.value = opt.label; });
                    } else {
                        q.options = [];
                    }
                });
            });

            const response = await fetch(url, {
              method: method,
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
              },
              body: JSON.stringify(this.form)
            });

            const result = await response.json();

            if (response.ok && result.success) {
              this.showToastMsg('{{ $isAr ? 'تم حفظ الاستبيان بنجاح' : 'Survey saved successfully' }}');
              setTimeout(() => window.location.reload(), 1000);
            } else {
              this.showToastMsg(result.error || result.message || 'Error occurred', 'error');
              this.isSaving = false;
            }
          } catch (error) {
            this.showToastMsg('Network Error', 'error');
            this.isSaving = false;
          }
        }
      }));
    });
  </script>
@endsection
