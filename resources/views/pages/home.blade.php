@extends('layouts.web')

@section('title', 'MedSurvey Pro - ' . __('system_description'))
@section('description', app()->getLocale() === 'ar' ? 'نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية.' : 'An integrated system for collecting and analyzing patient satisfaction surveys intelligently and confidentially.')

@section('content')
  <div class="relative min-h-[calc(100vh-4rem)] flex flex-col justify-between overflow-hidden">
    <!-- Floating Background Decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -left-40 w-80 h-80 bg-teal-200 dark:bg-teal-950/20 rounded-full opacity-20 blur-3xl"></div>
      <div class="absolute -bottom-40 -right-40 w-80 h-80 bg-blue-200 dark:bg-blue-950/20 rounded-full opacity-20 blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-100 dark:bg-emerald-950/10 rounded-full opacity-10 blur-3xl"></div>
    </div>

    <!-- Hero Section -->
    <section class="relative flex items-center flex-1 py-16 sm:py-24">
      <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
          
          <!-- Welcome message pill badge -->
          <div class="inline-flex max-w-full items-center gap-2 bg-linear-to-r from-teal-500/10 via-emerald-500/10 to-blue-500/10 border border-teal-500/20 dark:border-teal-500/30 backdrop-blur-md rounded-full px-5 py-2.5 mb-8 animate-slide-up shadow-sm">
            <i data-lucide="heart" class="w-4 h-4 text-teal-600 dark:text-teal-400 animate-pulse"></i>
            <span class="text-xs sm:text-sm text-teal-850 dark:text-teal-300 font-bold">
              @if(!empty($settings['hospital']['welcomeMessage']))
                {{ $settings['hospital']['welcomeMessage'] }}
              @else
                {{ app()->getLocale() === 'ar' ? 'أهلاً بكم في نظام التقييم والاستبيانات' : 'Welcome to the Survey and Feedback System' }}
              @endif
            </span>
          </div>

          <!-- Hero Heading -->
          <h3 class="text-3xl sm:text-5xl font-black text-gray-900 dark:text-white leading-tight mb-6 animate-slide-up">
            {{ __('hero_title_part1') }}
            <span class="text-transparent bg-clip-text bg-linear-to-r from-teal-600 to-emerald-600 dark:from-teal-400 dark:to-emerald-400"> {{ __('hero_title_highlight') }} </span>
            {{ __('hero_title_part2') }}
          </h3>

          <!-- Hero Description -->
          <p class="text-lg sm:text-xl text-gray-600 dark:text-slate-300 mb-10 leading-relaxed animate-slide-up">
            {{ __('hero_desc') }}
          </p>

          <!-- Start Survey Action Button -->
          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up">
            <a
              href="{{ route('survey.selection') }}"
              class="group w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-linear-to-r from-teal-600 to-emerald-600 text-white px-8 py-4 rounded-2xl text-lg font-bold shadow-xl shadow-teal-200 dark:shadow-teal-900/30 hover:shadow-2xl hover:shadow-teal-300 dark:hover:shadow-teal-900/45 transform hover:-translate-y-1 transition-all duration-300 cursor-pointer"
            >
              <i data-lucide="clipboard-list" class="w-5 h-5"></i>
              <span>{{ __('start_survey') }}</span>
              <i data-lucide="chevron-left" class="w-5 h-5 group-hover:-translate-x-1 transition-transform rtl:rotate-0 ltr:rotate-180"></i>
            </a>
          </div>

          <!-- Quick Stats and Trust info -->
          <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-6 mt-8 text-sm text-gray-500 dark:text-slate-400 animate-slide-up">
            <div class="flex items-center gap-2">
              <i data-lucide="clock" class="w-4 h-4 text-teal-500 dark:text-teal-400"></i>
              <span>{{ __('landing_3_mins') }}</span>
            </div>
            <div class="flex items-center gap-2">
              <i data-lucide="shield" class="w-4 h-4 text-teal-500 dark:text-teal-400"></i>
              <span>{{ __('landing_secure_100') }}</span>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="py-16 bg-white/50 dark:bg-slate-900/20 border-t border-gray-100/50 dark:border-slate-800/20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          
          <!-- Card 1 -->
          <div class="group bg-white dark:bg-slate-900 rounded-2xl p-8 shadow-sm dark:shadow-slate-900/10 hover:shadow-xl hover:border-teal-500/10 transition-all duration-300 border border-gray-100 dark:border-slate-800/40 animate-slide-up text-start">
            <div class="w-14 h-14 bg-linear-to-r from-teal-500 to-emerald-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-teal-200 dark:shadow-teal-900/20 mb-5 group-hover:scale-110 transition-transform">
              <i data-lucide="clipboard-list" class="w-7 h-7"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">{{ __('landing_feat_survey_title') }}</h3>
            <p class="text-gray-600 dark:text-slate-300 leading-relaxed">{{ __('landing_feat_survey_desc') }}</p>
          </div>

          <!-- Card 2 -->
          <div class="group bg-white dark:bg-slate-900 rounded-2xl p-8 shadow-sm dark:shadow-slate-900/10 hover:shadow-xl hover:border-teal-500/10 transition-all duration-300 border border-gray-100 dark:border-slate-800/40 animate-slide-up text-start" style="animation-delay: 100ms;">
            <div class="w-14 h-14 bg-linear-to-r from-blue-500 to-indigo-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-blue-200 dark:shadow-blue-900/20 mb-5 group-hover:scale-110 transition-transform">
              <i data-lucide="shield" class="w-7 h-7"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">{{ __('landing_feat_privacy_title') }}</h3>
            <p class="text-gray-600 dark:text-slate-300 leading-relaxed">{{ __('landing_feat_privacy_desc') }}</p>
          </div>

          <!-- Card 3 -->
          <div class="group bg-white dark:bg-slate-900 rounded-2xl p-8 shadow-sm dark:shadow-slate-900/10 hover:shadow-xl hover:border-teal-500/10 transition-all duration-300 border border-gray-100 dark:border-slate-800/40 animate-slide-up text-start" style="animation-delay: 200ms;">
            <div class="w-14 h-14 bg-linear-to-r from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-amber-200 dark:shadow-amber-900/20 mb-5 group-hover:scale-110 transition-transform">
              <i data-lucide="star" class="w-7 h-7"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">{{ __('landing_feat_improve_title') }}</h3>
            <p class="text-gray-600 dark:text-slate-300 leading-relaxed">{{ __('landing_feat_improve_desc') }}</p>
          </div>

        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 dark:bg-slate-950 text-white py-10 transition-colors">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 border-b border-gray-800 dark:border-slate-800/60 pb-6 mb-6">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 flex items-center justify-center rounded-lg overflow-hidden">
              <img src="/system-logo.png" alt="MedSurvey Pro" class="w-full h-full object-cover">
            </div>
            <span class="font-bold text-lg">MedSurvey Pro</span>
          </div>

          <div class="flex items-center gap-2">
            @if(!empty($settings['hospital']['logo']))
              <img src="{{ $settings['hospital']['logo'] }}" alt="{{ $settings['hospital']['name'] ?? '' }}" class="h-6 max-w-[100px] object-contain rounded opacity-80" />
            @else
              <div class="w-6 h-6 bg-white/10 rounded flex items-center justify-center text-teal-400">
                <i data-lucide="heart" class="w-3.5 h-3.5"></i>
              </div>
            @endif
            <span class="text-sm text-gray-300 font-semibold">
              {{ $settings['hospital']['name'] ?? '' }}
            </span>
          </div>
        </div>
        <p class="text-gray-500 text-xs">
          @if(app()->getLocale() === 'ar')
            تم التطوير والتشغيل لصالح {{ $settings['hospital']['name'] ?? '' }} عبر نظام قياس وتحسين رضا المرضى MedSurvey Pro © {{ date('Y') }}
          @else
            Developed and operated for {{ $settings['hospital']['name'] ?? '' }} via MedSurvey Pro patient satisfaction measurement system © {{ date('Y') }}
          @endif
        </p>
      </div>
    </footer>
  </div>
@endsection
