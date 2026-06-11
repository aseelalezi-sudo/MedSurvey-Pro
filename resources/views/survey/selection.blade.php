@extends('layouts.web')

@php
  $hideHeader = true;
  $showLanguageToggle = ($settings['appearance']['showLanguageToggle'] ?? true) !== false;
  $isKiosk = session('kiosk_mode', false);
  $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
  $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
@endphp

@section('title', __('select_survey') . ' - MedSurvey Pro')

@section('content')
  <div class="min-h-screen bg-linear-to-r from-teal-50 via-white to-blue-50 text-gray-900 transition-colors duration-300 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] dark:text-slate-100">
    <div class="sticky top-0 z-40 border-b border-gray-100 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/95">
      <div class="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          @if(!$isKiosk)
          <a
            href="{{ route('home') }}"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-slate-500 transition-colors hover:bg-gray-50 hover:text-slate-700 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-200"
            aria-label="{{ __('home') }}"
          >
            <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="h-5 w-5"></i>
          </a>
          @endif
          <div class="min-w-0 text-start">
            <h1 class="truncate text-lg font-black leading-tight text-gray-900 dark:text-white">{{ __('select_survey') }}</h1>
            <p class="truncate text-xs font-semibold text-gray-500 dark:text-slate-400">{{ __('select_appropriate_survey') }}</p>
          </div>
        </div>

        <div class="flex items-center gap-1.5 sm:gap-2">
          <!-- Timer Clock -->
          <div
            x-data="{
              timeLeft: 180,
              formattedTime: '03:00',
              paused: false,
              resumeTimeout: null,
              init() {
                setInterval(() => {
                  if (!this.paused) {
                    if (this.timeLeft > 0) {
                      this.timeLeft--;
                      const min = Math.floor(this.timeLeft / 60).toString().padStart(2, '0');
                      const sec = (this.timeLeft % 60).toString().padStart(2, '0');
                      this.formattedTime = `${min}:${sec}`;
                    } else {
                      window.location.href = '{{ route('home') }}';
                    }
                  }
                }, 1000);

                const activityEvents = ['pointerdown', 'pointermove', 'keydown', 'input', 'scroll', 'touchstart', 'wheel'];
                activityEvents.forEach(event => {
                  window.addEventListener(event, () => {
                    this.paused = true;
                    if (this.resumeTimeout) clearTimeout(this.resumeTimeout);
                    this.resumeTimeout = setTimeout(() => {
                      this.paused = false;
                    }, 3000);
                  }, { passive: true });
                });
              }
            }"
            class="flex shrink-0 items-center gap-1.5 rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 text-xs font-black text-teal-700 dark:border-teal-900/40 dark:bg-teal-950/30 dark:text-teal-400"
            dir="ltr"
          >
            <i data-lucide="clock" class="h-3.5 w-3.5 animate-pulse"></i>
            <span x-text="formattedTime">03:00</span>
          </div>

          @if(!$isKiosk)
          @if($showLanguageToggle)
          <!-- Language Switcher -->
          <div class="flex items-center">
            @if(app()->getLocale() === 'ar')
              <form method="POST" action="{{ route('set-locale', 'en') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-all cursor-pointer font-bold border border-transparent">
                <i data-lucide="globe" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span>English</span>
              </button>
                  </form>
            @else
              <form method="POST" action="{{ route('set-locale', 'ar') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-all cursor-pointer font-bold border border-transparent">
                <i data-lucide="globe" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span>العربية</span>
              </button>
                  </form>
            @endif
          </div>
          @endif

          <!-- Theme Toggler -->
          <button type="button" @click="toggleTheme()" class="p-2 rounded-xl border border-slate-200/50 hover:bg-slate-50 dark:border-slate-850/60 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 cursor-pointer" aria-label="Toggle Theme">
            <span x-show="theme === 'light'">
              <i data-lucide="moon" class="w-4 h-4"></i>
            </span>
            <span x-show="theme === 'dark'" style="display: none;">
              <i data-lucide="sun" class="w-4 h-4 text-amber-300"></i>
            </span>
          </button>
          @endif
        </div>
      </div>
    </div>

    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
      <section class="mb-8 text-center animate-slide-up">
        <div class="mb-4 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
          <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-linear-to-r from-teal-500 to-emerald-600 text-white shadow-xl shadow-teal-200 dark:shadow-teal-950/30">
            <i data-lucide="clipboard-list" class="h-7 w-7"></i>
          </div>
          <h2 class="text-2xl font-black text-gray-900 dark:text-white sm:text-3xl text-start">{{ __('which_survey_title') }}</h2>
        </div>
        <p class="mx-auto max-w-xl text-sm leading-7 text-gray-500 dark:text-slate-400 sm:text-base">
          {{ __('survey_selection_desc') }}
        </p>
      </section>

      @if ($surveys->isEmpty())
        <section class="rounded-3xl border border-gray-100 bg-white p-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <i data-lucide="clipboard-list" class="mx-auto mb-4 h-20 w-20 text-gray-200 dark:text-slate-700"></i>
          <h3 class="mb-2 text-xl font-black text-gray-700 dark:text-slate-200">{{ __('no_available_surveys') }}</h3>
          <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('try_again_later') }}</p>
        </section>
      @else
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
          @foreach ($surveys as $index => $survey)
            @php
              $totalQuestions = $survey->sections->sum(fn ($section) => $section->questions->count());
              $estimatedTime = max(2, (int) ceil($totalQuestions * 0.3));
              $cardIcon = 'clipboard-list';
            @endphp
            <a
              href="{{ route('survey.take', ['surveyId' => $survey->id]) }}"
              class="group flex h-full flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white text-start shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-teal-500/20 hover:shadow-xl dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-teal-500/30"
              style="animation-delay: {{ $index * 100 }}ms;"
            >
              <div class="relative min-h-[190px] overflow-hidden bg-linear-to-r from-teal-500 to-emerald-600 p-6 text-white">
                <div class="absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-10 -right-10 h-32 w-32 rounded-full bg-white/10"></div>
                <div class="relative">
                  <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm transition-transform group-hover:scale-110">
                    <i data-lucide="{{ $cardIcon }}" class="h-7 w-7"></i>
                  </div>
                  <h3 class="mb-2 line-clamp-2 text-xl font-black leading-relaxed">{{ $survey->title }}</h3>
                  <p class="line-clamp-2 text-sm leading-6 text-teal-50">{{ $survey->description }}</p>
                </div>
              </div>

              <div class="flex flex-1 flex-col justify-between p-5">
                <div>
                  <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-bold text-gray-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-2">
                      <i data-lucide="file-text" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                      <span title="{{ $formatNumber($survey->sections->count()) }}">{{ $compactNumber($survey->sections->count()) }}</span> {{ __('sections_count') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                      <i data-lucide="clipboard-check" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                      <span title="{{ $formatNumber($totalQuestions) }}">{{ $compactNumber($totalQuestions) }}</span> {{ __('questions_count') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                      <i data-lucide="clock" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                      ~<span title="{{ $formatNumber($estimatedTime) }}">{{ $compactNumber($estimatedTime) }}</span> {{ __('minutes') }}
                    </span>
                  </div>

                  <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($survey->sections->take(4) as $section)
                      @php
                        $iconName = $section->icon ?: 'clipboard-check';
                        $iconName = $iconName === 'door-open' ? 'door-closed' : $iconName;
                      @endphp
                      <span class="inline-flex items-center gap-1.5 rounded-full border border-transparent bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-600 dark:border-slate-700/50 dark:bg-slate-800/60 dark:text-slate-300">
                        <i data-lucide="{{ $iconName }}" class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400"></i>
                        {{ $section->title }}
                      </span>
                    @endforeach
                    @if ($survey->sections->count() > 4)
                      <span class="inline-flex items-center rounded-full bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">
                        +<span title="{{ $formatNumber($survey->sections->count() - 4) }}">{{ $compactNumber($survey->sections->count() - 4) }}</span> {{ __('more') }}
                      </span>
                    @endif
                  </div>
                </div>

                <div class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-linear-to-r from-teal-600 to-emerald-600 px-4 py-3 font-black text-white shadow-lg shadow-teal-200 transition-all duration-300 group-hover:-translate-y-0.5 group-hover:shadow-xl dark:shadow-teal-950/20">
                  <i data-lucide="check-circle-2" class="h-5 w-5"></i>
                  <span>{{ __('start_survey') }}</span>
                  <i data-lucide="{{ app()->getLocale() === 'ar' ? 'chevron-left' : 'chevron-right' }}" class="h-5 w-5 transition-transform rtl:group-hover:translate-x-1 ltr:group-hover:-translate-x-1"></i>
                </div>
              </div>
            </a>
          @endforeach
        </section>
      @endif

      @if(!$isKiosk)
      <div class="mt-8 text-center">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-black text-gray-500 transition-colors hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
          <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="h-4 w-4"></i>
          <span>{{ __('homepage') }}</span>
        </a>
      </div>
      @endif

      @if($isKiosk)
      <a href="{{ route('dashboard.kiosk.exit') }}" class="fixed top-24 left-4 p-4 rounded-full bg-slate-800 hover:bg-slate-700 text-white transition-all z-[9999] shadow-2xl group flex items-center justify-center opacity-100">
        <i data-lucide="lock" class="w-6 h-6"></i>
      </a>
      @endif
    </main>
  </div>
@endsection
