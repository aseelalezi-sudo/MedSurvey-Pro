@extends('layouts.dashboard')

@section('title', __('predictive_page_title') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $activeWarningsCount = collect($alertsData['alerts'] ?? [])->filter(fn ($alert) => !in_array($alert['department'], $activatedPlans))->count();
  @endphp

  <div x-data="{ activeActionPlan: null }" class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative animate-fade-in text-start">
    
    <!-- Premium Success Toast/Banner -->
    @if(session('success'))
      <div x-data="{ show: true }" 
           x-show="show" 
           x-init="setTimeout(() => show = false, 5000)" 
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
           x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
           x-transition:leave="ease-in duration-200"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           class="fixed top-6 left-6 right-6 sm:left-auto sm:right-6 sm:max-w-md bg-slate-900 border border-emerald-500/30 text-white p-5 rounded-2xl shadow-2xl z-[150] flex gap-3 overflow-hidden"
           x-cloak>
        <!-- Animated glowing bar -->
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-400"></div>
        <div class="w-10 h-10 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 shrink-0 animate-bounce">
          <i data-lucide="check" class="w-5 h-5"></i>
        </div>
        <div class="text-start flex-1">
          <h4 class="font-bold text-sm text-emerald-400 leading-none mb-1.5 flex items-center gap-1.5">
            <span>{{ __('plan_activated_title') }}</span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          </h4>
          <p class="text-emerald-100/80 text-xs leading-relaxed">
            {{ session('success') }}
          </p>
        </div>
        <button @click="show = false" class="text-emerald-300/50 hover:text-white p-1 hover:bg-white/5 rounded-lg transition-colors h-fit cursor-pointer">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
      <div class="flex items-center gap-3">
        <a href="{{ route('dashboard.index') }}" 
           class="p-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-gray-100 dark:hover:bg-slate-800 border border-gray-200 dark:border-slate-800 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors shadow-sm cursor-pointer"
        >
          <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="w-5 h-5"></i>
        </a>
        <div class="text-start">
          <div class="flex items-center gap-2">
            <i data-lucide="brain" class="w-6 h-6 text-indigo-600 dark:text-indigo-400 animate-pulse"></i>
            <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
              {{ __('predictive_page_title') }}
            </h1>
          </div>
          <p class="text-xs sm:text-sm text-gray-550 dark:text-slate-400 mt-1">
            {{ __('predictive_page_desc') }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl px-4 py-2.5">
        <i data-lucide="activity" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 animate-spin-slow"></i>
        <div class="text-start">
          <span class="block text-[9px] text-indigo-500 dark:text-indigo-400 font-bold uppercase tracking-wider">{{ __('ai_status') }}</span>
          <span class="text-xs font-black text-indigo-950 dark:text-white">{{ __('ai_online') }}</span>
        </div>
      </div>
    </div>

    <!-- Stats Summary Panel -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
        <span class="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{{ __('analyzed_responses') }}</span>
        <span class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white font-mono">
          {{ $alertsData['stats']['totalResponsesAnalyzed'] ?? 0 }}
        </span>
      </div>
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
        <span class="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{{ __('checked_depts') }}</span>
        <span class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white font-mono">
          {{ $alertsData['stats']['totalDepts'] ?? 0 }}
        </span>
      </div>
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
        <span class="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{{ __('health_index_label') }}</span>
        <span class="text-xl sm:text-2xl font-black font-mono {{ ($alertsData['stats']['healthIndex'] ?? 100) >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
          {{ $alertsData['stats']['healthIndex'] ?? 100 }}%
        </span>
      </div>
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
        <span class="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{{ __('active_alerts_count') }}</span>
        <span class="text-xl sm:text-2xl font-black font-mono {{ $activeWarningsCount > 0 ? 'text-rose-600 dark:text-rose-400 animate-pulse' : 'text-gray-400 dark:text-slate-500' }}">
          {{ $activeWarningsCount }}
        </span>
      </div>
    </div>

    <!-- Warnings Detected List -->
    @if(count($alertsData['alerts'] ?? []) > 0)
      <div class="space-y-6">
        <div class="flex items-center gap-2 mb-2 text-start">
          <span class="flex h-2.5 w-2.5 relative">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
          </span>
          <h2 class="text-sm font-black uppercase tracking-wider text-rose-700 dark:text-rose-400">
            {{ __('warnings_detected') }}
          </h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          @foreach($alertsData['alerts'] ?? [] as $alert)
            <div class="relative bg-linear-to-r from-slate-900 via-indigo-950 to-slate-950 text-white rounded-3xl p-6 shadow-2xl border border-indigo-500/30 overflow-hidden group hover:shadow-indigo-500/20 hover:border-indigo-500/50 transition-all duration-300">
              <!-- Decorative glowing background mesh -->
              <div class="absolute -right-10 -top-10 w-44 h-44 bg-indigo-50 rounded-full blur-[90px] opacity-25 group-hover:opacity-35 transition-opacity pointer-events-none"></div>
              <div class="absolute -left-10 -bottom-10 w-44 h-44 bg-purple-500 rounded-full blur-[90px] opacity-15 group-hover:opacity-25 transition-opacity pointer-events-none"></div>
              
              <div class="relative flex flex-col h-full justify-between">
                <div>
                  <!-- Header Badge -->
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-1.5 bg-indigo-500/20 border border-indigo-400/30 rounded-full px-3 py-1 text-[10px] font-bold text-indigo-300">
                      <i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-400 animate-spin-slow"></i>
                      <span>{{ __('early_warning_alert') }}</span>
                    </div>
                    <span class="text-[10px] text-indigo-200/50 font-mono">
                      {{ __('ai_confidence') }}
                    </span>
                  </div>

                  <!-- Department Title -->
                  <h3 class="text-base sm:text-lg font-black mb-3 text-white flex items-center gap-2 text-start leading-tight">
                    <i data-lucide="building-2" class="w-5 h-5 text-indigo-400"></i>
                    {{ __('warning_dept_title') }} <span class="text-teal-400 font-extrabold">{{ $alert['department'] }}</span>
                  </h3>

                  <!-- Explanation -->
                  <p class="text-xs text-indigo-100/70 mb-5 leading-relaxed text-start">
                    {{ __('warning_description_1') }}
                  </p>

                  <!-- Stats Comparison Grid -->
                  <div class="grid grid-cols-3 gap-3 bg-white/5 border border-white/10 rounded-2xl p-4 mb-5 backdrop-blur-sm text-center">
                    <div>
                      <span class="block text-[10px] text-indigo-200/80 mb-1">{{ __('previous_period') }}</span>
                      <span class="text-lg font-black text-emerald-400 block">{{ $alert['previousAvg'] }}%</span>
                    </div>
                    <div class="border-x border-white/10">
                      <span class="block text-[10px] text-indigo-200/80 mb-1">{{ __('current_period') }}</span>
                      <span class="text-lg font-black text-rose-400 block">{{ $alert['currentAvg'] }}%</span>
                    </div>
                    <div>
                      <span class="block text-[10px] text-indigo-200/80 mb-1">{{ __('predicted_period') }}</span>
                      <span class="text-lg font-black text-yellow-400 block flex items-center justify-center gap-0.5">
                        {{ $alert['predictedScore'] }}%
                        <i data-lucide="arrow-down-right" class="w-4 h-4 text-rose-500 animate-bounce"></i>
                      </span>
                    </div>
                  </div>

                  <!-- Analysis drivers & Details -->
                  <div class="space-y-2.5 mb-6 text-start">
                    <div class="flex items-start gap-2 text-xs">
                      <i data-lucide="trending-down" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                      <div>
                        <span class="text-indigo-200/90">{{ __('drop_amount') }}</span>
                        <span class="font-bold text-rose-300">
                          -{{ $alert['drop'] }}% ({{ str_replace(['{', '}'], '', str_replace('pct', $alert['dropPercentage'], __('predictive_relative_drop'))) }})
                        </span>
                      </div>
                    </div>
                    <div class="flex items-start gap-2 text-xs">
                      <i data-lucide="brain" class="w-4 h-4 text-indigo-400 shrink-0 mt-0.5"></i>
                      <div>
                        <span class="text-indigo-200/90">{{ __('main_driver') }}</span>
                        <span class="font-bold text-teal-300">"{{ $alert['keyDriver'] }}"</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-4 border-t border-white/10">
                  <button 
                    @click="
                      const isAr = '{{ app()->getLocale() }}' === 'ar';
                      const title = isAr ? 'إنذار مبكر (AI)' : 'Early Warning (AI)';
                      const message = isAr 
                        ? '⚠️ ' + title + ': تراجع الرضا في قسم ' + '{{ $alert['department'] }}' + ' من ' + '{{ $alert['previousAvg'] }}%' + ' إلى ' + '{{ $alert['currentAvg'] }}%.\n' +
                          'المسبب الرئيسي: ' + '{{ $alert['keyDriver'] }}' + '.\n' +
                          'التنبؤ القادم: يتوقع تراجع الرضا إلى ' + '{{ $alert['predictedScore'] }}%.\n\n' +
                          'يرجى مراجعة الاستبيانات الأخيرة واتخاذ الإجراءات اللازمة.'
                        : '⚠️ ' + title + ': Satisfaction dropped in ' + '{{ $alert['department'] }}' + ' department from ' + '{{ $alert['previousAvg'] }}%' + ' to ' + '{{ $alert['currentAvg'] }}%.\n' +
                          'Key Driver: ' + '{{ $alert['keyDriver'] }}' + '.\n' +
                          'Upcoming Prediction: Satisfaction is expected to drop to ' + '{{ $alert['predictedScore'] }}%.\n\n' +
                          'Please review recent surveys and take necessary actions.';
                      if (navigator.share) {
                        navigator.share({ title: title, text: message }).catch(() => {});
                      } else {
                        navigator.clipboard.writeText(message);
                        alert('{{ __('alert_copied') }}');
                      }
                    "
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-lg shadow-indigo-950/50 cursor-pointer"
                  >
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    <span>{{ __('share_alert') }}</span>
                  </button>

                  @if(in_array($alert['department'], $activatedPlans))
                    <div class="bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 font-extrabold px-4 py-2.5 rounded-xl text-xs flex items-center justify-center gap-1.5 animate-pulse-soft">
                      <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
                      <span>{{ __('plan_activated_badge') }}</span>
                    </div>
                  @else
                    <button 
                      @click="activeActionPlan = @json($alert)"
                      class="px-4 py-2.5 rounded-xl border border-white/20 hover:bg-white/10 text-white text-xs font-bold transition-all cursor-pointer"
                    >
                      {{ __('take_action') }}
                    </button>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @else
      <!-- Gorgeous clean slate for AI -->
      <div class="text-center py-20 px-6 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-3xl shadow-sm max-w-2xl mx-auto">
        <div class="relative w-24 h-24 mx-auto mb-6 flex items-center justify-center">
          <div class="absolute inset-0 bg-indigo-500/10 rounded-full animate-ping pointer-events-none"></div>
          <div class="w-16 h-16 bg-linear-to-r from-indigo-500 to-indigo-600 rounded-full flex items-center justify-center text-white shadow-xl dark:shadow-none animate-pulse">
            <i data-lucide="shield-check" class="w-8 h-8"></i>
          </div>
        </div>
        <h2 class="text-lg font-black text-gray-900 dark:text-white mb-2">
          {{ __('no_warnings_title') }}
        </h2>
        <p class="text-xs sm:text-sm text-gray-500 dark:text-slate-400 leading-relaxed max-w-md mx-auto">
          {{ __('no_warnings_desc') }}
        </p>
      </div>
    @endif

    <!-- AI Action Plan Modal -->
    <div x-show="activeActionPlan" 
         class="fixed inset-0 bg-black/75 backdrop-blur-md flex items-center justify-center z-[100] p-4 animate-fade-in"
         x-cloak>
      <div @click.away="activeActionPlan = null"
           class="bg-slate-900 border border-indigo-500/30 text-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden relative"
           x-show="activeActionPlan"
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="ease-in duration-250"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95">
        
        <!-- Glowing ambient background inside modal -->
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500 rounded-full blur-[80px] opacity-20 pointer-events-none"></div>
        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-purple-500 rounded-full blur-[80px] opacity-15 pointer-events-none"></div>

        <div class="relative">
          <!-- Modal Header -->
          <div class="bg-linear-to-r from-indigo-950 via-purple-950 to-indigo-950 p-6 border-b border-indigo-500/10">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                  <i data-lucide="brain" class="w-4 h-4 animate-pulse"></i>
                </div>
                <h3 class="text-base sm:text-lg font-black">{{ __('ai_plan_modal_title') }}</h3>
              </div>
              <button @click="activeActionPlan = null"
                      class="p-1.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-gray-400 hover:text-white transition-colors cursor-pointer"
              >
                <i data-lucide="x" class="w-5 h-5"></i>
              </button>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <span class="text-[10px] text-teal-400 font-bold bg-teal-500/10 px-2 py-0.5 rounded-full border border-teal-500/20" x-text="activeActionPlan ? activeActionPlan.department : ''"></span>
              <span class="text-[10px] text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20">
                {{ __('predicted_drop_lbl') }} -<span x-text="activeActionPlan ? activeActionPlan.drop : ''"></span>%
              </span>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-5 text-start max-h-[70vh] overflow-y-auto">
            <div class="bg-white/5 border border-white/10 rounded-2xl p-4">
              <h4 class="text-xs font-black text-indigo-300 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-400 animate-spin-slow"></i>
                <span>{{ __('ai_insight') }}</span>
              </h4>
              <p class="text-xs text-indigo-100/90 leading-relaxed">
                {{ __('ai_insight_desc') }} 
                <span class="text-teal-300 font-bold" x-text="activeActionPlan ? `(${activeActionPlan.keyDriver})` : ''"></span>. 
                {{ __('ai_insight_desc_2') }}
              </p>
            </div>

            <!-- Steps -->
            <div class="space-y-4">
              <h4 class="text-xs font-black text-gray-400 uppercase tracking-wider">{{ __('recommended_actions') }}</h4>
              
              <!-- Step 1 -->
              <div class="flex gap-3">
                <div class="w-6 h-6 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 animate-pulse">
                  1
                </div>
                <div>
                  <h5 class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                    {{ __('action_step_1_title') }}
                  </h5>
                  <p class="text-[11px] text-gray-300 mt-1 leading-relaxed">
                    {{ __('action_step_1_desc') }} 
                    <span x-text="activeActionPlan ? activeActionPlan.department : ''"></span> 
                    {{ __('action_step_1_desc_2') }} 
                    <span x-text="activeActionPlan ? `(${activeActionPlan.keyDriver})` : ''"></span>.
                  </p>
                </div>
              </div>

              <!-- Step 2 -->
              <div class="flex gap-3">
                <div class="w-6 h-6 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                  2
                </div>
                <div>
                  <h5 class="text-xs font-bold text-indigo-300 flex items-center gap-1.5">
                    {{ __('action_step_2_title') }}
                  </h5>
                  <p class="text-[11px] text-gray-300 mt-1 leading-relaxed">
                    {{ __('action_step_2_desc') }} 
                    <span x-text="activeActionPlan ? `(${activeActionPlan.keyDriver})` : ''"></span> 
                    {{ __('action_step_2_desc_2') }}
                  </p>
                </div>
              </div>

              <!-- Step 3 -->
              <div class="flex gap-3">
                <div class="w-6 h-6 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                  3
                </div>
                <div>
                  <h5 class="text-xs font-bold text-purple-400 flex items-center gap-1.5">
                    {{ __('action_step_3_title') }}
                  </h5>
                  <p class="text-[11px] text-gray-300 mt-1 leading-relaxed">
                    {{ __('action_step_3_desc') }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="p-6 border-t border-indigo-500/10 bg-slate-950">
            <form action="{{ route('dashboard.predictive.toggle') }}" method="POST" class="flex items-center gap-3">
              @csrf
              <input type="hidden" name="department" :value="activeActionPlan ? activeActionPlan.department : ''">
              
              <button type="button"
                      @click="activeActionPlan = null"
                      class="flex-1 py-3 rounded-2xl border border-white/10 hover:bg-white/5 text-white text-xs font-bold transition-all cursor-pointer"
              >
                {{ __('cancel') }}
              </button>
              <button type="submit"
                      class="flex-1 py-3 rounded-2xl bg-linear-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-950 cursor-pointer flex items-center justify-center gap-1.5"
              >
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>{{ __('activate_plan') }}</span>
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>

  </div>
@endsection
