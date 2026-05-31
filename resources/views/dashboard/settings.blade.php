@extends('layouts.dashboard')

@section('title', 'إعدادات النظام - MedSurvey Pro')

@php
  $hospital = $settings['hospital'] ?? [];
  $departments = $settings['departments'] ?? [];
  $ageGroups = $settings['ageGroups'] ?? [];
  $visitTypes = $settings['visitTypes'] ?? [];
  $surveySettings = $settings['surveySettings'] ?? [];
  $appearance = $settings['appearance'] ?? [];
  $backupSettings = $settings['backupSettings'] ?? [];
  $currentUser = auth()->user();
  $isSuperAdmin = $currentUser?->role === 'super_admin';
@endphp

@section('dashboard')
<div
  x-data="settingsManager()"
  x-init="init()"
  class="animate-fade-in text-start"
>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-slate-800/80 pb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-linear-to-br from-teal-500 to-teal-600 dark:from-teal-600 dark:to-teal-800 rounded-xl flex items-center justify-center shadow-lg shadow-teal-100 dark:shadow-none">
          <i data-lucide="settings" class="w-5 h-5 text-white"></i>
        </div>
        <div class="flex flex-col gap-0.5">
          <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">إعدادات النظام</h2>
          <p class="text-xs text-gray-500 dark:text-slate-400">تخصيص الهوية البصرية، بيانات المستشفى، إعدادات الاستبيان، وسياسة النسخ الاحتياطي.</p>
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-600 dark:border-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400">
        {{ session('success') }}
      </div>
    @endif

    @if($errors->any())
      <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-600 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">
        <ul class="list-disc list-inside space-y-1">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Toast notification -->
    <template x-if="toast.show">
      <div class="mb-6 rounded-xl border p-4 text-sm font-bold flex items-center gap-2 transition-all"
        :class="toast.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400' : 'border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400'">
        <i :class="toast.type === 'success' ? 'data-lucide-check-circle-2' : 'data-lucide-alert-circle'" class="w-5 h-5 shrink-0"></i>
        <span x-text="toast.message"></span>
      </div>
    </template>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Sidebar Tabs -->
      <div class="lg:w-64 shrink-0">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden sticky top-24">
          <template x-for="tab in tabs" :key="tab.id">
            <button
              type="button"
              @click="activeTab = tab.id; $nextTick(() => { if (window.lucide) lucide.createIcons(); })"
              :class="activeTab === tab.id ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-r-4 border-teal-500' : 'text-gray-600 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800'"
              class="w-full flex items-center gap-3 px-4 py-3 text-right transition-all cursor-pointer"
            >
              <i :data-lucide="tab.icon" class="w-5 h-5"
                :class="activeTab === tab.id ? 'text-teal-600 dark:text-teal-400' : 'text-gray-400 dark:text-slate-500'"></i>
              <span class="font-bold text-sm" x-text="tab.label"></span>
            </button>
          </template>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <form action="{{ route('dashboard.settings.update') }}" method="POST" enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <!-- ========== HOSPITAL TAB ========== -->
          <section x-show="activeTab === 'hospital'" class="space-y-6" x-cloak>
            <!-- Basic Info -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="building-2" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                بيانات المستشفى العامة
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Logo -->
                <div class="md:col-span-2 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-2xl p-6 bg-gray-50/50 dark:bg-slate-800/40 flex flex-col sm:flex-row items-center gap-6 mb-2">
                  <div class="relative group w-24 h-24 bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl flex items-center justify-center p-2 shadow-sm">
                    <template x-if="hospitalForm.logo">
                      <img :src="hospitalForm.logo" alt="Hospital Logo" class="max-w-full max-h-full object-contain rounded">
                    </template>
                    <template x-if="!hospitalForm.logo">
                      <i data-lucide="building-2" class="w-10 h-10 text-gray-300 dark:text-slate-600"></i>
                    </template>
                    <template x-if="hospitalForm.logo">
                      <button type="button" @click="hospitalForm.logo = ''; $refs.logoBase64.value = ''"
                        class="absolute -top-2 -left-2 bg-red-100 dark:bg-red-950/50 hover:bg-red-200 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 p-1.5 rounded-lg shadow transition-colors cursor-pointer"
                        title="إزالة الشعار">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                      </button>
                    </template>
                  </div>
                  <div class="flex-1 space-y-3 text-start w-full">
                    <label class="block text-sm font-bold text-gray-700 dark:text-slate-300">شعار المستشفى المشغل</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                      <label class="cursor-pointer bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 border-2 border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 font-bold px-4 py-2.5 rounded-xl text-center text-sm transition-all flex items-center justify-center gap-2 shadow-sm shrink-0">
                        <i data-lucide="plus" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                        رفع ملف الشعار
                        <input type="file" accept="image/*" @change="handleLogoFile($event)" class="hidden">
                      </label>
                      <div class="flex-1">
                        <input type="text" x-model="hospitalForm.logo"
                          placeholder="أو أدخل رابط الشعار الإلكتروني المباشر..."
                          class="w-full px-4 py-2.5 text-sm rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 placeholder-gray-400 dark:placeholder-slate-600 text-gray-900 dark:text-white text-start">
                      </div>
                    </div>
                    <input type="hidden" name="hospital[logo]" x-ref="logoBase64" :value="hospitalForm.logo">
                    <p class="text-[10px] text-gray-400 dark:text-slate-500">يمكنك رفع صورة مباشرة (JPEG, PNG, SVG) أو كتابة رابط الشعار مباشرة. المقاس الموصى به: 250×80 بكسل.</p>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">اسم المستشفى بالكامل<span class="text-red-500 mr-1">*</span></label>
                  <input type="text" x-model="hospitalForm.name" name="hospital[name]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium">
                </div>
                <div>
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">الاسم المختصر<span class="text-red-500 mr-1">*</span></label>
                  <input type="text" x-model="hospitalForm.shortName" name="hospital[shortName]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium">
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">نص وصف الجهة<span class="text-red-500 mr-1">*</span></label>
                  <input type="text" x-model="hospitalForm.operatingTitle" name="hospital[operatingTitle]"
                    placeholder="المستشفى المشغل"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium">
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">الرسالة الترحيبية<span class="text-red-500 mr-1">*</span></label>
                  <textarea x-model="hospitalForm.welcomeMessage" name="hospital[welcomeMessage]"
                    rows="2" placeholder="أهلاً بك في مستشفى ...، نسعد بمشاركتك رأيك..."
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"></textarea>
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">وصف المستشفى</label>
                  <textarea x-model="hospitalForm.description" name="hospital[description]"
                    rows="3"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"></textarea>
                </div>
              </div>
            </div>

            <!-- Contact Info -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="phone" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                معلومات الاتصال
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    العنوان<span class="text-red-500 mr-1">*</span>
                  </label>
                  <input type="text" x-model="hospitalForm.address" name="hospital[address]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium">
                </div>
                <div>
                  <label class="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    <i data-lucide="phone" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    رقم الهاتف<span class="text-red-500 mr-1">*</span>
                  </label>
                  <input type="tel" x-model="hospitalForm.phone" name="hospital[phone]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium" dir="ltr">
                </div>
                <div>
                  <label class="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    <i data-lucide="mail" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    البريد الإلكتروني<span class="text-red-500 mr-1">*</span>
                  </label>
                  <input type="email" x-model="hospitalForm.email" name="hospital[email]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium" dir="ltr">
                </div>
                <div>
                  <label class="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    <i data-lucide="globe" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    الموقع الإلكتروني<span class="text-red-500 mr-1">*</span>
                  </label>
                  <input type="url" x-model="hospitalForm.website" name="hospital[website]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium" dir="ltr">
                </div>
                <div>
                  <label class="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    <i data-lucide="clock" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    ساعات العمل<span class="text-red-500 mr-1">*</span>
                  </label>
                  <input type="text" x-model="hospitalForm.workingHours" name="hospital[workingHours]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium">
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" class="flex items-center gap-2 px-6 py-3 bg-linear-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer">
                <i data-lucide="save" class="w-5 h-5"></i>
                حفظ التغييرات
              </button>
            </div>
          </section>

          <!-- ========== DEPARTMENTS TAB ========== -->
          <section x-show="activeTab === 'departments'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                  <i data-lucide="users" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                  إدارة الأقسام (<span x-text="departments.length"></span>)
                </h3>
                @if($isSuperAdmin)
                  <button type="button" @click="openAddModal('department')"
                    class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    إضافة قسم
                  </button>
                @endif
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="(dept, index) in departments" :key="dept.id">
                  <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                    <div class="w-4 h-4 rounded-full shrink-0" :style="{ backgroundColor: dept.color }"></div>
                    <span class="flex-1 font-medium truncate"
                      :class="dept.isActive ? 'text-gray-800 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500 line-through'"
                      x-text="dept.name"></span>
                    @if($isSuperAdmin)
                      <button type="button" @click="openEditModal('department', index)"
                        class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-200 dark:hover:bg-blue-900/40 transition-colors cursor-pointer"
                        title="تعديل">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="toggleItemActive('departments', index)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                        :class="dept.isActive ? 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400' : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500'"
                        :title="dept.isActive ? 'تعطيل' : 'تفعيل'">
                        <i x-show="dept.isActive" data-lucide="check" class="w-4 h-4"></i>
                        <i x-show="!dept.isActive" data-lucide="x" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="confirmDeleteItem('department', dept.id, dept.name)"
                        class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-200 dark:hover:bg-red-900/40 transition-colors cursor-pointer"
                        title="حذف">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    @endif
                  </div>
                </template>
              </div>
            </div>
          </section>

          <!-- ========== AGE GROUPS TAB ========== -->
          <section x-show="activeTab === 'age-groups'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                  <i data-lucide="calendar" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                  الفئات العمرية (<span x-text="ageGroups.length"></span>)
                </h3>
                @if($isSuperAdmin)
                  <button type="button" @click="openAddModal('ageGroup')"
                    class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    إضافة فئة
                  </button>
                @endif
              </div>

              <div class="space-y-2">
                <template x-for="(item, index) in ageGroups" :key="item.id">
                  <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                    <i data-lucide="calendar" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    <span class="flex-1 font-medium truncate"
                      :class="item.isActive ? 'text-gray-800 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500 line-through'"
                      x-text="item.label"></span>
                    @if($isSuperAdmin)
                      <button type="button" @click="openEditModal('ageGroup', index)"
                        class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-200 dark:hover:bg-blue-900/40 transition-colors cursor-pointer" title="تعديل">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="toggleItemActive('ageGroups', index)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                        :class="item.isActive ? 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400' : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500'"
                        :title="item.isActive ? 'تعطيل' : 'تفعيل'">
                        <i x-show="item.isActive" data-lucide="check" class="w-4 h-4"></i>
                        <i x-show="!item.isActive" data-lucide="x" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="confirmDeleteItem('ageGroup', item.id, item.label)"
                        class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-200 dark:hover:bg-red-900/40 transition-colors cursor-pointer" title="حذف">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    @endif
                  </div>
                </template>
              </div>
            </div>
          </section>

          <!-- ========== VISIT TYPES TAB ========== -->
          <section x-show="activeTab === 'visit-types'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                  <i data-lucide="clipboard-list" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                  أنواع الزيارة (<span x-text="visitTypes.length"></span>)
                </h3>
                @if($isSuperAdmin)
                  <button type="button" @click="openAddModal('visitType')"
                    class="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    إضافة نوع
                  </button>
                @endif
              </div>

              <div class="space-y-2">
                <template x-for="(item, index) in visitTypes" :key="item.id">
                  <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                    <i data-lucide="clipboard-list" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
                    <span class="flex-1 font-medium truncate"
                      :class="item.isActive ? 'text-gray-800 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500 line-through'"
                      x-text="item.label"></span>
                    @if($isSuperAdmin)
                      <button type="button" @click="openEditModal('visitType', index)"
                        class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-200 dark:hover:bg-blue-900/40 transition-colors cursor-pointer" title="تعديل">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="toggleItemActive('visitTypes', index)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer"
                        :class="item.isActive ? 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400' : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500'"
                        :title="item.isActive ? 'تعطيل' : 'تفعيل'">
                        <i x-show="item.isActive" data-lucide="check" class="w-4 h-4"></i>
                        <i x-show="!item.isActive" data-lucide="x" class="w-4 h-4"></i>
                      </button>
                      <button type="button" @click="confirmDeleteItem('visitType', item.id, item.label)"
                        class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-200 dark:hover:bg-red-900/40 transition-colors cursor-pointer" title="حذف">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    @endif
                  </div>
                </template>
              </div>
            </div>
          </section>

          <!-- ========== SURVEY TAB ========== -->
          <section x-show="activeTab === 'survey'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="settings" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                خيارات تقديم الاستبيان
              </h3>
              <div class="space-y-4">
                <!-- allowAnonymous -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">السماح بالمشاركات المجهولة</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">السماح للمرضى بتقديم آرائهم دون إدخال بيانات شخصية</p>
                  </div>
                  <input type="hidden" name="surveySettings[allowAnonymous]" :value="surveySettings.allowAnonymous ? '1' : '0'">
                  <button type="button" @click="surveySettings.allowAnonymous = !surveySettings.allowAnonymous"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="surveySettings.allowAnonymous ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="surveySettings.allowAnonymous ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- requireAllQuestions -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">طلب الإجابة على كل الأسئلة إلزامياً</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">لن يتمكن المريض من إرسال الاستبيان إلا بعد إجابة جميع الأسئلة</p>
                  </div>
                  <input type="hidden" name="surveySettings[requireAllQuestions]" :value="surveySettings.requireAllQuestions ? '1' : '0'">
                  <button type="button" @click="surveySettings.requireAllQuestions = !surveySettings.requireAllQuestions"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="surveySettings.requireAllQuestions ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="surveySettings.requireAllQuestions ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- requireName / requirePhone (shown only when NOT anonymous) -->
                <template x-if="!surveySettings.allowAnonymous">
                  <div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                      <div>
                        <p class="font-bold text-gray-700 dark:text-slate-200">طلب اسم المريض في الحالات غير المجهولة</p>
                        <p class="text-sm text-gray-500 dark:text-slate-400">إلزام المريض بإدخال اسمه عند تقديم الاستبيان</p>
                      </div>
                      <input type="hidden" name="surveySettings[requireName]" :value="surveySettings.requireName ? '1' : '0'">
                      <button type="button" @click="surveySettings.requireName = !surveySettings.requireName"
                        class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                        :class="surveySettings.requireName ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                        <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                          :class="surveySettings.requireName ? 'right-7' : 'right-0.5'"></div>
                      </button>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl mt-4">
                      <div>
                        <p class="font-bold text-gray-700 dark:text-slate-200">طلب هاتف المريض في الحالات غير المجهولة</p>
                        <p class="text-sm text-gray-500 dark:text-slate-400">إلزام المريض بإدخال رقم هاتفه عند تقديم الاستبيان</p>
                      </div>
                      <input type="hidden" name="surveySettings[requirePhone]" :value="surveySettings.requirePhone ? '1' : '0'">
                      <button type="button" @click="surveySettings.requirePhone = !surveySettings.requirePhone"
                        class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                        :class="surveySettings.requirePhone ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                        <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                          :class="surveySettings.requirePhone ? 'right-7' : 'right-0.5'"></div>
                      </button>
                    </div>
                  </div>
                </template>

                <!-- showProgressBar -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">إظهار شريط تقدم الاستبيان للمرضى</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">عرض شريط يوضح مدى التقدم في الأسئلة أثناء التعبئة</p>
                  </div>
                  <input type="hidden" name="surveySettings[showProgressBar]" :value="surveySettings.showProgressBar ? '1' : '0'">
                  <button type="button" @click="surveySettings.showProgressBar = !surveySettings.showProgressBar"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="surveySettings.showProgressBar ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="surveySettings.showProgressBar ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- enableThankYouPage -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">تفعيل صفحة شكر منفصلة عند الانتهاء</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">عرض صفحة شكر مستقلة بعد إرسال الاستبيان</p>
                  </div>
                  <input type="hidden" name="surveySettings[enableThankYouPage]" :value="surveySettings.enableThankYouPage ? '1' : '0'">
                  <button type="button" @click="surveySettings.enableThankYouPage = !surveySettings.enableThankYouPage"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="surveySettings.enableThankYouPage ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="surveySettings.enableThankYouPage ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- Thank you message -->
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <label class="block font-bold text-gray-700 dark:text-slate-200 mb-2">رسالة الشكر للمرضى عند الإتمام</label>
                  <textarea x-model="surveySettings.thankYouMessage" name="surveySettings[thankYouMessage]"
                    rows="3"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
                    placeholder="شكراً لمشاركتكم! رأيكم يساعدنا في تحسين خدماتنا."></textarea>
                </div>
              </div>
            </div>
          </section>

          <!-- ========== APPEARANCE TAB ========== -->
          <section x-show="activeTab === 'appearance'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="palette" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                تخصيص المظهر
              </h3>
              <div class="space-y-6">
                <!-- Primary Color -->
                <div>
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-3">اللون الأساسي</label>
                  <input type="hidden" name="appearance[primaryColor]" :value="appearance.primaryColor">
                  <div class="flex flex-wrap gap-3">
                    <template x-for="color in colorOptions" :key="color">
                      <button type="button" @click="appearance.primaryColor = color"
                        class="w-12 h-12 rounded-xl transition-all cursor-pointer"
                        :class="appearance.primaryColor === color ? 'ring-4 ring-offset-2 ring-teal-500 scale-110' : 'hover:scale-105'"
                        :style="{ backgroundColor: color }"></button>
                    </template>
                  </div>
                </div>

                <!-- Secondary Color -->
                <div>
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-3">اللون الثانوي</label>
                  <input type="hidden" name="appearance[secondaryColor]" :value="appearance.secondaryColor">
                  <div class="flex flex-wrap gap-3">
                    <template x-for="color in colorOptions" :key="color">
                      <button type="button" @click="appearance.secondaryColor = color"
                        class="w-12 h-12 rounded-xl transition-all cursor-pointer"
                        :class="appearance.secondaryColor === color ? 'ring-4 ring-offset-2 ring-teal-500 scale-110' : 'hover:scale-105'"
                        :style="{ backgroundColor: color }"></button>
                    </template>
                  </div>
                </div>

                <!-- Language Toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">تفعيل أيقونة تغيير اللغة</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">إظهار أو إخفاء زر تبديل اللغة (العربية / English) في جميع شاشات النظام</p>
                  </div>
                  <input type="hidden" name="appearance[showLanguageToggle]" :value="appearance.showLanguageToggle !== false ? '1' : '0'">
                  <button type="button" @click="appearance.showLanguageToggle = appearance.showLanguageToggle !== false ? false : true"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="appearance.showLanguageToggle !== false ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="appearance.showLanguageToggle !== false ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- Color Preview -->
                <div>
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">معاينة الألوان</label>
                  <div class="p-6 rounded-2xl bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800/85">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                      <div class="flex gap-2">
                        <div class="w-16 h-16 rounded-xl shadow-lg shrink-0" :style="{ backgroundColor: appearance.primaryColor }"></div>
                        <div class="w-16 h-16 rounded-xl shadow-lg shrink-0" :style="{ backgroundColor: appearance.secondaryColor }"></div>
                      </div>
                      <div class="flex-1 p-4 rounded-xl text-white text-start" :style="{ backgroundColor: appearance.primaryColor }">
                        <p class="font-bold text-white">نموذج تجريبي</p>
                        <p class="text-sm opacity-80">هذا نص تجريبي يوضح كيفية ظهور الألوان في الواجهة</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- ========== BACKUP TAB ========== -->
          <section x-show="activeTab === 'backup'" class="space-y-6" x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
                إعدادات النسخ الاحتياطي التلقائي
              </h3>
              <div class="space-y-4">
                <!-- Schedule -->
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">وقت الجدولة اليومي</label>
                  <input type="time" x-model="backupSettings.schedule" name="backupSettings[schedule]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white">
                  <p class="text-xs text-gray-500 mt-2">الوقت الذي سيتم فيه أخذ النسخة الاحتياطية تلقائياً كل يوم (بصيغة 24 ساعة).</p>
                </div>

                <!-- Retention Days -->
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">مدة الاحتفاظ بالنسخ (بالأيام)</label>
                  <input type="number" min="1" x-model.number="backupSettings.retentionDays" name="backupSettings[retentionDays]"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white">
                  <p class="text-xs text-gray-500 mt-2">سيتم حذف النسخ الأقدم من هذا العدد من الأيام تلقائياً لتوفير المساحة.</p>
                </div>

                <!-- Gzip Compression -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <div>
                    <p class="font-bold text-gray-700 dark:text-slate-200">ضغط النسخ بصيغة GZIP</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">تفعيل هذا الخيار سيقوم بضغط قاعدة البيانات لتوفير المساحة (مستحسن).</p>
                  </div>
                  <input type="hidden" name="backupSettings[compressGzip]" :value="backupSettings.compressGzip ? '1' : '0'">
                  <button type="button" @click="backupSettings.compressGzip = !backupSettings.compressGzip"
                    class="w-14 h-7 rounded-full transition-all relative cursor-pointer shrink-0"
                    :class="backupSettings.compressGzip ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'">
                    <div class="absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all"
                      :class="backupSettings.compressGzip ? 'right-7' : 'right-0.5'"></div>
                  </button>
                </div>

                <!-- Backup Dir -->
                <div class="p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
                  <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">مسار حفظ النسخ الاحتياطية</label>
                  <input type="text" x-model="backupSettings.backupDir" name="backupSettings[backupDir]"
                    placeholder="storage/app/backups"
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-left dir-ltr">
                  <p class="text-xs text-gray-500 mt-2">المسار النسبي من مجلد المشروع (مثال: storage/app/backups) أو مسار مطلق (مثال: C:\backups).</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Global Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-slate-800">
            <button type="submit" class="flex items-center gap-2 px-6 py-3 bg-linear-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer">
              <i data-lucide="save" class="w-5 h-5"></i>
              حفظ جميع التغييرات
            </button>
          </div>

          <!-- Serialized list data for form submission -->
          <template x-for="(dept, i) in departments" :key="dept.id">
            <div>
              <input type="hidden" :name="'departments[' + i + '][id]'" :value="dept.id">
              <input type="hidden" :name="'departments[' + i + '][name]'" :value="dept.name">
              <input type="hidden" :name="'departments[' + i + '][isActive]'" :value="dept.isActive ? '1' : '0'">
              <input type="hidden" :name="'departments[' + i + '][color]'" :value="dept.color || '#0d9488'">
            </div>
          </template>
          <template x-for="(item, i) in ageGroups" :key="item.id">
            <div>
              <input type="hidden" :name="'ageGroups[' + i + '][id]'" :value="item.id">
              <input type="hidden" :name="'ageGroups[' + i + '][label]'" :value="item.label">
              <input type="hidden" :name="'ageGroups[' + i + '][isActive]'" :value="item.isActive ? '1' : '0'">
            </div>
          </template>
          <template x-for="(item, i) in visitTypes" :key="item.id">
            <div>
              <input type="hidden" :name="'visitTypes[' + i + '][id]'" :value="item.id">
              <input type="hidden" :name="'visitTypes[' + i + '][label]'" :value="item.label">
              <input type="hidden" :name="'visitTypes[' + i + '][isActive]'" :value="item.isActive ? '1' : '0'">
            </div>
          </template>
        </form>
      </div>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  <template x-if="editingItem">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 animate-scale-in text-start">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4" x-text="editingItem.id ? getEditTitle() : getAddTitle()"></h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2" x-text="getLabelText()"></label>
            <input type="text" x-model="newItemValue" @keydown.enter="saveEditItem()"
              placeholder="..."
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
              autofocus>
          </div>
          <!-- Color picker (only for department) -->
          <template x-if="editingItem && editingItem.type === 'department'">
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">لون القسم</label>
              <div class="flex flex-wrap gap-2">
                <template x-for="color in colorOptions" :key="color">
                  <button type="button" @click="editingItem.color = color"
                    class="w-8 h-8 rounded-full transition-all cursor-pointer"
                    :class="editingItem.color === color ? 'ring-4 ring-offset-2 ring-teal-500 scale-110' : ''"
                    :style="{ backgroundColor: color }"></button>
                </template>
              </div>
            </div>
          </template>
        </div>
        <div class="flex items-center gap-3 mt-6">
          <button type="button" @click="editingItem = null"
            class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
            إلغاء
          </button>
          <button type="button" @click="saveEditItem()" :disabled="!newItemValue.trim()"
            class="flex-1 px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-colors disabled:bg-gray-300 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:disabled:text-slate-550 disabled:cursor-not-allowed cursor-pointer"
            x-text="editingItem.id ? 'حفظ' : 'إضافة'"></button>
        </div>
      </div>
    </div>
  </template>

  <!-- Delete Confirmation Modal -->
  <template x-if="deleteConfirm">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 animate-scale-in text-start">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-950/30 flex items-center justify-center shrink-0">
            <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">تأكيد الحذف</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400">هذا الإجراء لا يمكن التراجع عنه</p>
          </div>
        </div>
        <p class="text-gray-700 dark:text-slate-300 mb-2">
          هل أنت متأكد من حذف <span class="font-bold" x-text="deleteConfirm.name"></span>؟
        </p>
        <template x-if="deleteConfirm.count > 0">
          <p class="text-sm text-red-600 dark:text-red-400 mb-2">
            <i data-lucide="alert-circle" class="w-4 h-4 inline"></i>
            يوجد <span x-text="deleteConfirm.count"></span> استجابة مرتبطة بهذا العنصر.
          </p>
        </template>
        <div class="flex items-center gap-3 mt-6">
          <button type="button" @click="deleteConfirm = null"
            class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
            إلغاء
          </button>
          <button type="button" @click="executeDelete()"
            class="flex-1 px-4 py-3 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 transition-colors cursor-pointer">
            حذف
          </button>
        </div>
      </div>
    </div>
  </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('settingsManager', () => ({
    activeTab: 'hospital',

    // Tab definitions
    tabs: [
      { id: 'hospital', label: 'بيانات المنشأة', icon: 'building-2' },
      { id: 'departments', label: 'الأقسام', icon: 'users' },
      { id: 'age-groups', label: 'الفئات العمرية', icon: 'calendar' },
      { id: 'visit-types', label: 'أنواع الزيارة', icon: 'clipboard-list' },
      { id: 'survey', label: 'إعدادات الاستبيان', icon: 'settings' },
      { id: 'appearance', label: 'المظهر والهوية', icon: 'palette' },
      { id: 'backup', label: 'النسخ الاحتياطي', icon: 'database' },
    ],

    colorOptions: [
      '#0d9488', '#10b981', '#3b82f6', '#6366f1', '#8b5cf6',
      '#ec4899', '#ef4444', '#f97316', '#f59e0b', '#14b8a6',
      '#06b6d4', '#7c3aed', '#dc2626', '#059669', '#2563eb',
    ],

    // Hospital form
    hospitalForm: {},
    // Survey settings
    surveySettings: {},
    // Appearance
    appearance: {},
    // Backup settings
    backupSettings: {},
    // Lists
    departments: [],
    ageGroups: [],
    visitTypes: [],

    // Toast
    toast: { show: false, message: '', type: 'success' },

    // Editing state
    editingItem: null,
    newItemValue: '',

    // Delete confirmation
    deleteConfirm: null,

    init() {
      this.hospitalForm = @json($hospital);
      this.surveySettings = @json($surveySettings);
      this.appearance = @json($appearance);
      this.backupSettings = @json($backupSettings);
      this.departments = @json($departments);
      this.ageGroups = @json($ageGroups);
      this.visitTypes = @json($visitTypes);
    },

    showToast(message, type = 'success') {
      this.toast = { show: true, message, type };
      setTimeout(() => { this.toast.show = false; }, 3000);
    },

    // Logo file handler
    handleLogoFile(event) {
      const file = event.target.files[0];
      if (!file) return;
      const maxSize = 500 * 1024;
      if (file.size > maxSize) {
        this.showToast('الملف كبير جداً. الحد الأقصى 500 كيلوبايت.', 'error');
        event.target.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        if (typeof e.target.result === 'string') {
          this.hospitalForm.logo = e.target.result;
          this.$refs.logoBase64.value = e.target.result;
        }
      };
      reader.readAsDataURL(file);
    },

    // --- List Management ---
    openAddModal(type) {
      this.editingItem = { type, id: null, value: '', color: '#0d9488' };
      this.newItemValue = '';
    },

    openEditModal(type, index) {
      const list = this[type === 'department' ? 'departments' : type === 'ageGroup' ? 'ageGroups' : 'visitTypes'];
      const item = list[index];
      this.editingItem = { type, id: item.id, value: item.label || item.name, color: item.color || '#0d9488' };
      this.newItemValue = item.label || item.name;
    },

    getEditTitle() {
      const titles = { department: 'تعديل القسم', ageGroup: 'تعديل الفئة العمرية', visitType: 'تعديل نوع الزيارة' };
      return titles[this.editingItem.type] || 'تعديل';
    },

    getAddTitle() {
      const titles = { department: 'إضافة قسم جديد', ageGroup: 'إضافة فئة عمرية جديدة', visitType: 'إضافة نوع زيارة جديد' };
      return titles[this.editingItem.type] || 'إضافة';
    },

    getLabelText() {
      const labels = { department: 'اسم القسم', ageGroup: 'اسم الفئة العمرية', visitType: 'اسم نوع الزيارة' };
      return labels[this.editingItem.type] || 'الاسم';
    },

    saveEditItem() {
      if (!this.newItemValue.trim()) return;
      const { type, id, color } = this.editingItem;
      let listKey, itemKey;
      if (type === 'department') { listKey = 'departments'; itemKey = 'name'; }
      else if (type === 'ageGroup') { listKey = 'ageGroups'; itemKey = 'label'; }
      else { listKey = 'visitTypes'; itemKey = 'label'; }

      if (id) {
        // Edit existing
        this[listKey] = this[listKey].map(item =>
          item.id === id ? { ...item, [itemKey]: this.newItemValue, ...(color ? { color } : {}) } : item
        );
      } else {
        // Add new
        const prefix = type === 'department' ? 'dept' : type === 'ageGroup' ? 'age' : 'vt';
        const newItem = { id: prefix + '-' + Date.now(), [itemKey]: this.newItemValue, isActive: true };
        if (type === 'department') newItem.color = color || '#0d9488';
        this[listKey] = [...this[listKey], newItem];
      }
      this.editingItem = null;
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
    },

    toggleItemActive(listKey, index) {
      const item = this[listKey][index];
      this[listKey][index] = { ...item, isActive: !item.isActive };
    },

    confirmDeleteItem(type, id, name) {
      // Check usage via API
      fetch('{{ route('dashboard.settings.usage-check') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ type, value: name })
      })
      .then(res => res.json())
      .then(data => {
        this.deleteConfirm = { type, id, name, count: data.count || 0 };
      })
      .catch(() => {
        this.deleteConfirm = { type, id, name, count: 0 };
      });
    },

    executeDelete() {
      if (!this.deleteConfirm) return;
      const { type, id } = this.deleteConfirm;
      const listKey = type === 'department' ? 'departments' : type === 'ageGroup' ? 'ageGroups' : 'visitTypes';
      this[listKey] = this[listKey].filter(item => item.id !== id);
      this.deleteConfirm = null;
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
    },
  }));
});
</script>
</div>
@endsection