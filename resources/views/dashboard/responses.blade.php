@extends('layouts.dashboard')

@section('title', 'الاستجابات - MedSurvey Pro')

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
  @endphp

  <div x-data="responsesComponent()" class="space-y-6 animate-fade-in font-cairo">
    <div class="max-w-7xl mx-auto py-6">
      
      <!-- Filters Panel -->
      <form id="filtersForm" method="GET" action="{{ route('dashboard.responses') }}" class="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
        
        <div class="flex items-center gap-3 flex-wrap">
          <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="absolute {{ $isRtl ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input 
              type="text" 
              name="q"
              value="{{ $searchQuery }}" 
              placeholder="{{ $isAr ? 'بحث بالاسم، الجوال أو القسم...' : 'Search name, phone or dept...' }}" 
              class="w-full {{ $isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white transition-all"
            >
          </div>

          <div class="flex gap-2">
            <select 
              name="sortBy"
              onchange="this.form.submit()"
              class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 bg-white dark:bg-slate-800 outline-none focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/20 cursor-pointer"
            >
              <option value="submittedAt-desc" @selected($sortBy === 'submittedAt-desc')>{{ $isAr ? 'الأحدث أولاً' : 'Newest First' }}</option>
              <option value="submittedAt-asc" @selected($sortBy === 'submittedAt-asc')>{{ $isAr ? 'الأقدم أولاً' : 'Oldest First' }}</option>
              <option value="overallScore-desc" @selected($sortBy === 'overallScore-desc')>{{ $isAr ? 'الأعلى تقييماً' : 'Highest Score' }}</option>
              <option value="overallScore-asc" @selected($sortBy === 'overallScore-asc')>{{ $isAr ? 'الأقل تقييماً' : 'Lowest Score' }}</option>
            </select>

            <button 
              type="button" 
              @click="showFilters = !showFilters"
              class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              <i data-lucide="filter" class="w-4 h-4"></i>
              {{ $isAr ? 'تصفية النتائج' : 'Filter' }}
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
        <div class="mt-4 pt-4 border-t border-gray-50 dark:border-slate-800/40 flex items-center gap-6 animate-fade-in text-start">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
            </div>
            <div>
              <div class="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{{ $isAr ? 'إجمالي الاستجابات' : 'Total Responses' }}</div>
              <div class="text-sm font-black text-gray-900 dark:text-white leading-tight">{{ number_format($responses->total()) }}</div>
            </div>
          </div>
          <div class="w-px h-8 bg-gray-100 dark:bg-slate-800"></div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400">
              <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
            <div>
              <div class="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{{ $isAr ? 'متوسط نسبة الرضا' : 'Satisfaction Rate' }}</div>
              <div class="text-sm font-black text-gray-900 dark:text-white leading-tight">{{ round($averageScore ?? 0, 1) }}%</div>
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
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm text-gray-500 dark:text-slate-400">{{ $isAr ? 'تاريخ الاستجابة:' : 'Date filter:' }}</span>
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
              <span class="text-xs text-gray-500 dark:text-slate-400">{{ $isAr ? 'من' : 'From' }}</span>
              <input type="date" name="startDate" x-model="startDate" @change="submitForm()" class="px-2 py-1 rounded-md border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-100 outline-none">
              <span class="text-xs text-gray-500 dark:text-slate-400">{{ $isAr ? 'إلى' : 'To' }}</span>
              <input type="date" name="endDate" x-model="endDate" @change="submitForm()" class="px-2 py-1 rounded-md border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-100 outline-none">
            </div>
          </div>

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

      <!-- Responses Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-start">
        @forelse($responses as $i => $resp)
          @php
            $score = $resp->overallScore;
            $colorClass = '';
            $scoreText = '';
            if($score >= 85) {
                $colorClass = 'bg-green-100 dark:bg-green-950/40 text-green-700 dark:text-green-400';
                $scoreText = $isAr ? 'ممتاز' : 'Excellent';
            } elseif($score >= 70) {
                $colorClass = 'bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400';
                $scoreText = $isAr ? 'جيد' : 'Good';
            } elseif($score >= 50) {
                $colorClass = 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400';
                $scoreText = $isAr ? 'متوسط' : 'Average';
            } else {
                $colorClass = 'bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400';
                $scoreText = $isAr ? 'ضعيف' : 'Poor';
            }

            $progressClass = $score >= 85 ? 'bg-green-500' : ($score >= 70 ? 'bg-blue-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-red-500'));
            
            $avatarLetter = $resp->patientName ? mb_substr($resp->patientName, 0, 1) : '?';
            $displayName = $resp->patientName ?: ($isAr ? 'زائر غير معروف' : 'Anonymous');
            
            $gender = $resp->gender ?? '';
            if ($gender) {
                if (strtolower($gender) === 'male') $gender = $isAr ? 'ذكر' : 'Male';
                elseif (strtolower($gender) === 'female') $gender = $isAr ? 'أنثى' : 'Female';
            } else {
                $gender = $isAr ? 'غير محدد' : 'Unknown';
            }

            $visitType = $resp->visitType;
            if ($visitType) {
                if ($visitType === 'inpatient') $visitType = $isAr ? 'تنويم' : 'Inpatient';
                elseif ($visitType === 'outpatient') $visitType = $isAr ? 'عيادات خارجية' : 'Outpatient';
                elseif ($visitType === 'emergency') $visitType = $isAr ? 'طوارئ' : 'Emergency';
            } else {
                $visitType = $isAr ? 'غير محدد' : 'Unknown';
            }
          @endphp
          
          <div 
            class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-gray-100 dark:border-slate-800/80 shadow-sm hover:shadow-md transition-all cursor-pointer animate-slide-up"
            style="animation-delay: {{ min($i, 10) * 50 }}ms"
            @click="viewSurveyDetails('{{ $resp->id }}')"
          >
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400 border border-teal-100 dark:border-teal-900/50">
                  <i data-lucide="building-2" class="w-4 h-4"></i>
                </div>
                <div class="flex flex-col">
                  <span class="font-bold text-gray-900 dark:text-white text-sm leading-tight">{{ $resp->department ?: ($isAr ? 'غير محدد' : 'Not specified') }}</span>
                  <span class="text-[10px] text-gray-400 dark:text-slate-400">{{ $isAr ? 'القسم الطبي' : 'Medical Dept' }}</span>
                </div>
              </div>
              <span class="text-[10px] font-black px-2 py-1 rounded-lg uppercase tracking-wider {{ $colorClass }}">
                {{ $scoreText }}
              </span>
            </div>

            <!-- Patient Identity Card -->
            <div class="bg-slate-50 dark:bg-slate-950/40 rounded-2xl p-3 mb-4 border border-slate-100/50 dark:border-slate-800/40">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-800 flex items-center justify-center text-teal-600 dark:text-teal-400 font-black text-sm border border-teal-100 dark:border-teal-900 shadow-sm shrink-0">
                  {{ $avatarLetter }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-xs truncate {{ $resp->patientName ? 'text-gray-900 dark:text-slate-200' : 'text-gray-400 dark:text-slate-550 italic' }}">
                    {{ $displayName }}
                  </div>
                  @if($resp->patientPhone)
                    <div class="text-[10px] text-teal-600 dark:text-teal-400 font-bold flex items-center gap-1 mt-0.5" dir="ltr">
                      <i data-lucide="phone" class="w-2.5 h-2.5"></i>
                      {{ $resp->patientPhone }}
                    </div>
                  @endif
                </div>
              </div>
              
              <div class="flex items-center gap-3 text-[10px] text-gray-500 dark:text-slate-400 pt-2 border-t border-slate-200/50 dark:border-slate-800/40">
                <div class="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                  <i data-lucide="user" class="w-2.5 h-2.5 text-slate-400"></i>
                  <span>{{ $gender }}</span>
                </div>
                <div class="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                  <i data-lucide="activity" class="w-2.5 h-2.5 text-slate-400"></i>
                  <span>{{ $visitType }}</span>
                </div>
                <div class="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                  <i data-lucide="calendar" class="w-2.5 h-2.5 text-slate-400"></i>
                  <span>{{ $resp->ageGroup ?? ($isAr ? 'غير محدد' : 'Unknown') }}</span>
                </div>
              </div>
            </div>

            <!-- Satisfaction Bar -->
            <div class="mb-4 px-1">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-tight">{{ $isAr ? 'معدل الرضا' : 'Satisfaction Rate' }}</span>
                <span class="text-sm font-black text-gray-900 dark:text-white">{{ $score }}%</span>
              </div>
              <div class="w-full h-1.5 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-700 {{ $progressClass }}" style="width: {{ $score }}%"></div>
              </div>
            </div>

            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50 dark:border-slate-800/60">
              <div class="flex items-center gap-1 text-xs text-gray-400 dark:text-slate-400">
                <i data-lucide="calendar" class="w-3 h-3"></i>
                <span dir="ltr">{{ $resp->submittedAt ? $resp->submittedAt->format('Y-m-d') : '' }}</span>
              </div>
              <button type="button" class="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 cursor-pointer">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        @empty
          <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-20">
            <i data-lucide="search" class="w-16 h-16 text-gray-200 dark:text-slate-700 mx-auto mb-4"></i>
            <p class="text-gray-500 dark:text-slate-400 text-lg">{{ $isAr ? 'لا توجد استجابات مطابقة للبحث' : 'No responses matching the query' }}</p>
          </div>
        @endforelse
      </div>

      <!-- Pagination -->
      <div class="mt-6">
        {{ $responses->links() }}
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
          <h3 class="font-bold text-lg text-gray-800 dark:text-white">{{ $isAr ? 'تفاصيل الاستبيان' : 'Survey Details' }}</h3>
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
                  <div class="flex items-center justify-between p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition">
                    <span class="text-sm text-gray-600 dark:text-slate-300 max-w-[70%]" x-text="getQuestionTitle(key)"></span>
                    
                    <!-- Formatted Answer -->
                    <span class="text-sm font-bold text-gray-800 dark:text-slate-100 shrink-0" x-html="renderAnswerValue(key, val)"></span>
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
            <label class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="file-type" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'صيغة التصدير' : 'Export Format' }}
            </label>
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
            <label class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'الفترة الزمنية' : 'Time Period' }}
            </label>
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
          </div>

          <!-- Department Filter -->
          <div>
            <label class="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">
              <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
              {{ $isAr ? 'القسم' : 'Department' }}
            </label>
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
            <span class="font-bold text-sm text-gray-700 dark:text-white">
              <span x-text="new Intl.NumberFormat().format(estimatedRecords)"></span>
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

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('responsesComponent', () => ({
        showFilters: false,
        exportModalOpen: false,
        exportFormat: 'pdf',
        exportDateFilter: 'all',
        exportDepartment: 'all',
        scoreFilter: '{{ $scoreFilter }}',
        dateFilter: '{{ $dateFilter }}',
        startDate: '{{ $startDate }}',
        endDate: '{{ $endDate }}',
        genderFilter: '{{ $genderFilter }}',
        hasName: {{ $hasName ? 'true' : 'false' }},
        hasPhone: {{ $hasPhone ? 'true' : 'false' }},
        loadingResponse: false,
        selectedResponse: null,
        survey: null,
        isAr: {{ $isAr ? 'true' : 'false' }},
        estimatedRecords: {{ $responses->total() }},

        fetchEstimatedCount() {
          // Build params from ALPINE STATE (all advanced filters)
          const params = new URLSearchParams();
          
          // Pass all current filters from Alpine state (the page filters)
          if (this.scoreFilter !== 'all') params.set('score', this.scoreFilter);
          if (this.genderFilter !== 'all') params.set('gender', this.genderFilter);
          if (this.hasName) params.set('hasName', '1');
          if (this.hasPhone) params.set('hasPhone', '1');
          if (this.startDate) params.set('startDate', this.startDate);
          if (this.endDate) params.set('endDate', this.endDate);
          
          // Date filter logic: if user changed period in export modal, use that; otherwise use page filter
          if (this.exportDateFilter !== 'all') {
            // User explicitly selected a period in the export modal - use it
            params.set('dateFilter', this.exportDateFilter);
          } else if (this.dateFilter !== 'all') {
            // User didn't change period in modal - respect the page's active filter
            params.set('dateFilter', this.dateFilter);
          }
          
          // Department: always from export modal
          if (this.exportDepartment !== 'all') {
            params.set('department', this.exportDepartment);
          }
          
          // Add search query from the form input
          const qInput = document.querySelector('#filtersForm input[name="q"]');
          if (qInput && qInput.value.trim()) {
            params.set('q', qInput.value.trim());
          }
          
          params.set('count_only', '1');
          
          const form = document.getElementById('filtersForm');
          fetch(form.action + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
          })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => { this.estimatedRecords = data.count; })
            .catch(e => console.error('Count fetch error:', e));
        },

        submitForm() {
          setTimeout(() => {
            document.getElementById('filtersForm').submit();
          }, 50);
        },

        triggerExport() {
          // Build params from ALPINE STATE (all advanced filters)
          const params = new URLSearchParams();
          
          // Pass all current filters from Alpine state (the page filters)
          if (this.scoreFilter !== 'all') params.set('score', this.scoreFilter);
          if (this.genderFilter !== 'all') params.set('gender', this.genderFilter);
          if (this.hasName) params.set('hasName', '1');
          if (this.hasPhone) params.set('hasPhone', '1');
          if (this.startDate) params.set('startDate', this.startDate);
          if (this.endDate) params.set('endDate', this.endDate);
          
          // Date filter logic: if user changed period in export modal, use that; otherwise use page filter
          if (this.exportDateFilter !== 'all') {
            // User explicitly selected a period in the export modal - use it
            params.set('dateFilter', this.exportDateFilter);
          } else if (this.dateFilter !== 'all') {
            // User didn't change period in modal - respect the page's active filter
            params.set('dateFilter', this.dateFilter);
          }
          
          // Department: always from export modal
          if (this.exportDepartment !== 'all') {
            params.set('department', this.exportDepartment);
          }
          
          // Add search query from the form input
          const qInput = document.querySelector('#filtersForm input[name="q"]');
          if (qInput && qInput.value.trim()) {
            params.set('q', qInput.value.trim());
          }
          
          const form = document.getElementById('filtersForm');
          if (this.exportFormat === 'excel') {
            params.set('export', 'csv');
            window.location.href = form.action + '?' + params.toString();
          } else {
            // PDF or Print opens the print view
            params.set('export', 'print');
            window.open(form.action + '?' + params.toString(), '_blank');
          }
          this.exportModalOpen = false;
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
          if (score >= 85) return 'bg-green-500';
          if (score >= 70) return 'bg-blue-500';
          if (score >= 50) return 'bg-amber-500';
          return 'bg-red-500';
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

          if (/^q\d+$/.test(key)) {
            const index = parseInt(key.substring(1), 10) - 1;
            if (index >= 0 && index < questions.length) {
              return questions[index].title;
            }
          }

          return key;
        },

        renderAnswerValue(key, val) {
          const questions = this.survey ? this.survey.sections.flatMap(s => s.questions || []) : [];
          const question = questions.find(q => q.id === key) || (key.startsWith('q') ? questions[parseInt(key.substring(1))-1] : null);
          const type = question ? question.type : '';

          if (typeof val === 'string' && !isNaN(val) && val.trim() !== '') {
            val = Number(val);
          }

          if (typeof val === 'number') {
            const isNps = type === 'nps';
            const scale = isNps ? 10 : 5;
            const starsHtml = !isNps ? '<i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 fill-amber-500 inline shrink-0 mb-0.5"></i>' : '';
            return `
              <span class="inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 px-3 py-1 rounded-full border border-amber-100 dark:border-amber-900/35 text-[11px] font-black">
                <span>${val} / ${scale}</span>
                ${starsHtml}
              </span>
            `;
          }

          let values = [];
          if (Array.isArray(val)) {
            values = val;
          } else if (typeof val === 'string' && val.startsWith('[') && val.endsWith(']')) {
            try {
              values = JSON.parse(val);
            } catch (e) {
              values = [val];
            }
          } else if (typeof val === 'string' && val.includes(',')) {
            values = val.split(',').map(v => v.trim());
          } else {
            values = [val];
          }

          if (typeof val === 'boolean' || val === 'true' || val === 'false' || val === 'yes' || val === 'no') {
              const strVal = String(val).toLowerCase();
              if (strVal === 'yes' || strVal === 'true') {
                return `
                  <span class="bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-400 px-3 py-1 rounded-full border border-green-100 dark:border-green-900/35 text-[11px] font-black">
                    ${this.isAr ? 'نعم' : 'Yes'}
                  </span>
                `;
              }
              if (strVal === 'no' || strVal === 'false') {
                return `
                  <span class="bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 px-3 py-1 rounded-full border border-rose-100 dark:border-rose-900/35 text-[11px] font-black">
                    ${this.isAr ? 'لا' : 'No'}
                  </span>
                `;
              }
          }

          const translatedList = values.map(v => this.translateValueLabel(v)).join('، ');
          return `<span class="bg-slate-50 dark:bg-slate-900 text-slate-750 dark:text-slate-300 px-3 py-1 rounded-full border border-slate-150 dark:border-slate-800 text-[11px] font-black">${translatedList}</span>`;
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
