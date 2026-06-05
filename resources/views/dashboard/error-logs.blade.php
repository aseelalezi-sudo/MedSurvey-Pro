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
  $formatNumber = fn ($value) => number_format((float) $value);
  $compactNumber = function ($value): string {
      $value = (float) $value;
      $abs = abs($value);

      if ($abs >= 1000000) {
          return rtrim(rtrim(number_format($value / 1000000, $abs >= 10000000 ? 0 : 1), '0'), '.').'M';
      }

      if ($abs >= 1000) {
          return rtrim(rtrim(number_format($value / 1000, $abs >= 10000 ? 0 : 1), '0'), '.').'K';
      }

      return number_format($value, 0);
  };
  $paginationSummary = $isAr
    ? 'سجلات: '.$formatNumber($logs->total()).' · صفحة '.$formatNumber($logs->currentPage()).' من '.$formatNumber($logs->lastPage())
    : 'Logs: '.$formatNumber($logs->total()).' · Page '.$formatNumber($logs->currentPage()).' of '.$formatNumber($logs->lastPage());
  $searchIconClass = $isAr ? 'right-3' : 'left-3';
  $searchInputPadding = $isAr ? 'pr-10 pl-4' : 'pl-10 pr-4';
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
      @if(auth()->user()?->role === 'super_admin')
        <button
          id="clear-logs-btn"
          onclick="handleClearLogs()"
          class="flex items-center gap-2 px-4 py-2 bg-red-600 border border-red-600 rounded-xl text-sm font-bold text-white hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer shadow-xs"
        >
          <!-- Trash SVG -->
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
          <span id="clear-logs-text">{{ __('error_logs_clear') }}</span>
        </button>
      @endif
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
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-4 rounded-3xl shadow-xs mb-6">
    <div class="flex flex-col md:flex-row gap-3">
      <!-- Search Input -->
      <div class="relative flex-1">
        <!-- Search SVG -->
        <svg class="absolute {{ $searchIconClass }} top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input
          id="search-input"
          oninput="handleFilterChange()"
          placeholder="{{ __('error_logs_search_placeholder') }}"
          class="w-full {{ $searchInputPadding }} py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:border-teal-500 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/20 dark:text-slate-250 transition-all text-start"
        />
      </div>
      <!-- Level Dropdown -->
      <select
        id="level-filter"
        onchange="handleFilterChange()"
        class="px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/20 dark:text-slate-250 cursor-pointer text-start"
      >
        <option value="all">{{ __('error_logs_all_levels') }}</option>
        <option value="error">{{ __('error_logs_level_error') }}</option>
        <option value="warn">{{ __('error_logs_level_warn') }}</option>
        <option value="info">{{ __('error_logs_level_info') }}</option>
      </select>
      <!-- Status Dropdown -->
      <select
        id="status-filter"
        onchange="handleFilterChange()"
        class="px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/20 dark:text-slate-250 cursor-pointer text-start"
      >
        <option value="all">{{ __('error_logs_all_statuses') }}</option>
        <option value="new">{{ __('error_logs_status_new') }}</option>
        <option value="investigating">{{ __('error_logs_status_in_progress') }}</option>
        <option value="resolved">{{ __('error_logs_status_resolved') }}</option>
        <option value="ignored">{{ $ignoredLabel }}</option>
      </select>
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
                <span class="text-slate-800 dark:text-slate-200 font-bold block truncate" title="{{ $log->message }}">
                  {{ $log->message }}
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
    <div id="pagination-footer" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 dark:border-slate-800/80">
      <span id="pagination-summary" class="text-xs text-slate-500 dark:text-slate-400 font-medium">
        {{ $paginationSummary }}
      </span>
      <div class="flex items-center gap-1">
        <button
          id="prev-page-btn"
          {{ $logs->onFirstPage() ? 'disabled' : '' }}
          onclick="handlePageChange({{ $logs->currentPage() - 1 }})"
          class="p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-30 text-slate-500 dark:text-slate-400 cursor-pointer disabled:cursor-not-allowed transition-all"
        >
          <svg class="w-4 h-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button
          id="next-page-btn"
          {{ $logs->hasMorePages() ? '' : 'disabled' }}
          onclick="handlePageChange({{ $logs->currentPage() + 1 }})"
          class="p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-30 text-slate-500 dark:text-slate-400 cursor-pointer disabled:cursor-not-allowed transition-all"
        >
          <svg class="w-4 h-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
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
          @if(auth()->user()?->role === 'super_admin')
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    const isRtl = document.documentElement.dir === 'rtl' || "{{ app()->getLocale() }}" === 'ar';
    const isSuperAdmin = {{ auth()->user()?->role === 'super_admin' ? 'true' : 'false' }};
    
    let currentPage = 1;
    let selectedLog = null;
    const formatNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));
    const compactNumber = (value) => {
        const number = Number(value || 0);
        const abs = Math.abs(number);

        if (abs >= 1000000) {
            return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
        }

        if (abs >= 1000) {
            return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
        }

        return formatNumber(number);
    };
    
    // Translation resources
    const t = {
        error: "{{ __('error_logs_level_error') }}",
        warn: "{{ __('error_logs_level_warn') }}",
        info: "{{ __('error_logs_level_info') }}",
        new: "{{ __('error_logs_status_new') }}",
        investigating: "{{ __('error_logs_status_in_progress') }}",
        resolved: "{{ __('error_logs_status_resolved') }}",
        ignored: @js($ignoredLabel),
        not_available: "{{ __('not_available') }}",
        no_logs: "{{ __('error_logs_no_logs') }}",
        no_matching: "{{ __('error_logs_no_matching_results') }}",
        repeated_tpl: "{{ __('error_logs_repeated_count') }}",
        clear_confirm: @js($isAr ? 'هل أنت متأكد من رغبتك في تفريغ وحذف جميع سجلات أخطاء النظام نهائياً؟' : 'Are you sure you want to permanently clear and delete all system error logs?'),
        delete_confirm: @js($isAr ? 'هل أنت متأكد من رغبتك في حذف هذا السجل بشكل نهائي؟' : 'Are you sure you want to permanently delete this log?'),
        load_failed: @js($isAr ? 'حدث خطأ أثناء تحميل السجلات.' : 'An error occurred while loading logs.'),
        saving: @js($isAr ? 'جاري الحفظ...' : 'Saving...'),
        logs_label: @js($isAr ? 'سجلات' : 'Logs'),
        page_label: @js($isAr ? 'صفحة' : 'Page'),
        of_label: @js($isAr ? 'من' : 'of'),
        unknown: "{{ __('unknown') }}"
    };

    // Card Counter updates
    function updateStatsHeader(stats) {
        if (!stats) return;
        
        const errors = stats.byLevel.find(l => l.level === 'error')?.count || 0;
        const totalNew = stats.byStatus.find(s => s.status === 'new')?.count || 0;
        const totalInProgress = stats.byStatus.find(s => s.status === 'investigating')?.count || 0;
        const totalSources = stats.topSources.length || 0;
        
        document.getElementById("stat-errors").textContent = compactNumber(errors);
        document.getElementById("stat-errors").title = formatNumber(errors);
        document.getElementById("stat-new").textContent = compactNumber(totalNew);
        document.getElementById("stat-new").title = formatNumber(totalNew);
        document.getElementById("stat-inprogress").textContent = compactNumber(totalInProgress);
        document.getElementById("stat-inprogress").title = formatNumber(totalInProgress);
        document.getElementById("stat-sources").textContent = compactNumber(totalSources);
        document.getElementById("stat-sources").title = formatNumber(totalSources);
        
        // Update top sources badges
        const sourcesPanel = document.getElementById("top-sources-panel");
        const sourcesList = document.getElementById("top-sources-list");
        if (totalSources === 0) {
            sourcesPanel.classList.add("hidden");
        } else {
            sourcesPanel.classList.remove("hidden");
            sourcesList.innerHTML = stats.topSources.map(s => `
                <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800/80 rounded-xl text-xs font-bold text-slate-650 dark:text-slate-350 border border-slate-100 dark:border-slate-800">
                    ${escapeHtml(s.source || t.unknown)} (${compactNumber(s.count)})
                </span>
            `).join('');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Modal Window functions
    window.openErrorLogDetails = function (log) {
        selectedLog = log;
        
        document.getElementById("modal-message").textContent = log.message;
        document.getElementById("modal-source").textContent = log.source || '-';
        
        const stackElement = document.getElementById("modal-stack");
        const stackContainer = document.getElementById("modal-stack-container");
        if (log.stack) {
            stackElement.textContent = log.stack;
            stackContainer.classList.remove("hidden");
        } else {
            stackContainer.classList.add("hidden");
        }
        
        const countContainer = document.getElementById("modal-count-container");
        const countPill = document.getElementById("modal-count-pill");
        if (log.count > 1) {
            countPill.textContent = t.repeated_tpl.replace('\x7b\x7bcount\x7d\x7d', formatNumber(log.count));
            countContainer.classList.remove("hidden");
        } else {
            countContainer.classList.add("hidden");
        }
        
        document.getElementById("modal-status-select").value = log.status;
        document.getElementById("modal-notes-textarea").value = log.resolutionNotes || '';
        
        // Show modal with animation
        const modal = document.getElementById("error-details-modal");
        const card = document.getElementById("modal-card");
        
        modal.classList.remove("hidden");
        // Reflow
        modal.offsetHeight;
        
        modal.classList.remove("bg-black/0");
        modal.classList.add("bg-black/50");
        card.classList.remove("scale-95", "opacity-0");
        card.classList.add("scale-100", "opacity-100");
    };

    window.closeErrorLogDetails = function () {
        const modal = document.getElementById("error-details-modal");
        const card = document.getElementById("modal-card");
        
        card.classList.remove("scale-100", "opacity-100");
        card.classList.add("scale-95", "opacity-0");
        modal.classList.remove("bg-black/50");
        modal.classList.add("bg-black/0");
        
        setTimeout(() => {
            modal.classList.add("hidden");
            selectedLog = null;
        }, 300);
    };

    // Fetch filtered data
    window.fetchLogsData = function (page = 1) {
        currentPage = page;
        
        const level = document.getElementById("level-filter").value;
        const status = document.getElementById("status-filter").value;
        const search = document.getElementById("search-input").value;
        
        const params = new URLSearchParams({
            ajax: 'true',
            page: page,
            level: level,
            status: status,
            search: search
        });
        
        // Set loading state
        const tbody = document.getElementById("logs-table-body");
        tbody.innerHTML = `
            <tr>
              <td colspan="7" class="text-center py-12">
                <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mx-auto"></div>
              </td>
            </tr>
        `;
        
        fetch(`{{ route('dashboard.error-logs') }}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error("Connection failed");
            return res.json();
        })
        .then(data => {
            renderTableRows(data.logs);
            renderPagination(data.pagination);
            updateStatsHeader(data.stats);
        })
        .catch(err => {
            console.error("Failed to load logs:", err);
            tbody.innerHTML = `
                <tr>
                  <td colspan="7" class="text-center py-12 text-rose-500 font-bold">
                    ${t.load_failed}
                  </td>
                </tr>
            `;
        });
    };

    window.handleFilterChange = function () {
        fetchLogsData(1);
    };

    window.handlePageChange = function (page) {
        fetchLogsData(page);
    };

    function renderTableRows(logs) {
        const tbody = document.getElementById("logs-table-body");
        if (logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                  <td colspan="7" class="text-center py-12 text-slate-450 dark:text-slate-500 text-sm font-bold">
                    ${t.no_matching}
                  </td>
                </tr>
            `;
            return;
        }
        
        const levelClasses = {
            error: 'bg-red-500/10 text-red-600 dark:text-red-400',
            warn: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
        };
        
        const statusClasses = {
            new: 'bg-red-500/10 text-red-600 dark:text-red-400',
            investigating: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            resolved: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            ignored: 'bg-slate-500/10 text-slate-600 dark:text-slate-400'
        };

        tbody.innerHTML = logs.map(log => {
            const lvlBg = levelClasses[log.level] || levelClasses.error;
            const lvlLabel = t[log.level] || t.error;
            const stBg = statusClasses[log.status] || statusClasses.new;
            const stLabel = t[log.status] || t.new;
            
            // Safe JSON rendering inside Javascript click handler
            const logSafeJson = JSON.stringify(log)
                .replace(/&/g, '&amp;')
                .replace(/'/g, '&#39;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const logDate = new Date(log.createdAt).toLocaleString(isRtl ? 'ar-SA' : 'en-US', {
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false
            });
            
            const countPill = log.count > 1 
                ? `<span class="stat-badge inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-slate-100 px-1.5 text-xs font-bold text-slate-600 dark:bg-slate-850 dark:text-slate-350" title="${formatNumber(log.count)}">${compactNumber(log.count)}</span>`
                : `<span class="text-slate-400 dark:text-slate-550 text-xs">-</span>`;
                
            return `
                <tr id="log-row-${log.id}" class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50/50 dark:hover:bg-slate-850/20 transition-all duration-300">
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black ${lvlBg}">
                      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                      <span>${escapeHtml(lvlLabel)}</span>
                    </span>
                  </td>
                  <td class="px-4 py-3 max-w-[200px] sm:max-w-xs text-start">
                    <span class="text-slate-800 dark:text-slate-200 font-bold block truncate" title="${escapeHtml(log.message)}">
                      ${escapeHtml(log.message)}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden sm:table-cell text-start">
                    <span class="text-slate-500 dark:text-slate-450 text-xs font-semibold truncate block max-w-[120px]" title="${escapeHtml(log.source || '')}">
                      ${escapeHtml(log.source || '-')}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden md:table-cell">
                    <span class="px-2.5 py-1 rounded-xl text-xs font-black ${stBg}">
                      ${escapeHtml(stLabel)}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden md:table-cell">
                    ${countPill}
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-semibold whitespace-nowrap">
                    ${escapeHtml(logDate)}
                  </td>
                  <td class="px-4 py-3">
                    <button
                      onclick="openErrorLogDetails(JSON.parse('${logSafeJson}'))"
                      class="p-1.5 rounded-lg hover:bg-slate-150 dark:hover:bg-slate-800 text-slate-400 hover:text-teal-500 dark:hover:text-teal-400 transition-all cursor-pointer"
                      title="{{ __('error_logs_details_action') }}"
                    >
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>
                    </button>
                  </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pageInfo) {
        document.getElementById("pagination-summary").textContent = isRtl
            ? `${t.logs_label}: ${formatNumber(pageInfo.total)} · ${t.page_label} ${formatNumber(pageInfo.page)} ${t.of_label} ${formatNumber(pageInfo.totalPages)}`
            : `${t.logs_label}: ${formatNumber(pageInfo.total)} · ${t.page_label} ${formatNumber(pageInfo.page)} ${t.of_label} ${formatNumber(pageInfo.totalPages)}`;
            
        const prevBtn = document.getElementById("prev-page-btn");
        const nextBtn = document.getElementById("next-page-btn");
        
        if (pageInfo.page <= 1) {
            prevBtn.setAttribute("disabled", "disabled");
            prevBtn.onclick = null;
        } else {
            prevBtn.removeAttribute("disabled");
            prevBtn.onclick = () => handlePageChange(pageInfo.page - 1);
        }
        
        if (pageInfo.page >= pageInfo.totalPages) {
            nextBtn.setAttribute("disabled", "disabled");
            nextBtn.onclick = null;
        } else {
            nextBtn.removeAttribute("disabled");
            nextBtn.onclick = () => handlePageChange(pageInfo.page + 1);
        }
    }

    // Refresh data and load stats
    window.refreshLogsData = function () {
        const icon = document.getElementById("refresh-icon");
        icon.classList.add("animate-spin");
        
        fetch(`{{ route('dashboard.error-logs') }}?ajax=true&page=${currentPage}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            renderTableRows(data.logs);
            renderPagination(data.pagination);
            updateStatsHeader(data.stats);
        })
        .finally(() => {
            setTimeout(() => icon.classList.remove("animate-spin"), 500);
        });
    };

    // Clear logs
    window.handleClearLogs = function () {
        if (!isSuperAdmin) return;
        if (!confirm(t.clear_confirm)) return;
        
        const btn = document.getElementById("clear-logs-btn");
        const text = document.getElementById("clear-logs-text");
        btn.setAttribute("disabled", "disabled");
        text.textContent = "{{ __('error_logs_clearing') }}";
        
        fetch("{{ route('dashboard.error-logs.clear') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reset stats and view
                document.getElementById("stat-errors").textContent = "0";
                document.getElementById("stat-new").textContent = "0";
                document.getElementById("stat-inprogress").textContent = "0";
                document.getElementById("stat-sources").textContent = "0";
                document.getElementById("top-sources-panel").classList.add("hidden");
                
                fetchLogsData(1);
            }
        })
        .finally(() => {
            btn.removeAttribute("disabled");
            text.textContent = "{{ __('error_logs_clear') }}";
        });
    };

    // Delete single log
    window.handleDeleteSelectedLog = function () {
        if (!isSuperAdmin || !selectedLog) return;
        if (!confirm(t.delete_confirm)) return;
        
        const delBtn = document.getElementById("modal-delete-btn");
        delBtn.setAttribute("disabled", "disabled");
        delBtn.textContent = "{{ __('error_logs_deleting') }}";
        
        fetch(`{{ url('dashboard/error-logs') }}/${selectedLog.id}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeErrorLogDetails();
                fetchLogsData(currentPage);
            }
        })
        .finally(() => {
            delBtn.removeAttribute("disabled");
            delBtn.textContent = "{{ __('error_logs_delete') }}";
        });
    };

    // Update status
    window.handleUpdateStatus = function () {
        if (!selectedLog) return;
        
        const status = document.getElementById("modal-status-select").value;
        const notes = document.getElementById("modal-notes-textarea").value;
        
        const saveBtn = document.getElementById("modal-save-btn");
        saveBtn.setAttribute("disabled", "disabled");
        saveBtn.textContent = t.saving;
        
        fetch(`{{ url('dashboard/error-logs') }}/${selectedLog.id}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                status: status,
                resolutionNotes: notes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeErrorLogDetails();
                fetchLogsData(currentPage);
            }
        })
        .finally(() => {
            saveBtn.removeAttribute("disabled");
            saveBtn.textContent = "{{ __('save_changes') }}";
        });
    };
});
</script>
@endsection
