@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'النسخ الاحتياطي لقاعدة البيانات' : 'Database Backups') . ' - MedSurvey Pro')

@section('dashboard')
@php
  $isAr = app()->getLocale() === 'ar';
  $totalBackups = count($backups);
  $totalSizeMb = collect($backups)->sum('sizeBytes') > 0 ? round(collect($backups)->sum('sizeBytes') / 1024 / 1024, 2) : 0;
  $latestBackup = collect($backups)->sortByDesc('createdAt')->first();
  $formatNumber = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals);
  $config = $config ?? [
    'enabled' => env('DB_BACKUP_ENABLED', false),
    'retentionDays' => 30,
    'schedule' => '03:00',
    'compressGzip' => true,
    'backupDir' => storage_path('app/backups'),
    'displayBackupDir' => $settings['backupSettings']['backupDir'] ?? 'storage/app/backups',
  ];
  $txt = [
    'title' => $isAr ? 'النسخ الاحتياطي لقاعدة البيانات' : 'Database Backups',
    'subtitle' => $isAr ? 'إدارة النسخ الاحتياطية التلقائية واليدوية لقاعدة البيانات' : 'Manage automatic and manual database backups',
    'refresh' => $isAr ? 'تحديث' : 'Refresh',
    'create' => $isAr ? 'إنشاء نسخة احتياطية' : 'Create Backup',
    'creating' => $isAr ? 'جاري الإنشاء...' : 'Creating...',
    'totalBackups' => $isAr ? 'إجمالي النسخ' : 'Total Backups',
    'totalSize' => $isAr ? 'الحجم الإجمالي' : 'Total Size',
    'retention' => $isAr ? 'مدة الاحتفاظ' : 'Retention',
    'day' => $isAr ? 'يوم' : 'days',
    'status' => $isAr ? 'الحالة' : 'Status',
    'active' => $isAr ? 'نشط' : 'Active',
    'stopped' => $isAr ? 'متوقف' : 'Stopped',
    'latest' => $isAr ? 'آخر نسخة احتياطية:' : 'Latest backup:',
    'verified' => $isAr ? 'تم التحقق' : 'Verified',
    'autoDisabled' => $isAr ? 'النسخ الاحتياطي التلقائي معطل حالياً. يمكنك تفعيله عبر متغير البيئة' : 'Automatic backups are currently disabled. You can enable them using the environment variable',
    'tabLocal' => $isAr ? 'النسخ الاحتياطية للنظام' : 'System Backups',
    'tabUpload' => $isAr ? 'استعادة من ملف محلي (.sql.gz)' : 'Restore From Local File (.sql.gz)',
    'tabExternal' => $isAr ? 'استعادة من مجلد خادم آخر' : 'Restore From Server Directory',
    'listTitle' => $isAr ? 'قائمة النسخ الاحتياطية' : 'Backup List',
    'emptyTitle' => $isAr ? 'لا توجد نسخ احتياطية بعد' : 'No backups yet',
    'emptyDesc' => $isAr ? 'انقر على "إنشاء نسخة احتياطية" لبدء النسخ الأول' : 'Click "Create Backup" to create the first backup',
    'fileName' => $isAr ? 'اسم الملف' : 'File Name',
    'size' => $isAr ? 'الحجم' : 'Size',
    'createdAt' => $isAr ? 'تاريخ الإنشاء' : 'Created At',
    'modifiedAt' => $isAr ? 'تاريخ التعديل' : 'Modified At',
    'actions' => $isAr ? 'إجراءات' : 'Actions',
    'valid' => $isAr ? 'صالحة' : 'Valid',
    'invalid' => $isAr ? 'غير صالحة' : 'Invalid',
    'notVerified' => $isAr ? 'لم يتم التحقق' : 'Not verified',
    'downloadTitle' => $isAr ? 'تنزيل ملف النسخة الاحتياطية' : 'Download backup file',
    'verifyTitle' => $isAr ? 'التحقق من الملف' : 'Verify file',
    'restoreTitle' => $isAr ? 'استعادة قاعدة البيانات من هذه النسخة' : 'Restore database from this backup',
    'delete' => $isAr ? 'حذف' : 'Delete',
    'uploadTitle' => $isAr ? 'رفع واستعادة نسخة احتياطية' : 'Upload and Restore Backup',
    'uploadDesc' => $isAr ? 'قم باختيار ملف نسخة احتياطية ينتهي بامتداد' : 'Choose a backup file ending with',
    'uploadDesc2' => $isAr ? 'من أي مجلد على جهازك وسيتكفل النظام برفعها وفحصها واستعادتها بأمان.' : 'from any folder on your device. The system will upload, verify, and restore it safely.',
    'chooseFile' => $isAr ? 'اختر ملف النسخة الاحتياطية' : 'Choose Backup File',
    'readingFile' => $isAr ? 'جاري قراءة الملف...' : 'Reading file...',
    'externalTitle' => $isAr ? 'مسار مجلد النسخ الاحتياطية على الخادم' : 'Backup Directory Path on Server',
    'externalDesc' => $isAr ? 'أدخل المسار الكامل للمجلد على الخادم ليقوم النظام بفحص الملفات الموجودة بداخله.' : 'Enter the full server directory path so the system can scan backup files inside it.',
    'externalPlaceholder' => $isAr ? 'مثال: C:\backups أو /var/backups' : 'Example: C:\backups or /var/backups',
    'scanning' => $isAr ? 'جاري الفحص...' : 'Scanning...',
    'scanFolder' => $isAr ? 'فحص المجلد' : 'Scan Folder',
    'discoveredFiles' => $isAr ? 'الملفات المكتشفة في المجلد' : 'Discovered Files',
    'noExternalPrefix' => $isAr ? 'لم يتم العثور على أي ملفات نسخة احتياطية ينتهي اسمها بـ' : 'No backup files ending with',
    'noExternalSuffix' => $isAr ? 'في هذا المجلد.' : 'were found in this folder.',
    'verifyFile' => $isAr ? 'التحقق من الملف' : 'Verify File',
    'restore' => $isAr ? 'استعادة' : 'Restore',
    'info' => $isAr ? 'معلومات' : 'Information',
    'infoSchedule' => $isAr ? 'يتم تشغيل النسخ الاحتياطي التلقائي يومياً في الساعة' : 'Automatic backup runs daily at',
    'infoRetentionPrefix' => $isAr ? 'يتم الاحتفاظ بالنسخ لمدة' : 'Backups are retained for',
    'infoRetentionSuffix' => $isAr ? 'يوماً قبل الحذف التلقائي' : 'days before automatic deletion',
    'infoGzip' => $isAr ? 'يتم ضغط النسخ بصيغة gzip لتوفير المساحة' : 'Backups are compressed with gzip to save space',
    'infoSql' => $isAr ? 'حفظ النسخ الاحتياطية كملفات SQL عادية (بدون ضغط)' : 'Backups are saved as plain SQL files (without compression)',
    'infoDir' => $isAr ? 'تم تحديد مجلد الحفظ إلى:' : 'Backup directory is set to:',
    'cancel' => $isAr ? 'إلغاء' : 'Cancel',
  ];
