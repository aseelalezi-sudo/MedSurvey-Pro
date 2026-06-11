@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'إدارة الاستبيانات' : 'Surveys Management') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isRtl = app()->getLocale() === 'ar';
    $isAr = $isRtl;
    $user = auth()->user();
    $isSuperAdmin = $user->role === 'super_admin';
    $formatCount = [\App\Support\NumberFormatter::class, 'format'];
    $compactCount = [\App\Support\NumberFormatter::class, 'compact'];
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

        @include('dashboard.surveys.partials.list')
    </div>

    @include('dashboard.surveys.partials.modal-delete')

    @include('dashboard.surveys.partials.modal-editor')

    @include('dashboard.surveys.partials.scripts')
@endsection
