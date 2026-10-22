@extends('layouts.dashboard')

@section('title', __('nav_reports') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $user = auth()->user();
    $restrictedDepartment = ($user->role === 'head_of_department' && $user->department) ? $user->department : null;

    // Fetch departments list from settings
    $settingsService = app(\App\Services\SettingsService::class);
    $allSettings = $settingsService->getAll($user->tenantId);
    $departments = collect($allSettings['departments'] ?? [])->where('isActive', true)->pluck('name')->all();

    $dateFilter = request()->query('dateFilter', 'all');
    $selectedDept = request()->query('department', 'all');
    $startDate = request()->query('startDate', '');
    $endDate = request()->query('endDate', '');

    $reportDepartmentLabel = ($restrictedDepartment || ($selectedDept !== 'all'))
      ? ($restrictedDepartment ?? $selectedDept)
      : __('reports_filter_all_depts');

    // Compute comparison values for trend indicators
    $avgChange = isset($stats, $comparisonStats) ? ($stats['averageScore'] ?? 0) - ($comparisonStats['averageScore'] ?? 0) : 0;
    $npsChange = isset($stats, $comparisonStats) ? ($stats['npsScore'] ?? 0) - ($comparisonStats['npsScore'] ?? 0) : 0;
    $respChange = isset($stats, $comparisonStats) ? ($stats['totalResponses'] ?? 0) - ($comparisonStats['totalResponses'] ?? 0) : 0;
    $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
    $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];

    // Translate dynamic labels/values (levels, departments, categories) for charts and reports
    $reportDepartmentLabel = __($reportDepartmentLabel);

    if (isset($stats)) {
        if (isset($stats['satisfactionDistribution'])) {
            $stats['satisfactionDistribution'] = collect($stats['satisfactionDistribution'])->map(function ($item) {
                $item['level'] = __($item['level'] === 'ممتاز' ? 'score_excellent' : 
                                   ($item['level'] === 'جيد' ? 'score_good' : 
                                   ($item['level'] === 'متوسط' ? 'score_average' : 
                                   ($item['level'] === 'ضعيف' ? 'score_poor' : $item['level']))));
                return $item;
            })->all();
        }
        
        if (isset($stats['departmentScores'])) {
            $stats['departmentScores'] = collect($stats['departmentScores'])->map(function ($item) {
                $item['name'] = __($item['name']);
                return $item;
            })->all();
        }
        
        if (isset($stats['categoryScores'])) {
            $stats['categoryScores'] = collect($stats['categoryScores'])->map(function ($item) {
                $item['category'] = __($item['category']);
                return $item;
            })->all();
        }
    }

    if (isset($deptTrends)) {
        $deptTrends = collect($deptTrends)->map(function ($item) {
            $item['name'] = __($item['name']);
            return $item;
        })->all();
    }

    if (isset($tickets)) {
        $tickets = collect($tickets)->map(function ($item) {
            if (isset($item->department)) {
                $item->department = __($item->department);
            }
            return $item;
        });
    }

    $ticketStatsPayload = $ticketStats ?? [
        'total' => 0,
        'open' => 0,
        'inProgress' => 0,
        'resolved' => 0,
        'detailLimit' => 1000,
    ];
  @endphp


  <div x-data="reportsComponent({
    dateFilter: @js($dateFilter),
    department: @js($restrictedDepartment ?? $selectedDept),
    startDate: @js($startDate),
    endDate: @js($endDate),
    restrictedDept: @js($restrictedDepartment),
    stats: @js($stats),
    comparisonStats: @js($comparisonStats),
    trendData: @js($trendData ?? []),
    deptTrends: @js($deptTrends ?? []),
    tickets: @js($tickets),
    ticketStats: @js($ticketStatsPayload),
    changes: {
      averageScore: @js((float) $avgChange),
      npsScore: @js((float) $npsChange),
      totalResponses: @js((float) $respChange),
    },
    hospitalName: @js(($allSettings['hospital']['name'] ?? null) ?: __('reports_default_hospital')),
    operatingTitle: @js(($allSettings['hospital']['operatingTitle'] ?? null) ?: __('reports_default_operating')),
    logo: @js($allSettings['hospital']['logo'] ?? ''),
    language: @js(app()->getLocale()),
    auditEventsUrl: @js(route('dashboard.audit.events')),
    csrf: @js(csrf_token()),
    i18n: @js(collect(['achieved_quality_level', 'active_processing_tickets', 'ai_proactive_recommendation', 'ai_reading_summary', 'all_rights_reserved', 'average', 'category', 'category_satisfaction', 'concerned_dept', 'confidential_report', 'count', 'criteria_category', 'current_satisfaction', 'date_label', 'department', 'department_satisfaction_comparative', 'dept_eval_table', 'excellent', 'export_department', 'good', 'in_progress', 'level', 'low_risk', 'measured_satisfaction', 'medical_department', 'medium_risk', 'normal', 'nps_score', 'open', 'open_waiting_tickets', 'overall_measured_satisfaction', 'page', 'patient_complainant', 'pdf_certified_copy', 'percentage', 'poor', 'predictive_ai_analysis', 'predictive_report_desc', 'predictive_report_file', 'predictive_report_title', 'predictive_risk_level', 'priority', 'quality_satisfaction_levels', 'reason_complaint', 'rec_high_risk', 'rec_low_risk', 'rec_medium_risk', 'received_responses', 'report_categories_desc', 'report_categories_title', 'report_date', 'report_departments_desc', 'report_departments_title', 'report_executive_subtitle', 'report_executive_title', 'report_tickets_title', 'reports_alert_popup_blocked', 'reports_current_period', 'reports_filter_all_depts', 'reports_previous_period', 'reports_satisfaction_distribution', 'reports_stat_nps_score', 'reports_stat_overall_satisfaction', 'resolved', 'resolved_closed_tickets', 'response_rate', 'satisfaction_distribution', 'satisfaction_level', 'satisfaction_rate', 'sensitivity_indicators_depts', 'submission_date', 'system_dept_reports', 'system_description', 'system_predictive_reports', 'system_quality_reports', 'target_dept', 'ticket_monitoring_desc', 'ticket_status', 'tickets_details_list', 'total_registered_tickets', 'total_responses', 'very_high', 'visual_representation'])->mapWithKeys(fn ($key) => [$key => __($key)])),
  })" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-start select-none">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
      <div class="flex items-center gap-3">
        <a href="{{ route('dashboard.index') }}"
           class="p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-850 rounded-xl transition-all shadow-sm cursor-pointer"
        >
          <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="w-5 h-5 text-gray-500 dark:text-slate-400"></i>
        </a>
        <div>
          <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
            <span>{{ __('reports_header_title') }}</span>
            <span class="text-xs bg-teal-150 dark:bg-teal-950/20 text-teal-700 dark:text-teal-450 font-bold px-2.5 py-1 rounded-full border border-teal-200 dark:border-teal-900/40">
              {{ __('reports_header_badge') }}
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-gray-400 dark:text-slate-400 mt-1">
            {{ __('reports_header_desc') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Interactive Filters Grid -->
    <div class="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl p-4 mb-8 shadow-sm">
      <div class="flex items-center gap-2.5 text-sm font-bold text-gray-800 dark:text-white mb-4 pb-2 border-b border-gray-50 dark:border-slate-800/80">
        <i data-lucide="filter" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        <span>{{ __('reports_filter_title') }}</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1.5 text-start">
          <div class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_date_range') }}</span>
          </div>
          <div class="grid grid-cols-3 md:grid-cols-5 gap-1.5">
            <button @click="setDateFilter('all')" type="button"
                    :class="dateFilter === 'all' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">{{ __('reports_filter_all') }}</button>
            <button @click="setDateFilter('week')" type="button"
                    :class="dateFilter === 'week' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">{{ __('reports_filter_week') }}</button>
            <button @click="setDateFilter('month')" type="button"
                    :class="dateFilter === 'month' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">{{ __('reports_filter_month') }}</button>
            <button @click="setDateFilter('quarter')" type="button"
                    :class="dateFilter === 'quarter' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">{{ __('reports_filter_quarter') }}</button>
            <button @click="setDateFilter('custom')" type="button"
                    :class="dateFilter === 'custom' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">{{ __('reports_filter_custom') }}</button>
          </div>
        </div>

        <div class="space-y-1.5 text-start">
          <label for="department_filter" class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
            <i data-lucide="building-2" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_dept') }}</span>
          </label>
          <select id="department_filter" name="department_filter" x-model="department" @change="applyFilters()" :disabled="!!restrictedDept"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm cursor-pointer">
            <option value="all">{{ __('reports_filter_all_depts') }}</option>
            @foreach($departments as $dept)
              <option value="{{ $dept }}">{{ $dept }}</option>
            @endforeach
          </select>
          <template x-if="!!restrictedDept">
            <p class="text-[11px] font-bold text-teal-600 dark:text-teal-400 animate-pulse mt-1 select-none">{{ __('reports_filter_dept_restricted') }}</p>
          </template>
        </div>
      </div>

      <div x-show="dateFilter === 'custom'" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
           class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-50 dark:border-slate-850" x-cloak>
        <div class="space-y-1.5 text-start">
          <label for="date_from_filter" class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400"><i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i><span>{{ __('reports_filter_date_from') }}</span></label>
          <div class="relative">
            <div class="flex min-h-[42px] w-full items-center gap-3 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-bold text-gray-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-gray-400 dark:text-slate-500"></i>
              <span class="font-mono text-sm font-bold" dir="ltr" x-text="startDate || 'YYYY-MM-DD'"></span>
            </div>
            <input
              id="date_from_filter"
              name="date_from_filter"
              type="date"
              x-model="startDate"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ __('reports_filter_date_from') }}"
              @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            >
          </div>
        </div>
        <div class="space-y-1.5 text-start">
          <label for="date_to_filter" class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400"><i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i><span>{{ __('reports_filter_date_to') }}</span></label>
          <div class="relative">
            <div class="flex min-h-[42px] w-full items-center gap-3 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-bold text-gray-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-gray-400 dark:text-slate-500"></i>
              <span class="font-mono text-sm font-bold" dir="ltr" x-text="endDate || 'YYYY-MM-DD'"></span>
            </div>
            <input
              id="date_to_filter"
              name="date_to_filter"
              type="date"
              x-model="endDate"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ __('reports_filter_date_to') }}"
              @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            >
          </div>
        </div>
        <div class="flex items-end">
          <button @click="applyFilters()" type="button" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer">{{ __('reports_filter_apply') }}</button>
        </div>
      </div>
    </div>

    <!-- Comparison Stats with Trend Indicators (NEW) -->
    @if(isset($stats) && isset($comparisonStats))
      <div class="relative grid grid-cols-2 md:grid-cols-4 gap-4 bg-white dark:bg-slate-900 p-4 border border-gray-150 dark:border-slate-800 rounded-2xl mb-8 shadow-sm">
        <div x-show="loadingReports" x-cloak class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/65 backdrop-blur-[1px] dark:bg-slate-950/55">
          <i data-lucide="loader-2" class="h-5 w-5 animate-spin text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="text-center p-3">
          <span class="block text-[10px] text-gray-500 dark:text-slate-400 font-bold mb-1">{{ __('reports_stat_total_responses') }}</span>
          <span class="stat-number text-xl font-black text-gray-800 dark:text-white font-mono" :title="formatNumber(stats.totalResponses)" x-text="compactNumber(stats.totalResponses)">{{ $compactNumber($stats['totalResponses'] ?? 0) }}</span>
          <span x-show="Number(changes.totalResponses || 0) !== 0" class="block text-[10px] font-bold mt-1" :class="Number(changes.totalResponses || 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
            <i :data-lucide="Number(changes.totalResponses || 0) > 0 ? 'trending-up' : 'trending-down'" class="w-3 h-3 inline"></i>
            <span :title="formatNumber(Math.abs(Number(changes.totalResponses || 0)))" x-text="compactNumber(Math.abs(Number(changes.totalResponses || 0)))"></span>
            <span>{{ __('reports_stat_vs_previous') }}</span>
          </span>
        </div>
        <div class="text-center p-3 border-r border-gray-100 dark:border-slate-800">
          <span class="block text-[10px] text-gray-500 dark:text-slate-400 font-bold mb-1">{{ __('reports_stat_overall_satisfaction') }}</span>
          <span class="stat-number text-xl font-black text-teal-600 dark:text-teal-400 font-mono" x-text="`${formatNumber(stats.averageScore, 1)}%`">{{ $formatNumber($stats['averageScore'] ?? 0, 1) }}%</span>
          <span x-show="Number(changes.averageScore || 0) !== 0" class="block text-[10px] font-bold mt-1" :class="Number(changes.averageScore || 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
            <i :data-lucide="Number(changes.averageScore || 0) > 0 ? 'trending-up' : 'trending-down'" class="w-3 h-3 inline"></i>
            <span x-text="`${formatNumber(Math.abs(Number(changes.averageScore || 0)), 1)}%`"></span>
          </span>
          <span x-show="Number(changes.averageScore || 0) === 0" class="block text-[10px] font-bold mt-1 text-gray-400 dark:text-slate-500">
            <i data-lucide="minus" class="w-3 h-3 inline"></i> {{ __('reports_stat_no_change') }}
          </span>
        </div>
        <div class="text-center p-3 border-r border-gray-100 dark:border-slate-800">
          <span class="block text-[10px] text-gray-500 dark:text-slate-400 font-bold mb-1">{{ __('reports_stat_nps_score') }}</span>
          <span class="stat-number text-xl font-black text-indigo-600 dark:text-indigo-400 font-mono" x-text="formatNumber(stats.npsScore, 1)">{{ $formatNumber($stats['npsScore'] ?? 0, 1) }}</span>
          <span x-show="Number(changes.npsScore || 0) !== 0" class="block text-[10px] font-bold mt-1" :class="Number(changes.npsScore || 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
            <i :data-lucide="Number(changes.npsScore || 0) > 0 ? 'trending-up' : 'trending-down'" class="w-3 h-3 inline"></i>
            <span x-text="formatNumber(Math.abs(Number(changes.npsScore || 0)), 1)"></span>
          </span>
          <span x-show="Number(changes.npsScore || 0) === 0" class="block text-[10px] font-bold mt-1 text-gray-400 dark:text-slate-500">
            <i data-lucide="minus" class="w-3 h-3 inline"></i> {{ __('reports_stat_no_change') }}
          </span>
        </div>
        <div class="text-center p-3 border-r border-gray-100 dark:border-slate-800">
          <span class="block text-[10px] text-gray-500 dark:text-slate-400 font-bold mb-1">{{ __('reports_stat_response_rate') }}</span>
          <span class="stat-number text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono" x-text="`${formatNumber(stats.responseRate, 1)}%`">{{ $formatNumber($stats['responseRate'] ?? 0, 1) }}%</span>
          <span class="block text-[10px] font-bold mt-1 text-teal-600 dark:text-teal-400">
            <i data-lucide="check-circle" class="w-3 h-3 inline"></i> {{ __('reports_stat_processed_updated') }}
          </span>
        </div>
      </div>

      <!-- Interactive Charts Section (NEW) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Satisfaction Donut Chart -->
        <div class="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
          <h3 class="font-black text-sm text-gray-800 dark:text-white mb-1 flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            {{ __('reports_satisfaction_distribution') }}
          </h3>
          <p class="text-[10px] text-gray-400 dark:text-slate-500 mb-4">{{ __('reports_hover_for_details') }}</p>
          <div id="satisfactionDonutChart" class="w-full h-64"></div>
        </div>

        <!-- Monthly Trend Line Chart -->
        <div class="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl p-5 shadow-sm lg:col-span-2">
          <h3 class="font-black text-sm text-gray-800 dark:text-white mb-1 flex items-center gap-2">
            <i data-lucide="trending-up" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
            {{ __('reports_monthly_trend') }}
          </h3>
          <p class="text-[10px] text-gray-400 dark:text-slate-500 mb-4">{{ __('reports_6month_trend') }}</p>
          <div id="monthlyTrendChart" class="w-full h-64"></div>
        </div>
      </div>

      <!-- Department Comparison Bar Chart (NEW) -->
      <div x-show="(deptTrends || []).length > 0" x-cloak class="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl p-5 mb-8 shadow-sm">
        <h3 class="font-black text-sm text-gray-800 dark:text-white mb-1 flex items-center gap-2">
          <i data-lucide="bar-chart-3" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          {{ __('reports_department_comparison') }}
        </h3>
        <p class="text-[10px] text-gray-400 dark:text-slate-500 mb-4">{{ __('reports_current_vs_previous') }}</p>
        <div id="deptComparisonChart" class="w-full h-72"></div>
      </div>

    @elseif(isset($stats))
      <!-- Fallback Stats without comparison -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-teal-50/50 dark:bg-teal-950/10 p-4 border border-teal-100 dark:border-teal-900/30 rounded-2xl mb-8">
        <div class="text-center">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_total_responses') }}</span>
          <span class="stat-number-tight text-lg font-black text-teal-800 dark:text-teal-300 font-mono" title="{{ $formatNumber($stats['totalResponses'] ?? 0) }}">{{ $compactNumber($stats['totalResponses'] ?? 0) }} {{ __('reports_stat_response_word') }}</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_overall_satisfaction') }}</span>
          <span class="stat-number text-lg font-black text-teal-800 dark:text-teal-300 font-mono">{{ $formatNumber($stats['averageScore'] ?? 0, 1) }}%</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_nps_score') }}</span>
          <span class="stat-number text-lg font-black text-teal-800 dark:text-teal-300 font-mono">{{ $formatNumber($stats['npsScore'] ?? 0, 1) }}</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_data_status') }}</span>
          <span class="text-sm font-black text-teal-800 dark:text-teal-300 flex items-center justify-center gap-1.5 mt-0.5">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 dark:text-emerald-405 shrink-0"></i>
            <span>{{ __('reports_stat_processed_updated') }}</span>
          </span>
        </div>
      </div>
    @endif

    <!-- Cards list -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      @foreach([
        ['type' => 'executive', 'title' => __('reports_card1_title'), 'desc' => __('reports_card1_desc'), 'icon' => 'file-text', 'color' => 'text-teal-600 dark:text-teal-400', 'bgGradient' => 'from-teal-500/10 to-teal-600/10 dark:from-teal-950/20 dark:to-teal-900/10 hover:from-teal-500/20 hover:to-teal-600/20', 'border' => 'border-teal-100 hover:border-teal-300 dark:border-slate-800 dark:hover:border-teal-900'],
        ['type' => 'departments', 'title' => __('reports_card2_title'), 'desc' => __('reports_card2_desc'), 'icon' => 'building-2', 'color' => 'text-indigo-600 dark:text-indigo-400', 'bgGradient' => 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20', 'border' => 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900'],
        ['type' => 'categories', 'title' => __('reports_card3_title'), 'desc' => __('reports_card3_desc'), 'icon' => 'trending-up', 'color' => 'text-emerald-600 dark:text-emerald-400', 'bgGradient' => 'from-emerald-500/10 to-emerald-600/10 dark:from-emerald-950/20 dark:to-emerald-900/10 hover:from-emerald-500/20 hover:to-emerald-600/20', 'border' => 'border-emerald-100 hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-900'],
        ['type' => 'tickets', 'title' => __('reports_card4_title'), 'desc' => __('reports_card4_desc'), 'icon' => 'circle-alert', 'color' => 'text-red-600 dark:text-red-400', 'bgGradient' => 'from-red-500/10 to-red-600/10 dark:from-red-950/20 dark:to-red-900/10 hover:from-red-500/20 hover:to-red-600/20', 'border' => 'border-red-100 hover:border-red-300 dark:border-slate-800 dark:hover:border-red-900'],
        ['type' => 'predictive', 'title' => __('reports_card5_title'), 'desc' => __('reports_card5_desc'), 'icon' => 'brain', 'color' => 'text-indigo-600 dark:text-indigo-400', 'bgGradient' => 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20', 'border' => 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900']
      ] as $card)
        <div class="bg-white dark:bg-slate-900 border rounded-2xl p-6 transition-all hover:shadow-lg flex flex-col justify-between {{ $card['border'] }}">
          <div class="space-y-3">
            <div class="flex items-start justify-between">
              <div class="p-3 rounded-xl bg-linear-to-br {{ $card['bgGradient'] }}">
                <i data-lucide="{{ $card['icon'] }}" class="w-6 h-6 {{ $card['color'] }}"></i>
              </div>
              <span class="text-[10px] bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 font-bold px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-800 flex items-center gap-1 shadow-sm select-none">
                <i data-lucide="award" class="w-3.5 h-3.5 text-amber-500"></i>
                <span>{{ __('reports_certified_official') }}</span>
              </span>
            </div>
            <h3 class="font-black text-base text-gray-800 dark:text-white">{{ $card['title'] }}</h3>
            <p class="text-xs text-gray-550 dark:text-slate-400 leading-relaxed">{{ $card['desc'] }}</p>
          </div>

          <div class="pt-5 border-t border-gray-100 dark:border-slate-850 mt-5 flex flex-col sm:flex-row items-center gap-3">
            <button @click="handleExportPDF('{{ $card['type'] }}', 'pdf')"
                    :disabled="exportingReport === '{{ $card['type'] }}_pdf' || exportingReport === '{{ $card['type'] }}_print'"
                    type="button"
                    class="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-indigo-600 to-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer">
              <template x-if="exportingReport === '{{ $card['type'] }}_pdf'"><div class="flex items-center gap-2"><i data-lucide="loader" class="w-4 h-4 animate-spin"></i><span>{{ __('reports_exporting') }}</span></div></template>
              <template x-if="exportingReport !== '{{ $card['type'] }}_pdf'"><div class="flex items-center gap-2"><i data-lucide="file-down" class="w-4 h-4"></i><span>{{ __('reports_export_pdf') }}</span></div></template>
            </button>

            <button @click="handleExportPDF('{{ $card['type'] }}', 'print')"
                    :disabled="exportingReport === '{{ $card['type'] }}_pdf' || exportingReport === '{{ $card['type'] }}_print'"
                    type="button"
                    class="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-teal-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer">
              <template x-if="exportingReport === '{{ $card['type'] }}_print'"><div class="flex items-center gap-2"><i data-lucide="loader" class="w-4 h-4 animate-spin"></i><span>{{ __('reports_printing') }}</span></div></template>
              <template x-if="exportingReport !== '{{ $card['type'] }}_print'"><div class="flex items-center gap-2"><i data-lucide="printer" class="w-4 h-4"></i><span>{{ __('reports_print_now') }}</span></div></template>
            </button>
          </div>
        </div>
      @endforeach
    </div>

  </div>
@endsection
