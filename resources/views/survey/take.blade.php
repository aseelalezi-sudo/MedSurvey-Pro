@extends('layouts.web')

@php
  $hideHeader = true;
  $isAr = app()->getLocale() === 'ar';
  $requiredQuestions = [];
  $surveySettings = $settings['surveySettings'] ?? [];
  $allowAnonymous = (bool) ($surveySettings['allowAnonymous'] ?? true);
  $requireAllQuestions = (bool) ($surveySettings['requireAllQuestions'] ?? false);
  $requireName = ! $allowAnonymous && ((bool) ($surveySettings['requireName'] ?? false) || (bool) $survey->requireName);
  $requirePhone = ! $allowAnonymous && ((bool) ($surveySettings['requirePhone'] ?? false) || (bool) $survey->requirePhone);
  $showProgressBar = (bool) ($surveySettings['showProgressBar'] ?? true);
  $enableThankYouPage = (bool) ($surveySettings['enableThankYouPage'] ?? true);
  $showLanguageToggle = ($settings['appearance']['showLanguageToggle'] ?? true) !== false;
  $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
  $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
  foreach ($survey->sections as $sectionIdx => $section) {
      $questions = $requireAllQuestions ? $section->questions : $section->questions->where('required', true);
      $requiredQuestions[$sectionIdx] = $questions->pluck('id')->values();
  }
  $isKiosk = session('kiosk_mode', false);
@endphp

@section('title', $survey->title . ' - ' . __('submit'))