@endphp

<div x-data="backupsManager()" class="p-4 sm:p-6 space-y-6 text-start animate-fade-in" x-cloak>
  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
        <i data-lucide="database" class="w-6 h-6 text-teal-500"></i>
        <span>{{ $txt['title'] }}</span>
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        {{ $txt['subtitle'] }}
      </p>
    </div>
    <div class="flex gap-2">
      <button
        @click="refreshBackups()"
        type="button"
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
      >
        <i data-lucide="refresh-ccw" class="w-4 h-4"></i>
        <span>{{ $txt['refresh'] }}</span>
      </button>
      <button
        @click="handleCreate()"
        :disabled="creating"
        type="button"
        class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-500/20 cursor-pointer"
      >
        <svg x-show="creating" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <i x-show="!creating" data-lucide="download" class="w-4 h-4"></i>
        <span x-show="!creating">{{ $txt['create'] }}</span>
        <span x-show="creating">{{ $txt['creating'] }}</span>
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
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $txt['totalBackups'] }}</p>
          <p class="stat-number text-xl font-bold text-slate-800 dark:text-white" x-text="formatNumber(backupsData.length)">{{ $formatNumber($totalBackups) }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
          <i data-lucide="hard-drive" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $txt['totalSize'] }}</p>
          <p class="stat-number-tight text-xl font-bold text-slate-800 dark:text-white" x-text="formatNumber(calcTotalSizeMb(), 2) + ' MB'">{{ $formatNumber($totalSizeMb, 2) }} MB</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-xl">
          <i data-lucide="calendar" class="w-5 h-5 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $txt['retention'] }}</p>
          <p class="stat-number-tight text-xl font-bold text-slate-800 dark:text-white" x-text="formatNumber(configData.retentionDays || 30) + ' {{ $txt['day'] }}'">{{ $formatNumber($config['retentionDays'] ?? 30) }} {{ $txt['day'] }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
      <div class="flex items-center gap-3">
        <div :class="(configData.enabled) ? 'bg-green-100 dark:bg-green-900/30' : 'bg-slate-100 dark:bg-slate-700'" class="p-2.5 rounded-xl">
          <i :class="(configData.enabled) ? 'text-green-600 dark:text-green-400' : 'text-slate-400'" data-lucide="shield" class="w-5 h-5"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ $txt['status'] }}</p>
          <p :class="(configData.enabled) ? 'text-green-600 dark:text-green-400' : 'text-slate-500'" class="text-xl font-bold" x-text="(configData.enabled) ? '{{ $txt['active'] }}' : '{{ $txt['stopped'] }}'">
            {{ ($config['enabled'] ?? false) ? $txt['active'] : $txt['stopped'] }}
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Latest Backup Info (static for initial load, Alpine for dynamic updates) -->
  @if ($latestBackup)
    <div id="latest-backup-static" class="rounded-2xl p-4 flex items-center gap-3 bg-teal-50 dark:bg-teal-900/10 border border-teal-200 dark:border-teal-800">
      <i data-lucide="check-circle-2" class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0"></i>
      <div class="text-sm text-teal-700 dark:text-teal-300">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span class="font-semibold">{{ $txt['latest'] }}</span>
          <span>{{ \Carbon\Carbon::parse($latestBackup['createdAt'])->format('Y-m-d H:i') }}</span>
          <span>·</span>
          <span class="font-semibold">{{ round($latestBackup['sizeBytes'] / 1024 / 1024, 2) }} MB</span>
          <span>·</span>
          <span>{{ $latestBackup['filename'] }}</span>
          @if($latestBackup['verified'] ?? false)
            <span>·</span>
            <span class="text-teal-600">✓ {{ $txt['verified'] }}</span>
          @endif
        </div>
      </div>
    </div>
    <template x-if="latestBackup">
      <div id="latest-backup-dynamic" style="display: none;" class="rounded-2xl p-4 flex items-center gap-3 bg-teal-50 dark:bg-teal-900/10 border border-teal-200 dark:border-teal-800">
        <i data-lucide="check-circle-2" class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0"></i>
        <div class="text-sm text-teal-700 dark:text-teal-300">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold">{{ $txt['latest'] }}</span>
            <span x-text="latestBackup.createdAt"></span>
            <span>·</span>
            <span class="font-semibold" x-text="(latestBackup.sizeBytes / 1048576).toFixed(2) + ' MB'"></span>
            <span>·</span>
            <span x-text="latestBackup.filename"></span>
          </div>
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
            <template x-if="latestBackup.verified === true">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                {{ $txt['valid'] }}
              </span>
            </template>
            <template x-if="latestBackup.verified === false">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                <i data-lucide="x-circle" class="w-3 h-3"></i>
                {{ $txt['invalid'] }}
              </span>
            </template>
            <template x-if="latestBackup.verified !== true && latestBackup.verified !== false">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                <i data-lucide="alert-circle" class="w-3 h-3"></i>
                {{ $txt['notVerified'] }}
              </span>
            </template>
            <template x-if="latestBackup.verifyMessage">
              <span class="text-teal-600 dark:text-teal-400 text-xs font-medium" x-text="latestBackup.verifyMessage"></span>
            </template>
          </div>
        </div>
      </div>
    </template>
    <script>
      document.addEventListener('alpine:init', () => {
        // Hide static version and show dynamic version once Alpine loads
        const staticEl = document.getElementById('latest-backup-static');
        const dynamicEl = document.getElementById('latest-backup-dynamic');
        if (staticEl && dynamicEl) {
          // After Alpine renders the template, hide static and show dynamic
          setTimeout(() => {
            staticEl.style.display = 'none';
            dynamicEl.style.display = 'flex';
          }, 100);
        }
      });
    </script>
  @else
    <template x-if="latestBackup">
      <div class="rounded-2xl p-4 flex items-center gap-3 bg-teal-50 dark:bg-teal-900/10 border border-teal-200 dark:border-teal-800">
        <i data-lucide="check-circle-2" class="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0"></i>
        <div class="text-sm text-teal-700 dark:text-teal-300">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold">{{ $txt['latest'] }}</span>
            <span x-text="latestBackup.createdAt"></span>
            <span>·</span>
            <span class="font-semibold" x-text="(latestBackup.sizeBytes / 1048576).toFixed(2) + ' MB'"></span>
            <span>·</span>
            <span x-text="latestBackup.filename"></span>
          </div>
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
            <template x-if="latestBackup.verified === true">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                {{ $txt['valid'] }}
              </span>
            </template>
            <template x-if="latestBackup.verified === false">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                <i data-lucide="x-circle" class="w-3 h-3"></i>
                {{ $txt['invalid'] }}
              </span>
            </template>
            <template x-if="latestBackup.verified !== true && latestBackup.verified !== false">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                <i data-lucide="alert-circle" class="w-3 h-3"></i>
                {{ $txt['notVerified'] }}
              </span>
            </template>
            <template x-if="latestBackup.verifyMessage">
              <span class="text-teal-600 dark:text-teal-400 text-xs font-medium" x-text="latestBackup.verifyMessage"></span>
            </template>
          </div>
        </div>
      </div>
    </template>
  @endif

  <!-- Auto-disabled warning (Dynamic) -->
  <template x-if="!(configData.enabled)">
    <div class="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-700 dark:text-amber-300 text-sm">
      <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ $txt['autoDisabled'] }}</span>
      <code class="mx-1 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 rounded text-xs font-mono">DB_BACKUP_ENABLED=true</code>
    </div>
  </template>

  <!-- Navigation Tabs -->
  <div class="flex border-b border-slate-200 dark:border-slate-800 gap-1 mt-4">
    <button
      @click="activeTab = 'local'"
      :class="activeTab === 'local' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="database" class="w-4 h-4"></i>
      {{ $txt['tabLocal'] }}
    </button>
    <button
      @click="activeTab = 'upload'"
      :class="activeTab === 'upload' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="upload" class="w-4 h-4"></i>
      {{ $txt['tabUpload'] }}
    </button>
    <button
      @click="activeTab = 'external'"
      :class="activeTab === 'external' ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
      class="flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer"
    >
      <i data-lucide="hard-drive" class="w-4 h-4"></i>
      {{ $txt['tabExternal'] }}
    </button>
  </div>

  <!-- Tab: Local Backups -->
  <div x-show="activeTab === 'local'" class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-lg font-semibold text-slate-800 dark:text-white">{{ $txt['listTitle'] }}</h2>
    </div>

    <template x-if="backupsData.length === 0">
      <div class="p-12 text-center">
        <i data-lucide="database" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
        <p class="text-slate-500 dark:text-slate-400">{{ $txt['emptyTitle'] }}</p>
        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
          {{ $txt['emptyDesc'] }}
        </p>
      </div>
    </template>

    <template x-if="backupsData.length > 0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800">
              <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['fileName'] }}</th>
              <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['size'] }}</th>
              <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['createdAt'] }}</th>
              <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['status'] }}</th>
              <th class="text-end p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['actions'] }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <template x-for="(backup, idx) in backupsData" :key="backup.filename">
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="p-4">
                  <div class="flex items-center gap-2">
                    <i data-lucide="file-archive" class="w-4 h-4 text-teal-500 shrink-0"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-medium break-all" x-text="backup.filename"></span>
                  </div>
                </td>
                <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap text-center" x-text="(backup.sizeBytes / 1048576).toFixed(2) + ' MB'"></td>
                <td class="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap" x-text="backup.createdAt"></td>
                <td class="p-4 whitespace-nowrap">
                  <div class="space-y-1">
                    <template x-if="backup.verified === true">
                      <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                        {{ $txt['valid'] }}
                      </span>
                    </template>
                    <template x-if="backup.verified === false">
                      <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>
                        {{ $txt['invalid'] }}
                      </span>
                    </template>
                    <template x-if="backup.verified !== true && backup.verified !== false">
                      <span class="text-xs text-slate-400 dark:text-slate-500">{{ $txt['notVerified'] }}</span>
                    </template>
                    <template x-if="backup.verifyMessage">
                      <div class="text-[11px] text-green-600 dark:text-green-400 font-medium leading-tight max-w-[200px]" x-text="backup.verifyMessage"></div>
                    </template>
                  </div>
                </td>
                <td class="p-4 whitespace-nowrap text-end">
                  <div class="flex items-center justify-end gap-1">
                    <!-- Download -->
                    <button
                      @click="handleDownload(backup.filename)"
                      :disabled="downloadingFilename === backup.filename"
                      class="p-2 text-teal-500 hover:text-teal-600 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="{{ $txt['downloadTitle'] }}"
                    >
                      <template x-if="downloadingFilename === backup.filename">
                        <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      </template>
                      <template x-if="downloadingFilename !== backup.filename">
                        <i data-lucide="download" class="w-4 h-4"></i>
                      </template>
                    </button>

                    <!-- Verify -->
                    <button
                      @click="handleVerify(backup.filename, idx)"
                      :disabled="verifying === backup.filename"
                      class="p-2 text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="{{ $txt['verifyTitle'] }}"
                    >
                      <svg x-show="verifying === backup.filename" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <i x-show="verifying !== backup.filename" data-lucide="file-search" class="w-4 h-4"></i>
                    </button>

                    <!-- Restore -->
                    <button
                      @click="openRestoreModal(backup.filename)"
                      :disabled="restoringFilename === backup.filename"
                      class="p-2 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="{{ $txt['restoreTitle'] }}"
                    >
                      <svg x-show="restoringFilename === backup.filename" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <i x-show="restoringFilename !== backup.filename" data-lucide="upload" class="w-4 h-4"></i>
                    </button>

                    <!-- Delete -->
                    <button
                      @click="openDeleteModal(backup.filename)"
                      class="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors cursor-pointer"
                      title="{{ $txt['delete'] }}"
                    >
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
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

  <!-- Tab: Upload -->
  <div x-show="activeTab === 'upload'" class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-8 text-center space-y-6">
    <div class="max-w-md mx-auto space-y-4">
      <div class="w-16 h-16 bg-teal-50 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mx-auto text-teal-600 dark:text-teal-400 shadow-md">
        <i data-lucide="upload" class="w-8 h-8"></i>
      </div>
      <div class="space-y-2">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white">{{ $txt['uploadTitle'] }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
          {{ $txt['uploadDesc'] }} <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-teal-600 dark:text-teal-400 text-xs font-mono">.sql.gz</code> {{ $txt['uploadDesc2'] }}
        </p>
      </div>
      <div class="pt-4">
        <form action="{{ route('dashboard.backups.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm" class="inline-block">
          @csrf
          <label class="cursor-pointer inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 transition-all shadow-lg shadow-teal-500/20">
            <i data-lucide="upload" class="w-5 h-5 animate-pulse"></i>
            <span x-show="!uploading">{{ $txt['chooseFile'] }}</span>
            <span x-show="uploading">{{ $txt['readingFile'] }}</span>
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
        <h2 class="text-lg font-bold text-slate-800 dark:text-white">{{ $txt['externalTitle'] }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ $txt['externalDesc'] }}
        </p>
      </div>
      <div class="flex flex-col sm:flex-row gap-3">
        <input
          type="text"
          x-model="externalDir"
          placeholder="{{ $txt['externalPlaceholder'] }}"
          class="flex-1 px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
        />
        <button
          @click="handleScanExternal()"
          :disabled="scanning"
          class="px-6 py-3 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 disabled:bg-teal-400 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0 cursor-pointer"
        >
          <svg x-show="scanning" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          <i x-show="!scanning" data-lucide="file-search" class="w-4 h-4"></i>
          <span x-text="scanning ? texts.scanning : texts.scanFolder"></span>
        </button>
      </div>
    </div>

    <template x-if="scanAttempted">
      <div class="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-800">
          <h3 class="text-md font-bold text-slate-800 dark:text-white">{{ $txt['discoveredFiles'] }}</h3>
        </div>

        <template x-if="externalFiles.length === 0">
          <div class="p-12 text-center text-slate-500 dark:text-slate-400">
            <i data-lucide="database" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
            <span>{{ $txt['noExternalPrefix'] }} </span>
            <code class="text-teal-500">.sql.gz</code>
            <span> {{ $txt['noExternalSuffix'] }}</span>
          </div>
        </template>

        <template x-if="externalFiles.length > 0">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800">
                  <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['fileName'] }}</th>
                  <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['size'] }}</th>
                  <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['modifiedAt'] }}</th>
                  <th class="text-start p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['status'] }}</th>
                  <th class="text-end p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $txt['actions'] }}</th>
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
                          {{ $txt['notVerified'] }}
                        </span>
                      </template>
                      <template x-if="externalVerifications[file.fullPath] && externalVerifications[file.fullPath].valid">
                        <div class="space-y-1">
                          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                            {{ $txt['valid'] }}
                          </span>
                          <template x-if="externalVerifications[file.fullPath].message">
                            <div class="text-[11px] text-green-600 dark:text-green-400 font-medium leading-tight max-w-[200px]" x-text="externalVerifications[file.fullPath].message"></div>
                          </template>
                        </div>
                      </template>
                      <template x-if="externalVerifications[file.fullPath] && !externalVerifications[file.fullPath].valid">
                        <div class="space-y-1">
                          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            <i data-lucide="x-circle" class="w-3 h-3"></i>
                            {{ $txt['invalid'] }}
                          </span>
                          <template x-if="externalVerifications[file.fullPath].message">
                            <div class="text-[11px] text-red-600 dark:text-red-400 font-medium leading-tight max-w-[200px]" x-text="externalVerifications[file.fullPath].message"></div>
                          </template>
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
                          {{ $txt['verifyFile'] }}
                        </button>
                        <button
                          @click="handleRestoreExternal(file.fullPath, file.filename)"
                          :disabled="restoringFilename === file.filename || !(externalVerifications[file.fullPath]?.valid)"
                          class="px-4 py-1.5 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 disabled:cursor-not-allowed rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                        >
                          <svg x-show="restoringFilename === file.filename" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                          <i x-show="restoringFilename !== file.filename" data-lucide="upload" class="w-3.5 h-3.5"></i>
                          {{ $txt['restore'] }}
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
      <p class="font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $txt['info'] }}</p>
      <ul class="list-disc list-inside space-y-1">
        <li>{{ $txt['infoSchedule'] }} <span dir="ltr">{{ $config['schedule'] ?? '03:00' }}</span></li>
        <li>{{ $txt['infoRetentionPrefix'] }} {{ $config['retentionDays'] ?? 30 }} {{ $txt['infoRetentionSuffix'] }}</li>
        <li>{{ ($config['compressGzip'] ?? true) ? $txt['infoGzip'] : $txt['infoSql'] }}</li>
        <li>{{ $txt['infoDir'] }} <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-xs font-mono" x-text="configData.displayBackupDir || configData.backupDir || @js($config['displayBackupDir'] ?? ($config['backupDir'] ?? storage_path('app/backups')))">{{ $config['displayBackupDir'] ?? ($config['backupDir'] ?? storage_path('app/backups')) }}</code></li>
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
            <h3 class="text-lg font-bold text-slate-800 dark:text-white" x-text="confirmModal.type === 'delete' ? texts.confirmDeleteTitle : texts.confirmRestoreTitle"></h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
              <template x-if="confirmModal.type === 'delete'">
                <span><span x-text="texts.deleteQuestionPrefix"></span> <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-red-600 dark:text-red-400 break-all" x-text="confirmModal.filename"></code><span x-text="texts.deleteQuestionSuffix"></span></span>
              </template>
              <template x-if="confirmModal.type === 'restore'">
                <span>
                  <span x-text="texts.restoreBackupPrefix"></span> <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-amber-600 dark:text-amber-400 break-all" x-text="confirmModal.filename"></code><span x-text="texts.questionMark"></span>
                  <strong class="block mt-2 text-red-600 dark:text-red-400" x-text="texts.restoreWarning"></strong>
                </span>
              </template>
              <template x-if="confirmModal.type === 'upload_restore'">
                <span>
                  <span x-text="texts.restoreUploadPrefix"></span> <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all" x-text="confirmModal.filename"></code><span x-text="texts.questionMark"></span>
                  <strong class="block mt-2 text-red-600 dark:text-red-400" x-text="texts.restoreUploadWarning"></strong>
                </span>
              </template>
              <template x-if="confirmModal.type === 'external_restore'">
                <span>
                  <span x-text="texts.restoreExternalPrefix"></span> <code class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all" x-text="confirmModal.filename"></code><span x-text="texts.questionMark"></span>
                  <strong class="block mt-2 text-red-600 dark:text-red-400" x-text="texts.restoreWarning"></strong>
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
            {{ $txt['cancel'] }}
          </button>
          <button
            type="button"
            @click="executeConfirmAction()"
            :class="confirmModal.type === 'delete' ? 'bg-red-500 hover:bg-red-600 shadow-red-500/20' : 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20'"
            class="px-4 py-2 text-sm font-medium text-white rounded-xl shadow-lg transition-all cursor-pointer"
            x-text="confirmModal.type === 'delete' ? texts.confirmDelete : texts.confirmRestore"
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
    backupsData: @json($backups),
    configData: @json($config),
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
    texts: {
      scanning: @js($txt['scanning']),
      scanFolder: @js($txt['scanFolder']),
      valid: @js($txt['valid']),
      invalid: @js($txt['invalid']),
      confirmDeleteTitle: @js($isAr ? 'تأكيد حذف النسخة الاحتياطية' : 'Confirm Backup Deletion'),
      confirmRestoreTitle: @js($isAr ? 'تأكيد استعادة قاعدة البيانات' : 'Confirm Database Restore'),
      deleteQuestionPrefix: @js($isAr ? 'هل أنت متأكد من حذف الملف' : 'Are you sure you want to delete the file'),
      deleteQuestionSuffix: @js($isAr ? '؟ لا يمكن التراجع عن هذا الإجراء بعد إتمامه.' : '? This action cannot be undone after it is completed.'),
      restoreBackupPrefix: @js($isAr ? 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من النسخة' : 'Warning: Are you sure you want to restore the database from backup'),
      restoreUploadPrefix: @js($isAr ? 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف المرفوع' : 'Warning: Are you sure you want to restore the database from the uploaded file'),
      restoreExternalPrefix: @js($isAr ? 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف الخارجي' : 'Warning: Are you sure you want to restore the database from the external file'),
      questionMark: @js($isAr ? '؟' : '?'),
      restoreWarning: @js($isAr ? 'سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المحددة!' : 'This will completely replace all current data with the selected backup data!'),
      restoreUploadWarning: @js($isAr ? 'سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المرفوعة المحددة!' : 'This will completely replace all current data with the selected uploaded backup data!'),
      confirmDelete: @js($isAr ? 'تأكيد الحذف' : 'Confirm Delete'),
      confirmRestore: @js($isAr ? 'تأكيد الاستعادة' : 'Confirm Restore'),
      createFailed: @js($isAr ? 'فشل إنشاء النسخة الاحتياطية' : 'Failed to create backup'),
      verifyFailed: @js($isAr ? 'فشل التحقق من الملف' : 'Failed to verify file'),
      downloadServerFailed: @js($isAr ? 'فشل في تحميل ملف النسخة الاحتياطية من الخادم' : 'Failed to load backup file from server'),
      downloadFailed: @js($isAr ? 'فشل في تنزيل ملف النسخة الاحتياطية' : 'Failed to download backup file'),
      deleteFailed: @js($isAr ? 'فشل حذف النسخة الاحتياطية' : 'Failed to delete backup'),
      restoreFailed: @js($isAr ? 'فشل استعادة النسخة الاحتياطية' : 'Failed to restore backup'),
      invalidFileType: @js($isAr ? 'نوع ملف غير صالح. الرجاء تحديد ملف ينتهي بامتداد .sql.gz' : 'Invalid file type. Please select a file ending with .sql.gz'),
      uploadProcessFailed: @js($isAr ? 'فشل في معالجة ملف النسخة الاحتياطية المرفوع' : 'Failed to process uploaded backup file'),
      uploadRestoreSuccessPrefix: @js($isAr ? 'تم استعادة قاعدة البيانات بنجاح من الملف المرفوع' : 'Database restored successfully from uploaded file'),
      uploadRestoreFailed: @js($isAr ? 'فشل في استعادة قاعدة البيانات من الملف المرفوع' : 'Failed to restore database from uploaded file'),
      pathRequired: @js($isAr ? 'الرجاء إدخال مسار المجلد أولاً' : 'Please enter the folder path first'),
      readFolderFailed: @js($isAr ? 'فشل في قراءة المجلد' : 'Failed to read folder'),
      restoreNeedsValid: @js($isAr ? 'افحص النسخة وتأكد أنها صالحة قبل الاستعادة' : 'Verify the backup and make sure it is valid before restoring'),
      externalRestoreSuccessPrefix: @js($isAr ? 'تم استعادة قاعدة البيانات بنجاح من الملف الخارجي' : 'Database restored successfully from external file'),
      externalRestoreFailed: @js($isAr ? 'فشل في استعادة قاعدة البيانات من المجلد المحدد' : 'Failed to restore database from the selected folder'),
    },

    formatNumber(value, fractionDigits = 0) {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
      }).format(Number(value || 0));
    },

    refreshBackups(skipClearSuccess = false) {
      if (!skipClearSuccess) this.successMessage = '';
      this.error = '';
      return fetch('{{ route('dashboard.backups') }}?ajax=true', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.backups) {
          this.backupsData = data.backups;
          this.configData = data.config;
        }
      })
      .catch(err => {
        this.error = @js($isAr ? 'فشل تحديث البيانات' : 'Failed to refresh data');
      });
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
          return this.refreshBackups(true);
        } else {
          this.error = data.message;
        }
      })
      .catch(err => {
        this.error = this.texts.createFailed;
      })
      .finally(() => {
        this.creating = false;
        // Auto-verify the latest backup after creation (runs regardless of success/failure)
        setTimeout(() => this.autoVerifyLatest(), 100);
      });
    },

    autoVerifyLatest() {
      if (!this.backupsData || this.backupsData.length === 0) return;
      // Get the latest backup (first item is newest if sorted desc)
      const sorted = [...this.backupsData].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
      const latest = sorted[0];
      if (!latest) return;
      const idx = this.backupsData.indexOf(latest);
      const filename = latest.filename;
      
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
        if (this.backupsData[idx]) {
          const updated = [...this.backupsData];
          updated[idx] = { 
            ...updated[idx], 
            verified: data.success ? true : false,
            verifyMessage: data.message || ''
          };
          this.backupsData = updated;
        }
        if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
      })
      .catch(() => {
        // Silent fail for auto-verify
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
          // Update the backup item in backupsData reactively for Alpine
          if (this.backupsData[idx]) {
            // Alpine reactivity: replace the item in the array
            const updated = [...this.backupsData];
            updated[idx] = { 
              ...updated[idx], 
              verified: data.success ? true : false,
              verifyMessage: data.message || ''
            };
            this.backupsData = updated;
          }
          if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
      })
      .catch(err => {
        this.error = this.texts.verifyFailed;
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
        if (!res.ok) throw new Error(this.texts.downloadServerFailed);
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
        this.error = err.message || this.texts.downloadFailed;
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
        this.error = this.texts.deleteFailed;
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
        this.error = this.texts.restoreFailed;
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    handleUpload(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      if (!file.name.endsWith('.sql.gz')) {
        this.error = this.texts.invalidFileType;
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
          this.error = this.texts.uploadProcessFailed;
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
          this.successMessage = this.texts.uploadRestoreSuccessPrefix + ' "' + filename + '"';
          setTimeout(() => window.location.reload(), 1500);
        } else {
          this.error = data.message || this.texts.uploadRestoreFailed;
        }
      })
      .catch(err => {
        this.error = this.texts.uploadRestoreFailed;
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    handleScanExternal() {
      if (!this.externalDir.trim()) {
        this.error = this.texts.pathRequired;
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
          this.error = data.message || this.texts.readFolderFailed;
          this.externalFiles = [];
        }
      })
      .catch(err => {
        this.error = this.texts.readFolderFailed;
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
          this.error = data.message || this.texts.verifyFailed;
        }
      })
      .catch(err => {
        this.error = this.texts.verifyFailed;
      })
      .finally(() => {
        this.verifyingExternalPath = null;
      });
    },

    handleRestoreExternal(fullPath, filename) {
      const verification = this.externalVerifications[fullPath];
      if (!verification?.valid) {
        this.error = this.texts.restoreNeedsValid;
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
          this.successMessage = this.texts.externalRestoreSuccessPrefix + ' "' + filename + '"';
          setTimeout(() => window.location.reload(), 1500);
        } else {
          this.error = data.message || this.texts.externalRestoreFailed;
        }
      })
      .catch(err => {
        this.error = this.texts.externalRestoreFailed;
      })
      .finally(() => {
        this.restoringFilename = null;
      });
    },

    get latestBackup() {
      if (!this.backupsData || this.backupsData.length === 0) return null;
      return [...this.backupsData].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))[0];
    },

    calcTotalSizeMb() {
      if (!this.backupsData || this.backupsData.length === 0) return '0.00';
      const totalBytes = this.backupsData.reduce((sum, b) => sum + (b.sizeBytes || 0), 0);
      return (totalBytes / 1048576).toFixed(2);
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
