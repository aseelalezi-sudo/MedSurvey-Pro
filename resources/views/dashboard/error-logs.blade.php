@extends('layouts.dashboard')

@section('title', __('error_logs_title') . ' - MedSurvey Pro')

@section('dashboard')
@php
  $isAr = app()->getLocale() === 'ar';
  $totalError = collect($stats['byLevel'])->firstWhere('level', 'error')['count'] ?? 0;
  $totalNew = collect($stats['byStatus'])->firstWhere('status', 'new')['count'] ?? 0;
  $totalInProgress = collect($stats['byStatus'])->firstWhere('status', 'investigating')['count'] ?? 0;
  $totalSources = count($stats['topSources'] ?? []);
  $ignoredLabel = $isAr ? 'تجاهل' : 'Ignored';
  $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
  $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
  $logsRangeStart = $logs->total() === 0 ? 0 : (($logs->currentPage() - 1) * $logs->perPage()) + 1;
  $logsRangeEnd = min($logs->currentPage() * $logs->perPage(), $logs->total());
  $paginationSummary = $isAr
    ? 'صفحة '.$compactNumber($logs->currentPage()).' من '.$compactNumber($logs->lastPage()).' | '.$compactNumber($logsRangeStart).'-'.$compactNumber($logsRangeEnd).' معروضة من '.$compactNumber($logs->total())
    : 'Page '.$compactNumber($logs->currentPage()).' of '.$compactNumber($logs->lastPage()).' | '.$compactNumber($logsRangeStart).'-'.$compactNumber($logsRangeEnd).' shown of '.$compactNumber($logs->total());
  $searchIconClass = $isAr ? 'right-3' : 'left-3';
  $searchInputPadding = $isAr ? 'pr-10 pl-4' : 'pl-10 pr-4';
  $startDateValue = \App\Support\DateFilterBounds::cappedAtToday(request('start_date'))?->toDateString();
  $endDateValue = \App\Support\DateFilterBounds::cappedAtToday(request('end_date'), true)?->toDateString();
  $hasAdvancedErrorFilters = (request('level') && request('level') !== 'all')
    || (request('status') && request('status') !== 'all')
    || $startDateValue
    || $endDateValue;
@endphp

