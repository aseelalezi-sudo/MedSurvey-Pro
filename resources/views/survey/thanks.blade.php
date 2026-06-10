@extends('layouts.web')

@php
  $hideHeader = true;
  $showLanguageToggle = ($settings['appearance']['showLanguageToggle'] ?? true) !== false;
  $isKiosk = session('kiosk_mode', false);
@endphp

@section('title', __('thank_you') . ' - MedSurvey Pro')

@section('content')
  <div class="relative flex min-h-screen items-center justify-center bg-linear-to-r from-green-50 via-white to-emerald-50 p-4 text-gray-900 transition-colors duration-300 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] dark:text-slate-100">
    <div class="absolute right-4 top-4 z-10 flex items-center gap-2">
      @if(!$isKiosk)
      @if($showLanguageToggle)
      <!-- Language Switcher -->
      @if(app()->getLocale() === 'ar')
        <form method="POST" action="{{ route('set-locale', 'en') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 rounded-lg border border-slate-200/50 bg-white/40 px-2.5 py-1.5 text-xs font-black text-slate-500 backdrop-blur-md transition-colors hover:bg-teal-50 hover:text-teal-600 dark:border-slate-800/50 dark:bg-slate-900/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-teal-400">
          <i data-lucide="globe" class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400"></i>
          <span>English</span>
        </button>
                  </form>
      @else
        <form method="POST" action="{{ route('set-locale', 'ar') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 rounded-lg border border-slate-200/50 bg-white/40 px-2.5 py-1.5 text-xs font-black text-slate-500 backdrop-blur-md transition-colors hover:bg-teal-50 hover:text-teal-600 dark:border-slate-800/50 dark:bg-slate-900/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-teal-400">
          <i data-lucide="globe" class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400"></i>
          <span>ط§ظ„ط¹ط±ط¨ظٹط©</span>
        </button>
                  </form>
      @endif
      @endif

      <!-- Theme Toggler -->
      <button @click="toggleTheme()" class="p-2 rounded-xl border border-slate-200/60 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900 text-slate-500 dark:text-slate-400 cursor-pointer backdrop-blur-md" aria-label="Toggle Theme">
        <span x-show="theme === 'light'">
          <i data-lucide="moon" class="w-4 h-4"></i>
        </span>
        <span x-show="theme === 'dark'" style="display: none;">
          <i data-lucide="sun" class="w-4 h-4 text-amber-300"></i>
        </span>
      </button>
      @endif
    </div>

    <main class="max-w-lg text-center animate-scale-in">
      {{-- Success Animation --}}
      <div class="relative w-28 h-28 mx-auto mb-8">
        <div class="w-full h-full bg-linear-to-r from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-2xl shadow-green-200 dark:shadow-green-950/20 animate-pulse">
          <i data-lucide="check-circle-2" class="h-14 w-14 text-white"></i>
        </div>
        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-bounce text-lg">
          â­گ
        </div>
        <div class="absolute -bottom-2 -left-2 w-8 h-8 bg-pink-400 rounded-full flex items-center justify-center shadow-lg animate-bounce" style="animation-delay: 0.2s">
          <i data-lucide="heart" class="h-4 w-4 text-white"></i>
        </div>
      </div>

      <h1 class="mb-4 text-3xl font-black text-gray-900 dark:text-white sm:text-4xl">
        {{ __('thank_you') }} ًںژ‰
      </h1>
      <p class="mb-3 text-lg leading-relaxed text-gray-600 dark:text-slate-350">{{ $thankYouMessage ?: __('survey_submitted_success') }}</p>
      <p class="mb-10 text-gray-500 dark:text-slate-400 leading-relaxed text-sm">
        {{ __('thank_you_desc') }}
      </p>

      @if (!empty($medicalTip))
        <section class="group relative mb-8 overflow-hidden rounded-3xl bg-linear-to-r from-teal-500 to-emerald-600 p-8 text-start text-white shadow-xl shadow-teal-200 dark:shadow-teal-950/20">
          <div class="pointer-events-none absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
          <div class="relative z-10">
            <div class="mb-4 flex items-center justify-start gap-2">
              <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                <i data-lucide="star" class="h-5 w-5 fill-yellow-300 text-yellow-300"></i>
              </div>
              <span class="text-lg font-black uppercase tracking-wider">{{ __('health_tip_today') }}</span>
            </div>
            <p class="text-lg font-medium italic leading-relaxed sm:text-xl">"{{ $medicalTip }}"</p>
          </div>
          <div class="absolute -bottom-6 -right-6 h-24 w-24 rounded-full bg-white/10 blur-2xl"></div>
        </section>
      @else
        <section class="mb-8 rounded-2xl border border-green-100 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div class="mb-3 flex items-center justify-center gap-2">
            <i data-lucide="heart" class="h-5 w-5 text-red-500 animate-pulse"></i>
            <span class="font-black text-gray-800 dark:text-slate-200">{{ __('health_priority') }}</span>
          </div>
          <p class="text-sm leading-6 text-gray-500 dark:text-slate-400">
            {{ __('health_wish') }}
          </p>
        </section>
      @endif

      <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
        @if(!$isKiosk)
        <a href="{{ route('home') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-linear-to-r from-teal-600 to-emerald-600 px-6 py-3 font-bold text-white shadow-lg shadow-teal-200 transition-all hover:-translate-y-0.5 hover:shadow-xl dark:shadow-teal-950/20 sm:w-auto">
          <i data-lucide="home" class="h-5 w-5"></i>
          <span>{{ __('home') }}</span>
        </a>
        @endif
        <a href="{{ route('survey.selection') }}" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-gray-200 px-6 py-3 font-bold text-gray-650 transition-all hover:border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-350 dark:hover:border-slate-600 dark:hover:bg-slate-800 sm:w-auto">
          <i data-lucide="rotate-ccw" class="h-5 w-5"></i>
          <span>{{ __('new_survey') }}</span>
        </a>
      </div>
    </main>

    @if($isKiosk)
    <a href="{{ route('dashboard.kiosk.exit') }}" class="fixed top-24 left-4 p-4 rounded-full bg-slate-800 hover:bg-slate-700 text-white transition-all z-[9999] shadow-2xl group flex items-center justify-center opacity-100">
      <i data-lucide="lock" class="w-6 h-6"></i>
    </a>
    @endif
  </div>

  <script>
    setTimeout(() => {
      window.location.href = @js($isKiosk) ? '{{ route('survey.selection') }}' : '{{ route('home') }}';
    }, 15000);
  </script>
@endsection
