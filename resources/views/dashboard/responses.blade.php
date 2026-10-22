@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'الاستجابات' : 'Responses') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isRtl = app()->getLocale() === 'ar';
    $isAr = $isRtl;
    // Query params
    $dateFilter = request()->query('dateFilter', 'all');
    $startDate = request()->query('startDate', '');
    $endDate = request()->query('endDate', '');
    $scoreFilter = request()->query('score', 'all');
    $genderFilter = request()->query('gender', 'all');
    $hasName = request()->query('hasName') === '1';
    $hasPhone = request()->query('hasPhone') === '1';
    $searchQuery = request()->query('q', '');
    $sortBy = request()->query('sortBy', 'submittedAt-desc');
    $perPage = (int) request()->query('per_page', 20);
    $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;
    $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
    $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
  @endphp

  <div x-data="responsesComponent({
    scoreFilter: @js($scoreFilter),
    dateFilter: @js($dateFilter),
    startDate: @js($startDate),
    endDate: @js($endDate),
    genderFilter: @js($genderFilter),
    hasName: @js($hasName),
    hasPhone: @js($hasPhone),
    perPage: @js($perPage),
    isAr: @js($isAr),
    forcedDateFilter: @js(auth()->user()->role === 'staff' ? 'today' : null),
    totalResponses: @js($responses->total()),
    averageScore: @js(round((float) ($averageScore ?? 0), 1)),
  })" class="space-y-6 animate-fade-in font-cairo">
    <div class="max-w-7xl mx-auto py-6">
      
      <!-- Filters Panel -->
      <form id="filtersForm" method="GET" action="{{ route('dashboard.responses') }}" @submit.prevent="submitForm()" class="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
        
        <div class="flex items-center gap-3 flex-wrap">
          <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="absolute {{ $isRtl ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input 
              type="text" 
              name="q"
              value="{{ $searchQuery }}" 
              @input.debounce.400ms="filterResponses()"
              placeholder="{{ $isAr ? 'بحث بالاسم، الجوال أو القسم...' : 'Search name, phone or dept...' }}" 
              class="w-full {{ $isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white transition-all"
            >
          </div>

          <div class="flex gap-2">
            <select 
              name="sortBy"
              @change="filterResponses()"
              class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 bg-white dark:bg-slate-800 outline-none focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/20 cursor-pointer"
            >
              <option value="submittedAt-desc" @selected($sortBy === 'submittedAt-desc')>{{ $isAr ? 'الأحدث أولاً' : 'Newest First' }}</option>
              <option value="submittedAt-asc" @selected($sortBy === 'submittedAt-asc')>{{ $isAr ? 'الأقدم أولاً' : 'Oldest First' }}</option>
              <option value="overallScore-desc" @selected($sortBy === 'overallScore-desc')>{{ $isAr ? 'الأعلى تقييماً' : 'Highest Score' }}</option>
              <option value="overallScore-asc" @selected($sortBy === 'overallScore-asc')>{{ $isAr ? 'الأقل تقييماً' : 'Lowest Score' }}</option>
            </select>

            <button 
              type="button" 
              @click="toggleFilters()"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              <i data-lucide="filter" x-show="!showFilters" class="w-4 h-4"></i>
              <i data-lucide="x" x-show="showFilters" class="w-4 h-4"></i>
              <span x-text="showFilters ? '{{ $isAr ? 'إغلاق التصفية' : 'Close Filter' }}' : '{{ $isAr ? 'تصفية النتائج' : 'Filter' }}'"></span>
            </button>
            
            @if(true)
              <button 
                type="button" 
                @click="exportModalOpen = true"
                class="hidden sm:flex items-center gap-2 px-4 py-2.5 rounded-xl bg-linear-to-r from-teal-600 to-emerald-600 text-white font-bold shadow-lg shadow-teal-200/50 dark:shadow-teal-950/30 hover:shadow-xl hover:-translate-y-0.5 transition-all text-sm cursor-pointer"
              >
                <i data-lucide="download" class="w-4 h-4"></i>
                {{ $isAr ? 'تصدير التقرير' : 'Export Report' }}
              </button>
              <button 
                type="button" 
                @click="exportModalOpen = true"
                class="sm:hidden flex items-center justify-center w-10 h-10 rounded-xl bg-linear-to-r from-teal-600 to-emerald-600 text-white font-bold shadow-lg shadow-teal-200/50 dark:shadow-teal-950/30 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
              >
                <i data-lucide="download" class="w-5 h-5"></i>
              </button>
            @endif
          </div>
        </div>

        <!-- Quick Stats Bar -->
        @if($responses->total() > 0)
        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-50 pt-4 text-start animate-fade-in dark:border-slate-800/40 sm:gap-6">
          <div class="flex min-w-0 items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <div class="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{{ $isAr ? 'إجمالي الاستجابات' : 'Total Responses' }}</div>
              <div class="stat-number text-sm font-black text-gray-900 dark:text-white leading-tight" :title="formatNumber(totalResponses)" x-text="compactNumber(totalResponses)">{{ $compactNumber($responses->total()) }}</div>
            </div>
          </div>
          <div class="w-px h-8 bg-gray-100 dark:bg-slate-800"></div>
          <div class="flex min-w-0 items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400">
              <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <div class="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{{ $isAr ? 'متوسط نسبة الرضا' : 'Satisfaction Rate' }}</div>
              <div class="stat-number text-sm font-black text-gray-900 dark:text-white leading-tight" x-text="`${formatNumber(averageScore, 1)}%`">{{ $formatNumber($averageScore ?? 0, 1) }}%</div>
            </div>
          </div>
        </div>
        @endif

        <!-- Expanded Filters -->
        <div x-show="showFilters" x-collapse class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800/80 space-y-4 text-start" style="display: none;">
          <!-- Satisfaction Level -->
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'مستوى الرضا:' : 'Satisfaction level:' }}</span>
            <input type="hidden" name="score" x-model="scoreFilter">
            @foreach([
              'all' => $isAr ? 'الكل' : 'All',
              'excellent' => $isAr ? 'ممتاز' : 'Excellent',
              'good' => $isAr ? 'جيد' : 'Good',
              'average' => $isAr ? 'متوسط' : 'Average',
              'poor' => $isAr ? 'ضعيف' : 'Poor'
            ] as $val => $label)
              <button 
                type="button" 
                @click="scoreFilter = '{{ $val }}'; submitForm()"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer"
                :class="scoreFilter === '{{ $val }}' ? 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'"
              >
                {{ $label }}
              </button>
            @endforeach
          </div>

          <!-- Date Filter -->
          @if(auth()->user()->role === 'staff')
            <div class="flex items-center gap-2 bg-amber-50/50 dark:bg-amber-950/10 border border-amber-100 dark:border-amber-900/30 rounded-xl px-4 py-2.5 text-xs text-amber-700 dark:text-amber-400 font-bold select-none">
              <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
              <span>{{ $isAr ? 'تنبيه: يتم عرض استجابات اليوم فقط للمتابعة.' : 'Notice: Only today\'s responses are displayed for follow-up.' }}</span>
              <input type="hidden" name="dateFilter" value="today">
            </div>
          @else
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm text-gray-505 dark:text-slate-400">{{ $isAr ? 'تاريخ الاستجابة:' : 'Date filter:' }}</span>
              <input type="hidden" name="dateFilter" x-model="dateFilter">
              @foreach([
                'all' => $isAr ? 'الكل' : 'All',
                'today' => $isAr ? 'اليوم' : 'Today',
                'week' => $isAr ? 'آخر 7 أيام' : 'Last 7 Days',
                'month' => $isAr ? 'آخر 30 يوماً' : 'Last 30 Days',
                'custom' => $isAr ? 'تاريخ مخصص' : 'Custom Date'
              ] as $val => $label)
                <button 
                  type="button" 
                  @click="dateFilter = '{{ $val }}'; if(dateFilter !== 'custom') submitForm()"
                  class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer"
                  :class="dateFilter === '{{ $val }}' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'"
                >
                  {{ $label }}
                </button>
              @endforeach

              <!-- Custom Date Inputs -->
              <div x-show="dateFilter === 'custom'" class="flex items-center gap-2 bg-gray-50 dark:bg-slate-800/50 px-2.5 py-1 rounded-lg border border-gray-100 dark:border-slate-700">
                <span class="text-xs text-gray-505 dark:text-slate-400">{{ $isAr ? 'من' : 'From' }}</span>
                <div class="relative">
                  <div class="flex min-h-[34px] min-w-[8.75rem] items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-gray-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <i data-lucide="calendar" class="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-slate-500"></i>
                    <span class="font-mono text-xs font-bold" dir="ltr" x-text="startDate || 'YYYY-MM-DD'"></span>
                  </div>
                  <input
                    type="date"
                    name="startDate"
                    x-model="startDate"
                    max="{{ now()->toDateString() }}"
                    dir="ltr"
                    lang="en-CA"
                    aria-label="{{ $isAr ? 'من' : 'From' }}"
                    @change="submitForm()"
                    @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                  >
                </div>
                <span class="text-xs text-gray-505 dark:text-slate-400">{{ $isAr ? 'إلى' : 'To' }}</span>
                <div class="relative">
                  <div class="flex min-h-[34px] min-w-[8.75rem] items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-gray-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <i data-lucide="calendar" class="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-slate-500"></i>
                    <span class="font-mono text-xs font-bold" dir="ltr" x-text="endDate || 'YYYY-MM-DD'"></span>
                  </div>
                  <input
                    type="date"
                    name="endDate"
                    x-model="endDate"
                    max="{{ now()->toDateString() }}"
                    dir="ltr"
                    lang="en-CA"
                    aria-label="{{ $isAr ? 'إلى' : 'To' }}"
                    @change="submitForm()"
                    @click="typeof $el.showPicker === 'function' ? $el.showPicker() : null"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                  >
                </div>
              </div>
            </div>
          @endif

          <!-- Gender & Identity Filters -->
          <div class="flex flex-col sm:flex-row sm:items-center gap-4 pt-2 border-t border-gray-50 dark:border-slate-800/40">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'الجنس:' : 'Gender:' }}</span>
              <input type="hidden" name="gender" x-model="genderFilter">
              <button type="button" @click="genderFilter = 'all'; submitForm()" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer" :class="genderFilter === 'all' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'">{{ $isAr ? 'الكل' : 'All' }}</button>
              <button type="button" @click="genderFilter = 'male'; submitForm()" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer" :class="genderFilter === 'male' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'">{{ $isAr ? 'ذكر' : 'Male' }}</button>
              <button type="button" @click="genderFilter = 'female'; submitForm()" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer" :class="genderFilter === 'female' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'">{{ $isAr ? 'أنثى' : 'Female' }}</button>
            </div>
            
            <div class="hidden sm:block w-px h-6 bg-gray-200 dark:bg-slate-700"></div>
            
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'هوية المراجع:' : 'Identity:' }}</span>
              <input type="hidden" name="hasName" :value="hasName ? '1' : '0'">
              <input type="hidden" name="hasPhone" :value="hasPhone ? '1' : '0'">
              
              <button 
                type="button" 
                @click="hasName = !hasName; submitForm()"
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer border"
                :class="hasName ? 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 border-transparent hover:bg-gray-200 dark:hover:bg-slate-700'"
              >
                <i data-lucide="user" class="w-3.5 h-3.5"></i>
                {{ $isAr ? 'له اسم' : 'With Name' }}
              </button>
              <button 
                type="button" 
                @click="hasPhone = !hasPhone; submitForm()"
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer border"
                :class="hasPhone ? 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 border-transparent hover:bg-gray-200 dark:hover:bg-slate-700'"
              >
                <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                {{ $isAr ? 'له رقم هاتف' : 'With Phone' }}
              </button>
            </div>
            <button 
              type="submit" 
              class="sm:hidden w-full flex justify-center items-center gap-2 px-4 py-2 mt-2 rounded-xl bg-teal-600 text-white font-medium hover:bg-teal-700 transition-colors text-sm cursor-pointer"
            >
              {{ $isAr ? 'تطبيق الفلترة' : 'Apply Filter' }}
            </button>
          </div>
        </div>
      </form>

      <!-- Responses Grid with Loading Overlay -->
      <div class="relative">
        <div 
          x-show="loadingFilters" 
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
          id="responses-grid" 
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-start"
          :class="loadingFilters ? 'opacity-40 pointer-events-none' : ''"
        >
          @include('dashboard.partials._response-cards', ['responses' => $responses, 'isAr' => $isAr, 'isRtl' => $isRtl])
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-6" id="responses-pagination">
        @include('dashboard.partials._responses-pagination', ['responses' => $responses, 'isAr' => $isAr])
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
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-100 dark:border-slate-800/80 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-3xl">
          <div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white">{{ $isAr ? 'تفاصيل الاستبيان' : 'Survey Details' }}</h3>
            <p class="text-gray-400 dark:text-slate-500 text-[11px] mt-1 flex items-center gap-1.5">
              <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-550 shrink-0"></i>
              <span>{{ $isAr ? 'تاريخ التقديم:' : 'Submission Date:' }}</span>
              <span class="font-semibold" x-text="formatDate(selectedResponse?.submittedAt)"></span>
            </p>
          </div>
          <button 
            @click="selectedResponse = null" 
            type="button" 
            class="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer"
          >
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <!-- Scrollable survey answers content -->
        <div class="p-6 overflow-y-auto space-y-6 text-start flex-1">
          <!-- Satisfaction score dynamic banner -->
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-xl mb-3 text-white"
                 :class="getScoreGradientClass(selectedResponse?.overallScore)">
              <span class="text-2xl font-black text-white" x-text="(selectedResponse?.overallScore || 0) + '%'"></span>
            </div>
            <p class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'نسبة الرضا العامة' : 'Satisfaction rate' }}</p>
          </div>

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
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-3 border-t border-slate-200/50 dark:border-slate-800 text-[11px] font-bold text-slate-600 dark:text-slate-355">
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
                      <span class="text-sm text-gray-600 dark:text-slate-300 max-w-sm sm:text-start" x-text="getQuestionTitle(key)"></span>

                      <!-- Formatted Answer -->
                      <span class="text-sm font-bold text-gray-800 dark:text-slate-100 shrink-0 self-start sm:self-auto" x-html="renderAnswerValue(key, val)"></span>
                    </div>

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
            class="px-6 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-950 text-slate-700 dark:text-slate-300 font-black rounded-2xl text-xs shadow-xs transition cursor-pointer"
          >
            {{ $isAr ? 'إغلاق نافذة التفاصيل' : 'Close Details' }}
          </button>
        </div>
      </div>
    </div>


    <!-- Export Modal -->
    <div x-show="exportModalOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center animate-fade-in p-4">
      <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="exportModalOpen = false"></div>
      
      <div class="relative bg-white dark:bg-slate-900 w-full max-w-lg rounded-2xl shadow-2xl flex flex-col mx-auto animate-scale-in border border-gray-100 dark:border-slate-800 max-h-[90vh] overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-linear-to-br from-emerald-400 to-emerald-600 rounded-xl flex items-center justify-center shadow-md shadow-emerald-200/50 dark:shadow-emerald-950/25 shrink-0">
              <i data-lucide="download" class="w-5 h-5 text-white"></i>
            </div>
            <div>
              <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $isAr ? 'تصدير التقرير' : 'Export Report' }}</h2>
              <p class="text-xs text-gray-400 dark:text-slate-400">{{ $isAr ? 'اختر الصيغة والخيارات المطلوبة' : 'Choose format and options' }}</p>
            </div>
          </div>
          <button @click="exportModalOpen = false" type="button" class="text-gray-400 hover:text-gray-600 dark:text-slate-400 dark:hover:text-slate-200 p-1 cursor-pointer rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-5 overflow-y-auto flex-1 custom-scrollbar">
          <!-- Export Format -->
          <div>
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="file-type" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'صيغة التصدير' : 'Export Format' }}
            </div>
            <div class="grid grid-cols-3 gap-2">
              <!-- Print -->
              <button
                @click="exportFormat = 'print'"
                type="button"
                :class="exportFormat === 'print' ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 ring-2 ring-teal-100 dark:ring-teal-900/30' : 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'"
                class="flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all cursor-pointer h-24"
              >
                <i data-lucide="printer" :class="exportFormat === 'print' ? 'text-teal-600 dark:text-teal-400' : 'text-gray-400 dark:text-slate-400'" class="w-6 h-6"></i>
                <span :class="exportFormat === 'print' ? 'text-teal-700 dark:text-teal-400' : 'text-gray-600 dark:text-slate-300'" class="font-bold text-xs">{{ $isAr ? 'طباعة' : 'Print' }}</span>
              </button>

              <!-- Excel -->
              <button
                @click="exportFormat = 'excel'"
                type="button"
                :class="exportFormat === 'excel' ? 'border-green-500 bg-green-50 dark:bg-green-950/40 ring-2 ring-green-100 dark:ring-green-900/30' : 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'"
                class="flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all cursor-pointer h-24"
              >
                <i data-lucide="sheet" :class="exportFormat === 'excel' ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-slate-400'" class="w-6 h-6"></i>
                <span :class="exportFormat === 'excel' ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-slate-300'" class="font-bold text-xs">Excel</span>
              </button>

              <!-- PDF -->
              <button
                @click="exportFormat = 'pdf'"
                type="button"
                :class="exportFormat === 'pdf' ? 'border-red-500 bg-red-50 dark:bg-red-950/40 ring-2 ring-red-100 dark:ring-red-900/30' : 'border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'"
                class="flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all cursor-pointer h-24"
              >
                <i data-lucide="file-text" :class="exportFormat === 'pdf' ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-slate-400'" class="w-6 h-6"></i>
                <span :class="exportFormat === 'pdf' ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-slate-300'" class="font-bold text-xs">PDF</span>
              </button>
            </div>
          </div>
          
          <!-- Time Period Filter -->
          <div>
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'الفترة الزمنية' : 'Time Period' }}
            </div>
            @if(auth()->user()->role === 'staff')
              <div class="inline-flex items-center gap-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-3 py-2 rounded-xl text-xs font-bold text-slate-650 dark:text-slate-300 select-none">
                {{ $isAr ? 'اليوم فقط' : 'Today Only' }}
              </div>
            @else
              <div class="flex items-center gap-1.5 flex-wrap">
                @foreach([
                  'all' => $isAr ? 'الكل' : 'All',
                  'week' => $isAr ? 'آخر أسبوع' : 'Last week',
                  'month' => $isAr ? 'آخر شهر' : 'Last month',
                  '3months' => $isAr ? 'آخر 3 أشهر' : 'Last 3 months'
                ] as $val => $label)
                  <button
                    type="button"
                    @click="exportDateFilter = '{{ $val }}'; fetchEstimatedCount()"
                    class="shrink-0 px-3.5 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap"
                    :class="exportDateFilter === '{{ $val }}' ? 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400 border border-teal-200 dark:border-teal-800' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 border border-transparent hover:bg-gray-200 dark:hover:bg-slate-700'"
                  >
                    {{ $label }}
                  </button>
                @endforeach
              </div>
            @endif
          </div>

          <!-- Department Filter -->
          <div>
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'القسم' : 'Department' }}
            </div>
            <div x-data="{ deptDropdownOpen: false }" class="relative">
              <button 
                @click="deptDropdownOpen = !deptDropdownOpen" 
                type="button" 
                class="w-full flex items-center justify-between bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-200 rounded-xl px-4 py-3 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/20 outline-none cursor-pointer hover:border-gray-300 dark:hover:border-slate-600 transition-colors"
              >
                <span class="truncate" x-text="exportDepartment === 'all' ? '{{ $isAr ? 'جميع الأقسام' : 'All Departments' }}' : exportDepartment"></span>
                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="deptDropdownOpen ? 'rotate-180' : ''"></i>
              </button>
              
              <!-- Dropdown Menu -->
              <div 
                x-show="deptDropdownOpen" 
                @click.away="deptDropdownOpen = false"
                style="display: none;" 
                class="absolute z-[110] w-full mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-lg overflow-hidden max-h-48 overflow-y-auto custom-scrollbar"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
              >
                <button 
                  type="button" 
                  @click="exportDepartment = 'all'; fetchEstimatedCount(); deptDropdownOpen = false" 
                  class="w-full text-start px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors cursor-pointer" 
                  :class="exportDepartment === 'all' ? 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/40 font-bold' : 'text-gray-600 dark:text-slate-300'"
                >
                  {{ $isAr ? 'جميع الأقسام' : 'All Departments' }}
                </button>
                @foreach($departments as $dept)
                  <button 
                    type="button" 
                    @click="exportDepartment = '{{ $dept }}'; fetchEstimatedCount(); deptDropdownOpen = false" 
                    class="w-full text-start px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors cursor-pointer" 
                    :class="exportDepartment === '{{ $dept }}' ? 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/40 font-bold' : 'text-gray-600 dark:text-slate-300'"
                  >
                    {{ $dept }}
                  </button>
                @endforeach
              </div>
            </div>
          </div>

          <!-- Estimated Records -->
          <div class="bg-gray-50 dark:bg-slate-800/50 border border-gray-100 dark:border-slate-700/50 rounded-xl px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'عدد السجلات المقدر:' : 'Estimated Records:' }}</span>
            <span class="stat-number-tight font-bold text-sm text-gray-700 dark:text-white">
              <span :title="formatNumber(estimatedRecords)" x-text="compactNumber(estimatedRecords)"></span>
              <span class="text-gray-400 dark:text-slate-400 font-normal">{{ $isAr ? 'سجل' : 'records' }}</span>
            </span>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-100 dark:border-slate-800 flex items-center gap-3 bg-gray-50/50 dark:bg-slate-950 rounded-b-2xl">
          <button
            @click="exportModalOpen = false"
            type="button"
            class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-bold hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors cursor-pointer text-sm"
          >
            {{ $isAr ? 'إلغاء' : 'Cancel' }}
          </button>
          <button
            @click="triggerExport"
            type="button"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-bold text-white transition-all bg-linear-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 shadow-md shadow-teal-200/50 dark:shadow-teal-950/30 cursor-pointer text-sm"
          >
            <i data-lucide="download" class="w-4 h-4"></i>
            <span x-text="exportFormat === 'excel' ? '{{ $isAr ? 'تحميل Excel' : 'Download Excel' }}' : (exportFormat === 'pdf' ? '{{ $isAr ? 'تحميل PDF' : 'Download PDF' }}' : '{{ $isAr ? 'طباعة' : 'Print' }}')"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

@endsection