<div class="space-y-6 animate-fade-in">
  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="p-3 bg-red-500/10 dark:bg-red-500/20 rounded-2xl">
        <!-- Bug SVG Icon -->
        <svg class="w-6 h-6 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="8" height="14" x="8" y="6" rx="4"/>
          <path d="m19 7-3 2"/>
          <path d="m5 7 3 2"/>
          <path d="m19 19-3-2"/>
          <path d="m5 19 3-2"/>
          <path d="M20 13h-4"/>
          <path d="M4 13h4"/>
          <path d="m10 4 1 2h2l1-2"/>
        </svg>
      </div>
      <div class="text-start">
        <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ __('error_logs_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('error_logs_subtitle') }}</p>
      </div>
    </div>
    
    <!-- Action buttons -->
    <div class="flex items-center gap-2">
      @can('operations.error-logs.delete')
        <button
          id="clear-logs-btn"
          onclick="handleClearLogs()"
          class="flex items-center gap-2 px-4 py-2 bg-red-600 border border-red-600 rounded-xl text-sm font-bold text-white hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer shadow-xs"
        >
          <!-- Trash SVG -->
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
          <span id="clear-logs-text">{{ __('error_logs_clear') }}</span>
        </button>
      @endcan
      <button
        onclick="refreshLogsData()"
        class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold text-slate-650 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all cursor-pointer shadow-xs"
      >
        <!-- Refresh SVG -->
        <svg id="refresh-icon" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
        <span>{{ __('refresh') }}</span>
      </button>
    </div>
  </div>

  <!-- Stats Grid -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Card 1: 7-Day Errors -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-2xl shadow-xs transition-all hover:shadow-xs text-start">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/30 flex items-center justify-center text-red-600 dark:text-red-400">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_errors_7_days') }}</span>
      </div>
      <span id="stat-errors" class="stat-number text-2xl font-black text-slate-900 dark:text-white" title="{{ $formatNumber($totalError) }}">{{ $compactNumber($totalError) }}</span>
    </div>

    <!-- Card 2: New Status -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-2xl shadow-xs transition-all hover:shadow-xs text-start">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/30 flex items-center justify-center text-red-600 dark:text-red-400">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_status_new') }}</span>
      </div>
      <span id="stat-new" class="stat-number text-2xl font-black text-slate-900 dark:text-white" title="{{ $formatNumber($totalNew) }}">{{ $compactNumber($totalNew) }}</span>
    </div>

    <!-- Card 3: In Progress Status -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-2xl shadow-xs transition-all hover:shadow-xs text-start">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_status_in_progress') }}</span>
      </div>
      <span id="stat-inprogress" class="stat-number text-2xl font-black text-slate-900 dark:text-white" title="{{ $formatNumber($totalInProgress) }}">{{ $compactNumber($totalInProgress) }}</span>
    </div>

    <!-- Card 4: Top Sources count -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-2xl shadow-xs transition-all hover:shadow-xs text-start">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-950/30 flex items-center justify-center text-green-600 dark:text-green-400">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_sources') }}</span>
      </div>
      <span id="stat-sources" class="stat-number text-2xl font-black text-slate-900 dark:text-white" title="{{ $formatNumber($totalSources) }}">{{ $compactNumber($totalSources) }}</span>
    </div>
  </div>

  <!-- Filters Panel -->
  <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-3 shadow-xs dark:border-slate-800/80 dark:bg-slate-900">
    <div class="space-y-3">
      <div class="flex flex-wrap lg:flex-nowrap items-center gap-2">
        <div class="relative min-w-[200px] flex-1">
          <!-- Search SVG -->
          <svg class="absolute {{ $searchIconClass }} top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input
            id="search-input"
            name="search"
            aria-label="{{ __('error_logs_search_placeholder') }}"
            oninput="handleFilterChange()"
            placeholder="{{ __('error_logs_search_placeholder') }}"
            class="h-10 w-full {{ $searchInputPadding }} rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-700 outline-none transition-all focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-250 text-start"
          />
        </div>

        <!-- Level Dropdown -->
        <select
          id="level-filter"
          name="level"
          aria-label="{{ __('error_logs_all_levels') }}"
          onchange="handleFilterChange()"
          class="h-10 w-full sm:w-auto min-w-[130px] rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-250 cursor-pointer text-start"
        >
          <option value="all">{{ __('error_logs_all_levels') }}</option>
          <option value="error" @selected(request('level') === 'error')>{{ __('error_logs_level_error') }}</option>
          <option value="warn" @selected(request('level') === 'warn')>{{ __('error_logs_level_warn') }}</option>
          <option value="info" @selected(request('level') === 'info')>{{ __('error_logs_level_info') }}</option>
        </select>

        <!-- Status Dropdown -->
        <select
          id="status-filter"
          name="status"
          aria-label="{{ __('error_logs_all_statuses') }}"
          onchange="handleFilterChange()"
          class="h-10 w-full sm:w-auto min-w-[140px] rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-250 cursor-pointer text-start"
        >
          <option value="all">{{ __('error_logs_all_statuses') }}</option>
          <option value="new" @selected(request('status') === 'new')>{{ __('error_logs_status_new') }}</option>
          <option value="investigating" @selected(request('status') === 'investigating')>{{ __('error_logs_status_in_progress') }}</option>
          <option value="resolved" @selected(request('status') === 'resolved')>{{ __('error_logs_status_resolved') }}</option>
          <option value="ignored" @selected(request('status') === 'ignored')>{{ $ignoredLabel }}</option>
        </select>

        <button
          type="button"
          id="error-filters-toggle"
          onclick="toggleErrorLogFilters()"
          class="relative inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-bold transition {{ ($startDateValue || $endDateValue) ? 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900/50 dark:bg-teal-950/30 dark:text-teal-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' }}"
        >
          <i data-lucide="calendar" class="h-4 w-4"></i>
          <span class="hidden sm:inline">{{ $isAr ? 'التاريخ' : 'Date' }}</span>
          <span id="error-filters-dot" class="{{ ($startDateValue || $endDateValue) ? '' : 'hidden' }} absolute -top-1 {{ $isAr ? '-left-1' : '-right-1' }} h-2.5 w-2.5 rounded-full bg-teal-500 ring-2 ring-white dark:ring-slate-900"></span>
        </button>

        <button
          type="button"
          id="error-clear-filters-btn"
          onclick="resetErrorLogFilters()"
          class="{{ (request('search') || $hasAdvancedErrorFilters) ? '' : 'hidden' }} inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
          title="{{ $isAr ? 'مسح الفلاتر' : 'Clear Filters' }}"
        >
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>

      <div id="error-advanced-filters" class="{{ ($startDateValue || $endDateValue) ? '' : 'hidden' }} grid gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-2 sm:grid-cols-2 xl:grid-cols-2 dark:border-slate-800 dark:bg-slate-900/50">
        <!-- Start Date -->
        <div>
          <label for="error-start-date" class="mb-1 block text-[10px] font-black leading-none text-slate-400 dark:text-slate-500">{{ $isAr ? 'من تاريخ' : 'From Date' }}</label>
          <div class="relative">
            <div class="flex h-10 w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-slate-900 transition dark:border-slate-700 dark:bg-slate-950 dark:text-white">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-300"></i>
              <span class="font-mono text-xs font-bold" dir="ltr" id="error-start-date-label">{{ $startDateValue ?: 'YYYY-MM-DD' }}</span>
            </div>
            <input
              type="date"
              id="error-start-date"
              value="{{ $startDateValue }}"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ $isAr ? 'من تاريخ' : 'From Date' }}"
              onchange="handleFilterChange()"
              onclick="typeof this.showPicker === 'function' ? this.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            />
          </div>
        </div>

        <!-- End Date -->
        <div>
          <label for="error-end-date" class="mb-1 block text-[10px] font-black leading-none text-slate-400 dark:text-slate-500">{{ $isAr ? 'إلى تاريخ' : 'To Date' }}</label>
          <div class="relative">
            <div class="flex h-10 w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-slate-900 transition dark:border-slate-700 dark:bg-slate-950 dark:text-white">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-300"></i>
              <span class="font-mono text-xs font-bold" dir="ltr" id="error-end-date-label">{{ $endDateValue ?: 'YYYY-MM-DD' }}</span>
            </div>
            <input
              type="date"
              id="error-end-date"
              value="{{ $endDateValue }}"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ $isAr ? 'إلى تاريخ' : 'To Date' }}"
              onchange="handleFilterChange()"
              onclick="typeof this.showPicker === 'function' ? this.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Sources Panel -->
  <div id="top-sources-panel" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-3xl shadow-xs mb-6 {{ count($stats['topSources'] ?? []) === 0 ? 'hidden' : '' }}">
    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 mb-3 text-start">{{ __('error_logs_top_sources') }}</h3>
    <div id="top-sources-list" class="flex flex-wrap gap-2 justify-start">
      @foreach($stats['topSources'] ?? [] as $source)
        <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800/80 rounded-xl text-xs font-bold text-slate-650 dark:text-slate-350 border border-slate-100 dark:border-slate-800">
          {{ $source['source'] ?: __('unknown') }} ({{ $compactNumber($source['count']) }})
        </span>
      @endforeach
    </div>
  </div>

  <!-- Interactive Log Table -->
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-850/40">
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_level') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('error_logs_message') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 hidden sm:table-cell">{{ __('error_logs_source') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 hidden md:table-cell">{{ __('error_logs_status') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 hidden md:table-cell">{{ __('error_logs_count') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('date_time') }}</th>
            <th class="text-start px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400"></th>
          </tr>
        </thead>
        <tbody id="logs-table-body">
          @forelse ($logs as $log)
            @php
              $levelClasses = [
                'error' => ['bg' => 'bg-red-500/10 text-red-600 dark:text-red-400', 'label' => __('error_logs_level_error')],
                'warn' => ['bg' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'label' => __('error_logs_level_warn')],
                'info' => ['bg' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400', 'label' => __('error_logs_level_info')]
              ];
              $lvl = $levelClasses[$log->level] ?? $levelClasses['error'];
              
              $statusClasses = [
                'new' => ['bg' => 'bg-red-500/10 text-red-600 dark:text-red-400', 'label' => __('error_logs_status_new')],
                'investigating' => ['bg' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'label' => __('error_logs_status_in_progress')],
                'resolved' => ['bg' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', 'label' => __('error_logs_status_resolved')],
                'ignored' => ['bg' => 'bg-slate-500/10 text-slate-600 dark:text-slate-400', 'label' => $ignoredLabel]
              ];
              $st = $statusClasses[$log->status] ?? $statusClasses['new'];
            @endphp
            <tr id="log-row-{{ $log->id }}" class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50/50 dark:hover:bg-slate-850/20 transition-all duration-300">
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black {{ $lvl['bg'] }}">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                  <span>{{ $lvl['label'] }}</span>
                </span>
              </td>
              <td class="px-4 py-3 max-w-[200px] sm:max-w-xs text-start">
                <span class="text-slate-800 dark:text-slate-200 font-bold block truncate" title="{{ $log->translatedMessage }}">
                  {{ $log->translatedMessage }}
                </span>
              </td>
              <td class="px-4 py-3 hidden sm:table-cell text-start">
                <span class="text-slate-500 dark:text-slate-450 text-xs font-semibold truncate block max-w-[120px]" title="{{ $log->source }}">
                  {{ $log->source ?? '-' }}
                </span>
              </td>
              <td class="px-4 py-3 hidden md:table-cell">
                <span class="px-2.5 py-1 rounded-xl text-xs font-black {{ $st['bg'] }}">
                  {{ $st['label'] }}
                </span>
              </td>
              <td class="px-4 py-3 hidden md:table-cell">
                @if($log->count > 1)
                  <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 bg-slate-100 dark:bg-slate-850 rounded-full text-xs font-bold text-slate-600 dark:text-slate-350">
                    {{ $compactNumber($log->count) }}
                  </span>
                @else
                  <span class="text-slate-400 dark:text-slate-550 text-xs">-</span>
                @endif
              </td>
              <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-semibold whitespace-nowrap">
                {{ optional($log->createdAt)->format('Y-m-d H:i') }}
              </td>
              <td class="px-4 py-3">
                <button
                  onclick='openErrorLogDetails(@json($log))'
                  class="p-1.5 rounded-lg hover:bg-slate-150 dark:hover:bg-slate-800 text-slate-400 hover:text-teal-500 dark:hover:text-teal-400 transition-all cursor-pointer"
                  title="{{ __('error_logs_details_action') }}"
                >
                  <!-- ExternalLink SVG -->
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>
                </button>
              </td>
            </tr>
          @empty
            <tr id="no-matching-row">
              <td colSpan="7" class="text-center py-12 text-slate-450 dark:text-slate-500 text-sm font-bold">
                {{ __('error_logs_no_logs') }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination Footer -->
    <div id="pagination-footer" class="flex flex-col gap-3 px-4 py-3 border-t border-slate-100 dark:border-slate-800/80 sm:flex-row sm:items-center sm:justify-between">
      <span id="pagination-summary" class="text-xs text-slate-500 dark:text-slate-400 font-medium">
        {{ $paginationSummary }}
      </span>
      <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-1.5">
          <span class="hidden text-xs font-black text-slate-400 sm:inline">{{ $isAr ? 'السجلات المعروضة' : 'Rows shown' }}</span>
          <select
            id="per-page-select"
            name="per-page"
            aria-label="{{ $isAr ? 'السجلات المعروضة' : 'Rows shown' }}"
            onchange="handlePerPageChange()"
            class="h-9 rounded-xl border border-slate-200 bg-white px-2 text-xs font-black text-slate-700 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          >
            @foreach ([10, 25, 50, 100] as $pageSize)
              <option value="{{ $pageSize }}" @selected($logs->perPage() === $pageSize)>{{ $compactNumber($pageSize) }}</option>
            @endforeach
          </select>
        </div>
        <button
          id="prev-page-btn"
          {{ $logs->onFirstPage() ? 'disabled' : '' }}
          onclick="handlePageChange({{ $logs->currentPage() - 1 }})"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          <svg class="w-4 h-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          <span>{{ $isAr ? 'السابق' : 'Previous' }}</span>
        </button>
        <button
          id="next-page-btn"
          {{ $logs->hasMorePages() ? '' : 'disabled' }}
          onclick="handlePageChange({{ $logs->currentPage() + 1 }})"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          <span>{{ $isAr ? 'التالي' : 'Next' }}</span>
          <svg class="w-4 h-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <div class="flex items-center gap-1.5">
          <span class="hidden text-xs font-black text-slate-400 sm:inline">{{ $isAr ? 'انتقل لصفحة' : 'Go to page' }}</span>
          <input
            id="page-jump-input"
            name="page-jump"
            aria-label="{{ $isAr ? 'انتقل لصفحة' : 'Go to page' }}"
            type="number"
            min="1"
            max="{{ $logs->lastPage() }}"
            onkeydown="if (event.key === 'Enter') jumpToPage()"
            class="h-9 w-16 rounded-xl border border-slate-200 bg-white px-2 text-center text-xs font-black text-slate-700 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            placeholder="#"
          />
          <button
            type="button"
            onclick="jumpToPage()"
            class="h-9 rounded-xl bg-slate-100 px-3 text-xs font-black text-slate-600 transition hover:bg-teal-100 hover:text-teal-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-teal-950/30 dark:hover:text-teal-300"
          >
            {{ $isAr ? 'انتقال' : 'Go' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Clear Logs Confirmation Modal -->
  <div
    id="clear-logs-modal"
    class="fixed inset-0 z-50 flex hidden items-center justify-center bg-black/0 p-4 text-start backdrop-blur-xs transition-all duration-300"
    onclick="closeClearLogsConfirm()"
  >
    <div
      id="clear-logs-modal-card"
      class="w-full max-w-md scale-95 overflow-hidden rounded-3xl border border-red-100 bg-white opacity-0 shadow-2xl transition-all duration-300 dark:border-red-950/40 dark:bg-slate-900"
      onclick="event.stopPropagation()"
    >
      <div class="p-6">
        <div class="mb-5 flex items-start gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-red-500/10 text-red-600 dark:bg-red-500/15 dark:text-red-400">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
          </div>
          <div>
            <h2 class="text-base font-black text-slate-900 dark:text-white">
              {{ $isAr ? 'تأكيد مسح سجل الأخطاء' : 'Confirm Error Log Clear' }}
            </h2>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
              {{ $isAr ? 'سيتم حذف جميع سجلات أخطاء النظام نهائياً. لا يمكن التراجع عن هذا الإجراء.' : 'All system error logs will be permanently deleted. This action cannot be undone.' }}
            </p>
          </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            onclick="closeClearLogsConfirm()"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-all hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer"
          >
            {{ __('cancel') }}
          </button>
          <button
            id="confirm-clear-logs-btn"
            type="button"
            onclick="confirmClearLogs()"
            class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-xs transition-all hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
          >
            {{ __('error_logs_clear') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Error Log Details Modal -->
  <div
    id="error-details-modal"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/0 backdrop-blur-xs hidden transition-all duration-300 text-start"
    onclick="closeErrorLogDetails()"
  >
    <div
      class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300"
      onclick="event.stopPropagation()"
      id="modal-card"
    >
      <div class="p-6">
        <!-- Modal Header -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ __('error_logs_details_title') }}</h2>
          <button onclick="closeErrorLogDetails()" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 cursor-pointer transition-colors">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
          </button>
        </div>

        <!-- Modal Body Content -->
        <div class="space-y-4 mb-6">
          <div>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">{{ __('error_logs_message') }}</span>
            <p id="modal-message" class="text-sm font-semibold text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-850 p-3 rounded-xl max-h-24 overflow-y-auto leading-relaxed border border-slate-150 dark:border-slate-800/80">
              -
            </p>
          </div>
          <div id="modal-original-message-container" class="hidden">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">{{ __('error_logs_original_message') }}</span>
            <p id="modal-original-message" dir="ltr" class="text-xs font-mono text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-850 p-3 rounded-xl max-h-20 overflow-y-auto leading-relaxed border border-slate-150 dark:border-slate-800/80 text-left">
              -
            </p>
          </div>
          <div>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">{{ __('error_logs_source') }}</span>
            <p id="modal-source" class="text-sm font-medium text-slate-600 dark:text-slate-350">
              -
            </p>
          </div>
          <div id="modal-stack-container">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">Stack Trace</span>
            <pre id="modal-stack" class="text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-850 p-3 rounded-xl overflow-x-auto max-h-32 leading-relaxed font-mono border border-slate-150 dark:border-slate-800/80 select-all">
              -
            </pre>
          </div>
          <div id="modal-count-container" class="hidden">
            <span id="modal-count-pill" class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/10 text-amber-700 dark:text-amber-400 rounded-lg text-xs font-bold border border-amber-500/20">
              -
            </span>
          </div>
          <div>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">{{ __('error_logs_update_status') }}</span>
            <select
              id="modal-status-select"
              class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/20 dark:text-slate-200 cursor-pointer"
            >
              <option value="new">{{ __('error_logs_status_new') }}</option>
              <option value="investigating">{{ __('error_logs_status_in_progress') }}</option>
              <option value="resolved">{{ __('error_logs_status_resolved') }}</option>
              <option value="ignored">{{ $ignoredLabel }}</option>
            </select>
          </div>
          <div>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-1">{{ __('error_logs_resolution_notes') }}</span>
            <textarea
              id="modal-notes-textarea"
              placeholder="{{ __('error_logs_resolution_notes_placeholder') }}"
              class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/20 dark:text-slate-250 resize-none min-h-[80px]"
            ></textarea>
          </div>
        </div>

        <!-- Modal Actions Footer -->
        <div class="flex gap-3">
          @can('operations.error-logs.delete')
            <button
              id="modal-delete-btn"
              onclick="handleDeleteSelectedLog()"
              class="flex-1 py-2.5 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 rounded-xl text-sm font-bold hover:bg-red-50 dark:hover:bg-red-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
            >
              {{ __('error_logs_delete') }}
            </button>
          @endif
          <button
            onclick="closeErrorLogDetails()"
            class="flex-1 py-2.5 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all cursor-pointer"
          >
            {{ __('cancel') }}
          </button>
          <button
            id="modal-save-btn"
            onclick="handleUpdateStatus()"
            class="flex-1 py-2.5 bg-linear-to-r from-teal-600 to-emerald-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-teal-200/50 dark:shadow-teal-950/20 hover:shadow-xl transition-all cursor-pointer"
          >
            {{ __('save_changes') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@php
  $errorLogsConfig = [
    'isAr' => $isAr,
    'canDeleteLogs' => (bool) auth()->user()?->can('operations.error-logs.delete'),
    'currentPage' => $logs->currentPage(),
    'perPage' => $logs->perPage(),
    'csrf' => csrf_token(),
    'routes' => [
      'index' => route('dashboard.error-logs'),
      'clear' => route('dashboard.error-logs.clear'),
      'base' => url('dashboard/error-logs'),
    ],
    'texts' => [
      'error' => __('error_logs_level_error'),
      'warn' => __('error_logs_level_warn'),
      'info' => __('error_logs_level_info'),
      'new' => __('error_logs_status_new'),
      'investigating' => __('error_logs_status_in_progress'),
      'resolved' => __('error_logs_status_resolved'),
      'ignored' => $ignoredLabel,
      'not_available' => __('not_available'),
      'no_logs' => __('error_logs_no_logs'),
      'no_matching' => __('error_logs_no_matching_results'),
      'repeated_tpl' => __('error_logs_repeated_count'),
      'clear_confirm' => $isAr ? 'هل أنت متأكد من رغبتك في تفريغ وحذف جميع سجلات أخطاء النظام نهائياً؟' : 'Are you sure you want to permanently clear and delete all system error logs?',
      'delete_confirm' => $isAr ? 'هل أنت متأكد من رغبتك في حذف هذا السجل بشكل نهائي؟' : 'Are you sure you want to permanently delete this log?',
      'load_failed' => $isAr ? 'حدث خطأ أثناء تحميل السجلات.' : 'An error occurred while loading logs.',
      'saving' => $isAr ? 'جاري الحفظ...' : 'Saving...',
      'logs_label' => $isAr ? 'سجلات' : 'Logs',
      'page_label' => $isAr ? 'صفحة' : 'Page',
      'of_label' => $isAr ? 'من' : 'of',
      'shown_from_total' => $isAr ? 'معروضة من' : 'shown of',
      'unknown' => __('unknown'),
      'details_action' => __('error_logs_details_action'),
      'clearing' => __('error_logs_clearing'),
      'clear' => __('error_logs_clear'),
      'deleting' => __('error_logs_deleting'),
      'delete' => __('error_logs_delete'),
      'save_changes' => __('save_changes'),
    ],
  ];
@endphp
<script type="application/json" id="error-logs-config">@json($errorLogsConfig, JSON_HEX_TAG | JSON_HEX_AMP)</script>
@endsection
