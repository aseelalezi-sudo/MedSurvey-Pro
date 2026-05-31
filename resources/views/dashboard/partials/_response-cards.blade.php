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