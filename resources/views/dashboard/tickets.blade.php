@extends('layouts.dashboard')

@section('title', __('nav_tickets') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $user = auth()->user();
    $isRtl = app()->getLocale() === 'ar';
    $restrictedDepartment = ($user->role === 'head_of_department' && $user->department) ? $user->department : null;

    $dateFilter = request()->query('dateFilter', 'all');
    $selectedDept = request()->query('department', 'all');
    $startDate = request()->query('startDate', '');
    $endDate = request()->query('endDate', '');
    $statusFilter = request()->query('status', '');
    $priorityFilter = request()->query('priority', '');
    $searchQuery = request()->query('q', '');

    $isAr = $isRtl;
    $priorityLabels = $isAr ? [
        'high' => 'عالية',
        'medium' => 'متوسطة',
        'low' => 'منخفضة'
    ] : [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low'
    ];

    $statusLabels = [
        'open' => __('ticket_status_open') ?: ($isAr ? 'مفتوحة' : 'Open'),
        'in_progress' => __('ticket_status_in_progress') ?: ($isAr ? 'قيد المعالجة' : 'In Progress'),
        'resolved' => __('ticket_status_resolved') ?: ($isAr ? 'تم الحل' : 'Resolved')
    ];
    $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
    $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
  @endphp

  <div x-data="ticketsComponent()" class="space-y-6 animate-fade-in font-cairo">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-kicker">{{ $isAr ? 'المتابعة والآراء والشكاوى' : 'Complaints & Follow-up' }}</p>
        <h1 class="page-title">{{ __('tickets_title') }}</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('tickets_subtitle') }}</p>
      </div>
      <div class="inline-flex max-w-full items-center gap-2 rounded-2xl border border-rose-100 bg-rose-50/70 px-4 py-3 text-sm font-black text-rose-700 shadow-sm dark:border-rose-950/40 dark:bg-rose-950/20 dark:text-rose-300">
        <i data-lucide="circle-alert" class="h-4 w-4"></i>
        <span class="min-w-0 truncate" title="{{ $formatNumber($tickets->total()) }} {{ $isAr ? 'تذكرة بلاغ' : 'Tickets' }}">
          <span class="stat-number-tight">{{ $compactNumber($tickets->total()) }}</span> {{ $isAr ? 'تذكرة بلاغ' : 'Tickets' }}
        </span>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid gap-4 sm:grid-cols-3">
      <!-- Open Stats -->
      <div class="metric-card group overflow-hidden relative transition-all duration-300 hover:shadow-lg">
        <div class="absolute right-0 top-0 h-16 w-16 rounded-bl-full bg-linear-to-r from-rose-500 to-red-500 opacity-5"></div>
        <div class="flex items-center justify-between">
          <div class="min-w-0">
            <p class="text-xs font-black text-slate-400 uppercase tracking-wider">{{ __('ticket_status_open') }}</p>
            <p class="stat-number mt-2 text-3xl font-black text-rose-600 dark:text-rose-450" title="{{ $formatNumber($ticketStats['open'] ?? 0) }}">{{ $compactNumber($ticketStats['open'] ?? 0) }}</p>
          </div>
          <div class="rounded-2xl bg-rose-50 p-3.5 text-rose-500 transition-all group-hover:scale-110 dark:bg-rose-950/30 dark:text-rose-400">
            <i data-lucide="circle-alert" class="h-6 w-6"></i>
          </div>
        </div>
      </div>

      <!-- In Progress Stats -->
      <div class="metric-card group overflow-hidden relative transition-all duration-300 hover:shadow-lg">
        <div class="absolute right-0 top-0 h-16 w-16 rounded-bl-full bg-linear-to-r from-blue-500 to-indigo-500 opacity-5"></div>
        <div class="flex items-center justify-between">
          <div class="min-w-0">
            <p class="text-xs font-black text-slate-400 uppercase tracking-wider">{{ __('ticket_status_in_progress') }}</p>
            <p class="stat-number mt-2 text-3xl font-black text-blue-600 dark:text-blue-450" title="{{ $formatNumber($ticketStats['in_progress'] ?? 0) }}">{{ $compactNumber($ticketStats['in_progress'] ?? 0) }}</p>
          </div>
          <div class="rounded-2xl bg-blue-50 p-3.5 text-blue-500 transition-all group-hover:scale-110 dark:bg-blue-950/30 dark:text-blue-400">
            <i data-lucide="timer" class="h-6 w-6"></i>
          </div>
        </div>
      </div>

      <!-- Resolved Stats -->
      <div class="metric-card group overflow-hidden relative transition-all duration-300 hover:shadow-lg">
        <div class="absolute right-0 top-0 h-16 w-16 rounded-bl-full bg-linear-to-r from-emerald-500 to-teal-500 opacity-5"></div>
        <div class="flex items-center justify-between">
          <div class="min-w-0">
            <p class="text-xs font-black text-slate-400 uppercase tracking-wider">{{ __('ticket_status_resolved') }}</p>
            <p class="stat-number mt-2 text-3xl font-black text-emerald-600 dark:text-emerald-450" title="{{ $formatNumber($ticketStats['resolved'] ?? 0) }}">{{ $compactNumber($ticketStats['resolved'] ?? 0) }}</p>
          </div>
          <div class="rounded-2xl bg-emerald-50 p-3.5 text-emerald-500 transition-all group-hover:scale-110 dark:bg-emerald-950/30 dark:text-emerald-400">
            <i data-lucide="badge-check" class="h-6 w-6"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters Panel -->
    <div class="dashboard-panel p-5 space-y-4">
      <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        <!-- Search Query -->
        <label class="relative">
          <i data-lucide="search" class="absolute {{ $isRtl ? 'right-3' : 'left-3' }} top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
          <input 
            type="text" 
            x-model="q" 
            @input.debounce.400ms="searchTickets()"
            @keydown.enter="searchTickets()"
            placeholder="{{ __('tickets_search_placeholder') }}" 
            class="w-full rounded-xl border border-slate-200 bg-white py-3 {{ $isRtl ? 'pl-3 pr-10' : 'pr-3 pl-10' }} text-sm font-bold text-slate-700 outline-none transition focus:border-teal-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
          >
        </label>

        <!-- Status Filter -->
        <select 
          x-model="status" 
          @change="searchTickets()"
          class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700 outline-none transition focus:border-teal-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
        >
          <option value="">{{ $isAr ? 'كل الحالات' : 'All Statuses' }}</option>
          <option value="open">{{ __('ticket_status_open') }}</option>
          <option value="in_progress">{{ __('ticket_status_in_progress') }}</option>
          <option value="resolved">{{ __('ticket_status_resolved') }}</option>
        </select>

        <!-- Priority Filter -->
        <select 
          x-model="priority" 
          @change="searchTickets()"
          class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700 outline-none transition focus:border-teal-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
        >
          <option value="">{{ $isAr ? 'كل الأولويات' : 'All Priorities' }}</option>
          <option value="high">{{ $priorityLabels['high'] }}</option>
          <option value="medium">{{ $priorityLabels['medium'] }}</option>
          <option value="low">{{ $priorityLabels['low'] }}</option>
        </select>

        <!-- Department Filter -->
        <select 
          x-model="department" 
          @change="searchTickets()"
          :disabled="restrictedDept"
          class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700 outline-none transition focus:border-teal-500 disabled:opacity-60 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
        >
          <option value="all">{{ $isAr ? 'كل الأقسام' : 'All Departments' }}</option>
          @foreach ($departments as $dept)
            <option value="{{ $dept }}">{{ $dept }}</option>
          @endforeach
        </select>
      </div>

      <!-- Advanced Date Filtering Bar -->
      <div class="flex flex-col gap-4 pt-3 border-t border-slate-100 dark:border-slate-850/60 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-2 text-sm font-bold text-slate-500 dark:text-slate-400">
          <i data-lucide="calendar" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          <span>{{ $isAr ? 'تصفية الفترات الزمنية:' : 'Date range filter:' }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          @foreach([
            ['all', $isAr ? 'كل الفترات' : 'All Dates'],
            ['today', $isAr ? 'اليوم' : 'Today'],
            ['week', $isAr ? 'آخر 7 أيام' : 'Last 7 Days'],
            ['month', $isAr ? 'آخر 30 يوماً' : 'Last 30 Days'],
            ['custom', $isAr ? 'تاريخ مخصص' : 'Custom Date']
          ] as $option)
            <button
              type="button"
              @click="setDateFilter('{{ $option[0] }}')"
              class="min-h-9 rounded-xl px-4 py-2 text-xs font-black transition-all"
              :class="dateFilter === '{{ $option[0] }}' 
                ? 'gradient-action text-white shadow-md' 
                : 'bg-slate-50 text-slate-650 hover:bg-slate-100 dark:bg-slate-950 dark:text-slate-450 dark:hover:bg-slate-900'"
            >
              {{ $option[1] }}
            </button>
          @endforeach
        </div>
      </div>

      <!-- Custom Date Pickers (Collapsible via Alpine) -->
      <div 
        x-show="dateFilter === 'custom'" 
        x-collapse 
        class="grid grid-cols-1 gap-3 pt-3 sm:grid-cols-2 border-t border-dashed border-slate-100 dark:border-slate-850/50"
      >
        <label class="space-y-1.5 text-start">
          <span class="block text-xs font-black text-slate-400 uppercase tracking-wider">{{ $isAr ? 'من تاريخ:' : 'From Date:' }}</span>
          <div class="relative">
            <div class="flex min-h-[46px] w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 transition dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500"></i>
              <span class="font-mono text-sm font-bold" dir="ltr" x-text="startDate || 'YYYY-MM-DD'"></span>
            </div>
            <input
              type="date"
              x-model="startDate"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ $isAr ? 'من تاريخ' : 'From Date' }}"
              @change="searchTickets()"
              @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            />
          </div>
        </label>
        <label class="space-y-1.5 text-start">
          <span class="block text-xs font-black text-slate-400 uppercase tracking-wider">{{ $isAr ? 'إلى تاريخ:' : 'To Date:' }}</span>
          <div class="relative">
            <div class="flex min-h-[46px] w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 transition dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100">
              <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500"></i>
              <span class="font-mono text-sm font-bold" dir="ltr" x-text="endDate || 'YYYY-MM-DD'"></span>
            </div>
            <input
              type="date"
              x-model="endDate"
              max="{{ now()->toDateString() }}"
              dir="ltr"
              lang="en-CA"
              aria-label="{{ $isAr ? 'إلى تاريخ' : 'To Date' }}"
              @change="searchTickets()"
              @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            />
          </div>
        </label>
      </div>
    </div>

    <!-- Active Filters Feedback & Reset Button -->
    <div x-show="isFiltered()" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-teal-50/50 p-4 border border-teal-100/50 dark:bg-teal-950/5 dark:border-teal-950/20">
      <div class="flex items-center gap-2 text-xs font-bold text-teal-800 dark:text-teal-400">
        <i data-lucide="info" class="h-4 w-4 shrink-0"></i>
        <span>{{ $isAr ? 'تظهر النتائج بناء على الفلاتر النشطة.' : 'Showing results matching your active filters.' }}</span>
      </div>
      <button 
        type="button" 
        @click="resetFilters()" 
        class="inline-flex items-center gap-1.5 text-xs font-black text-teal-600 hover:text-teal-700 dark:text-teal-400 transition"
      >
        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
        <span>{{ $isAr ? 'إعادة تعيين كافة الفلاتر' : 'Reset all filters' }}</span>
      </button>
    </div>

    <!-- Tickets Grid with Loading Overlay -->
    <div class="relative">
      <div 
        x-show="loadingTickets" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 z-10 flex items-start justify-center pt-12 pointer-events-none"
        aria-live="polite"
      >
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-2xl px-5 py-3 shadow-lg border border-gray-100 dark:border-slate-800 flex items-center gap-3">
          <div class="w-5 h-5 border-2 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
          <span class="text-sm font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'جاري تحديث النتائج...' : 'Updating results...' }}</span>
        </div>
      </div>
      <div 
        id="tickets-grid" 
        class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
        :class="loadingTickets ? 'opacity-40 pointer-events-none' : ''"
      >
        @include('dashboard.partials._ticket-cards')
      </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6" id="tickets-pagination">
      {{ $tickets->links() }}
    </div>

    <!-- Resolution Notes Overlay Modal (Alpine controlled) -->
    <div 
      x-show="resolvingTicketId !== null" 
      class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-[100] p-4 animate-fade-in"
      style="display: none;"
    >
      <div 
        @click.away="resolvingTicketId = null"
        class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-scale-in"
      >
        <div class="bg-linear-to-r from-red-500 to-rose-600 p-6 text-white text-start">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-black">{{ __('tickets_modal_title') }}</h3>
            <button @click="resolvingTicketId = null" type="button" class="text-white/80 hover:text-white transition">
              <i data-lucide="x" class="w-6 h-6"></i>
            </button>
          </div>
          <p class="text-red-100 text-xs font-bold">{{ $isAr ? 'كتابة إجراء الحل لتأكيد إغلاق البلاغ بنجاح' : 'Provide resolution notes to confirm closing complaint' }}</p>
        </div>
        
        <form :action="'{{ url('/dashboard/tickets') }}/' + resolvingTicketId" method="POST" class="p-6 space-y-4 text-start">
          @csrf
          @method('PATCH')
          <input type="hidden" name="status" value="resolved">

          <div>
            <label class="block text-xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">
              {{ __('tickets_form_notes_label') }}
            </label>
            <textarea 
              name="resolutionNotes" 
              rows="4"
              x-model="resolutionNotes"
              placeholder="{{ __('tickets_form_notes_placeholder') }}"
              class="w-full rounded-2xl border-2 border-slate-100 dark:border-slate-800 focus:border-teal-500 p-4 outline-none text-sm bg-white dark:bg-slate-950 dark:text-white"
              required
            ></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-3">
            <button 
              type="button" 
              @click="resolvingTicketId = null"
              class="py-3 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-950 text-xs font-black"
            >
              {{ $isAr ? 'إلغاء' : 'Cancel' }}
            </button>
            <button 
              type="submit"
              :disabled="!resolutionNotes.trim()"
              class="py-3 rounded-2xl bg-emerald-600 text-white font-black text-xs hover:bg-emerald-700 transition disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-md shadow-emerald-100 dark:shadow-none"
            >
              <i data-lucide="badge-check" class="w-4 h-4"></i>
              <span>{{ __('tickets_close_ticket_btn') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- AJAX Loading Spinner Overlay -->
    <div 
      x-show="loadingResponse" 
      class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-[110]"
      style="display: none;"
    >
      <div class="bg-white dark:bg-slate-900 p-8 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-2xl flex flex-col items-center gap-4 animate-scale-in">
        <div class="w-12 h-12 border-4 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-sm font-black text-slate-700 dark:text-white">{{ $isAr ? 'جاري تحميل تفاصيل الاستبيان...' : 'Loading survey response...' }}</p>
      </div>
    </div>

    <!-- AJAX Glassmorphic Survey Response Details Modal Overlay -->
    <div 
      x-show="selectedResponse !== null" 
      class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-[100] p-4 animate-fade-in"
      style="display: none;"
      @click="selectedResponse = null"
    >
      <div 
        @click.stop
        class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl max-w-lg w-full max-h-[85vh] overflow-hidden shadow-2xl animate-scale-in flex flex-col"
      >
        <!-- Modal Dynamic Score-based Gradient Header -->
        <div 
          class="p-6 text-white shrink-0 bg-linear-to-r relative"
          :class="getScoreGradientClass(selectedResponse?.overallScore)"
        >
          <div class="flex items-center justify-between mb-3 text-start">
            <div class="flex items-center gap-2">
              <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/20">
                <i data-lucide="file-text" class="w-5 h-5 text-white"></i>
              </div>
              <div>
                <h3 class="font-black text-base leading-tight">{{ $isAr ? 'تفاصيل الاستبيان للمريض' : 'Detailed Survey Response' }}</h3>
                <p class="text-white/80 text-[10px] mt-0.5">
                  <span>{{ $isAr ? 'تاريخ التقديم:' : 'Submission Date:' }}</span> 
                  <span x-text="formatDate(selectedResponse?.submittedAt)"></span>
                </p>
              </div>
            </div>
            <button 
              @click="selectedResponse = null" 
              type="button" 
              class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition"
            >
              <i data-lucide="x" class="w-4 h-4"></i>
            </button>
          </div>

          <!-- Satisfaction score dynamic banner -->
          <div class="flex items-center justify-between bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 mt-4">
            <span class="text-xs font-black text-white/95">{{ $isAr ? 'نسبة الرضا العامة' : 'Satisfaction rate' }}</span>
            <div class="flex items-center gap-3">
              <span class="text-2xl font-black" x-text="(selectedResponse?.overallScore || 0) + '%'"></span>
              <div class="w-20 h-2.5 bg-white/20 rounded-full overflow-hidden">
                <div class="h-full bg-white rounded-full transition-all duration-500" :style="'width: ' + (selectedResponse?.overallScore || 0) + '%'"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Scrollable survey answers content -->
        <div class="p-6 overflow-y-auto space-y-6 text-start flex-1">
          <!-- Patient profile summary card -->
          <div class="bg-slate-50 dark:bg-slate-950/40 rounded-2xl p-4 border border-slate-150 dark:border-slate-800/80 space-y-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-teal-50 dark:bg-teal-950/40 border border-teal-100 dark:border-teal-900/50 flex items-center justify-center text-teal-700 dark:text-teal-400 font-black text-sm shadow-xs shrink-0">
                <span x-text="selectedResponse?.patientInfo?.name ? selectedResponse.patientInfo.name.charAt(0) : '?'"></span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-black text-sm text-slate-800 dark:text-white truncate" x-text="selectedResponse?.patientInfo?.name || '{{ $isAr ? 'زائر' : 'Anonymous' }}'"></div>
                <template x-if="selectedResponse?.patientInfo?.phone">
                  <div class="text-[11px] text-teal-600 dark:text-teal-400 font-black flex items-center gap-1 mt-0.5" dir="ltr">
                    <i data-lucide="phone" class="w-3 h-3"></i>
                    <span x-text="selectedResponse.patientInfo.phone"></span>
                  </div>
                </template>
              </div>
            </div>

            <!-- Profile labels grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-3 border-t border-slate-200/50 dark:border-slate-800 text-[11px] font-bold text-slate-600 dark:text-slate-350">
              <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 px-2.5 py-2 rounded-xl border border-slate-100 dark:border-slate-800 shadow-xs">
                <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-550 shrink-0"></i>
                <span class="truncate" x-text="selectedResponse?.department"></span>
              </div>
              <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 px-2.5 py-2 rounded-xl border border-slate-100 dark:border-slate-800 shadow-xs">
                <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-550 shrink-0"></i>
                <span class="truncate" x-text="formatGender(selectedResponse?.patientInfo?.gender)"></span>
              </div>
              <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 px-2.5 py-2 rounded-xl border border-slate-100 dark:border-slate-800 shadow-xs col-span-2 sm:col-span-1">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-550 shrink-0"></i>
                <span class="truncate" x-text="selectedResponse?.patientInfo?.ageGroup || '{{ $isAr ? 'العمر غير محدد' : 'Age unspecified' }}'"></span>
              </div>
            </div>
          </div>

          <!-- Answers and Questions details section -->
          <div class="space-y-3">
            <h4 class="font-black text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">{{ $isAr ? 'تفاصيل إجابات المريض ومصفوفة الأسئلة' : 'Detailed answers' }}</h4>
            
            <div class="divide-y divide-slate-100 dark:divide-slate-850 border border-slate-150 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-950 overflow-hidden shadow-xs">
              <template x-for="[key, val] in Object.entries(selectedResponse?.answers || {})" :key="key">
                <template x-if="val !== null && val !== undefined && val !== '' && !key.endsWith('_reason')">
                  <div class="flex flex-col gap-2.5 p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                      <!-- Question title -->
                      <span class="text-xs font-black text-slate-700 dark:text-slate-300 max-w-sm sm:text-end" x-text="getQuestionTitle(key)"></span>
                      
                      <!-- Formatted Answer badge -->
                      <span class="shrink-0 self-start sm:self-auto">
                        <span x-html="renderAnswerValue(key, val)"></span>
                      </span>
                    </div>

                    <!-- Follow up text reasons if present -->
                    <template x-if="selectedResponse?.answers[key + '_reason']">
                      <div class="mt-1 bg-amber-50/40 dark:bg-amber-950/5 border border-dashed border-amber-100/60 dark:border-amber-900/20 rounded-xl p-3 text-xs">
                        <span class="font-black text-amber-800 dark:text-amber-450 text-[10px] block mb-0.5">{{ $isAr ? 'تفصيل المبرر الإضافي للمريض:' : 'Additional justification notes:' }}</span>
                        <p class="text-slate-600 dark:text-slate-350 font-medium italic" x-text="selectedResponse.answers[key + '_reason']"></p>
                      </div>
                    </template>
                  </div>
                </template>
              </template>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-100 dark:border-slate-800 flex justify-end shrink-0">
          <button 
            type="button"
            @click="selectedResponse = null"
            class="px-6 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-950 text-slate-700 dark:text-slate-300 font-black rounded-2xl text-xs shadow-xs transition"
          >
            {{ $isAr ? 'إغلاق نافذة التفاصيل' : 'Close Details' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Beautiful Delete Confirmation Modal -->
    <div 
      x-show="deletingTicketId !== null" 
      class="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-[110] p-4 animate-fade-in"
      style="display: none;"
    >
      <div 
        @click.away="deletingTicketId = null"
        class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl max-w-sm w-full shadow-2xl overflow-hidden animate-scale-in text-center p-6 space-y-4"
      >
        <!-- Icon -->
        <div class="mx-auto w-14 h-14 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center">
          <i data-lucide="trash-2" class="h-6 w-6"></i>
        </div>
        
        <div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $isAr ? 'حذف البلاغ؟' : 'Delete Ticket?' }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
            {{ $isAr ? 'هل أنت متأكد من رغبتك في حذف هذا البلاغ بشكل نهائي؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to permanently delete this ticket? This action cannot be undone.' }}
          </p>
        </div>

        <!-- Form & Buttons -->
        <form :action="'{{ url('/dashboard/tickets') }}/' + deletingTicketId" method="POST" class="grid grid-cols-2 gap-3 pt-2">
          @csrf
          @method('DELETE')
          <button 
            type="button" 
            @click="deletingTicketId = null"
            class="py-3 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-950 text-xs font-black transition"
          >
            {{ $isAr ? 'إلغاء' : 'Cancel' }}
          </button>
          <button 
            type="submit"
            class="py-3 rounded-2xl bg-red-600 text-white font-black text-xs hover:bg-red-700 transition shadow-md shadow-red-100 dark:shadow-none"
          >
            {{ $isAr ? 'تأكيد الحذف' : 'Confirm Delete' }}
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('ticketsComponent', () => ({
        q: '{{ $searchQuery }}',
        status: '{{ $statusFilter }}',
        priority: '{{ $priorityFilter }}',
        department: '{{ $selectedDept }}',
        dateFilter: '{{ $dateFilter }}',
        startDate: '{{ $startDate }}',
        endDate: '{{ $endDate }}',
        restrictedDept: @json($restrictedDepartment ? true : false),
        
        resolvingTicketId: null,
        resolutionNotes: '',
        deletingTicketId: null,
        
        loadingTickets: false,
        loadingResponse: false,
        selectedResponse: null,
        survey: null,
        isAr: {{ $isAr ? 'true' : 'false' }},

        applyFilters() {
          const params = new URLSearchParams();
          if (this.q) params.set('q', this.q);
          if (this.status) params.set('status', this.status);
          if (this.priority) params.set('priority', this.priority);
          if (this.department !== 'all') params.set('department', this.department);
          
          params.set('dateFilter', this.dateFilter);
          if (this.dateFilter === 'custom') {
            if (this.startDate) params.set('startDate', this.startDate);
            if (this.endDate) params.set('endDate', this.endDate);
          }
          window.location.search = params.toString();
        },

        async searchTickets() {
          const params = new URLSearchParams();
          if (this.q) params.set('q', this.q);
          if (this.status) params.set('status', this.status);
          if (this.priority) params.set('priority', this.priority);
          if (this.department !== 'all') params.set('department', this.department);
          params.set('dateFilter', this.dateFilter);
          if (this.dateFilter === 'custom') {
            if (this.startDate) params.set('startDate', this.startDate);
            if (this.endDate) params.set('endDate', this.endDate);
          }
          const qs = params.toString();
          MedSurveyAjax.updateUrl(`/dashboard/tickets${qs ? `?${qs}` : ''}`);
          try {
            this.loadingTickets = true;
            const data = await MedSurveyAjax.fetchJson(`/dashboard/tickets/filter?${qs}`);
            MedSurveyAjax.replaceHtml('tickets-grid', data.html);
            MedSurveyAjax.replaceHtml('tickets-pagination', data.pagination);
            MedSurveyAjax.refreshIcons();
          } catch (e) {
            console.error('AJAX search failed, reloading page', e);
            window.location.search = qs;
          } finally {
            this.loadingTickets = false;
          }
        },

        setDateFilter(val) {
          this.dateFilter = val;
          if (val !== 'custom') {
            this.searchTickets();
          }
        },

        resetFilters() {
          this.q = '';
          this.status = '';
          this.priority = '';
          this.department = 'all';
          this.dateFilter = 'all';
          this.startDate = '';
          this.endDate = '';
          this.searchTickets();
        },

        isFiltered() {
          return this.q !== '' || 
                 this.status !== '' || 
                 this.priority !== '' || 
                 this.department !== 'all' || 
                 this.dateFilter !== 'all' ||
                 this.startDate !== '' ||
                 this.endDate !== '';
        },

        openResolutionNotes(ticketId, initialNotes) {
          this.resolvingTicketId = ticketId;
          this.resolutionNotes = initialNotes || '';
        },

        async viewSurveyDetails(responseId) {
          try {
            this.loadingResponse = true;
            const res = await fetch(`/dashboard/responses/${responseId}/json`);
            if (!res.ok) {
              throw new Error("Failed to fetch response details");
            }
            const data = await res.json();
            this.selectedResponse = data.response;
            this.survey = data.survey;
            
            // Re-trigger Lucide icons inside loaded content after Alpine tick
            setTimeout(() => {
              if (window.lucide) {
                window.lucide.createIcons();
              }
            }, 50);
          } catch (err) {
            console.error("AJAX survey loading error:", err);
            alert(this.isAr ? "فشل في تحميل تفاصيل استبيان المريض المعتمد." : "Failed to load patient survey response details.");
          } finally {
            this.loadingResponse = false;
          }
        },

        getScoreGradientClass(score) {
          score = score || 0;
          if (score >= 85) return 'from-green-500 to-teal-600';
          if (score >= 70) return 'from-blue-500 to-indigo-600';
          if (score >= 50) return 'from-amber-500 to-orange-650';
          return 'from-red-500 to-rose-600';
        },

        formatDate(isoString) {
          if (!isoString) return '';
          const date = new Date(isoString);
          return date.toLocaleDateString(this.isAr ? 'ar-SA' : 'en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          });
        },

        formatGender(gender) {
          if (!gender) return this.isAr ? 'النوع غير محدد' : 'Gender unspecified';
          const val = String(gender).trim().toLowerCase();
          if (val === 'male') return this.isAr ? 'ذكر' : 'Male';
          if (val === 'female') return this.isAr ? 'أنثى' : 'Female';
          return gender;
        },

        getQuestionTitle(key) {
          if (!this.survey) return key;
          const questions = this.survey.sections.flatMap(s => s.questions || []);
          const directMatch = questions.find(q => q.id === key);
          if (directMatch) return directMatch.title;

          // Matching q1, q2 format
          if (/^q\d+$/.test(key)) {
            const index = parseInt(key.substring(1), 10) - 1;
            if (index >= 0 && index < questions.length) {
              return questions[index].title;
            }
          }

          return key;
        },

        renderAnswerValue(key, val) {
          // Find question type
          const questions = this.survey ? this.survey.sections.flatMap(s => s.questions || []) : [];
          const question = questions.find(q => q.id === key) || (key.startsWith('q') ? questions[parseInt(key.substring(1))-1] : null);
          const type = question ? question.type : '';

          const numericValue = typeof val === 'number'
            ? val
            : (typeof val === 'string' && val.trim() !== '' && !Number.isNaN(Number(val)) ? Number(val) : null);

          if (numericValue !== null) {
            const isNps = type === 'nps';
            const scale = isNps ? 10 : 5;
            const starsHtml = !isNps ? '<span class="text-sm leading-none">&#9733;</span>' : '';
            return `
              <span class="${this.ratingBadgeClass(numericValue, scale)}">
                ${starsHtml}
                <span>${scale} / ${numericValue}</span>
              </span>
            `;
          }

          const strVal = typeof val === 'string' ? val.trim().toLowerCase() : String(val);

          if (strVal === 'yes' || strVal === 'true' || val === true) {
            return `
              <span class="bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-400 px-3 py-1 rounded-full border border-green-100 dark:border-green-900/35 text-[11px] font-black">
                ${this.isAr ? 'نعم' : 'Yes'}
              </span>
            `;
          }

          if (strVal === 'no' || strVal === 'false' || val === false) {
            return `
              <span class="bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 px-3 py-1 rounded-full border border-rose-100 dark:border-rose-900/35 text-[11px] font-black">
                ${this.isAr ? 'لا' : 'No'}
              </span>
            `;
          }

          // Array handling
          if (Array.isArray(val)) {
            const list = val.map(v => this.translateValueLabel(v)).join(', ');
            return `<span class="bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 px-3 py-1 rounded-full border border-slate-150 dark:border-slate-800 text-[11px] font-bold">${list}</span>`;
          }

          return `<span class="bg-slate-50 dark:bg-slate-900 text-slate-750 dark:text-slate-300 px-3 py-1 rounded-full border border-slate-150 dark:border-slate-800 text-[11px] font-black">${this.translateValueLabel(val)}</span>`;
        },

        ratingBadgeClass(value, scale) {
          const percentage = (Number(value) / Math.max(Number(scale), 1)) * 100;
          const base = 'inline-flex h-8 min-w-[4.75rem] items-center justify-center gap-1.5 rounded-full border px-3 text-[11px] font-black leading-none shadow-sm';

          if (percentage >= 85) {
            return `${base} border-emerald-500/25 bg-emerald-500/10 text-emerald-500 shadow-emerald-950/10`;
          }

          if (percentage >= 70) {
            return `${base} border-blue-500/25 bg-blue-500/10 text-blue-500 shadow-blue-950/10`;
          }

          if (percentage >= 50) {
            return `${base} border-amber-500/25 bg-amber-500/10 text-amber-500 shadow-amber-950/10`;
          }

          return `${base} border-rose-500/25 bg-rose-500/10 text-rose-500 shadow-rose-950/10`;
        },

        translateValueLabel(val) {
          if (!val) return '';
          const normalized = String(val).trim().toLowerCase();
          const arTranslations = {
            'inpatient': 'تنويم',
            'outpatient': 'عيادات خارجية',
            'emergency': 'طوارئ',
            'male': 'ذكر',
            'female': 'أنثى',
            'yes': 'نعم',
            'no': 'لا',
            'staff': 'الكادر الطبي',
            'waiting': 'الانتظار / وقت الانتظار',
            'cleanliness': 'النظافة',
            'treatment': 'المعاملة والتشخيص',
            'speed': 'سرعة الخدمة',
            'facility': 'المرافق والبيئة',
            'parking': 'مواقف السيارات',
            'signs': 'اللوحات الإرشادية',
            'reception': 'الاستقبال',
            'pharmacy': 'الصيدلية',
            'appointments': 'المواعيد'
          };
          const enTranslations = {
            'inpatient': 'Inpatient',
            'outpatient': 'Outpatient',
            'emergency': 'Emergency',
            'male': 'Male',
            'female': 'Female',
            'yes': 'Yes',
            'no': 'No',
            'staff': 'Medical Staff',
            'waiting': 'Waiting Time',
            'cleanliness': 'Cleanliness',
            'treatment': 'Treatment & Diagnostic',
            'speed': 'Service Speed',
            'facility': 'Facilities & Environment',
            'parking': 'Parking',
            'signs': 'Signage',
            'reception': 'Reception',
            'pharmacy': 'Pharmacy',
            'appointments': 'Appointments'
          };

          if (this.isAr) {
            return arTranslations[normalized] || val;
          } else {
            return enTranslations[normalized] || val;
          }
        }
      }));
    });
  </script>
@endsection
