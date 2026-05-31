@extends('layouts.dashboard')

@section('title', 'النسخ الاحتياطي لقاعدة البيانات - MedSurvey Pro')

@section('dashboard')
@php
  $isAr = app()->getLocale() === 'ar';
  $totalBackups = count($backups);
  $totalSizeMb = collect($backups)->sum('sizeBytes') > 0 ? round(collect($backups)->sum('sizeBytes') / 1024 / 1024, 2) : 0;
  $latestBackup = collect($backups)->sortByDesc('createdAt')->first();
  $config = $config ?? [
    'enabled' => env('DB_BACKUP_ENABLED', false),
    'retentionDays' => 30,
    'schedule' => '03:00',
    'compressGzip' => true,
    'backupDir' => storage_path('app/backups'),
  ];
@endphp

<div x-data="backupsManager()" class="p-4 sm:p-6 space-y-6 text-start animate-fade-in" x-cloak>
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
        <i data-lucide="database" class="w-6 h-6 text-teal-500"></i>
        <span>النسخ الاحتياطي لقاعدة البيانات</span>
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        إدارة النسخ الاحتياطية التلقائية واليدوية لقاعدة البيانات
      </p>
    </div>
    <div class="flex gap-2">
      <button
        @click="refreshBackups()"
        type="button"
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
      >
        <i data-lucide="refresh-ccw" class="w-4 h-4"></i>
        <span>تحديث</span>
      </button>
      <button
        @click="handleCreate()"
        :disabled="creating"
        type="button"
        class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-500/20 cursor-pointer"
      >
        <svg x-show="creating" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <i x-show="!creating" data-lucide="download" class="w-4 h-4"></i>
        <span x-show="!creating">إنشاء نسخة احتياطية</span>
        <span x-show="creating">جاري الإنشاء...</span>
      </button>
    </div>
  </div>

  <!-- Error Alert -->
  <template x-if="error">
    <div class="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-300 text-sm">
      <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
      <span x-text="error"></span>
    </div>
  </template>

  <!-- Success Alert -->
  <template x-if="successMessage">
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-300 text-sm">
      <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
      <span x-text="successMessage"></span>
    </div>
  </template>

  @if (session('success'))
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-300 text-sm">
      <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
      {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-300 text-sm">
      <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
      {{ session('error') }}
    </div>
  @endif

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-teal-100 dark:bg-teal-900/30 rounded-xl">
          <i data-lucide="file-archive" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">إجمالي النسخ</p>
          <p class="text-xl font-bold text-slate-800 dark:text-white">{{ $totalBackups }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
          <i data-lucide="hard-drive" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">الحجم الإجمالي</p>
          <p class="text-xl font-bold text-slate-800 dark:text-white">{{ $totalSizeMb }} MB</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-xl">
          <i data-lucide="calendar" class="w-5 h-5 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">مدة الاحتفاظ</p>
          <p class="text-xl font-bold text-slate-800 dark:text-white">{{ $config['retentionDays'] ?? 30 }} يوم</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 {{ ($config['enabled'] ?? false) ? 'bg-green-100 dark:bg-green-900/30' : 'bg-slate-100 dark:bg-slate-700' }} rounded-xl">
          <i data-lucide="shield" class="w-5 h-5 {{ ($config['enabled'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-slate-400' }}"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">الحالة</p>
          <p class="text-xl font-bold {{ ($config['enabled'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-slate-500' }}">
            {{ ($config['enabled'] ?? false) ? 'نشط' : 'متوقف' }}
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Latest Backup Info -->
  @if($latestBackup)
    <div class="rounded-2xl p-4 flex items-center gap-3 bg-teal-50 dark:bg-teal-900/10 border border-teal-200 dark:border-teal-800">
      <i data-lucide="check-circle-2" class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0"></i>
      <div class="text-sm text-teal-700 dark:text-teal-300">
        <span class="font-semibold">آخر نسخة احتياطية:</span>
        <span>{{ \Carbon\Carbon::parse($latestBackup['createdAt'])->format('Y-m-d H:i') }}</span>
        <span class="mx-2">·</span>
        <span class="font-semibold">{{ round($latestBackup['sizeBytes'] / 1024 / 1024, 2) }} MB</span>
        <span class="mx-2">·</span>
        <span>{{ $latestBackup['filename'] }}</span>
        @if($latestBackup['verified'] ?? false)
          <span class="mx-2">·</span>
          <span class="text-teal-600">✓ تم التحقق</span>
        @endif
      </div>
    </div>
  @endif

  @if(!($config['enabled'] ?? false))
    <div class="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-700 dark:text-amber-300 text-sm">
      <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
      <span>النسخ الاحتياطي التلقائي معطل حالياً. يمكنك تفعيله عبر متغير البيئة</span>
      <code class="mx-1 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 rounded text-xs font-mono">DB_BACKUP_ENABLED=true</code>
    </div>
  @endif

  <!-- Navigation Tabs -->
  <div class="flex border-b border-slate-200 dark:border-slate-800 gap-1 mt-4">
    <button
      @click="activeTab = 'local'"
      :class="activeTab === 'local' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="database" class="w-4 h-4"></i>
      النسخ الاحتياطية للنظام
    </button>
    <button
      @click="activeTab = 'upload'"
      :class="activeTab === 'upload' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="upload" class="w-4 h-4"></i>
      استعادة من ملف محلي (.sql.gz)
    </button>
    <button
      @click="activeTab = 'external'"
      :class="activeTab === 'external' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="hard-drive" class="w-4 h-4"></i>
      استعادة من مجلد خادم آخر
    </button>
  </div>

  <!-- Tab: Local Backups -->
  <div x-show="activeTab === 'local'" class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-lg font-semibold text-slate-800 dark:text-white">قائمة النسخ الاحتياطية</h2>
    </div>

    @if(count($backups) === 0)
      <div class="p-12 text-center">
        <i data-lucide="database" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
        <p class="text-slate-500 dark:text-slate-400">لا توجد نسخ احتياطية بعد</p>
        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
          انقر على "إنشاء نسخة احتياطية" لبدء النسخ الأول
        </p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800">
              <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">اسم الملف</th>
              <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحجم</th>
              <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">تاريخ الإنشاء</th>
              <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحالة</th>
              <th class="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($backups as $backup)
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="p-4">
                  <div class="flex items-center gap-2">
                    <i data-lucide="file-archive" class="w-4 h-4 text-teal-500 shrink-0"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-medium break-all">{{ $backup['filename'] }}</span>
                  </div>
                </td>
                <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap text-center" dir="ltr">{{ round($backup['sizeBytes'] / 1024 / 1024, 2) }} MB</td>
                <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($backup['createdAt'])->format('Y-m-d H:i') }}</td>
                <td class="p-4 whitespace-nowrap" id="sv-{{ $loop->index }}">
                  @if($backup['verified'] ?? false)
                    <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                      <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                      صالحة
                    </span>
                  @else
                    <span class="text-xs text-slate-400 dark:text-slate-500">لم يتم التحقق</span>
                  @endif
                </td>
                <td class="p-4 whitespace-nowrap text-left">
                  <div class="flex items-center justify-end gap-1">
                    <!-- Download -->
                    <button
                      @click="handleDownload('{{ $backup['filename'] }}')"
                      :disabled="downloadingFilename === '{{ $backup['filename'] }}'"
                      class="p-2 text-teal-500 hover:text-teal-600 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="تنزيل ملف النسخة الاحتياطية"
                    >
                      <template x-if="downloadingFilename === '{{ $backup['filename'] }}'">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      </template>
                      <template x-if="downloadingFilename !== '{{ $backup['filename'] }}'">
                        <i data-lucide="download" class="w-4 h-4"></i>
                      </template>
                    </button>

                    <!-- Verify -->
                    <button
                      @click="handleVerify('{{ $backup['filename'] }}', {{ $loop->index }})"
                      :disabled="verifying === '{{ $backup['filename'] }}'"
                      class="p-2 text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="التحقق من الملف"
                    >
                      <svg x-show="verifying === '{{ $backup['filename'] }}'" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <i x-show="verifying !== '{{ $backup['filename'] }}'" data-lucide="file-search" class="w-4 h-4"></i>
                    </button>

                    <!-- Restore -->
                    <button
                      @click="openRestoreModal('{{ $backup['filename'] }}')"
                      :disabled="restoringFilename === '{{ $backup['filename'] }}'"
                      class="p-2 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="استعادة قاعدة البيانات من هذه النسخة"
                    >
                      <svg x-show="restoringFilename === '{{ $backup['filename'] }}'" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <i x-show="restoringFilename !== '{{ $backup['filename'] }}'" data-lucide="upload" class="w-4 h-4"></i>
                    </button>

                    <!-- Delete -->
                    <button
                      @click="openDeleteModal('{{ $backup['filename'] }}')"
                      class="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors cursor-pointer"
                      title="حذف"
                    >
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <!-- Tab: Upload -->
  <div x-show="activeTab === 'upload'" class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-8 text-center space-y-6">
    <div class="max-w-md mx-auto space-y-4">
      <div class="w-16 h-16 bg-teal-50 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mx-auto text-teal-600 dark:text-teal-400 shadow-md">
        <i data-lucide="upload" class="w-8 h-8"></i>
      </div>
      <div class="space-y-2">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white">رفع واستعادة نسخة احتياطية</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
          قم باختيار ملف نسخة احتياطية ينتهي بامتداد <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-teal-600 dark:text-teal-400 text-xs font-mono">.sql.gz</code> من أي مجلد على جهازك وسيتكفل النظام برفعها وفحصها واستعادتها بأمان.
        </p>
      </div>
      <div class="pt-4">
        <form action="{{ route('dashboard.backups.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm" class="inline-block">
          @csrf
          <label class="cursor-pointer inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 transition-all shadow-lg shadow-teal-500/20">
            <i data-lucide="upload" class="w-5 h-5 animate-pulse"></i>
            <span x-show="!uploading">اختر ملف النسخة الاحتياطية</span>
            <span x-show="uploading">جاري قراءة الملف...</span>
            <input
              type="file"
              name="backup_file"
              accept=".sql.gz"
              @change="handleUpload($event)"
              class="hidden"
              :disabled="uploading"
            />
          </label>
        </form>
      </div>
    </div>
  </div>

  <!-- Tab: External Directory -->
  <div x-show="activeTab === 'external'" class="space-y-6">
    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-6 space-y-4">
      <div class="space-y-2">
        <h2 class="text-lg font-bold text-slate-800 dark:text-white">مسار مجلد النسخ الاحتياطية على الخادم</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          أدخل المسار الكامل للمجلد على الخادم ليقوم النظام بفحص الملفات الموجودة بداخله.
        </p>
      </div>
      <div class="flex flex-col sm:flex-row gap-3">
        <input
          type="text"
          x-model="externalDir"
          placeholder="مثال: C:\backups أو /var/backups"
          class="flex-1 px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
        <button
          @click="handleScanExternal()"
          :disabled="scanning"
          class="px-6 py-3 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 disabled:bg-teal-400 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0 cursor-pointer"
        >
          <svg x-show="scanning" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          <i x-show="!scanning" data-lucide="file-search" class="w-4 h-4"></i>
          <span x-text="scanning ? 'جاري الفحص...' : 'فحص المجلد'"></span>
        </button>
      </div>
    </div>

    <template x-if="scanAttempted">
      <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-800">
          <h3 class="text-md font-bold text-slate-800 dark:text-white">الملفات المكتشفة في المجلد</h3>
        </div>

        <template x-if="externalFiles.length === 0">
          <div class="p-12 text-center text-slate-500 dark:text-slate-400">
            <i data-lucide="database" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
            <span>لم يتم العثور على أي ملفات نسخة احتياطية ينتهي اسمها بـ </span>
            <code class="text-teal-500">.sql.gz</code>
            <span> في هذا المجلد.</span>
          </div>
        </template>

        <template x-if="externalFiles.length > 0">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800">
                  <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">اسم الملف</th>
                  <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحجم</th>
                  <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">تاريخ التعديل</th>
                  <th class="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحالة</th>
                  <th class="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">إجراءات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="file in externalFiles" :key="file.fullPath">
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                    <td class="p-4">
                      <div class="flex items-center gap-2">
                        <i data-lucide="file-archive" class="w-4 h-4 text-teal-500 shrink-0"></i>
                        <span class="text-slate-700 dark:text-slate-300 font-medium break-all" x-text="file.filename"></span>
                      </div>
                    </td>
                    <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap" x-text="file.sizeMb + ' MB'"></td>
                    <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap" x-text="file.createdAt"></td>
                    <td class="p-4">
                      <template x-if="!externalVerifications[file.fullPath]">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                          <i data-lucide="alert-circle" class="w-3 h-3"></i>
                          لم يتم التحقق
                        </span>
                      </template>
                      <template x-if="externalVerifications[file.fullPath] && externalVerifications[file.fullPath].valid">
                        <div class="space-y-1">
                          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                            صالحة
                          </span>
                        </div>
                      </template>
                      <template x-if="externalVerifications[file.fullPath] && !externalVerifications[file.fullPath].valid">
                        <div class="space-y-1">
                          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            <i data-lucide="x-circle" class="w-3 h-3"></i>
                            غير صالحة
                          </span>
                        </div>
                      </template>
                    </td>
                    <td class="p-4 whitespace-nowrap">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="handleVerifyExternal(file.fullPath)"
                          :disabled="verifyingExternalPath === file.fullPath"
                          class="px-3 py-1.5 text-xs font-bold text-teal-700 bg-teal-50 hover:bg-teal-100 disabled:opacity-50 dark:text-teal-300 dark:bg-teal-900/20 dark:hover:bg-teal-900/30 rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                        >
                          <svg x-show="verifyingExternalPath === file.fullPath" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                          <i x-show="verifyingExternalPath !== file.fullPath" data-lucide="file-search" class="w-3.5 h-3.5"></i>
                          التحقق من الملف
                        </button>
                        <button
                          @click="handleRestoreExternal(file.fullPath, file.filename)"
                          :disabled="restoringFilename === file.filename || !(externalVerifications[file.fullPath]?.valid)"
                          class="px-4 py-1.5 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 disabled:cursor-not-allowed rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                        >
                          <svg x-show="restoringFilename === file.filename" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                          <i x-show="restoringFilename !== file.filename" data-lucide="upload" class="w-3.5 h-3.5"></i>
                          استعادة
                        </button>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </template>
  </div>

  <!-- Info note -->
  <div class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-500 dark:text-slate-400">
    <i data-lucide="clock" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <div>
      <p class="font-medium text-slate-700 dark:text-slate-300 mb-1">معلومات</p>
      <ul class="list-disc list-inside space-y-1">
        <li>يتم تشغيل النسخ الاحتياطي التلقائي يومياً في الساعة <span dir="ltr">{{ $config['schedule'] ?? '03:00' }}</span></li>
        <li>يتم الاحتفاظ بالنسخ لمدة {{ $config['retentionDays'] ?? 30 }} يوماً قبل الحذف التلقائي</li>
        <li>{{ ($config['compressGzip'] ?? true) ? 'يتم ضغط النسخ بصيغة gzip لتوفير المساحة' : 'حفظ النسخ الاحتياطية كملفات SQL عادية (بدون ضغط)' }}</li>
        @if($config['enabled'] ?? false)
          <li>تم تحديد مجلد الحفظ إلى: <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-xs font-mono">{{ $config['backupDir'] }}</code></li>
        @endif
      </ul>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <template x-if="confirmModal.isOpen">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeConfirmModal()"></div>
      <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700/50 relative z-10">
        <div class="flex items-start gap-4">
          <div :class="confirmModal.type === 'delete' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400'" class="p-3 rounded-xl shrink-0">
            <template x-if="confirmModal.type === 'delete'">
              <i data-lucide="trash-2" class="w-6 h-6"></i>
            </template>
            <template x-if="confirmModal.type !== 'delete'">
              <i data-lucide="alert-circle" class="w-6 h-6 animate-pulse"></i>
            </template>
          </div>
          <div class="space-y-2">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white" x-text="confirmModal.type === 'delete' ? 'تأكيد حذف النسخة الاحتياطية' : 'تأكيد استعادة قاعدة البيانات'"></h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
              <template x-if="confirmModal.type === 'delete'">
                <span>هل أنت متأكد من حذف الملف <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-red-600 dark:text-red-400 break-all" x-text="confirmModal.filename"></code>؟ لا يمكن التراجع عن هذا الإجراء بعد إتمامه.</span>
              </template>
              <template x-if="confirmModal.type === 'restore'">
                <span>
                  تحذير: هل أنت متأكد من استعادة قاعدة البيانات من النسخة <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-amber-600 dark:text-amber-400 break-all" x-text="confirmModal.filename"></code>؟
                  <strong class="block mt-2 text-red-600 dark:text-red-400">سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المحددة!</strong>
                </span>
              </template>
              <template x-if="confirmModal.type === 'upload_restore'">
                <span>
                  تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف المرفوع <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all" x-text="confirmModal.filename"></code>؟
                  <strong class="block mt-2 text-red-600 dark:text-red-400">سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المرفوعة المحددة!</strong>
                </span>
              </template>
              <template x-if="confirmModal.type === 'external_restore'">
                <span>
                  تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف الخارجي <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all" x-text="confirmModal.filename"></code>؟
                  <strong class="block mt-2 text-red-600 dark:text-red-400">سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المحددة!</strong>
                </span>
              </template>
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button
            type="button"
            @click="closeConfirmModal()"
            class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors cursor-pointer"
          >
            إلغاء
          </button>
          <button
            type="button"
            @click="executeConfirmAction()"
            :class="confirmModal.type === 'delete' ? 'bg-red-500 hover:bg-red-600 shadow-red-500/20' : 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20'"
            class="px-4 py-2 text-sm font-medium text-white rounded-xl shadow-lg transition-all cursor-pointer"
            x-text="confirmModal.type === 'delete' ? 'تأكيد الحذف' : 'تأكيد الاستعادة'"
          ></button>
        </div>
      </div>
    </div>
  </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('backupsManager', () => ({
    activeTab: 'local',
    error: '',
    successMessage: '',
    confirmModal: { isOpen: false, type: null, filename: '', extraData: '' },
    creating: false,
    verifying: null,
    downloadingFilename: null,
    restoringFilename: null,
    uploading: false,
    externalDir: '',
    scanning: false,
    scanAttempted: false,
    externalFiles: [],
    externalVerifications: {},
    verifyingExternalPath: null,

    refreshBackups() {
      window.location.reload();
    },

    handleCreate() {
      this.creating = true;
      this.error = '';
      this.successMessage = '';
      fetch('{{ route('dashboard.backups.create') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.successMessage = data.message;
          this.refreshBackups();
        } else {
          this.error = data.message;
        }
      })
      .catch(err => {
        this.error = 'فشل إنشاء النسخة الاحتياطية';
      })
      .finally(() => {
        this.creating = false;
      });
    },

    handleVerify(filename, idx) {
      this.verifying = filename;
      this.error = '';
      this.successMessage = '';
      fetch('{{ url('dashboard/backups') }}/' + encodeURIComponent(filename) + '/verify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
          const cell = document.getElementById('sv-' + idx);
          if (cell) {
            if (data.success) {
              cell.innerHTML = '<div class="flex flex-col gap-1"><span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 w-fit"><i data-lucide="check-circle-2" class="w-3 h-3"></i> صالحة</span><span class="text-[11px] text-green-600 dark:text-green-400">' + this.escapeHtml(data.message) + '</span></div>';
            } else {
              cell.innerHTML = '<div class="flex flex-col gap-1"><span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 w-fit"><i data-lucide="x-circle" class="w-3 h-3"></i> غير صالحة</span><span class="text-[11px] text-red-600 dark:text-red-400">' + this.escapeHtml(data.message) + '</span></div>';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
          }
      })
      .catch(err => {
        this.error = 'فشل التحقق من الملف';
      })
      .finally(() => {
        this.verifying = null;
      });
    },

    handleDownload(filename) {
      this.downloadingFilename = filename;
      this.error = '';
      fetch('/dashboard/backups/' + encodeURIComponent(filename) + '/download', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        }
      })
      .then(res => {
        if (!res.ok) throw new Error('فشل في تحميل ملف النسخة الاحتياطية من الخادم');
        return res.blob();
      })
      .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(err => {
        this.error = err.message || 'فشل في تنزيل ملف النسخة الاحتياطية';
      })
      .finally(() => {
        this.downloadingFilename = null;
      });
    },

    openRestoreModal(filename) {
      this.confirmModal = { isOpen: true, type: 'restore', filename: filename, extraData: '' };
    },

    openDeleteModal(filename) {
      this.confirmModal = { isOpen: true, type: 'delete', filename: filename, extraData: '' };
    },

    closeConfirmModal() {
      this.confirmModal = { isOpen: false, type: null, filename: '', extraData: '' };
    },

    executeConfirmAction() {
      const type = this.confirmModal.type;
      const filename = this.confirmModal.filename;
      this.closeConfirmModal();

      if (type === 'delete') {
        this.executeDelete(filename);
      } else if (type === 'restore') {
        this.executeRestore(filename);
      } else if (type === 'upload_restore') {
        this.executeUploadRestore(filename, this.confirmModal.extraData);
      } else if (type === 'external_restore') {
        this.executeExternalRestore(this.confirmModal.extraData, filename);
      }
    },

    executeDelete(filename) {
      this.error = '';
      this.successMessage = '';
      fetch('{{ url('dashboard/backups') }}/' + encodeURIComponent(filename), {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.successMessage = data.message;
          this.refreshBackups();
        } else {
          this.error = data.message;
        }
      })
      .catch(err => {
        this.error = 'فشل حذف النسخة الاحتياطية';
      });
    },

    executeRestore(filename) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch('{{ url('dashboard/backups') }}/' + encodeURIComponent(filename) + '/restore', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.successMessage = data.message;
          this.refreshBackups();
        } else {
          this.error = data.message;
        }
      })
      .catch(err => {
        this.error = 'فشل استعادة النسخة الاحتياطية';
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    handleUpload(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      if (!file.name.endsWith('.sql.gz')) {
        this.error = 'نوع ملف غير صالح. الرجاء تحديد ملف ينتهي بامتداد .sql.gz';
        return;
      }
      this.uploading = true;
      this.error = '';
      this.successMessage = '';

      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const result = e.target?.result;
          const base64Content = typeof result === 'string' ? (result.split(',')[1] || result) : '';
          this.uploading = false;
          this.confirmModal = {
            isOpen: true,
            type: 'upload_restore',
            filename: file.name,
            extraData: base64Content
          };
        } catch (err) {
          this.error = 'فشل في معالجة ملف النسخة الاحتياطية المرفوع';
          this.uploading = false;
        }
      };
      reader.readAsDataURL(file);
    },

    executeUploadRestore(filename, content) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch('/dashboard/backups/upload-restore', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ filename: filename, content: content })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.successMessage = '✅ تم استعادة قاعدة البيانات بنجاح من الملف المرفوع "' + filename + '"';
          setTimeout(() => window.location.reload(), 1500);
        } else {
          this.error = data.message || 'فشل في استعادة قاعدة البيانات من الملف المرفوع';
        }
      })
      .catch(err => {
        this.error = 'فشل في استعادة قاعدة البيانات من الملف المرفوع';
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    handleScanExternal() {
      if (!this.externalDir.trim()) {
        this.error = 'الرجاء إدخال مسار المجلد أولاً';
        return;
      }
      this.scanning = true;
      this.error = '';
      this.successMessage = '';
      this.scanAttempted = true;

      fetch('/dashboard/backups/scan-external', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ path: this.externalDir })
      })
      .then(res => res.json())
      .then(data => {
        if (data.backups) {
          this.externalFiles = data.backups;
          this.externalVerifications = {};
        } else {
          this.error = data.message || 'فشل في قراءة المجلد';
          this.externalFiles = [];
        }
      })
      .catch(err => {
        this.error = 'فشل في قراءة المجلد';
        this.externalFiles = [];
      })
      .finally(() => {
        this.scanning = false;
      });
    },

    handleVerifyExternal(fullPath) {
      this.verifyingExternalPath = fullPath;
      this.error = '';
      fetch('/dashboard/backups/verify-external', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ path: fullPath })
      })
      .then(res => res.json())
      .then(data => {
        if (data.valid !== undefined) {
          this.externalVerifications[fullPath] = data;
        } else {
          this.error = data.message || 'فشل في التحقق من الملف';
        }
      })
      .catch(err => {
        this.error = 'فشل في التحقق من الملف';
      })
      .finally(() => {
        this.verifyingExternalPath = null;
      });
    },

    handleRestoreExternal(fullPath, filename) {
      const verification = this.externalVerifications[fullPath];
      if (!verification?.valid) {
        this.error = 'افحص النسخة وتأكد أنها صالحة قبل الاستعادة';
        return;
      }
      this.confirmModal = {
        isOpen: true,
        type: 'external_restore',
        filename: filename,
        extraData: fullPath
      };
    },

    executeExternalRestore(filepath, filename) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch('/dashboard/backups/restore-external', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ path: filepath })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          this.successMessage = '✅ تم استعادة قاعدة البيانات بنجاح من الملف الخارجي "' + filename + '"';
          setTimeout(() => window.location.reload(), 1500);
        } else {
          this.error = data.message || 'فشل في استعادة قاعدة البيانات من المجلد المحدد';
        }
      })
      .catch(err => {
        this.error = 'فشل في استعادة قاعدة البيانات من المجلد المحدد';
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  }));
});
</script>
@endsection