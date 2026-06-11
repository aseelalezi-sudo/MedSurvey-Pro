<div id="surveys-content" class="relative">
        <div x-show="isRefreshing" x-cloak class="absolute inset-0 z-20 flex items-start justify-center rounded-3xl bg-white/60 pt-16 backdrop-blur-[1px] dark:bg-slate-950/55">
          <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-teal-600 dark:text-teal-400"></i>
        </div>

      <!-- Empty State -->
      @if($surveys->isEmpty())
        <div class="text-center py-20 bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm">
          <div class="w-20 h-20 bg-teal-50 dark:bg-teal-950/20 text-teal-600 dark:text-teal-400 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
            <i data-lucide="clipboard-list" class="w-10 h-10"></i>
          </div>
          <h3 class="text-lg font-black text-gray-800 dark:text-white mb-2">{{ $isAr ? 'لا توجد استبيانات مضافة حالياً' : 'No surveys added yet' }}</h3>
          <p class="text-gray-500 dark:text-slate-400 max-w-sm mx-auto mb-6 text-sm">{{ $isAr ? 'ابدأ بإنشاء استبيانك الأول لتتمكن من جمع وتقييم آراء المرضى وتطوير أداء عيادات المستشفى.' : 'Start creating your first survey to collect patient feedback.' }}</p>
          <button @click="openCreate()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-sm transition-colors cursor-pointer">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ $isAr ? 'إضافة استبيان جديد' : 'Create New Survey' }}
          </button>
        </div>
      @endif

      <!-- Grid List -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($surveys as $survey)
          @php
            $sectionsCount = $survey->sections->count();
            $questionsCount = $survey->sections->sum(fn($s) => $s->questions->count());
            $responsesCount = $survey->responses_count;
          @endphp
          <div class="group bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800/80 shadow-md hover:shadow-2xl hover:border-teal-500/20 dark:hover:border-teal-500/30 transition-all duration-300 overflow-hidden flex flex-col text-start animate-fade-in">
            <!-- Card Header -->
            <div class="p-6 text-white relative overflow-hidden text-start {{ $survey->isActive ? 'bg-linear-to-br from-teal-500 to-emerald-600' : 'bg-linear-to-br from-slate-400 to-slate-500 dark:from-slate-700 dark:to-slate-800' }}">
              <div class="absolute inset-0 opacity-10">
                <div class="absolute -top-10 -left-10 w-40 h-40 bg-white rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-32 h-32 bg-white rounded-full"></div>
              </div>

              <div class="relative">
                <div class="flex items-center justify-between mb-4">
                  <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i data-lucide="clipboard-list" class="w-6 h-6 text-white"></i>
                  </div>
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-black {{ $survey->isActive ? 'bg-emerald-500/20 border border-emerald-400/30 text-emerald-100' : 'bg-white/20 border border-white/25 text-white/90' }}">
                    {{ $survey->isActive ? ($isAr ? 'نشط' : 'Active') : ($isAr ? 'غير نشط' : 'Inactive') }}
                  </span>
                </div>

                <h3 class="text-lg font-black mb-1.5 leading-relaxed text-white line-clamp-1">{{ $survey->title }}</h3>
                <p class="text-xs line-clamp-2 min-h-8 {{ $survey->isActive ? 'text-teal-100' : 'text-slate-100/90' }}">
                  {{ $survey->description ?: ($isAr ? 'لا يوجد وصف لهذا الاستبيان.' : 'No description provided.') }}
                </p>
              </div>
            </div>

            <!-- Card Body -->
            <div class="p-5 flex-1 flex flex-col justify-between text-start">
              <!-- Stats -->
              <div class="grid grid-cols-[0.9fr_0.95fr_1.2fr_1.15fr] gap-1.5 text-[11px] font-bold text-gray-500 dark:text-slate-400 mb-4 sm:text-xs">
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ $formatCount($sectionsCount) }} {{ $isAr ? 'أقسام' : 'Sections' }}">{{ $compactCount($sectionsCount) }} {{ $isAr ? 'أقسام' : 'Sections' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="clipboard-check" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ $formatCount($questionsCount) }} {{ $isAr ? 'أسئلة' : 'Questions' }}">{{ $compactCount($questionsCount) }} {{ $isAr ? 'أسئلة' : 'Questions' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="users" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span title="{{ $formatCount($responsesCount) }} {{ $isAr ? 'استجابة' : 'Responses' }}">{{ $compactCount($responsesCount) }} {{ $isAr ? 'استجابة' : 'Responses' }}</span>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-1 whitespace-nowrap">
                  <i data-lucide="calendar" class="h-3.5 w-3.5 shrink-0 text-teal-600 dark:text-teal-400 sm:h-4 sm:w-4"></i>
                  <span>{{ $survey->createdAt ? $survey->createdAt->format($isAr ? 'Y/m/d' : 'M d, Y') : '' }}</span>
                </div>
              </div>

              <!-- Badges -->
              <div class="flex flex-wrap gap-1.5 mb-5 min-h-7">
                @if($survey->requireName)
                  <span class="bg-amber-50 dark:bg-amber-950/20 border border-amber-100/30 dark:border-amber-900/40 text-[10px] font-bold text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="user" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'الاسم مطلوب' : 'Name Required' }}</span>
                  </span>
                @endif
                @if($survey->requirePhone)
                  <span class="bg-orange-50 dark:bg-orange-950/20 border border-orange-100/30 dark:border-orange-900/40 text-[10px] font-bold text-orange-700 dark:text-orange-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="phone" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'الهاتف مطلوب' : 'Phone Required' }}</span>
                  </span>
                @endif

                @php $assignedDepts = $survey->assignedDepartments ?? []; @endphp
                @if(count($assignedDepts) > 0)
                  @foreach(array_slice($assignedDepts, 0, 2) as $dept)
                    <span class="bg-teal-50 dark:bg-teal-950/20 border border-teal-100/30 dark:border-teal-900/40 text-[10px] font-bold text-teal-700 dark:text-teal-400 px-2 py-0.5 rounded-md">
                      {{ $dept }}
                    </span>
                  @endforeach
                  @if(count($assignedDepts) > 2)
                    <span class="bg-gray-100 dark:bg-slate-800 text-[10px] font-bold text-gray-500 dark:text-slate-400 px-2 py-0.5 rounded-md">
                      +{{ count($assignedDepts) - 2 }}
                    </span>
                  @endif
                @else
                  <span class="bg-slate-50 dark:bg-slate-800/30 border border-slate-100/40 dark:border-slate-800 text-[10px] font-bold text-slate-400 px-2.5 py-0.5 rounded-md flex items-center gap-1">
                    <i data-lucide="building-2" class="w-3 h-3"></i>
                    <span>{{ $isAr ? 'غير مخصص لأي قسم' : 'Not assigned' }}</span>
                  </span>
                @endif
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 pt-4 border-t border-gray-50 dark:border-slate-800/80 mt-auto">
                <form method="POST" action="{{ route('dashboard.surveys.toggle', $survey->id) }}" @submit.prevent="submitSurveyAction($event.target, '{{ $survey->isActive ? ($isAr ? 'تم إيقاف الاستبيان' : 'Survey deactivated') : ($isAr ? 'تم تفعيل الاستبيان' : 'Survey activated') }}')" class="flex-1">
                  @csrf @method('PATCH')
                  <button type="submit" class="w-full py-2 px-3 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 border border-gray-200/70 dark:border-slate-750 text-xs font-bold text-gray-600 dark:text-slate-300 transition-all cursor-pointer flex items-center justify-center gap-1">
                    <span>{{ $survey->isActive ? ($isAr ? 'إيقاف' : 'Deactivate') : ($isAr ? 'تفعيل' : 'Activate') }}</span>
                  </button>
                </form>
                <button @click="openEdit('{{ $survey->id }}')" class="flex-1 py-2 px-3 rounded-xl bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/30 dark:hover:bg-teal-950/50 border border-teal-100/30 dark:border-teal-900/40 text-xs font-bold text-teal-600 dark:text-teal-400 transition-all cursor-pointer flex items-center justify-center gap-1">
                  <i data-lucide="edit-3" class="w-4 h-4"></i>
                  <span>{{ $isAr ? 'تعديل' : 'Edit' }}</span>
                </button>
                <form method="POST" action="{{ route('dashboard.surveys.duplicate', $survey->id) }}" @submit.prevent="submitSurveyAction($event.target, '{{ $isAr ? 'تم تكرار الاستبيان بنجاح' : 'Survey duplicated successfully' }}')" title="{{ $isAr ? 'تكرار الاستبيان' : 'Duplicate' }}">
                  @csrf
                  <button type="submit" class="py-2 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/20 dark:hover:bg-blue-950/30 border border-blue-100/30 dark:border-blue-900/40 text-xs font-bold text-blue-500 dark:text-blue-400 transition-all cursor-pointer flex items-center justify-center">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                  </button>
                </form>
                @if($isSuperAdmin || $survey->responses_count === 0)
                  <button
                    type="button"
                    @click="openDelete(@js($survey->id), @js($survey->title), @js(route('dashboard.surveys.destroy', $survey->id)), @js($survey->responses_count))"
                    class="py-2 px-2.5 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/30 border border-red-100/30 dark:border-red-900/40 text-xs font-bold text-red-500 dark:text-red-400 transition-all cursor-pointer flex items-center justify-center"
                  >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                  </button>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="mt-8">
        {{ $surveys->links() }}
      </div>
      </div>
