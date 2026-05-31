@extends('layouts.dashboard')

@section('title', 'إدارة الاستبيانات - MedSurvey Pro')

@section('dashboard')
  @php
    $isRtl = app()->getLocale() === 'ar';
    $isAr = $isRtl;
    $user = auth()->user();
    $isSuperAdmin = $user->role === 'super_admin';
  @endphp

  <div x-data="surveyComponent()" class="space-y-6 animate-fade-in font-cairo text-start">
    <!-- Toast Notification -->
    <div x-show="toast.show" x-transition.opacity.duration.300ms class="fixed top-4 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-2xl shadow-xl border font-bold text-sm flex items-center gap-3 transition-all"
         :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:border-emerald-800' : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-800'" style="display: none;">
      <i data-lucide="check-circle-2" x-show="toast.type === 'success'" class="w-5 h-5"></i>
      <i data-lucide="alert-circle" x-show="toast.type === 'error'" class="w-5 h-5"></i>
      <span x-text="toast.message"></span>
    </div>

    <!-- Header -->
    <div class="max-w-7xl mx-auto py-6">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 border-b border-gray-100 dark:border-slate-800/80 pb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-linear-to-br from-teal-500 to-teal-600 dark:from-teal-600 dark:to-teal-800 rounded-xl flex items-center justify-center shadow-lg shadow-teal-100 dark:shadow-none">
            <i data-lucide="clipboard-list" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <h2 class="text-lg sm:text-xl font-black text-gray-900 dark:text-white leading-tight">{{ $isAr ? 'إدارة وتصميم الاستبيانات' : 'Surveys Management' }}</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">{{ $isAr ? 'قم بإنشاء وتعديل استبيانات رضا المرضى وتخصيصها للأقسام الطبية' : 'Create and manage patient satisfaction surveys' }}</p>
          </div>
        </div>
        <button
          @click="openCreate()"
          class="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-3 bg-linear-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
        >
          <i data-lucide="plus" class="w-5 h-5"></i>
          {{ $isAr ? 'إضافة استبيان جديد' : 'Create New Survey' }}
        </button>
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
          <div class="group bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800/80 shadow-md hover:shadow-2xl hover:border-teal-500/20 transition-all duration-300 overflow-hidden flex flex-col text-start animate-fade-in">
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
              <div class="flex items-center gap-4 mb-4 text-xs font-bold text-gray-500 dark:text-slate-400">
                <div class="flex items-center gap-1.5">
                  <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span>{{ $survey->sections->count() }} {{ $isAr ? 'أقسام' : 'Sections' }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <i data-lucide="clipboard-check" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span>{{ $survey->sections->sum(fn($s) => $s->questions->count()) }} {{ $isAr ? 'أسئلة' : 'Questions' }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <i data-lucide="users" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span>{{ $survey->responses_count }} {{ $isAr ? 'استجابة' : 'Responses' }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <i data-lucide="calendar" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
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
                <form method="POST" action="{{ route('dashboard.surveys.toggle', $survey->id) }}" class="flex-1">
                  @csrf @method('PATCH')
                  <button type="submit" class="w-full py-2 px-3 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 border border-gray-200/70 dark:border-slate-750 text-xs font-bold text-gray-600 dark:text-slate-300 transition-all cursor-pointer flex items-center justify-center gap-1">
                    <span>{{ $survey->isActive ? ($isAr ? 'إيقاف' : 'Deactivate') : ($isAr ? 'تفعيل' : 'Activate') }}</span>
                  </button>
                </form>
                <button @click="openEdit('{{ $survey->id }}')" class="flex-1 py-2 px-3 rounded-xl bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/30 dark:hover:bg-teal-950/50 border border-teal-100/30 dark:border-teal-900/40 text-xs font-bold text-teal-600 dark:text-teal-400 transition-all cursor-pointer flex items-center justify-center gap-1">
                  <span>{{ $isAr ? 'تعديل' : 'Edit' }}</span>
                </button>
                <form method="POST" action="{{ route('dashboard.surveys.duplicate', $survey->id) }}" title="{{ $isAr ? 'تكرار الاستبيان' : 'Duplicate' }}">
                  @csrf
                  <button type="submit" class="py-2 px-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/20 dark:hover:bg-blue-950/30 border border-blue-100/30 dark:border-blue-900/40 text-xs font-bold text-blue-500 dark:text-blue-400 transition-all cursor-pointer flex items-center justify-center">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                  </button>
                </form>
                @if($isSuperAdmin)
                  <form method="POST" action="{{ route('dashboard.surveys.destroy', $survey->id) }}" onsubmit="return confirm('{{ $isAr ? 'هل أنت متأكد من حذف الاستبيان بالكامل؟' : 'Are you sure?' }}');">
                    @csrf @method('DELETE')
                    <button type="submit" class="py-2 px-2.5 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/30 border border-red-100/30 dark:border-red-900/40 text-xs font-bold text-red-500 dark:text-red-400 transition-all cursor-pointer flex items-center justify-center">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </form>
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

    <!-- Survey Editor Modal -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-start justify-center p-4 overflow-y-auto">
      <div @click.away="!isSaving && closeModal()" class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-4xl w-full my-8 text-start shadow-2xl transition-all" x-transition.scale.origin.bottom>
        
        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
          <h2 class="text-xl font-black text-gray-800 dark:text-white" x-text="isEditing ? '{{ $isAr ? 'تعديل الاستبيان' : 'Edit Survey' }}' : '{{ $isAr ? 'إنشاء استبيان جديد' : 'Create New Survey' }}'"></h2>
          <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <div class="p-6 space-y-6">
          <div class="space-y-4">
            <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
              <i data-lucide="clipboard-list" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              {{ $isAr ? 'المعلومات الأساسية' : 'Basic Info' }}
            </h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'عنوان الاستبيان' : 'Survey Title' }} <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.title" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold" placeholder="{{ $isAr ? 'أدخل عنوان الاستبيان...' : 'Enter survey title...' }}">
              </div>
              <div>
                <label class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'الوصف (اختياري)' : 'Description (Optional)' }}</label>
                <textarea x-model="form.description" rows="2" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold"></textarea>
              </div>
            </div>

            <!-- Toggles -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حالة الاستبيان' : 'Survey Status' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1">{{ $isAr ? 'تفعيل ليظهر للمرضى' : 'Activate to show to patients' }}</p>
              </div>
              <button type="button" @click="form.isActive = !form.isActive" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.isActive ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.isActive ? '{{ $isRtl ? 'left-0.5' : 'right-0.5' }} translate-x-full' : '{{ $isRtl ? 'left-7' : 'right-7' }} -translate-x-full'" :style="form.isActive ? 'transform: translateX({{ $isRtl ? '-' : '' }}100%)' : 'transform: translateX(0)'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requireName ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الاسم' : 'Name Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requireName ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requireName = !form.requireName" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requireName ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :style="form.requireName ? 'transform: translateX({{ $isRtl ? '-' : '' }}100%)' : 'transform: translateX(0)'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requirePhone ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الهاتف' : 'Phone Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requirePhone ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requirePhone = !form.requirePhone" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requirePhone ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :style="form.requirePhone ? 'transform: translateX({{ $isRtl ? '-' : '' }}100%)' : 'transform: translateX(0)'"></div>
              </button>
            </div>

            <!-- Tips -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
                <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
                  <i data-lucide="heart" class="w-5 h-5 text-red-500"></i>
                  {{ $isAr ? 'نصائح طبية للمرضى' : 'Medical Tips' }}
                </h3>
                <button type="button" @click="form.tips.push('')" class="text-xs font-bold text-teal-600 dark:text-teal-400 cursor-pointer">{{ $isAr ? '+ إضافة نصيحة' : '+ Add Tip' }}</button>
              </div>
              <div class="space-y-2">
                <template x-for="(tip, index) in form.tips" :key="index">
                  <div class="flex items-center gap-2">
                    <input type="text" x-model="form.tips[index]" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold">
                    <button type="button" @click="form.tips.splice(index, 1)" class="p-2.5 text-red-400 hover:text-red-500 cursor-pointer">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </div>
                </template>
              </div>
            </div>
            
            <!-- Assigned Departments -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
                <i data-lucide="building-2" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'تخصيص للأقسام' : 'Assigned Departments' }}
              </h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                <template x-for="dept in availableDepartments" :key="dept">
                  <button type="button" @click="toggleDepartment(dept)" class="p-3 rounded-xl border-2 text-sm font-bold transition-all cursor-pointer" :class="form.assignedDepartments.includes(dept) ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-950 text-gray-700 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800'">
                    <div class="flex items-center gap-2">
                      <i data-lucide="check" class="w-4 h-4 text-teal-600 dark:text-teal-400" x-show="form.assignedDepartments.includes(dept)"></i>
                      <span x-text="dept"></span>
                    </div>
                  </button>
                </template>
              </div>
            </div>

            <!-- Sections Builder -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
                <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
                  <i data-lucide="file-text" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                  {{ $isAr ? 'أقسام الاستبيان' : 'Survey Sections' }} (<span x-text="form.sections.length"></span>)
                </h3>
                <button type="button" @click="addSection()" class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-bold hover:bg-teal-700 transition-colors cursor-pointer">
                  <i data-lucide="plus" class="w-4 h-4"></i>
                  {{ $isAr ? 'إضافة قسم' : 'Add Section' }}
                </button>
              </div>

              <!-- Sections List -->
              <div class="space-y-4">
                <template x-for="(section, sIndex) in form.sections" :key="sIndex">
                  <div class="border border-gray-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-gray-50 dark:bg-slate-800/60 p-4">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-slate-700">
                      <div class="flex-1 space-y-2">
                        <input type="text" x-model="section.title" class="w-full bg-transparent font-black text-lg text-gray-800 dark:text-white outline-none border-b border-transparent focus:border-teal-500 placeholder-gray-400" placeholder="{{ $isAr ? 'عنوان القسم...' : 'Section Title...' }}">
                        <input type="text" x-model="section.description" class="w-full bg-transparent font-bold text-sm text-gray-500 dark:text-slate-400 outline-none border-b border-transparent focus:border-teal-500 placeholder-gray-400" placeholder="{{ $isAr ? 'وصف القسم (اختياري)...' : 'Section Description...' }}">
                      </div>
                      <button type="button" @click="form.sections.splice(sIndex, 1)" class="p-2 text-red-400 hover:text-red-500 bg-white dark:bg-slate-900 rounded-xl shadow-sm">
                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                      </button>
                    </div>

                    <!-- Questions List -->
                    <div class="space-y-3 pl-4 border-l-2 border-teal-100 dark:border-teal-900/50">
                      <template x-for="(question, qIndex) in section.questions" :key="qIndex">
                        <div class="bg-white dark:bg-slate-900 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-800 space-y-3 relative group">
                          
                          <button type="button" @click="section.questions.splice(qIndex, 1)" class="absolute top-3 {{ $isRtl ? 'left-3' : 'right-3' }} text-gray-400 hover:text-red-500">
                            <i data-lucide="x" class="w-4 h-4"></i>
                          </button>

                          <div class="flex gap-2 mb-2">
                            <template x-for="type in questionTypes" :key="type.id">
                              <button type="button" @click="question.type = type.id" class="px-2 py-1.5 rounded-lg border text-[10px] font-bold transition-all" :class="question.type === type.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-700 text-gray-500'">
                                <span x-text="type.label"></span>
                              </button>
                            </template>
                          </div>

                          <input type="text" x-model="question.title" class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-lg text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-teal-500" placeholder="{{ $isAr ? 'نص السؤال...' : 'Question text...' }}">
                          
                          <!-- Options (only for multiple choice) -->
                          <div x-show="question.type === 'multiple_choice'" class="space-y-2 mt-3 p-3 bg-gray-50 dark:bg-slate-800/40 rounded-xl">
                            <label class="text-xs font-bold text-gray-500">{{ $isAr ? 'خيارات الإجابة:' : 'Options:' }}</label>
                            <template x-for="(opt, optIndex) in question.options" :key="optIndex">
                              <div class="flex gap-2">
                                <input type="text" x-model="opt.label" class="flex-1 px-2 py-1 text-xs font-bold bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-md outline-none focus:border-teal-500" placeholder="{{ $isAr ? 'الخيار...' : 'Option...' }}">
                                <button type="button" @click="question.options.splice(optIndex, 1)" class="text-red-400 hover:text-red-500"><i data-lucide="x" class="w-4 h-4"></i></button>
                              </div>
                            </template>
                            <button type="button" @click="if(!question.options) question.options = []; question.options.push({label:'', value:''})" class="text-xs font-bold text-teal-600 mt-1">{{ $isAr ? '+ إضافة خيار' : '+ Add Option' }}</button>
                          </div>

                          <div class="flex items-center gap-2 mt-2">
                            <input type="checkbox" x-model="question.required" class="rounded text-teal-500 border-gray-300">
                            <span class="text-xs font-bold text-gray-600 dark:text-slate-400">{{ $isAr ? 'إجابة مطلوبة' : 'Required' }}</span>
                          </div>
                        </div>
                      </template>
                      
                      <button type="button" @click="addQuestion(sIndex)" class="w-full py-3 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-xl text-gray-500 dark:text-slate-400 font-bold text-sm hover:border-teal-500 hover:text-teal-600 transition-colors">
                        {{ $isAr ? '+ إضافة سؤال للقسم' : '+ Add Question to Section' }}
                      </button>
                    </div>

                  </div>
                </template>
              </div>

            </div>

          </div>
        </div>

        <div class="p-6 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between sticky bottom-0 bg-white dark:bg-slate-900 rounded-b-2xl z-10">
          <button type="button" @click="closeModal()" class="px-6 py-3 rounded-xl text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:bg-slate-800 font-bold transition-all cursor-pointer">
            {{ $isAr ? 'إلغاء' : 'Cancel' }}
          </button>
          <button type="button" @click="saveSurvey()" :disabled="!form.title || isSaving" class="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white bg-teal-600 hover:bg-teal-700 disabled:opacity-50 transition-all cursor-pointer">
            <i data-lucide="save" class="w-5 h-5" x-show="!isSaving"></i>
            <i data-lucide="loader-2" class="w-5 h-5 animate-spin" x-show="isSaving"></i>
            <span x-text="isSaving ? '{{ $isAr ? 'جاري الحفظ...' : 'Saving...' }}' : '{{ $isAr ? 'حفظ الاستبيان' : 'Save Survey' }}'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  @php
    $surveysJson = $surveys->map(function($survey) {
        $data = $survey->toArray();
        // Decode fields if they are strings (JSON)
        $data['tips'] = is_string($survey->tips) ? json_decode($survey->tips, true) : ($survey->tips ?? []);
        $data['assignedDepartments'] = is_string($survey->assignedDepartments) ? json_decode($survey->assignedDepartments, true) : ($survey->assignedDepartments ?? []);
        
        $data['sections'] = $survey->sections->map(function($sec) {
            $secData = $sec->toArray();
            $secData['questions'] = $sec->questions->map(function($q) {
                $qData = $q->toArray();
                $qData['options'] = is_string($q->options) ? json_decode($q->options, true) : ($q->options ?? []);
                return $qData;
            });
            return $secData;
        });
        return $data;
    })->values();
  @endphp

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('surveyComponent', () => ({
        surveys: @json($surveysJson),
        availableDepartments: @json($departments),
        showModal: false,
        isEditing: false,
        isSaving: false,
        toast: { show: false, message: '', type: 'success' },
        questionTypes: [
          { id: 'stars', label: '{{ $isAr ? 'نجوم' : 'Stars' }}' },
          { id: 'emoji', label: '{{ $isAr ? 'وجوه تعبيرية' : 'Emoji' }}' },
          { id: 'nps', label: '{{ $isAr ? 'NPS' : 'NPS' }}' },
          { id: 'yes_no', label: '{{ $isAr ? 'نعم/لا' : 'Yes/No' }}' },
          { id: 'multiple_choice', label: '{{ $isAr ? 'خيارات' : 'Multiple Choice' }}' },
          { id: 'text', label: '{{ $isAr ? 'نص حر' : 'Text' }}' }
        ],
        form: {
          id: null,
          title: '',
          description: '',
          isActive: true,
          requireName: false,
          requirePhone: false,
          tips: [],
          assignedDepartments: [],
          sections: []
        },

        showToastMsg(msg, type = 'success') {
          this.toast = { show: true, message: msg, type: type };
          setTimeout(() => { this.toast.show = false; }, 3000);
        },

        openCreate() {
          this.isEditing = false;
          this.form = {
            id: null, title: '', description: '', isActive: true,
            requireName: false, requirePhone: false, tips: [],
            assignedDepartments: [], sections: []
          };
          this.showModal = true;
        },

        openEdit(id) {
          const survey = this.surveys.find(s => String(s.id) === String(id));
          if(survey) {
            this.isEditing = true;
            this.form = JSON.parse(JSON.stringify(survey)); // Deep copy
            this.form.tips = this.form.tips || [];
            this.form.assignedDepartments = this.form.assignedDepartments || [];
            this.form.sections = this.form.sections || [];
            
            // Fix options stringification issue
            this.form.sections.forEach(s => {
                s.questions.forEach(q => {
                    if(typeof q.options === 'string') {
                        try { q.options = JSON.parse(q.options); } catch(e) { q.options = []; }
                    }
                    if(!q.options) q.options = [];
                });
            });

            this.showModal = true;
          }
        },

        closeModal() {
          this.showModal = false;
        },

        toggleDepartment(dept) {
          if (this.form.assignedDepartments.includes(dept)) {
            this.form.assignedDepartments = this.form.assignedDepartments.filter(d => d !== dept);
          } else {
            this.form.assignedDepartments.push(dept);
          }
        },

        addSection() {
          this.form.sections.push({
            id: 'section-' + Date.now(),
            title: '',
            description: '',
            icon: 'clipboard-check',
            questions: []
          });
        },

        addQuestion(sectionIndex) {
          this.form.sections[sectionIndex].questions.push({
            id: 'question-' + Date.now(),
            type: 'stars',
            title: '',
            description: '',
            required: false,
            options: []
          });
        },

        async saveSurvey() {
          this.isSaving = true;
          try {
            const url = this.isEditing ? `/dashboard/surveys/${this.form.id}` : '/dashboard/surveys';
            const method = this.isEditing ? 'PUT' : 'POST';
            
            // Clean up options: ensure value is same as label
            this.form.sections.forEach(sec => {
                sec.questions.forEach(q => {
                    if (q.type === 'multiple_choice' && q.options) {
                        q.options.forEach(opt => { opt.value = opt.label; });
                    } else {
                        q.options = [];
                    }
                });
            });

            const response = await fetch(url, {
              method: method,
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
              },
              body: JSON.stringify(this.form)
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
              this.showToastMsg('{{ $isAr ? 'تم حفظ الاستبيان بنجاح' : 'Survey saved successfully' }}');
              setTimeout(() => window.location.reload(), 1000);
            } else {
              this.showToastMsg(result.error || result.message || 'Error occurred', 'error');
              this.isSaving = false;
            }
          } catch (error) {
            this.showToastMsg('Network Error', 'error');
            this.isSaving = false;
          }
        }
      }));
    });
  </script>
@endsection
