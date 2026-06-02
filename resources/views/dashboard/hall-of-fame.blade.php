@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'لوحة شرف الأداء المتميز' : 'Excellence Honor Board') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isRtl = app()->getLocale() === 'ar';
    $isAr = $isRtl;
    
    $user = auth()->user();
    $isHead = $user->role === 'head_of_department';
    $userDept = $user->department;
    
    $searchQuery = request()->query('q', '');
    $dateFilter = request()->query('dateFilter', 'all');
    $startDate = request()->query('startDate', '');
    $endDate = request()->query('endDate', '');
    
    $topThree = array_slice($departmentScores, 0, 3);
    
    $myDeptIndex = -1;
    $myDeptData = null;
    if ($isHead && $userDept) {
        foreach ($departmentScores as $index => $dept) {
            if (mb_strtolower(trim($dept['name'])) === mb_strtolower(trim($userDept))) {
                $myDeptIndex = $index;
                $myDeptData = $dept;
                break;
            }
        }
    }
  @endphp

  <div x-data="hallOfFameComponent()" class="max-w-7xl mx-auto py-8 text-start animate-fade-in font-cairo">
    <!-- Header & Filters -->
    <form id="filtersForm" method="GET" action="{{ route('dashboard.hall-of-fame') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-10">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-950/20 rounded-2xl flex items-center justify-center shadow-sm">
            <i data-lucide="trophy" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
          </div>
          <h1 class="text-2xl font-black text-gray-900 dark:text-white">{{ $isAr ? 'لوحة شرف الأداء المتميز' : 'Excellence Honor Board' }}</h1>
        </div>
        <p class="text-gray-500 dark:text-slate-400">{{ $isAr ? 'تكريم الأقسام الطبية الأعلى تقييماً ونسب رضا لدى المرضى.' : 'Honoring the top rated medical departments.' }}</p>
      </div>

      <div class="flex flex-col sm:flex-row items-center gap-3">
        <!-- Search (Hidden for head of department) -->
        @if(!$isHead)
          <div class="relative w-full sm:w-64">
            <i data-lucide="search" class="absolute {{ $isRtl ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
            <input 
              type="text"
              name="q"
              value="{{ $searchQuery }}"
              @keydown.enter.prevent="submitForm"
              placeholder="{{ $isAr ? 'ابحث عن قسم...' : 'Search department...' }}"
              class="w-full {{ $isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-yellow-500 focus:ring-4 focus:ring-yellow-50 dark:focus:ring-yellow-950/15 outline-none text-sm transition-all bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-550"
            />
          </div>
        @endif

        <!-- Time Filter -->
        <div class="flex flex-col sm:flex-row items-center bg-white dark:bg-slate-950 p-1 rounded-xl border border-gray-100 dark:border-slate-800 shadow-sm w-full sm:w-auto gap-1">
          <input type="hidden" name="dateFilter" x-model="dateFilter">
          <div class="flex items-center gap-1 w-full sm:w-auto">
            @foreach([
              'all' => $isAr ? 'الكل' : 'All Time',
              'week' => $isAr ? 'أسبوع' : 'Week',
              'month' => $isAr ? 'شهر' : 'Month',
              'year' => $isAr ? 'سنة' : 'Year',
              'custom' => $isAr ? 'مخصص' : 'Custom'
            ] as $val => $label)
              <button
                type="button"
                @click="dateFilter = '{{ $val }}'; if('{{ $val }}' !== 'custom') submitForm()"
                class="flex-1 sm:flex-none px-3 py-2 rounded-lg text-[10px] sm:text-xs font-bold transition-all cursor-pointer"
                :class="dateFilter === '{{ $val }}' ? 'bg-yellow-500 text-white shadow-md' : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800'"
              >
                {{ $label }}
              </button>
            @endforeach
          </div>

          <div x-show="dateFilter === 'custom'" class="flex items-center gap-2 px-2 border-r border-gray-100 dark:border-slate-800 animate-fade-in py-1 sm:py-0" style="display: none;">
            <div class="flex items-center gap-1">
              <span class="text-[10px] text-gray-400 dark:text-slate-500">{{ $isAr ? 'من' : 'From' }}</span>
              <input 
                type="date" 
                name="startDate"
                x-model="startDate"
                @change="submitForm"
                class="text-[10px] border border-gray-200 dark:border-slate-700 rounded px-1 py-0.5 outline-none focus:border-yellow-500 bg-white dark:bg-slate-850 text-gray-900 dark:text-white"
              />
            </div>
            <div class="flex items-center gap-1">
              <span class="text-[10px] text-gray-400 dark:text-slate-500">{{ $isAr ? 'إلى' : 'To' }}</span>
              <input 
                type="date" 
                name="endDate"
                x-model="endDate"
                @change="submitForm"
                class="text-[10px] border border-gray-200 dark:border-slate-700 rounded px-1 py-0.5 outline-none focus:border-yellow-500 bg-white dark:bg-slate-850 text-gray-900 dark:text-white"
              />
            </div>
          </div>
        </div>
      </div>
    </form>

    @if($isHead && $userDept)
      @if($myDeptData)
        <div class="max-w-4xl mx-auto space-y-8 animate-fade-in mt-6">
          <!-- Rank Spotlight Card -->
          @php
            $rank = $myDeptIndex + 1;
            $spotlightClass = '';
            $spotlightIcon = '';
            if($rank === 1) {
                $spotlightClass = 'bg-linear-to-r from-yellow-500 via-amber-500 to-yellow-600 shadow-yellow-200 border border-yellow-400';
                $spotlightIcon = 'trophy';
            } elseif($rank === 2) {
                $spotlightClass = 'bg-linear-to-r from-slate-400 via-slate-500 to-slate-600 shadow-slate-200 border border-slate-300';
                $spotlightIcon = 'award';
            } elseif($rank === 3) {
                $spotlightClass = 'bg-linear-to-r from-orange-400 via-amber-600 to-amber-700 shadow-amber-200 border border-orange-500';
                $spotlightIcon = 'medal';
            } else {
                $spotlightClass = 'bg-linear-to-r from-teal-600 via-emerald-600 to-teal-700 border border-teal-500';
                $spotlightIcon = 'building-2';
            }
          @endphp
          <div class="relative overflow-hidden rounded-3xl p-8 text-white shadow-2xl transition-all duration-500 {{ $spotlightClass }}">
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute right-6 top-6 opacity-15 pointer-events-none">
              <i data-lucide="{{ $spotlightIcon }}" class="w-32 h-32"></i>
            </div>

            <div class="relative flex flex-col md:flex-row items-center justify-between gap-6">
              <div class="text-center md:text-start">
                <span class="inline-block px-3 py-1 bg-white/20 rounded-full text-xs font-black tracking-widest uppercase mb-3">
                  {{ $userDept }}
                </span>
                <h2 class="text-2xl font-black mb-2">{{ $isAr ? 'أداء القسم وترتيبه الحالي' : 'Department Performance and Rank' }}</h2>
                <p class="text-white/80 text-sm max-w-md">
                  @if($rank === 1)
                    {{ $isAr ? 'تهانينا الحارة! يحتل قسمكم المركز الأول بجدارة وتميز تام.' : 'Congratulations! Your department is in the first place.' }}
                  @elseif($rank <= 3)
                    {{ $isAr ? 'رائع جداً! قسمكم ضمن المراكز الثلاثة الأولى الأكثر تميزاً في المستشفى.' : 'Great job! Your department is in the top 3.' }}
                  @else
                    {{ $isAr ? 'أداء متميز وجهود مباركة! نسعى دائماً للوصول للقمة وتقديم أفضل رعاية للمرضى.' : 'Good performance! Keep up the good work to reach the top.' }}
                  @endif
                </p>
              </div>

              <div class="flex flex-col items-center justify-center bg-white/10 backdrop-blur-md rounded-2xl p-6 min-w-[200px] border border-white/10">
                <span class="text-xs font-bold text-white/70 uppercase tracking-widest mb-1">{{ $isAr ? 'الترتيب في لوحة شرف الأداء المتميز' : 'Leaderboard Rank' }}</span>
                <div class="flex items-baseline gap-1">
                  <span class="text-5xl font-black leading-none">{{ $rank }}</span>
                  <span class="text-lg font-bold text-white/80">/ {{ count($departmentScores) }}</span>
                </div>
                <div class="flex gap-0.5 mt-3">
                  @for($s = 1; $s <= 5; $s++)
                    <i data-lucide="star" class="w-4 h-4 {{ $s <= round($myDeptData['score'] / 20) ? 'text-yellow-300 fill-yellow-300' : 'text-white/20' }}"></i>
                  @endfor
                </div>
              </div>
            </div>
          </div>

          <!-- Score & Detailed Stats Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Satisfaction Score -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-6 flex items-center justify-between shadow-sm">
              <div>
                <span class="text-xs text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider block mb-1">{{ $isAr ? 'نسبة رضا المرضى' : 'Patient Satisfaction' }}</span>
                <span class="text-3xl font-black text-gray-900 dark:text-white">{{ $myDeptData['score'] }}%</span>
              </div>
              <div class="w-16 h-16 rounded-2xl bg-teal-50 dark:bg-teal-950/20 flex items-center justify-center text-teal-600 dark:text-teal-400">
                <i data-lucide="trending-up" class="w-8 h-8"></i>
              </div>
            </div>

            <!-- Response Count -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-6 flex items-center justify-between shadow-sm">
              <div>
                <span class="text-xs text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider block mb-1">{{ $isAr ? 'عدد استجابات المرضى' : 'Patient Responses' }}</span>
                <span class="text-3xl font-black text-gray-900 dark:text-white">{{ $myDeptData['count'] }}</span>
              </div>
              <div class="w-16 h-16 rounded-2xl bg-blue-50 dark:bg-blue-950/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                <i data-lucide="users" class="w-8 h-8"></i>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-12 text-center mt-6">
          <i data-lucide="building-2" class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-4"></i>
          <h3 class="text-lg font-bold text-gray-850 dark:text-white mb-1">{{ $isAr ? 'لا توجد بيانات متاحة حالياً' : 'No data available' }}</h3>
          <p class="text-gray-500 dark:text-slate-400 text-sm">{{ $isAr ? 'لم يتم تسجيل أي استجابات أو تقييمات لقسم ' . $userDept . ' بعد في هذه الفترة.' : 'No responses recorded for ' . $userDept . ' yet in this period.' }}</p>
        </div>
      @endif
    @else
      
      @if(count($departmentScores) > 0)
        <!-- Top 3 Podiums -->
        <div class="mb-12">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            <!-- Second Place -->
            @if(isset($topThree[1]))
              <div class="order-2 md:order-1 flex flex-col items-center">
                <div class="relative mb-4">
                  <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-850 border-4 border-slate-300 dark:border-slate-700 flex items-center justify-center shadow-lg">
                    <i data-lucide="award" class="w-10 h-10 text-slate-400"></i>
                  </div>
                  <div class="absolute -bottom-2 -right-2 bg-slate-400 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 border-white">2</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800/80 p-5 w-full text-center shadow-sm hover:shadow-md transition-all">
                  <h3 class="font-black text-gray-800 dark:text-white mb-1">{{ $topThree[1]['name'] }}</h3>
                  <div class="text-2xl font-black text-slate-500 dark:text-slate-400 mb-2">{{ $topThree[1]['score'] }}%</div>
                  <div class="flex justify-center gap-0.5">
                    @for($s = 1; $s <= 5; $s++)
                      <i data-lucide="star" class="w-3 h-3 {{ $s <= round($topThree[1]['score'] / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700' }}"></i>
                    @endfor
                  </div>
                </div>
              </div>
            @endif

            <!-- First Place -->
            @if(isset($topThree[0]))
              <div class="order-1 md:order-2 flex flex-col items-center mb-6 md:mb-0 scale-110">
                <div class="relative mb-6">
                  <div class="absolute -top-6 left-1/2 -translate-x-1/2 animate-bounce">
                    <i data-lucide="trophy" class="w-8 h-8 text-yellow-500 fill-yellow-250"></i>
                  </div>
                  <div class="w-24 h-24 rounded-full bg-yellow-50 dark:bg-yellow-950/20 border-4 border-yellow-400 flex items-center justify-center shadow-xl ring-8 ring-yellow-400/10">
                    <i data-lucide="building-2" class="w-12 h-12 text-yellow-600 dark:text-yellow-400"></i>
                  </div>
                  <div class="absolute -bottom-2 -right-2 bg-yellow-500 text-white w-10 h-10 rounded-full flex items-center justify-center font-bold border-4 border-white shadow-lg">1</div>
                </div>
                <div class="bg-linear-to-r from-yellow-500 to-yellow-600 rounded-3xl p-6 w-full text-center shadow-xl border-2 border-yellow-450">
                  <h3 class="font-black text-white text-xl mb-1">{{ $topThree[0]['name'] }}</h3>
                  <div class="text-3xl font-black text-white mb-2">{{ $topThree[0]['score'] }}%</div>
                  <div class="flex justify-center gap-1">
                    @for($s = 1; $s <= 5; $s++)
                      <i data-lucide="star" class="w-4 h-4 {{ $s <= round($topThree[0]['score'] / 20) ? 'text-yellow-200 fill-yellow-200' : 'text-yellow-700' }}"></i>
                    @endfor
                  </div>
                </div>
              </div>
            @endif

            <!-- Third Place -->
            @if(isset($topThree[2]))
              <div class="order-3 flex flex-col items-center">
                <div class="relative mb-4">
                  <div class="w-20 h-20 rounded-full bg-orange-50 dark:bg-orange-950/20 border-4 border-orange-300 dark:border-orange-850 flex items-center justify-center shadow-lg">
                    <i data-lucide="medal" class="w-10 h-10 text-orange-400"></i>
                  </div>
                  <div class="absolute -bottom-2 -right-2 bg-orange-400 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 border-white">3</div>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-orange-200 dark:border-orange-950/45 p-5 w-full text-center shadow-sm hover:shadow-md transition-all">
                  <h3 class="font-black text-gray-850 dark:text-white mb-1">{{ $topThree[2]['name'] }}</h3>
                  <div class="text-2xl font-black text-orange-500 dark:text-orange-400 mb-2">{{ $topThree[2]['score'] }}%</div>
                  <div class="flex justify-center gap-0.5">
                    @for($s = 1; $s <= 5; $s++)
                      <i data-lucide="star" class="w-3 h-3 {{ $s <= round($topThree[2]['score'] / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700' }}"></i>
                    @endfor
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>

        <!-- Full Leaderboard Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden">
          <div class="p-6 border-b border-gray-50 dark:border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i data-lucide="trending-up" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              <h2 class="font-bold text-gray-800 dark:text-white">{{ $isAr ? 'الترتيب الكامل للأقسام' : 'Full Leaderboard' }}</h2>
            </div>
            <span class="text-xs text-gray-400 dark:text-slate-500">{{ count($departmentScores) }} {{ $isAr ? 'أقسام تم تقييمها' : 'departments rated' }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-start">
              <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-850/40">
                  <th class="px-6 py-4 text-start text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest w-20">{{ $isAr ? 'الترتيب' : 'Rank' }}</th>
                  <th class="px-6 py-4 text-start text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{{ $isAr ? 'القسم الطبي' : 'Department' }}</th>
                  <th class="px-6 py-4 text-start text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{{ $isAr ? 'الاستجابات' : 'Responses' }}</th>
                  <th class="px-6 py-4 text-start text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest w-48">{{ $isAr ? 'نسبة الرضا' : 'Satisfaction' }}</th>
                  <th class="px-6 py-4 text-center text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{{ $isAr ? 'التقييم' : 'Rating' }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
                @foreach($departmentScores as $index => $dept)
                  <tr class="hover:bg-gray-50/80 dark:hover:bg-slate-850/50 transition-colors group">
                    <td class="px-6 py-4">
                      @php
                        $rankClass = 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400';
                        if($index === 0) $rankClass = 'bg-yellow-500 text-white';
                        elseif($index === 1) $rankClass = 'bg-slate-300 dark:bg-slate-700 text-slate-700 dark:text-slate-300';
                        elseif($index === 2) $rankClass = 'bg-orange-300 dark:bg-orange-950/50 text-orange-800 dark:text-orange-400';
                      @endphp
                      <span class="flex items-center justify-center w-8 h-8 rounded-lg font-black text-sm {{ $rankClass }}">
                        {{ $index + 1 }}
                      </span>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-950/20 flex items-center justify-center text-teal-600 dark:text-teal-400 border border-teal-100 dark:border-teal-900/30 font-bold text-xs">
                          {{ mb_substr($dept['name'], 0, 1) }}
                        </div>
                        <span class="font-black text-gray-900 dark:text-white">{{ $dept['name'] }}</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">
                      <div class="flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4 text-gray-300 dark:text-slate-600"></i>
                        {{ $dept['count'] }} {{ $isAr ? 'مريض' : 'patients' }}
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="flex-1 h-2 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                          @php
                            $barClass = 'bg-yellow-500';
                            if($dept['score'] >= 85) $barClass = 'bg-green-500';
                            elseif($dept['score'] >= 70) $barClass = 'bg-blue-500';
                          @endphp
                          <div class="h-full rounded-full transition-all duration-1000 {{ $barClass }}" style="width: {{ $dept['score'] }}%"></div>
                        </div>
                        <span class="font-black text-gray-900 dark:text-white text-sm">{{ $dept['score'] }}%</span>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex justify-center gap-0.5 opacity-40 group-hover:opacity-100 transition-opacity">
                        @for($s = 1; $s <= 5; $s++)
                          <i data-lucide="star" class="w-3.5 h-3.5 {{ $s <= round($dept['score'] / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700' }}"></i>
                        @endfor
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @else
        <div class="text-center py-20">
          <div class="w-20 h-20 bg-gray-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="building-2" class="w-10 h-10 text-gray-300 dark:text-slate-600"></i>
          </div>
          <p class="text-gray-500 dark:text-slate-400 font-bold">{{ $isAr ? 'لا توجد بيانات متاحة في لوحة شرف الأداء المتميز للفترة المحددة' : 'No data available for the selected period' }}</p>
        </div>
      @endif

    @endif
  </div>

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('hallOfFameComponent', () => ({
        dateFilter: '{{ $dateFilter }}',
        startDate: '{{ $startDate }}',
        endDate: '{{ $endDate }}',
        
        submitForm() {
          setTimeout(() => {
            document.getElementById('filtersForm').submit();
          }, 50);
        }
      }));
    });
  </script>
@endsection