@section('content')
  <div
    x-data="{
      step: 0,
      activeSection: 0,
      surveyId: @js($survey->id),
      requireName: @js($requireName),
      requirePhone: @js($requirePhone),
      requireAllQuestions: @js($requireAllQuestions),
      enableThankYouPage: @js($enableThankYouPage),
      requiredQuestions: @js($requiredQuestions),
      patientInfo: { name: '', phone: '', department: '', ageGroup: '', gender: '', visitType: '' },
      answers: {},
      validationErrors: {},
      phoneError: '',
      texts: {
        phoneStart: @js(__('phone_start_with_7')),
        phoneEnterMore: @js(__('phone_enter_more', ['count' => '__COUNT__'])),
        nameRequired: @js($isAr ? 'الاسم مطلوب للتقييم' : 'Name is required to continue'),
        phoneRequired: @js($isAr ? 'رقم الهاتف مطلوب ويجب أن يبدأ بالرقم 7 ويتكون من 9 أرقام' : 'Phone number is required, must start with 7, and must be 9 digits'),
        phoneInvalid: @js($isAr ? 'رقم الهاتف غير صحيح' : 'Phone number is invalid'),
        departmentRequired: @js($isAr ? 'يرجى اختيار القسم' : 'Please select a department'),
        ageGroupRequired: @js($isAr ? 'يرجى اختيار الفئة العمرية' : 'Please select an age group'),
        genderRequired: @js($isAr ? 'يرجى تحديد الجنس' : 'Please select a gender'),
        visitTypeRequired: @js($isAr ? 'يرجى اختيار نوع الزيارة' : 'Please select a visit type'),
        submitError: @js($isAr ? 'حدث خطأ أثناء حفظ التقييم. يرجى المحاولة مرة أخرى.' : 'An error occurred while saving your survey. Please try again.'),
        networkError: @js($isAr ? 'تعذر الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.' : 'Could not connect to the server. Please check your internet connection.'),
      },
      isSubmitting: false,
      timeLeft: 180,
      formattedTime: '03:00',
      paused: false,
      resumeTimeout: null,
      _startedAt: Date.now(),
      init() {
        setInterval(() => {
          if (!this.paused) {
            if (this.timeLeft > 0) {
              this.timeLeft--;
              const min = Math.floor(this.timeLeft / 60).toString().padStart(2, '0');
              const sec = (this.timeLeft % 60).toString().padStart(2, '0');
              this.formattedTime = `${min}:${sec}`;
            } else {
              window.location.href = @js($isKiosk) ? '{{ route('survey.selection') }}' : '{{ route('home') }}';
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
      },
      setName(value) {
        this.patientInfo.name = value.replace(/[^\u0621-\u064A\u0671-\u06D3a-zA-Z\s]/g, '');
      },
      setPhone(value) {
        const limited = value.replace(/\D/g, '').slice(0, 9);
        this.patientInfo.phone = limited;
        if (limited.length > 0 && !limited.startsWith('7')) {
          this.phoneError = this.texts.phoneStart;
        } else if (limited.length > 0 && limited.length < 9) {
          this.phoneError = this.texts.phoneEnterMore.replace('__COUNT__', 9 - limited.length);
        } else {
          this.phoneError = '';
        }
      },
      isPhoneValid() {
        if (!this.patientInfo.phone) return !this.requirePhone;
        return this.patientInfo.phone.length === 9 && this.patientInfo.phone.startsWith('7');
      },
      get isPatientInfoValid() {
        return (!this.requireName || this.patientInfo.name.trim().length > 0)
          && this.isPhoneValid()
          && this.patientInfo.department
          && this.patientInfo.gender
          && this.patientInfo.ageGroup
          && this.patientInfo.visitType;
      },
      validateStep0() {
        this.validationErrors = {};
        if (this.requireName && !this.patientInfo.name.trim()) this.validationErrors.name = this.texts.nameRequired;
        if (!this.isPhoneValid()) this.validationErrors.phone = this.requirePhone ? this.texts.phoneRequired : this.texts.phoneInvalid;
        if (!this.patientInfo.department) this.validationErrors.department = this.texts.departmentRequired;
        if (!this.patientInfo.ageGroup) this.validationErrors.ageGroup = this.texts.ageGroupRequired;
        if (!this.patientInfo.gender) this.validationErrors.gender = this.texts.genderRequired;
        if (!this.patientInfo.visitType) this.validationErrors.visitType = this.texts.visitTypeRequired;
        if (Object.keys(this.validationErrors).length === 0) {
          this.step = 1;
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      setAnswer(questionId, value) {
        this.answers[questionId] = value;
        this.$nextTick(() => window.lucide && window.lucide.createIcons());
      },
      isSectionComplete(idx) {
        const qIds = this.requiredQuestions[idx] || [];
        return qIds.every((id) => {
          const val = this.answers[id];
          return val !== undefined && val !== null && val !== '' && val !== -1;
        });
      },
      isSectionUnlocked(idx) {
        if (idx === 0) return true;
        return this.isSectionComplete(idx - 1);
      },
      get progressPercentage() {
        const totalSections = {{ max($survey->sections->count(), 1) }};
        if (this.step === 0) return 0;
        return Math.round(((this.activeSection + 1) / totalSections) * 100);
      },
      get progressColor() {
        const pct = this.progressPercentage;
        if (pct >= 80) return 'from-emerald-500 to-green-500';
        if (pct >= 40) return 'from-amber-500 to-yellow-500';
        if (pct >= 1)  return 'from-orange-500 to-red-500';
        return 'from-teal-500 to-emerald-600';
      },
      async submitSurvey() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;
        const payload = {
          surveyId: this.surveyId,
          department: this.patientInfo.department,
          patientInfo: this.patientInfo,
          _startedAt: this._startedAt,
          _website: this.$refs.websiteHoneypot.value,
          answers: Object.keys(this.answers).map((questionId) => ({
            questionId,
            value: String(this.answers[questionId])
          }))
        };
        try {
          const response = await fetch('/survey/responses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(payload)
          });
          if (response.ok) {
            const result = await response.json().catch(() => ({}));
            window.location.href = result.redirectUrl || (this.enableThankYouPage ? '{{ route('survey.thanks') }}' : (@js($isKiosk) ? '{{ route('survey.selection') }}' : '{{ route('home') }}'));
            return;
          }
          const errData = await response.json();
          alert(errData.message || this.texts.submitError);
        } catch (e) {
          alert(this.texts.networkError);
        }
        this.isSubmitting = false;
      }
    }"
    class="min-h-screen bg-linear-to-r from-teal-50 via-white to-blue-50 text-gray-900 transition-colors duration-300 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] dark:text-slate-100"
  >
    <header x-show="step === 1" class="fixed left-0 right-0 top-0 z-50 border-b border-gray-100 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/95" style="display:none">
      <div x-show="@js($showProgressBar)" class="h-2 bg-gray-100 dark:bg-slate-800 rounded-full mx-3 mt-1.5 sm:mx-4 sm:mt-2">
        <div class="h-full rounded-full transition-all duration-700 ease-out" :class="'bg-linear-to-r ' + progressColor" :style="'width: ' + progressPercentage + '%'"></div>
      </div>
      <div class="mx-auto flex max-w-4xl items-center justify-between gap-2 px-3 py-3 sm:px-4">
        <div class="flex min-w-0 items-center gap-2 sm:gap-4">
          <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
            <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg drop-shadow-sm">
              <img src="/system-logo.png" alt="MedSurvey Pro" class="h-full w-full object-contain">
            </div>
            <span class="hidden text-sm font-bold text-gray-700 dark:text-slate-200 sm:block">MedSurvey Pro</span>
          </div>
          <div class="hidden h-6 w-px bg-gray-200 dark:bg-slate-800 sm:block"></div>
          <div class="flex min-w-0 items-center gap-1.5">
            @if(!empty($settings['hospital']['logo']))
              <div class="flex shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white p-0.5 shadow-md dark:border-slate-600">
                <img src="{{ $settings['hospital']['logo'] }}" alt="{{ $settings['hospital']['name'] ?? '' }}" class="h-5 max-w-[50px] rounded-md object-contain transition-transform duration-300 hover:scale-105 sm:h-6 sm:max-w-[64px]">
              </div>
            @else
              <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-teal-200 bg-teal-50 text-teal-600 dark:border-teal-900 dark:bg-teal-950/20 dark:text-teal-400">
                <i data-lucide="heart" class="h-3 w-3"></i>
              </div>
            @endif
            <div class="min-w-0 text-start">
              @php $hospitalMobileName = $settings['hospital']['shortName'] ?? ($settings['hospital']['name'] ?? ''); @endphp
              <span class="block truncate text-xs font-semibold text-teal-700 dark:text-teal-400">
                <span class="sm:hidden">{{ $hospitalMobileName }}</span>
                <span class="hidden sm:inline">{{ $settings['hospital']['name'] ?? '' }}</span>
              </span>
              <div class="truncate text-[9px] leading-none text-gray-400 dark:text-slate-500">{{ $settings['hospital']['operatingTitle'] ?? __('settings_placeholder_operating_hospital') }}</div>
            </div>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5 sm:gap-4">
          @if(!$isKiosk)
          @if($showLanguageToggle)
          <!-- Language Switcher -->
          <div class="flex items-center">
            @if(app()->getLocale() === 'ar')
              <form method="POST" action="{{ route('set-locale', 'en') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-350 hover:text-teal-600 dark:hover:text-teal-400 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-all cursor-pointer font-bold border border-transparent">
                <i data-lucide="globe" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
                <span>English</span>
              </button>
                  </form>
            @else
              <form method="POST" action="{{ route('set-locale', 'ar') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-slate-350 hover:text-teal-600 dark:hover:text-teal-400 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-all cursor-pointer font-bold border border-transparent">
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

          <!-- Clock -->
          <div class="flex items-center gap-1.5 rounded-xl border border-teal-100 bg-teal-50 px-3 py-2 text-xs font-black text-teal-700 dark:border-teal-900/40 dark:bg-teal-950/30 dark:text-teal-400" dir="ltr">
            <i data-lucide="clock" class="h-3.5 w-3.5"></i>
            <span x-text="formattedTime">05:00</span>
          </div>

          <!-- Section Indicators Count -->
          <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
            <span class="font-bold text-teal-600 dark:text-teal-400" x-text="activeSection + 1">1</span>
            <span>{{ __('of') }}</span>
            <span title="{{ $formatNumber($survey->sections->count()) }}">{{ $compactNumber($survey->sections->count()) }}</span>
          </div>
        </div>
      </div>
    </header>

    <input type="text" x-ref="websiteHoneypot" style="display:none !important" tabindex="-1" autocomplete="off">

    <section x-show="step === 0" class="flex min-h-screen items-center justify-center p-4 animate-scale-in">
      <div class="w-full max-w-2xl">
        <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xl dark:border-slate-800/80 dark:bg-slate-900">
          <div class="bg-linear-to-l from-teal-600 to-emerald-600 px-6 py-6 text-start text-white sm:px-8">
            <div class="mb-4 flex min-w-0 items-center justify-between gap-3">
              <div class="flex min-w-0 items-center gap-2">
                @if(!empty($settings['hospital']['logo']))
                  <div class="flex shrink-0 items-center justify-center rounded-lg border border-white/30 bg-white p-0.5 shadow-md">
                    <img src="{{ $settings['hospital']['logo'] }}" alt="{{ $settings['hospital']['name'] ?? '' }}" class="h-7 max-w-[64px] rounded-md object-contain sm:h-8 sm:max-w-[88px]">
                  </div>
                @else
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/20">
                    <i data-lucide="heart" class="h-4 w-4 text-white"></i>
                  </div>
                @endif
                <div class="min-w-0 text-start">
                  @php $hospitalMobileName = $settings['hospital']['shortName'] ?? ($settings['hospital']['name'] ?? ''); @endphp
                  <span class="block truncate text-sm font-bold leading-none tracking-wide">
                    <span class="sm:hidden">{{ $hospitalMobileName }}</span>
                    <span class="hidden sm:inline">{{ $settings['hospital']['name'] ?? '' }}</span>
                  </span>
                  <span class="mt-1 block truncate text-[10px] leading-none text-teal-100">{{ $settings['hospital']['operatingTitle'] ?? __('settings_placeholder_operating_hospital') }}</span>
                </div>
              </div>
              <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <!-- Clock -->
                <div class="flex select-none items-center gap-1.5 rounded-xl border border-white/10 bg-white/15 px-3 py-2 text-xs font-black text-white shadow-sm transition-all hover:bg-white/20" dir="ltr">
                  <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                  <span x-text="formattedTime">05:00</span>
                </div>

                @if(!$isKiosk)
                @if($showLanguageToggle)
                <!-- Language Switcher -->
                <div class="flex items-center">
                  @if(app()->getLocale() === 'ar')
                    <form method="POST" action="{{ route('set-locale', 'en') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-white/80 hover:text-white px-2.5 py-1.5 rounded-xl border border-white/10 bg-white/15 hover:bg-white/20 transition-all cursor-pointer font-bold">
                      <i data-lucide="globe" class="w-3.5 h-3.5 text-teal-300"></i>
                      <span>English</span>
                    </button>
                  </form>
                  @else
                    <form method="POST" action="{{ route('set-locale', 'ar') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-white/80 hover:text-white px-2.5 py-1.5 rounded-xl border border-white/10 bg-white/15 hover:bg-white/20 transition-all cursor-pointer font-bold">
                      <i data-lucide="globe" class="w-3.5 h-3.5 text-teal-300"></i>
                      <span>العربية</span>
                    </button>
                  </form>
                  @endif
                </div>
                @endif

                <!-- Theme Toggler -->
                <button type="button" @click="toggleTheme()" class="flex items-center justify-center rounded-xl border border-white/10 bg-white/15 p-2.5 shadow-sm transition-all hover:bg-white/20" title="{{ __('toggle_theme') }}">
                  <span x-show="theme === 'light'">
                    <i data-lucide="moon" class="h-4 w-4 text-white"></i>
                  </span>
                  <span x-show="theme === 'dark'" style="display: none;">
                    <i data-lucide="sun" class="h-4 w-4 text-amber-300"></i>
                  </span>
                </button>
                @endif
              </div>
            </div>

            <div class="border-t border-white/10 pt-4">
              <h2 class="mb-1 text-xl font-bold sm:text-2xl">{{ __('patient_info') }}</h2>
              <p class="text-sm text-teal-100">{{ __('please_fill_info') }}</p>
            </div>
          </div>

          <div class="space-y-6 p-6 sm:p-8">
            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="user" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('full_name') }}
                <template x-if="requireName"><span class="text-red-500">*</span></template>
                <template x-if="!requireName"><span class="text-xs font-normal text-gray-400">{{ __('optional') }}</span></template>
              </label>
              <input type="text" :value="patientInfo.name" @input="setName($event.target.value); $event.target.value = patientInfo.name" placeholder="{{ __('full_name_placeholder') }}" class="w-full rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-teal-500 focus:ring-4 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-600">
              <p x-show="validationErrors.name" x-text="validationErrors.name" class="text-xs font-bold text-red-500" style="display:none"></p>
            </div>

            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="phone" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('phone_number') }}
                <template x-if="requirePhone"><span class="text-red-500">*</span></template>
                <template x-if="!requirePhone"><span class="text-xs font-normal text-gray-400">{{ __('optional') }}</span></template>
              </label>
              <input type="tel" inputmode="numeric" :value="patientInfo.phone" @input="setPhone($event.target.value); $event.target.value = patientInfo.phone" placeholder="7XXXXXXXX" maxlength="9" dir="ltr" :class="phoneError || validationErrors.phone ? 'border-red-300 focus:border-red-500 focus:ring-red-100' : (patientInfo.phone.length === 9 && isPhoneValid() ? 'border-green-300 focus:border-green-500 focus:ring-green-100' : 'border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-teal-100')" class="w-full rounded-xl border-2 bg-white px-4 py-3 text-left tracking-wider text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:ring-4 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-600">
              <div class="mt-1.5 flex items-center justify-between">
                <div>
                  <p x-show="phoneError" class="flex items-center gap-1 text-xs text-red-500 animate-slide-up" style="display:none">
                    <i data-lucide="circle-alert" class="h-3 w-3"></i>
                    <span x-text="phoneError"></span>
                  </p>
                  <p x-show="!phoneError && patientInfo.phone.length === 9 && isPhoneValid()" class="text-xs text-green-500" style="display:none">✓ {{ __('phone_correct') }}</p>
                  <p x-show="validationErrors.phone && !phoneError" x-text="validationErrors.phone" class="text-xs font-bold text-red-500" style="display:none"></p>
                </div>
                <span :class="patientInfo.phone.length === 9 ? 'text-green-500' : 'text-gray-400 dark:text-slate-500'" class="text-xs font-medium"><span x-text="patientInfo.phone.length">0</span>/9</span>
              </div>
            </div>

            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="user" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('gender') }} <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2">
                <button type="button" @click="patientInfo.gender = 'ذكر'" :class="patientInfo.gender === 'ذكر' ? 'survey-choice-active' : ''" class="survey-choice">👨 {{ __('male') }}</button>
                <button type="button" @click="patientInfo.gender = 'أنثى'" :class="patientInfo.gender === 'أنثى' ? 'survey-choice-active' : ''" class="survey-choice">👩 {{ __('female') }}</button>
              </div>
              <p x-show="validationErrors.gender" x-text="validationErrors.gender" class="text-xs font-bold text-red-500" style="display:none"></p>
            </div>

            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="calendar" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('age_group') }} <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 sm:grid-cols-3">
                @foreach ($settings['ageGroups'] ?? [] as $age)
                  @if ($age['isActive'] ?? true)
                    <button type="button" @click="patientInfo.ageGroup = @js($age['label'])" :class="patientInfo.ageGroup === @js($age['label']) ? 'survey-choice-active' : ''" class="survey-choice">{{ $age['label'] }}</button>
                  @endif
                @endforeach
              </div>
              <p x-show="validationErrors.ageGroup" x-text="validationErrors.ageGroup" class="text-xs font-bold text-red-500" style="display:none"></p>
            </div>

            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="building-2" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('department') }} <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 sm:grid-cols-3">
                @foreach ($settings['departments'] ?? [] as $dept)
                  @if ($dept['isActive'] ?? true)
                    <button type="button" @click="patientInfo.department = @js($dept['name'])" :class="patientInfo.department === @js($dept['name']) ? 'survey-choice-active' : ''" class="survey-choice">{{ $dept['name'] }}</button>
                  @endif
                @endforeach
              </div>
              <p x-show="validationErrors.department" x-text="validationErrors.department" class="text-xs font-bold text-red-500" style="display:none"></p>
            </div>

            <div class="space-y-3 text-start">
              <label class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-300">
                <i data-lucide="activity" class="h-4 w-4 text-teal-600 dark:text-teal-400"></i>
                {{ __('visit_type') }} <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 sm:grid-cols-3">
                @foreach ($settings['visitTypes'] ?? [] as $visit)
                  @if ($visit['isActive'] ?? true)
                    <button type="button" @click="patientInfo.visitType = @js($visit['label'])" :class="patientInfo.visitType === @js($visit['label']) ? 'survey-choice-active' : ''" class="survey-choice">{{ $visit['label'] }}</button>
                  @endif
                @endforeach
              </div>
              <p x-show="validationErrors.visitType" x-text="validationErrors.visitType" class="text-xs font-bold text-red-500" style="display:none"></p>
            </div>
          </div>

          <div class="flex flex-col-reverse items-stretch justify-between gap-3 px-6 pb-6 min-[380px]:flex-row min-[380px]:items-center sm:px-8 sm:pb-8">
            <a href="{{ route('survey.selection') }}" class="flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
              <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="h-4 w-4"></i>
              {{ __('back') }}
            </a>
            <button type="button" @click="validateStep0()" :disabled="!isPatientInfoValid" :class="isPatientInfoValid ? 'bg-linear-to-l from-teal-600 to-emerald-600 shadow-lg shadow-teal-200 hover:shadow-xl hover:-translate-y-0.5 dark:shadow-teal-950/20' : 'bg-gray-300 text-gray-500 shadow-none dark:bg-slate-800 dark:text-slate-500 cursor-not-allowed'" class="flex items-center justify-center gap-2 rounded-xl px-8 py-3 font-bold text-white transition-all duration-300">
              {{ __('next') }}
              <i data-lucide="{{ app()->getLocale() === 'ar' ? 'chevron-left' : 'chevron-right' }}" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </div>
    </section>

    <div x-show="step === 1" class="px-4 pb-4 pt-20" style="display:none">
      <div class="mx-auto max-w-4xl">
        <div class="flex w-full snap-x snap-mandatory flex-nowrap items-center justify-start gap-1 overflow-x-auto pb-2 sm:gap-2">
          @foreach ($survey->sections as $sectionIdx => $section)
            @php
              $sectionIcon = $section->icon ?: 'clipboard-check';
              $sectionIcon = $sectionIcon === 'door-open' ? 'door-closed' : $sectionIcon;
            @endphp
            <button type="button" @click="if (isSectionUnlocked({{ $sectionIdx }})) { activeSection = {{ $sectionIdx }}; window.scrollTo({ top: 0, behavior: 'smooth' }); }" :disabled="!isSectionUnlocked({{ $sectionIdx }})" :class="activeSection === {{ $sectionIdx }} ? 'bg-teal-500 text-white shadow-md ring-2 ring-teal-500/30 font-bold' : (isSectionComplete({{ $sectionIdx }}) ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 border border-gray-200 dark:border-slate-700')" class="flex shrink-0 snap-start items-center gap-1 rounded-full px-2.5 py-1.5 text-[11px] font-medium transition-all disabled:cursor-not-allowed disabled:opacity-60 sm:gap-1.5 sm:px-3.5 sm:py-2 sm:text-xs">
              <i data-lucide="{{ $sectionIcon }}" class="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4"></i>
              <span class="font-bold">{{ $section->title }}</span>
            </button>
          @endforeach
        </div>
      </div>
    </div>

    <main x-show="step === 1" class="mx-auto max-w-4xl px-4 pb-24 text-center" style="display:none">
      @foreach ($survey->sections as $sectionIdx => $section)
        @php
          $sectionIconName = $section->icon ?: 'clipboard-check';
          $sectionIconName = $sectionIconName === 'door-open' ? 'door-closed' : $sectionIconName;
        @endphp
        <section x-show="activeSection === {{ $sectionIdx }}" class="space-y-6 animate-slide-up">
          <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-linear-to-r from-teal-500 to-emerald-600 text-white shadow-xl shadow-teal-200 dark:shadow-teal-950/30">
              <i data-lucide="{{ $sectionIconName }}" class="h-8 w-8"></i>
            </div>
            <h2 class="mb-2 text-2xl font-black text-gray-900 dark:text-white sm:text-3xl">{{ $section->title }}</h2>
            <p class="mx-auto max-w-xl text-sm leading-7 text-gray-500 dark:text-slate-400">{{ $section->description }}</p>
          </div>

          <div class="space-y-6">
            @foreach ($section->questions as $questionIdx => $question)
              <div class="space-y-4">
                <article class="rounded-2xl border border-gray-100 bg-white p-4 text-start shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900 sm:p-8 animate-slide-up" style="animation-delay: {{ $questionIdx * 100 }}ms;">
                  <div class="mb-6 flex items-start gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-xs font-bold text-teal-700 dark:bg-teal-950/60 dark:text-teal-400">{{ $questionIdx + 1 }}</span>
                    <div class="min-w-0 flex-1">
                      <h3 class="text-start text-base font-bold leading-relaxed text-gray-800 dark:text-white sm:text-lg">
                        {{ $question->title }}
                        @if ($requireAllQuestions || $question->required)
                          <span class="mr-1 text-red-500">*</span>
                        @endif
                      </h3>
                      @if ($question->description)
                        <p class="mt-1 text-start text-sm leading-6 text-gray-500 dark:text-slate-400">{{ $question->description }}</p>
                      @endif
                    </div>
                  </div>

                  @if ($question->type === 'nps')
                    <div class="py-4">
                      <div class="grid grid-cols-6 min-[420px]:grid-cols-11 gap-1.5 sm:gap-2.5 justify-center">
                        @for ($n = 0; $n <= 10; $n++)
                          @php
                            if ($n <= 6) {
                                $btnColorClass = "answers['{$question->id}'] === {$n} ? 'bg-red-500 text-white border-red-500 shadow-lg shadow-red-500/25 scale-110' : 'bg-white dark:bg-slate-900 text-red-650 dark:text-red-400 border-red-200/80 dark:border-red-900/40 hover:bg-red-50 dark:hover:bg-red-950/20 hover:border-red-300'";
                            } elseif ($n <= 8) {
                                $btnColorClass = "answers['{$question->id}'] === {$n} ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/25 scale-110' : 'bg-white dark:bg-slate-900 text-amber-650 dark:text-amber-400 border-amber-200/80 dark:border-amber-900/40 hover:bg-amber-50 dark:hover:bg-amber-950/20 hover:border-amber-300'";
                            } else {
                                $btnColorClass = "answers['{$question->id}'] === {$n} ? 'bg-emerald-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/25 scale-110' : 'bg-white dark:bg-slate-900 text-emerald-650 dark:text-emerald-400 border-emerald-200/80 dark:border-emerald-900/40 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 hover:border-emerald-300'";
                            }
                          @endphp
                          <button
                            type="button"
                            @click="setAnswer('{{ $question->id }}', {{ $n }})"
                            :class="{{ $btnColorClass }}"
                            class="aspect-square flex h-10 w-full items-center justify-center rounded-2xl border-2 text-sm font-black transition-all duration-300 hover:scale-105 cursor-pointer sm:text-base"
                          >
                            {{ $n }}
                          </button>
                        @endfor
                      </div>
                      <div class="flex justify-between gap-3 mt-4 px-2 text-xs font-bold sm:text-sm">
                        <span class="text-red-500">{{ __('nps_never_recommend') }}</span>
                        <span class="text-emerald-500">{{ __('nps_definitely_recommend') }}</span>
                      </div>
                    </div>
                  @elseif ($question->type === 'stars' || $question->type === 'rating')
                    <div x-data="{ hoverRating: 0 }" class="flex flex-wrap items-center justify-center gap-1.5 py-4 sm:gap-2">
                      @for ($s = 1; $s <= 5; $s++)
                        <button
                          type="button"
                          @click="setAnswer('{{ $question->id }}', {{ $s }})"
                          @mouseenter="hoverRating = {{ $s }}"
                          @mouseleave="hoverRating = 0"
                          :class="hoverRating > 0
                            ? (hoverRating >= {{ $s }} ? 'text-amber-400 drop-shadow-[0_0_10px_rgba(251,191,36,0.6)] scale-110 animate-pulse' : 'text-gray-300 dark:text-slate-700')
                            : (answers['{{ $question->id }}'] >= {{ $s }} ? 'text-amber-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.4)] scale-105' : 'text-gray-300 dark:text-slate-700')"
                          class="survey-star-button transition-all duration-200 cursor-pointer"
                        >
                          <svg class="survey-star-icon transition-all duration-200" :class="(hoverRating > 0 ? hoverRating >= {{ $s }} : answers['{{ $question->id }}'] >= {{ $s }}) ? 'fill-amber-400 stroke-amber-400' : 'fill-transparent stroke-current'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.907c.969 0 1.371 1.24.588 1.81l-3.97 2.883a1 1 0 00-.364 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.971-2.883a1 1 0 00-1.18 0l-3.97 2.883c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.364-1.118L2.98 10.1c-.783-.57-.38-1.81.588-1.81h4.906a1 1 0 00.95-.69l1.519-4.674z" />
                          </svg>
                        </button>
                      @endfor
                    </div>
                  @elseif ($question->type === 'emoji')
                    @php
                      $emojis = [
                        1 => ['char' => '😡', 'label' => $isAr ? 'سيء جدا' : 'Very bad', 'active' => 'bg-red-100 border-red-400 text-red-600 emoji-shadow-red'],
                        2 => ['char' => '😞', 'label' => $isAr ? 'سيء' : 'Bad', 'active' => 'bg-orange-100 border-orange-400 text-orange-600 emoji-shadow-orange'],
                        3 => ['char' => '😐', 'label' => $isAr ? 'مقبول' : 'Acceptable', 'active' => 'bg-yellow-100 border-yellow-400 text-yellow-600 emoji-shadow-yellow'],
                        4 => ['char' => '🙂', 'label' => $isAr ? 'جيد' : 'Good', 'active' => 'bg-lime-100 border-lime-400 text-lime-700 emoji-shadow-lime'],
                        5 => ['char' => '😍', 'label' => $isAr ? 'ممتاز' : 'Excellent', 'active' => 'bg-green-100 border-green-400 text-green-700 emoji-shadow-green'],
                      ];
                    @endphp
                    <div class="grid grid-cols-2 gap-2 py-4 min-[430px]:grid-cols-5 sm:gap-3">
                      @foreach ($emojis as $val => $emoji)
                        <button
                          type="button"
                          @click="setAnswer('{{ $question->id }}', {{ $val }})"
                          :class="answers['{{ $question->id }}'] === {{ $val }} ? 'is-active {{ $emoji['active'] }}' : 'hover:scale-105 hover:border-gray-300 dark:hover:border-slate-600'"
                          class="survey-emoji-button"
                        >
                          <span
                            :class="answers['{{ $question->id }}'] === {{ $val }} ? 'scale-110 drop-shadow-md' : ''"
                            class="text-3xl leading-none transition-transform duration-200 sm:text-4xl"
                          >{{ $emoji['char'] }}</span>
                          <span
                            :class="answers['{{ $question->id }}'] === {{ $val }} ? 'font-bold' : 'text-gray-500 dark:text-slate-400'"
                            class="break-words text-center text-xs transition-all duration-200"
                          >{{ $emoji['label'] }}</span>
                        </button>
                      @endforeach
                    </div>
                  @elseif ($question->type === 'yes_no')
                    <div class="flex justify-center items-center gap-6 py-4">
                      <button
                        type="button"
                        @click="setAnswer('{{ $question->id }}', 'yes')"
                        :class="answers['{{ $question->id }}'] === 'yes' ? 'bg-teal-600 text-white border-teal-600 scale-105 shadow-lg shadow-teal-500/20 dark:bg-teal-500 dark:border-teal-500 dark:text-slate-950' : 'bg-transparent text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50'"
                        class="rounded-2xl border-2 px-10 py-4 text-base font-black transition-all duration-300 sm:px-12 sm:py-4.5 sm:text-lg cursor-pointer"
                      >
                        {{ __('yes') }}
                      </button>
                      <button
                        type="button"
                        @click="setAnswer('{{ $question->id }}', 'no')"
                        :class="answers['{{ $question->id }}'] === 'no' ? 'bg-red-600 text-white border-red-600 scale-105 shadow-lg shadow-red-500/20 dark:bg-red-500 dark:border-red-500 dark:text-white' : 'bg-transparent text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50'"
                        class="rounded-2xl border-2 px-10 py-4 text-base font-black transition-all duration-300 sm:px-12 sm:py-4.5 sm:text-lg cursor-pointer"
                      >
                        {{ __('no') }}
                      </button>
                    </div>
                  @elseif ($question->type === 'multiple_choice')
                    <div class="grid grid-cols-1 gap-3 py-2 sm:grid-cols-2">
                      @foreach ($question->options ?: [] as $option)
                        @php
                          $optLabel = is_array($option) ? ($option['label'] ?? '') : $option;
                          $optValue = is_array($option) ? ($option['value'] ?? '') : $option;
                        @endphp
                        <button type="button" @click="setAnswer('{{ $question->id }}', @js($optValue))" :class="answers['{{ $question->id }}'] === @js($optValue) ? 'bg-teal-550 dark:bg-teal-950/20 border-teal-500 text-teal-700 dark:text-teal-400 shadow-md font-bold' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800'" class="flex min-w-0 items-center gap-3 rounded-xl border-2 p-4 text-right transition-all">
                          <span :class="answers['{{ $question->id }}'] === @js($optValue) ? 'bg-teal-500 border-teal-500' : 'border-gray-300 dark:border-slate-700'" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2">
                            <i x-show="answers['{{ $question->id }}'] === @js($optValue)" data-lucide="check" class="h-4 w-4 text-white" style="display:none"></i>
                          </span>
                          <span class="min-w-0 break-words text-sm font-bold sm:text-base">{{ $optLabel }}</span>
                        </button>
                      @endforeach
                    </div>
                  @elseif ($question->type === 'text')
                    <textarea @input="setAnswer('{{ $question->id }}', $event.target.value)" x-model="answers['{{ $question->id }}']" rows="3" placeholder="{{ app()->getLocale() === 'ar' ? 'اكتب ملاحظاتك أو تعليقاتك هنا...' : 'Write your notes or comments here...' }}" class="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-hidden dark:border-slate-800 dark:text-white"></textarea>
                  @endif
                </article>

                @if (in_array($question->type, ['stars', 'emoji', 'rating'], true))
                  <div x-show="[1, 2].includes(Number(answers['{{ $question->id }}']))" class="mx-4 rounded-2xl border border-amber-100 bg-amber-50 p-6 shadow-inner animate-slide-up dark:border-amber-900/30 dark:bg-amber-950/15 sm:p-8" style="display:none">
                    <div class="mb-4 flex items-start gap-3 text-start">
                      <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-200 dark:bg-amber-950/45">
                        <i data-lucide="message-square" class="h-3.5 w-3.5 text-amber-700 dark:text-amber-400"></i>
                      </div>
                      <h4 class="text-sm font-bold text-amber-900 dark:text-amber-300">{{ __('follow_up_reason') }}</h4>
                    </div>
                    <textarea @input="setAnswer('{{ $question->id }}_reason', $event.target.value)" x-model="answers['{{ $question->id }}_reason']" rows="3" placeholder="{{ __('explain_reason') }}" class="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm text-slate-800 focus:border-teal-500 focus:outline-hidden dark:border-slate-800 dark:text-white"></textarea>
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        </section>
      @endforeach
    </main>

    <nav x-show="step === 1" class="fixed bottom-0 left-0 right-0 z-40 border-t border-gray-100 bg-white/90 backdrop-blur-md transition-colors duration-300 dark:border-slate-800/80 dark:bg-slate-900/95" style="display:none">
      <div class="mx-auto grid max-w-4xl grid-cols-[1fr_auto_1fr] items-center gap-2 px-3 py-3 sm:px-4 sm:py-4">
        <button type="button" @click="if (activeSection > 0) { activeSection--; window.scrollTo({ top: 0, behavior: 'smooth' }); } else { step = 0; }" class="justify-self-start flex min-w-0 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 transition-all hover:bg-gray-100 hover:text-gray-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white sm:gap-2 sm:px-5 sm:py-3 sm:text-base">
          <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="h-4 w-4"></i>
          <span class="truncate">{{ __('previous') }}</span>
        </button>

        <div x-show="@js($showProgressBar)" class="hidden items-center gap-1.5 min-[360px]:flex">
          @foreach ($survey->sections as $sectionIdx => $section)
            <button type="button" @click="if (isSectionUnlocked({{ $sectionIdx }})) { activeSection = {{ $sectionIdx }}; window.scrollTo({ top: 0, behavior: 'smooth' }); }" :disabled="!isSectionUnlocked({{ $sectionIdx }})" :class="activeSection === {{ $sectionIdx }} ? 'w-8 bg-teal-500' : (isSectionComplete({{ $sectionIdx }}) ? 'w-2 bg-teal-300' : 'w-2 bg-gray-200 dark:bg-slate-800')" class="h-2 rounded-full transition-all duration-300 disabled:opacity-50"></button>
          @endforeach
        </div>

        <template x-if="activeSection < {{ $survey->sections->count() - 1 }}">
          <button type="button" @click="if (isSectionComplete(activeSection)) { activeSection++; window.scrollTo({ top: 0, behavior: 'smooth' }); }" :disabled="!isSectionComplete(activeSection)" :class="isSectionComplete(activeSection) ? 'bg-linear-to-r from-teal-600 to-emerald-600 shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5' : 'bg-gray-300 dark:bg-slate-800 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'" class="justify-self-end flex min-w-0 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-bold text-white transition-all duration-300 sm:gap-2 sm:px-6 sm:py-3 sm:text-base">
            <span class="truncate">{{ __('next') }}</span>
            <i data-lucide="{{ app()->getLocale() === 'ar' ? 'chevron-left' : 'chevron-right' }}" class="h-4 w-4"></i>
          </button>
        </template>

        <template x-if="activeSection === {{ $survey->sections->count() - 1 }}">
          <button type="button" @click="submitSurvey()" :disabled="!isSectionComplete(activeSection) || isSubmitting" :class="isSectionComplete(activeSection) && !isSubmitting ? 'bg-linear-to-r from-green-500 to-emerald-500 shadow-lg shadow-green-200 dark:shadow-green-950/20 hover:shadow-xl hover:-translate-y-0.5' : 'bg-gray-300 dark:bg-slate-800 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'" class="justify-self-end flex min-w-0 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-bold text-white transition-all duration-300 sm:gap-2 sm:px-6 sm:py-3 sm:text-base">
            <span x-show="isSubmitting" class="h-5 w-5 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            <span x-show="!isSubmitting" class="flex items-center gap-1.5">
              <i data-lucide="check-circle-2" class="h-5 w-5"></i>
              <span class="truncate">{{ __('submit') }}</span>
            </span>
          </button>
        </template>
      </div>
    </nav>

    @if($isKiosk)
    <a href="{{ route('dashboard.kiosk.exit') }}" class="fixed top-24 left-4 p-4 rounded-full bg-slate-800 hover:bg-slate-700 text-white transition-all z-[9999] shadow-2xl group flex items-center justify-center opacity-100">
      <i data-lucide="lock" class="w-6 h-6"></i>
    </a>
    @endif
  </div>
@endsection
