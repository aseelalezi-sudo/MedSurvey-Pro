<!-- Survey Editor Modal -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-start justify-center p-4 overflow-y-auto">
      <div @click.away="!isSaving && closeModal()" class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-4xl w-full my-8 text-start shadow-2xl transition-all" x-transition.scale.origin.bottom>

        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
          <h2 class="text-xl font-black text-gray-800 dark:text-white" x-text="isEditing ? ('{{ $isAr ? 'تعديل' : 'Edit' }}: ' + (form.title || '{{ $isAr ? 'استبيان جديد' : 'New Survey' }}')) : '{{ $isAr ? 'إنشاء استبيان جديد' : 'Create New Survey' }}'"></h2>
          <div class="flex items-center gap-2">
            <!-- Preview Button -->
            <button type="button" @click="togglePreview()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-teal-50 hover:text-teal-600 dark:hover:bg-teal-950/30 dark:hover:text-teal-400 transition-all cursor-pointer">
              <i data-lucide="eye" class="w-4 h-4"></i>
              <span>{{ $isAr ? 'معاينة' : 'Preview' }}</span>
            </button>
            <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
              <i data-lucide="x" class="w-6 h-6"></i>
            </button>
          </div>
        </div>

        <div class="p-6 space-y-6">
          <div class="space-y-4">
            <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
              <i data-lucide="clipboard-list" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              {{ $isAr ? 'المعلومات الأساسية' : 'Basic Info' }}
              <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'قم بتعبئة المعلومات الأساسية للاستبيان مثل العنوان والوصف' : 'Fill in the basic survey information like title and description' }}">?</span>
            </h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label for="surveyTitle" class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'عنوان الاستبيان' : 'Survey Title' }} <span class="text-red-500">*</span></label>
                <input id="surveyTitle" name="surveyTitle" type="text" x-model="form.title" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold" placeholder="{{ $isAr ? 'أدخل عنوان الاستبيان...' : 'Enter survey title...' }}">
              </div>
              <div>
                <label for="surveyDescription" class="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2">{{ $isAr ? 'الوصف (اختياري)' : 'Description (Optional)' }}</label>
                <textarea id="surveyDescription" name="surveyDescription" x-model="form.description" rows="2" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-bold"></textarea>
              </div>
            </div>

            <!-- Toggles -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حالة الاستبيان' : 'Survey Status' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1">{{ $isAr ? 'تفعيل ليظهر للمرضى' : 'Activate to show to patients' }}</p>
              </div>
              <button type="button" @click="form.isActive = !form.isActive" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.isActive ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.isActive ? '{{ $isRtl ? 'right-7' : 'left-7' }}' : '{{ $isRtl ? 'right-0.5' : 'left-0.5' }}'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requireName ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الاسم' : 'Name Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requireName ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requireName = !form.requireName" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requireName ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.requireName ? '{{ $isRtl ? 'right-7' : 'left-7' }}' : '{{ $isRtl ? 'right-0.5' : 'left-0.5' }}'"></div>
              </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border-2 transition-all" :class="form.requirePhone ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'">
              <div>
                <p class="font-bold text-gray-700 dark:text-slate-200">{{ $isAr ? 'حقل الهاتف' : 'Phone Field' }}</p>
                <p class="text-xs font-bold text-gray-500 dark:text-slate-400 mt-1" x-text="form.requirePhone ? '{{ $isAr ? 'مطلوب إجبارياً' : 'Required' }}' : '{{ $isAr ? 'اختياري' : 'Optional' }}'"></p>
              </div>
              <button type="button" @click="form.requirePhone = !form.requirePhone" class="w-14 h-7 rounded-full transition-all relative cursor-pointer" :class="form.requirePhone ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'">
                <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all" :class="form.requirePhone ? '{{ $isRtl ? 'right-7' : 'left-7' }}' : '{{ $isRtl ? 'right-0.5' : 'left-0.5' }}'"></div>
              </button>
            </div>

            <!-- Tips -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
                <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
                  <i data-lucide="heart" class="w-5 h-5 text-red-500"></i>
                  {{ $isAr ? 'نصائح طبية للمرضى' : 'Medical Tips' }}
                  <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'أضف نصائح صحية تظهر للمريض بعد إكمال الاستبيان. سيتم اختيار نصيحة عشوائية في كل مرة.' : 'Add health tips that appear to patients after completing the survey. A random tip will be shown each time.' }}">?</span>
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
              <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2 relative group">
                <i data-lucide="building-2" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'تخصيص للأقسام' : 'Assigned Departments' }}
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'اختر الأقسام الطبية التي سيظهر لها هذا الاستبيان. إذا لم تختر أي قسم، سيظهر لجميع الأقسام.' : 'Select which medical departments this survey will be available for. If none selected, it will be available to all departments.' }}">?</span>
              </h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                <template x-for="dept in availableDepartments" :key="dept">
                  <button type="button" @click="toggleDepartment(dept)" class="p-3 rounded-xl border-2 text-sm font-bold transition-all cursor-pointer text-start" :class="form.assignedDepartments.includes(dept) ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-950 text-gray-700 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800'">
                    <div class="flex items-center gap-2">
                      <i data-lucide="check" class="w-4 h-4 text-teal-600 dark:text-teal-400" x-show="form.assignedDepartments.includes(dept)"></i>
                      <span x-text="dept"></span>
                    </div>
                  </button>
                </template>
              </div>
            </div>

            <!-- Templates Bar (NEW) -->
            <div x-show="form.sections.length === 0" class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-teal-50/50 dark:bg-teal-950/10 border border-teal-100 dark:border-teal-900/30 rounded-xl">
              <span class="text-xs font-bold text-teal-700 dark:text-teal-400 flex items-center gap-1">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                {{ $isAr ? 'قوالب جاهزة:' : 'Templates:' }}
              </span>
              <button type="button" @click="loadTemplate('reception')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'رضا الاستقبال' : 'Reception' }}</button>
              <button type="button" @click="loadTemplate('nursing')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'الخدمة التمريضية' : 'Nursing' }}</button>
              <button type="button" @click="loadTemplate('full')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'استبيان شامل' : 'Full Survey' }}</button>
              <button type="button" @click="loadTemplate('quick')" class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-teal-200 dark:border-teal-800/60 rounded-lg font-bold text-teal-700 dark:text-teal-400 hover:bg-teal-100 dark:hover:bg-teal-950/30 transition-all cursor-pointer">{{ $isAr ? 'سريع (سؤالين)' : 'Quick (2 Q)' }}</button>
            </div>

            <!-- Sections Builder - Enhanced with Icons, Collapse/Expand, Reordering -->
            <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
              <div class="flex items-center justify-between">
              <h3 class="font-black text-gray-700 dark:text-white flex items-center gap-2">
                <i data-lucide="file-text" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'أقسام الاستبيان' : 'Survey Sections' }} (<span x-text="form.sections.length"></span>)
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[9px] font-bold cursor-help shrink-0 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" title="{{ $isAr ? 'الأقسام هي مجموعات من الأسئلة. يمكنك إضافة أقسام متعددة وتخصيص أيقونة لكل قسم.' : 'Sections are groups of questions. You can add multiple sections and customize an icon for each one.' }}">?</span>
              </h3>
                <button type="button" @click="addSection()" class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-bold hover:bg-teal-700 transition-colors cursor-pointer">
                  <i data-lucide="plus" class="w-4 h-4"></i>
                  {{ $isAr ? 'إضافة قسم' : 'Add Section' }}
                </button>
              </div>

              <!-- Empty Sections State -->
              <div x-show="form.sections.length === 0" class="text-center py-10 bg-gray-50 dark:bg-slate-800/40 rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-750">
                <i data-lucide="alert-circle" class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-3"></i>
                <p class="text-gray-500 dark:text-slate-400 font-bold">{{ $isAr ? 'لا توجد أقسام بعد. أضف قسماً جديداً للبدء.' : 'No sections yet. Add a new section to start building your survey.' }}</p>
              </div>

              <!-- Sections List -->
              <div class="space-y-4">
                <template x-for="(section, sIndex) in form.sections" :key="section.id || sIndex">
                  <div class="border border-gray-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                    <!-- Section Header (collapsible) -->
                    <div
                      class="bg-gray-50 dark:bg-slate-800/60 p-4 flex items-center gap-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
                      @click="toggleSection(sIndex)"
                      draggable="true"
                      @dragstart="handleSectionDragStart(sIndex, $event)"
                      @dragend="handleSectionDragEnd($event)"
                      @dragover="handleSectionDragOver(sIndex, $event)"
                      @drop="handleSectionDrop(sIndex, $event)"
                      :class="dragOverSectionIndex === sIndex ? 'border-2 border-teal-500' : ''"
                    >
                      <!-- Drag Handle -->
                      <div class="cursor-grab active:cursor-grabbing text-gray-300 dark:text-slate-600 hover:text-teal-500 transition-colors" @click.stop title="{{ $isAr ? 'اسحب لإعادة الترتيب' : 'Drag to reorder' }}">
                        <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                      </div>
                      <!-- Section Icon -->
                      <div class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-900 shadow-sm border border-gray-100 dark:border-slate-750 shrink-0" x-html="getSectionIconHtml(section.icon)"></div>

                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="font-bold text-gray-700 dark:text-white truncate" x-text="section.title || '{{ $isAr ? 'قسم' : 'Section' }} ' + (sIndex + 1)"></span>
                          <span class="text-xs font-bold text-gray-400 dark:text-slate-500 whitespace-nowrap" x-text="'(' + (section.questions ? section.questions.length : 0) + ' {{ $isAr ? 'أسئلة' : 'Q' }})'"></span>
                        </div>
                        <p x-show="section.description" class="text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5" x-text="section.description"></p>
                      </div>

                      <div class="flex items-center gap-1 shrink-0">
                        <!-- Move Up -->
                        <button type="button" @click.stop="moveSection(sIndex, -1)" :disabled="sIndex === 0" class="p-1.5 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="chevron-up" class="w-4 h-4"></i>
                        </button>
                        <!-- Move Down -->
                        <button type="button" @click.stop="moveSection(sIndex, 1)" :disabled="sIndex === form.sections.length - 1" class="p-1.5 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                        <!-- Delete Section -->
                        <button type="button" @click.stop="form.sections.splice(sIndex, 1)" class="p-1.5 text-gray-400 hover:text-red-500 cursor-pointer rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-all">
                          <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                      </div>
                      <!-- Expand/Collapse Chevron -->
                      <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400 transition-transform duration-200" :class="expandedSections[sIndex] ? 'rotate-180' : ''"></i>
                    </div>

                    <!-- Section Body (collapsible content) -->
                    <div x-show="expandedSections[sIndex]" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="p-4 space-y-4 bg-white dark:bg-slate-900 border-t border-gray-150 dark:border-slate-800">

                      <!-- Section Title & Description -->
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1.5 relative group">
                            {{ $isAr ? 'عنوان القسم' : 'Section Title' }}
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-400 text-[8px] font-bold cursor-help ml-1 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors align-middle" title="{{ $isAr ? 'مثال: قسم الاستقبال، قسم الطبيب، قسم الخدمة التمريضية' : 'Example: Reception Section, Doctor Section, Nursing Section' }}">?</span>
                          </label>
                          <input type="text" x-model="section.title" class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'عنوان القسم...' : 'Section Title...' }}">
                        </div>
                        <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-1.5">{{ $isAr ? 'وصف القسم (اختياري)' : 'Section Description' }}</label>
                          <input type="text" x-model="section.description" class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'وصف القسم...' : 'Section Description...' }}">
                        </div>
                      </div>

                      <!-- Section Icon Picker -->
                      <div>
                          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2 relative group">
                            {{ $isAr ? 'اختيار أيقونة القسم' : 'Section Icon' }}
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-400 text-[8px] font-bold cursor-help ml-1 group-hover:bg-teal-100 dark:group-hover:bg-teal-950/30 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors align-middle" title="{{ $isAr ? 'اختر أيقونة تعبر عن محتوى القسم لتظهر للمريض أثناء التعبئة' : 'Choose an icon that represents the section content to show to patients while filling the survey' }}">?</span>
                          </label>
                        <div class="flex flex-wrap gap-2">
                          <template x-for="si in sectionIcons" :key="si.id">
                            <button type="button" @click="section.icon = si.id" class="p-3 rounded-xl border-2 transition-all cursor-pointer" :class="section.icon === si.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/30' : 'border-gray-200 dark:border-slate-750 hover:border-gray-300 dark:hover:border-slate-650'">
                              <div x-html="getIconHtml(si.icon)" class="w-5 h-5" :class="section.icon === si.id ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-slate-400'"></div>
                            </button>
                          </template>
                        </div>
                      </div>

                      <!-- Questions List -->
                      <div class="space-y-3">
                        <div class="flex items-center justify-between">
                          <h4 class="font-bold text-gray-600 dark:text-slate-350 text-sm">{{ $isAr ? 'الأسئلة' : 'Questions' }} (<span x-text="section.questions ? section.questions.length : 0"></span>)</h4>
                          <button type="button" @click="addQuestion(sIndex)" class="flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                            <i data-lucide="plus" class="w-3 h-3"></i>
                            {{ $isAr ? 'إضافة سؤال' : 'Add Question' }}
                          </button>
                        </div>

                        <template x-for="(question, qIndex) in section.questions" :key="question.id || qIndex">
                          <div class="bg-gray-50 dark:bg-slate-900/60 border border-transparent dark:border-slate-800 rounded-xl p-4 space-y-3 relative group">
                            <div class="flex items-start gap-3">
                              <!-- Question Number -->
                              <div class="w-7 h-7 bg-teal-100 dark:bg-teal-950/30 text-teal-700 dark:text-teal-400 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 mt-0.5" x-text="qIndex + 1"></div>

                              <div class="flex-1 space-y-3">
                                <!-- Question Type Picker with Icons -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                  <template x-for="qt in questionTypes" :key="qt.id">
                                    <button type="button" @click="question.type = qt.id" class="p-2 rounded-lg border-2 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer" :class="question.type === qt.id ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' : 'border-gray-200 dark:border-slate-750 text-gray-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-600'">
                                      <div x-html="getQuestionTypeIcon(qt.id)" class="w-3.5 h-3.5 shrink-0"></div>
                                      <span x-text="qt.label"></span>
                                    </button>
                                  </template>
                                </div>

                                <!-- Question Title -->
                                <input type="text" x-model="question.title" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'نص السؤال...' : 'Question text...' }}">

                                <!-- Question Description (NEW) -->
                                <input type="text" x-model="question.description" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-bold" placeholder="{{ $isAr ? 'وصف السؤال (اختياري)...' : 'Question description (optional)...' }}">

                                <!-- Options (only for multiple_choice) -->
                                <div x-show="question.type === 'multiple_choice'" class="space-y-2 mt-3 p-3 bg-gray-50 dark:bg-slate-800/40 rounded-xl">
                                  <label class="text-xs font-bold text-gray-500">{{ $isAr ? 'خيارات الإجابة:' : 'Options:' }}</label>
                                  <template x-for="(opt, optIndex) in question.options" :key="optIndex">
                                    <div class="flex gap-2">
                                      <input type="text" x-model="opt.label" @input="opt.value = opt.label" class="flex-1 px-3 py-1.5 text-xs font-bold bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-md outline-none focus:border-teal-500" placeholder="{{ $isAr ? 'الخيار...' : 'Option...' }}">
                                      <button type="button" @click="question.options.splice(optIndex, 1)" class="text-red-400 hover:text-red-500"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    </div>
                                  </template>
                                  <button type="button" @click="if(!question.options) question.options = []; question.options.push({label:'', value:''})" class="text-xs font-bold text-teal-600 mt-1 cursor-pointer">{{ $isAr ? '+ إضافة خيار' : '+ Add Option' }}</button>
                                </div>

                                <!-- Required Toggle -->
                                <div class="flex items-center gap-3">
                                  <button type="button" @click="question.required = !question.required" class="w-10 h-5 rounded-full transition-all relative cursor-pointer" :class="question.required ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                                    <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow-sm transition-all" :class="question.required ? '{{ $isRtl ? 'right-5' : 'left-5' }}' : '{{ $isRtl ? 'right-0.5' : 'left-0.5' }}'"></div>
                                  </button>
                                  <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ $isAr ? 'إجابة مطلوبة' : 'Required' }}</span>
                                </div>
                              </div>

                              <!-- Question Actions (Move Up/Down/Delete) -->
                              <div class="flex flex-col items-center gap-1 shrink-0">
                                <button type="button" @click="moveQuestion(sIndex, qIndex, -1)" :disabled="qIndex === 0" class="p-1 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer" title="{{ $isAr ? 'تحريك لأعلى' : 'Move Up' }}">
                                  <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                </button>
                                <button type="button" @click="moveQuestion(sIndex, qIndex, 1)" :disabled="qIndex === section.questions.length - 1" class="p-1 text-gray-400 hover:text-teal-600 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer" title="{{ $isAr ? 'تحريك لأسفل' : 'Move Down' }}">
                                  <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </button>
                                <button type="button" @click="section.questions.splice(qIndex, 1)" class="p-1 text-gray-400 hover:text-red-500 cursor-pointer" title="{{ $isAr ? 'حذف السؤال' : 'Delete Question' }}">
                                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                              </div>
                            </div>
                          </div>
                        </template>
                      </div>

                    </div>
                  </div>
                </template>
              </div>

            </div>

          </div>
        </div>

            <!-- Live Preview Modal -->
        <div x-show="showPreview" style="display: none;" class="fixed inset-0 z-[70] bg-slate-950/70 backdrop-blur-sm flex items-start justify-center p-2 overflow-y-auto" @keydown.escape.window="showPreview = false">
          <div @click.away="showPreview = false" class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-2xl w-full my-4 sm:my-8 text-start shadow-2xl transition-all animate-scale-in">
            <div class="p-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
              <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                <i data-lucide="eye" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                {{ $isAr ? 'معاينة الاستبيان' : 'Survey Preview' }}
              </h3>
              <button type="button" @click="showPreview = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300">
                <i data-lucide="x" class="w-5 h-5"></i>
              </button>
            </div>
            <div class="p-6 space-y-6">
              <!-- Survey Title in Preview -->
              <div class="text-center">
                <h2 class="text-2xl font-black text-gray-900 dark:text-white" x-text="form.title || '{{ $isAr ? '(بدون عنوان)' : '(Untitled)' }}'"></h2>
                <p x-show="form.description" class="text-sm text-gray-500 dark:text-slate-400 mt-2" x-text="form.description"></p>
              </div>

              <!-- Sections in Preview -->
              <template x-for="(section, sIdx) in form.sections" :key="sIdx">
                <div class="space-y-4">
                  <div class="flex items-center gap-3 border-b border-gray-100 dark:border-slate-800 pb-3">
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-teal-100 dark:bg-teal-950/30 text-teal-600 dark:text-teal-400" x-html="getSectionIconHtml(section.icon)"></div>
                    <div>
                      <h4 class="font-bold text-gray-800 dark:text-white" x-text="section.title || '{{ $isAr ? 'قسم' : 'Section' }} ' + (sIdx + 1)"></h4>
                      <p x-show="section.description" class="text-xs text-gray-500 dark:text-slate-400" x-text="section.description"></p>
                    </div>
                  </div>

                  <template x-for="(question, qIdx) in section.questions" :key="qIdx">
                    <div class="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-4 space-y-3">
                      <div class="flex items-start gap-2">
                        <span class="text-xs font-bold text-teal-600 dark:text-teal-400 shrink-0 mt-0.5" x-text="(qIdx + 1) + '.'"></span>
                        <div>
                          <p class="text-sm font-bold text-gray-800 dark:text-white" x-text="question.title"></p>
                          <p x-show="question.description" class="text-xs text-gray-500 dark:text-slate-400 mt-0.5" x-text="question.description"></p>
                        </div>
                      </div>
                      <!-- Star Rating Preview -->
                      <div x-show="question.type === 'stars'" class="flex gap-1" x-html="getPreviewStars(question)"></div>
                      <!-- Emoji Preview -->
                      <div x-show="question.type === 'emoji'" class="flex gap-2 text-xl">
                        <span class="opacity-50">😡</span> <span class="opacity-50">😕</span> <span class="opacity-100 scale-110">😐</span> <span class="opacity-50">😊</span> <span class="opacity-50">😍</span>
                      </div>
                      <!-- Yes/No Preview -->
                      <div x-show="question.type === 'yes_no'" class="flex gap-3">
                        <span class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-sm font-bold text-gray-600 dark:text-slate-300">{{ $isAr ? 'نعم' : 'Yes' }}</span>
                        <span class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-sm font-bold text-gray-600 dark:text-slate-300">{{ $isAr ? 'لا' : 'No' }}</span>
                      </div>
                      <!-- NPS Preview -->
                      <div x-show="question.type === 'nps'" class="flex gap-1">
                        <template x-for="n in 11">
                          <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold border border-gray-200 dark:border-slate-700 text-gray-500" x-text="n - 1"></span>
                        </template>
                      </div>
                      <!-- Text Preview -->
                      <div x-show="question.type === 'text'" class="border border-gray-200 dark:border-slate-700 rounded-lg p-3 text-sm text-gray-400 italic">
                        {{ $isAr ? '[مكان إدخال النص]' : '[Text input]' }}
                      </div>
                      <!-- Required Badge -->
                      <span x-show="question.required" class="text-[10px] text-red-500 font-bold">{{ $isAr ? '* مطلوب' : '* Required' }}</span>
                    </div>
                  </template>
                </div>
              </template>

              <!-- Empty State -->
              <div x-show="form.sections.length === 0" class="text-center py-10">
                <i data-lucide="file-text" class="w-16 h-16 text-gray-200 dark:text-slate-700 mx-auto mb-3"></i>
                <p class="text-gray-500 dark:text-slate-400 font-bold">{{ $isAr ? 'أضف أقساماً وأسئلة لرؤية المعاينة' : 'Add sections and questions to see the preview' }}</p>
              </div>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-slate-800 text-center">
              <button type="button" @click="showPreview = false" class="px-6 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm transition-all cursor-pointer">
                {{ $isAr ? 'إغلاق المعاينة' : 'Close Preview' }}
              </button>
            </div>
          </div>
        </div>

        <div class="p-6 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between sticky bottom-0 bg-white dark:bg-slate-900 rounded-b-2xl z-10">
          <div class="flex items-center gap-2">
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
            $secData['icon'] = $sec->icon ?? 'clipboard-check';
            $secData['questions'] = $sec->questions->map(function($q) {
                $qData = $q->toArray();
                $qData['options'] = is_string($q->options) ? json_decode($q->options, true) : ($q->options ?? []);
                $qData['description'] = $q->description ?? '';
                return $qData;
            });
            return $secData;
        });
        return $data;
    })->values();
  @endphp

  <script id="surveys-json" type="application/json">@json($surveysJson)</script>