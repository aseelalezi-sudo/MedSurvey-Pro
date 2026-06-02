@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'سجل العمليات والأمان' : 'Audit & Security Log') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isAr = app()->getLocale() === 'ar';
    $actionBadges = [
      'login' => ['bg' => 'bg-green-50 dark:bg-green-950/25 border-green-100 dark:border-green-900/30', 'text' => 'text-green-700 dark:text-green-400'],
      'login_failed' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30 animate-pulse', 'text' => 'text-red-700 dark:text-red-400'],
      'logout' => ['bg' => 'bg-gray-50 dark:bg-slate-800/40 border-gray-100 dark:border-slate-700/50', 'text' => 'text-gray-600 dark:text-slate-400'],
      'create_user' => ['bg' => 'bg-purple-50 dark:bg-purple-950/25 border-purple-100 dark:border-purple-900/30', 'text' => 'text-purple-700 dark:text-purple-400'],
      'update_user' => ['bg' => 'bg-blue-50 dark:bg-blue-950/25 border-blue-100 dark:border-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-400'],
      'change_user_password' => ['bg' => 'bg-indigo-50 dark:bg-indigo-950/25 border-indigo-100 dark:border-indigo-900/30', 'text' => 'text-indigo-700 dark:text-indigo-400'],
      'delete_user' => ['bg' => 'bg-rose-50 dark:bg-rose-950/25 border-rose-100 dark:border-rose-900/30', 'text' => 'text-rose-700 dark:text-rose-400'],
      'activate_user' => ['bg' => 'bg-emerald-50 dark:bg-emerald-950/25 border-emerald-100 dark:border-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400'],
      'deactivate_user' => ['bg' => 'bg-slate-50 dark:bg-slate-800/40 border-slate-100 dark:border-slate-700/50', 'text' => 'text-slate-700 dark:text-slate-300'],
      'create_survey' => ['bg' => 'bg-teal-50 dark:bg-teal-950/25 border-teal-100 dark:border-teal-900/30', 'text' => 'text-teal-700 dark:text-teal-400'],
      'update_survey' => ['bg' => 'bg-sky-50 dark:bg-sky-950/25 border-sky-100 dark:border-sky-900/30', 'text' => 'text-sky-700 dark:text-sky-400'],
      'delete_survey' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30', 'text' => 'text-red-700 dark:text-red-400'],
      'update_settings' => ['bg' => 'bg-orange-50 dark:bg-orange-950/25 border-orange-100 dark:border-orange-900/30', 'text' => 'text-orange-700 dark:text-orange-400'],
      'update_ticket' => ['bg' => 'bg-amber-50 dark:bg-amber-950/25 border-amber-100 dark:border-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400'],
      'delete_response' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30', 'text' => 'text-red-700 dark:text-red-400'],
      'export_responses' => ['bg' => 'bg-cyan-50 dark:bg-cyan-950/25 border-cyan-100 dark:border-cyan-900/30', 'text' => 'text-cyan-700 dark:text-cyan-400'],
      'export_report' => ['bg' => 'bg-indigo-50 dark:bg-indigo-950/25 border-indigo-100 dark:border-indigo-900/30', 'text' => 'text-indigo-700 dark:text-indigo-400'],
      'print_report' => ['bg' => 'bg-fuchsia-50 dark:bg-fuchsia-950/25 border-fuchsia-100 dark:border-fuchsia-900/30', 'text' => 'text-fuchsia-700 dark:text-fuchsia-400'],
      'api_change' => ['bg' => 'bg-slate-50 dark:bg-slate-800/40 border-slate-100 dark:border-slate-700/50', 'text' => 'text-slate-700 dark:text-slate-300'],
      'delete_ticket' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30', 'text' => 'text-red-700 dark:text-red-400'],
      'create_backup' => ['bg' => 'bg-emerald-50 dark:bg-emerald-950/25 border-emerald-100 dark:border-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400'],
      'restore_backup' => ['bg' => 'bg-orange-50 dark:bg-orange-950/25 border-orange-100 dark:border-orange-900/30', 'text' => 'text-orange-700 dark:text-orange-400'],
      'delete_backup' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30', 'text' => 'text-red-700 dark:text-red-400'],
    ];
    $actionLabels = [
      'login' => $isAr ? 'تسجيل دخول ناجح' : 'Successful Login',
      'login_failed' => $isAr ? 'محاولة دخول فاشلة' : 'Failed Login Attempt',
      'logout' => $isAr ? 'تسجيل خروج' : 'Logout',
      'create_user' => $isAr ? 'إنشاء مستخدم جديد' : 'Create User',
      'update_user' => $isAr ? 'تعديل بيانات المستخدم' : 'Update User',
      'change_user_password' => $isAr ? 'تغيير كلمة مرور المستخدم' : 'Change User Password',
      'delete_user' => $isAr ? 'حذف مستخدم' : 'Delete User',
      'activate_user' => $isAr ? 'تفعيل مستخدم' : 'Activate User',
      'deactivate_user' => $isAr ? 'تعطيل مستخدم' : 'Deactivate User',
      'create_survey' => $isAr ? 'إنشاء استبيان' : 'Create Survey',
      'update_survey' => $isAr ? 'تعديل استبيان' : 'Update Survey',
      'delete_survey' => $isAr ? 'حذف استبيان' : 'Delete Survey',
      'update_settings' => $isAr ? 'تحديث الإعدادات العامة' : 'Update Settings',
      'update_ticket' => $isAr ? 'تحديث حالة التذكرة' : 'Update Ticket',
      'delete_response' => $isAr ? 'حذف رد مريض' : 'Delete Patient Response',
      'export_responses' => $isAr ? 'تصدير الردود' : 'Export Responses',
      'export_report' => $isAr ? 'تصدير تقرير' : 'Export Report',
      'print_report' => $isAr ? 'طباعة تقرير' : 'Print Report',
      'api_change' => $isAr ? 'تغيير في النظام' : 'System Change',
      'delete_ticket' => $isAr ? 'حذف تذكرة' : 'Delete Ticket',
      'create_backup' => $isAr ? 'إنشاء نسخة احتياطية' : 'Create Backup',
      'restore_backup' => $isAr ? 'استعادة نسخة احتياطية' : 'Restore Backup',
      'delete_backup' => $isAr ? 'حذف نسخة احتياطية' : 'Delete Backup',
    ];
    $roleLabels = [
      'super_admin' => $isAr ? 'مدير عام' : 'Super Admin',
      'admin' => $isAr ? 'مدير نظام' : 'Admin',
      'unit_manager' => $isAr ? 'مدير وحدة' : 'Unit Manager',
      'head_of_department' => $isAr ? 'رئيس قسم' : 'Head of Department',
      'staff' => $isAr ? 'موظف' : 'Staff',
    ];
    $roleBadgeColors = [
      'super_admin' => 'text-purple-700 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/25 border-purple-200 dark:border-purple-900/30',
      'admin' => 'text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/25 border-blue-200 dark:border-blue-900/30',
      'head_of_department' => 'text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/25 border-indigo-200 dark:border-indigo-900/30',
      'staff' => 'text-teal-700 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/25 border-teal-200 dark:border-teal-900/30',
      'unit_manager' => 'text-cyan-700 dark:text-cyan-400 bg-cyan-50 dark:bg-cyan-950/25 border-cyan-200 dark:border-cyan-900/30',
    ];
    $hasActiveFilters = request('action') || request('start_date') || request('end_date');
    $searchPadding = $isAr ? 'pr-10 pl-4' : 'pl-10 pr-4';

    $translateDetails = function($details) use ($isAr, $roleLabels) {
        if (!$details) return '—';
        $decoded = json_decode($details, true);
        if ($decoded && isset($decoded['messageKey'])) {
            $key = $decoded['messageKey'];
            $rawParams = $decoded['params'] ?? [];
            
            // Translate key using Laravel translation (it will return the string with {{name}} placeholders)
            $template = __($key);
            
            // If the translation key doesn't exist, it returns the key itself.
            if ($template === $key) {
                return $key . ' ' . json_encode($rawParams, JSON_UNESCAPED_UNICODE);
            }
            
            $auditParamLabels = [
                'status' => [
                    'open' => $isAr ? 'مفتوحة' : 'Open',
                    'in_progress' => $isAr ? 'قيد المعالجة' : 'In Progress',
                    'resolved' => $isAr ? 'تم الحل' : 'Resolved',
                    'unchanged' => $isAr ? 'دون تغيير' : 'Unchanged',
                ],
                'format' => [
                    'pdf' => 'PDF',
                    'excel' => 'Excel',
                    'print' => $isAr ? 'طباعة' : 'Print',
                ],
                'dateRange' => [
                    'all' => $isAr ? 'كل الفترات' : 'All periods',
                    'week' => $isAr ? 'الأسبوع الماضي' : 'Last week',
                    'month' => $isAr ? 'الشهر الماضي' : 'Last month',
                    'quarter' => $isAr ? 'آخر 3 أشهر' : 'Last 3 months',
                    'custom' => $isAr ? 'فترة مخصصة' : 'Custom period',
                ],
                'reportType' => [
                    'executive' => $isAr ? 'الملخص التنفيذي' : 'Executive Summary',
                    'departments' => $isAr ? 'الأقسام' : 'Departments',
                    'categories' => $isAr ? 'التصنيفات' : 'Categories',
                    'tickets' => $isAr ? 'البلاغات' : 'Tickets',
                    'predictive' => $isAr ? 'التنبؤي' : 'Predictive',
                ],
                'department' => [
                    'all' => $isAr ? 'جميع الأقسام' : 'All Departments',
                ],
            ];
            
            $formatAuditParam = function($k, $v) use ($auditParamLabels, $isAr, $roleLabels) {
                if (!is_string($v) && !is_numeric($v)) return $v;
                $v = (string)$v;
                if ($k === 'id' && preg_match('/^[a-z0-9]{16,}$/i', $v)) {
                    return '#' . strtoupper(substr($v, -8));
                }
                if ($k === 'role') {
                    return $roleLabels[$v] ?? $v;
                }
                if (isset($auditParamLabels[$k][$v])) {
                    return $auditParamLabels[$k][$v];
                }
                $transKey = "audit_param_{$k}_{$v}";
                $transVal = __($transKey);
                if ($transVal !== $transKey) {
                    return $transVal;
                }
                return $v;
            };

            $params = [];
            foreach ($rawParams as $k => $v) {
                $params[$k] = $formatAuditParam($k, $v);
            }
            
            if ($key === 'audit.details.update_ticket' && empty($params['ticketCode']) && !empty($rawParams['id'])) {
                $params['ticketCode'] = $formatAuditParam('id', $rawParams['id']);
            }
            
            if (isset($params['role']) && isset($roleLabels[$rawParams['role']])) {
                $params['role'] = $roleLabels[$rawParams['role']];
            }
            
            $result = $template;
            foreach ($params as $k => $v) {
                $result = str_replace('{{' . $k . '}}', $v, $result);
            }
            return $result;
        }
        
        $result = $details;
        $replacements = [
            'in_progress' => $isAr ? 'قيد المعالجة' : 'In Progress',
            'resolved' => $isAr ? 'تم الحل' : 'Resolved',
            'open' => $isAr ? 'مفتوحة' : 'Open',
            'unchanged' => $isAr ? 'دون تغيير' : 'Unchanged',
        ];
        foreach ($replacements as $k => $v) {
            $result = preg_replace('/\b' . preg_quote($k, '/') . '\b/', $v, $result);
        }
        $result = preg_replace_callback('/\b[a-z0-9]{20,}\b/i', function($matches) {
            return '#' . strtoupper(substr($matches[0], -8));
        }, $result);
        
        return $result;
    };
  @endphp

  <div x-data="auditManagement()" class="text-start animate-fade-in" x-cloak>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <div class="flex items-center gap-3">
          <a
            href="{{ route('dashboard.index') }}"
            class="w-10 h-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-450 hover:border-teal-200 dark:hover:border-teal-900 hover:shadow-md transition-all cursor-pointer"
          >
            <i data-lucide="arrow-left" class="w-5 h-5 rtl:rotate-180"></i>
          </a>
          <div>
            <div class="flex items-center gap-2">
              <span class="p-1.5 bg-orange-100 dark:bg-orange-950/25 rounded-lg text-orange-600 dark:text-orange-400">
                <i data-lucide="shield" class="w-5 h-5"></i>
              </span>
              <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">{{ $isAr ? 'سجل العمليات والأمان' : 'Audit & Security Log' }}</h2>
            </div>
            <p class="text-xs text-gray-400 dark:text-slate-450 mt-1">{{ $isAr ? 'تتبع كافة الإجراءات والأنشطة داخل النظام' : 'Track all actions and activity inside the system' }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2 self-stretch sm:self-auto">
          <button
            onclick="window.location.reload()"
            type="button"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2 text-xs bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300 px-4 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-800 transition-all font-bold cursor-pointer"
          >
            <i data-lucide="refresh-cw" class="w-4 h-4 text-gray-400 dark:text-slate-500"></i>
            <span>{{ $isAr ? 'تحديث السجل' : 'Refresh Log' }}</span>
          </button>
        </div>
      </div>

      <!-- Stats Cards Section -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-teal-50 dark:bg-teal-950/20 border border-teal-100 dark:border-teal-900/30 rounded-xl flex items-center justify-center text-teal-600 dark:text-teal-400 shrink-0 shadow-sm">
            <i data-lucide="activity" class="w-6 h-6"></i>
          </div>
          <div>
            <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $totalLogs }}</div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-0.5">{{ $isAr ? 'إجمالي العمليات' : 'Total Operations' }}</p>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-purple-50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/30 rounded-xl flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0 shadow-sm">
            <i data-lucide="user-check" class="w-6 h-6"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-black text-gray-900 dark:text-white truncate">
              {{ $mostActiveUser && $mostActiveUser->user ? $mostActiveUser->user->name . ' (' . $mostActiveUser->cnt . ' ' . ($isAr ? 'عملية' : 'operations') . ')' : ($isAr ? 'لا يوجد' : 'None') }}
            </div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-1">{{ $isAr ? 'المستخدم الأكثر نشاطاً' : 'Most Active User' }}</p>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0 shadow-sm">
            <i data-lucide="shield-check" class="w-6 h-6"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-black text-gray-900 dark:text-white truncate">
              {{ $mostCommonAction ? ($actionLabels[$mostCommonAction->action] ?? $mostCommonAction->action) . ' (' . $mostCommonAction->cnt . ')' : ($isAr ? 'لا يوجد' : 'None') }}
            </div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-1">{{ $isAr ? 'الإجراء الأكثر شيوعاً' : 'Most Common Action' }}</p>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 rounded-xl flex items-center justify-center text-red-600 dark:text-red-450 shrink-0 shadow-sm">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
          </div>
          <div>
            <div class="text-2xl font-black text-red-700 dark:text-red-400">{{ $failedLogins }}</div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-0.5">{{ $isAr ? 'محاولات دخول فاشلة' : 'Failed Login Attempts' }}</p>
          </div>
        </div>
      </div>

      <!-- Graphical Dashboard & Analysis Row -->
      @if($trendData && count($trendData) > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          <!-- Trend Chart (Volume over time) -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm p-6 lg:col-span-2">
            <div class="flex items-center gap-2 mb-6 text-start">
              <i data-lucide="activity" class="w-5 h-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-bold text-gray-800 dark:text-white">{{ $isAr ? 'توزيع العمليات على مدار الوقت (30 يوماً)' : 'Operation Trend Over Time (30 days)' }}</h3>
            </div>
            <div id="trendChart" class="w-full min-h-[250px]"></div>
          </div>

          <!-- Action Types Distribution -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm p-6 text-start">
            <div class="flex items-center gap-2 mb-6">
              <i data-lucide="sliders-horizontal" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
              <h3 class="font-bold text-gray-800 dark:text-white">{{ $isAr ? 'توزيع العمليات المنجزة' : 'Completed Operations Distribution' }}</h3>
            </div>
            <div id="actionDistributionChart" class="w-full min-h-[220px]"></div>
          </div>
        </div>
      @endif

      <!-- Filter and Table Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden">
        
        <!-- Search and Filter Form -->
        <form method="GET" action="{{ route('dashboard.audit') }}">
          <!-- Filters Top Bar -->
          <div class="p-5 border-b border-gray-100 dark:border-slate-800/80 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-gray-50/50 dark:bg-slate-850/20" dir="rtl">
            
            <div class="flex-1 flex items-center gap-2 w-full">
              <!-- Search Input Container -->
              <div class="relative flex-1">
                <i data-lucide="search" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input
                  type="text"
                  name="search"
                  value="{{ request('search') }}"
                  placeholder="{{ $isAr ? 'البحث بالاسم أو اسم المستخدم أو تفاصيل العملية...' : 'Search by name, username, or operation details...' }}"
                  class="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-10 pl-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all text-start placeholder-gray-400 dark:placeholder-gray-550"
                />
              </div>
              <!-- Search Button -->
              <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded-xl text-sm font-bold transition-all shadow-sm cursor-pointer whitespace-nowrap">
                {{ $isAr ? 'بحث' : 'Search' }}
              </button>
            </div>

            <!-- Advanced Filters & Reset Button -->
            <div class="flex items-center gap-2 flex-wrap">
              <button
                @click="showFilters = !showFilters"
                type="button"
                :class="showFilters || {{ $hasActiveFilters ? 'true' : 'false' }} 
                  ? 'border-teal-200 dark:border-teal-900/30 bg-teal-50 dark:bg-teal-950/25 text-teal-700 dark:text-teal-400' 
                  : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-900 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-850'"
                class="flex items-center gap-2 text-sm px-4 py-2 rounded-xl border font-bold transition-all cursor-pointer whitespace-nowrap"
              >
                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                <span>{{ $isAr ? 'تصفية متقدمة' : 'Advanced Filters' }}</span>
              </button>

              @if (request('search') || request('action') || request('start_date') || request('end_date'))
                <a
                  href="{{ route('dashboard.audit') }}"
                  class="text-xs text-gray-500 dark:text-slate-400 hover:text-red-600 px-2 py-1 transition-all cursor-pointer whitespace-nowrap"
                >
                  {{ $isAr ? 'إعادة ضبط' : 'Reset' }}
                </a>
              @endif
            </div>

          </div>

          <!-- Advanced Filters Panel -->
          <div x-show="showFilters" x-cloak class="p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50/30 dark:bg-slate-900/20 grid grid-cols-1 md:grid-cols-3 gap-4 animate-slide-down">
            <!-- Action Filter -->
            <div>
              <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'نوع الإجراء' : 'Action Type' }}</label>
              <select
                name="action"
                onchange="this.form.submit()"
                class="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
              >
                <option value="">{{ $isAr ? 'جميع الإجراءات' : 'All Actions' }}</option>
                @foreach ($availableActions as $act)
                  <option value="{{ $act }}" @selected(request('action') === $act)>
                    {{ $actionLabels[$act] ?? $act }}
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Start Date -->
            <div>
              <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'من تاريخ' : 'From Date' }}</label>
              <div class="relative">
                <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input
                  type="date"
                  name="start_date"
                  value="{{ request('start_date') }}"
                  onchange="this.form.submit()"
                  class="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-9 pl-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
                />
              </div>
            </div>

            <!-- End Date -->
            <div>
              <label class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'إلى تاريخ' : 'To Date' }}</label>
              <div class="relative">
                <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input
                  type="date"
                  name="end_date"
                  value="{{ request('end_date') }}"
                  onchange="this.form.submit()"
                  class="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-9 pl-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
                />
              </div>
            </div>
          </div>
        </form>

        <!-- Audit Logs Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-right" dir="rtl">
            <thead>
              <tr class="border-b border-gray-100 dark:border-slate-800 bg-gray-50/20 dark:bg-slate-850/40">
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'المسؤول عن العملية' : 'Actor' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'نوع الإجراء' : 'Action Type' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'التفاصيل والوصف' : 'Details' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'الجهاز ومصدر الاتصال' : 'Device & Source' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'التاريخ والوقت' : 'Date & Time' }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800/80">
              @forelse ($logs as $log)
                @php
                  $badge = $actionBadges[$log->action] ?? ['bg' => 'border-gray-200 dark:border-slate-700', 'text' => 'text-gray-700 dark:text-slate-300'];
                  $roleBadge = $log->user && $log->user->role ? ($roleBadgeColors[$log->user->role] ?? '') : '';
                  $initial = $log->user ? mb_substr($log->user->name ?: $log->user->username, 0, 1) : 'S';
                  $details = $log->details;
                @endphp
                <tr class="border-b border-gray-50 dark:border-slate-800/80 hover:bg-gray-50/50 dark:hover:bg-slate-850/40 transition-colors">
                  <!-- User Column (Far Right) -->
                  <td class="py-3.5 px-5 text-sm whitespace-nowrap">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-linear-to-r from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">
                        @if ($log->user)
                          {{ mb_substr($log->user->name ?: $log->user->username, 0, 1) }}
                        @else
                          <i data-lucide="user" class="w-4 h-4"></i>
                        @endif
                      </div>
                      <div class="flex flex-col text-start">
                        <div class="flex items-center gap-2">
                          <span class="font-bold text-gray-900 dark:text-white">{{ $log->user?->name ?? ($isAr ? 'مستخدم غير معروف' : 'Unknown User') }}</span>
                          @if ($log->user && $log->user->role && isset($roleBadgeColors[$log->user->role]))
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded border {{ $roleBadgeColors[$log->user->role] }}">
                              {{ $roleLabels[$log->user->role] ?? $log->user->role }}
                            </span>
                          @endif
                        </div>
                        <span class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5 font-bold" dir="ltr">{{ '@' . ($log->user ? $log->user->username : 'system') }}</span>
                      </div>
                    </div>
                  </td>

                  <!-- Action Type Column -->
                  <td class="py-3.5 px-5 text-sm text-center whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border whitespace-nowrap {{ $badge['bg'] }} {{ $badge['text'] }}">
                      {{ $actionLabels[$log->action] ?? $log->action }}
                    </span>
                  </td>

                  <!-- Details Column -->
                  <td class="py-3.5 px-5 text-sm max-w-2xl text-start">
                    <p class="text-gray-700 dark:text-slate-300 leading-relaxed font-medium break-words text-xs text-start">
                      {{ $translateDetails($log->details) }}
                    </p>
                  </td>

                  <!-- Device Column -->
                  <td class="py-3.5 px-5 text-xs text-gray-500 dark:text-slate-400 min-w-40 text-start whitespace-nowrap">
                    <div class="flex items-start gap-2">
                      <i data-lucide="monitor-smartphone" class="w-4 h-4 text-teal-600 dark:text-teal-400 mt-0.5 shrink-0"></i>
                      <div class="space-y-1 text-start">
                        <div class="font-bold text-gray-700 dark:text-slate-300">
                          {{ $log->deviceName ?: ($isAr ? 'جهاز غير معروف' : 'Unknown Device') }}
                        </div>
                        <div class="font-mono text-[10px]" dir="ltr">
                          {{ $log->ipAddress ?: ($isAr ? 'IP غير معروف' : 'Unknown IP') }}
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Time Column (Far Left) -->
                  <td class="py-3.5 px-5 text-xs text-gray-400 dark:text-slate-500 font-bold whitespace-nowrap text-left" dir="ltr">
                    {{ $log->timestamp ? $log->timestamp->translatedFormat('Y/m/d, g:i:s A') : '—' }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="py-20 text-center">
                    <div class="max-w-md mx-auto flex flex-col items-center justify-center text-center">
                      <div class="w-16 h-16 bg-gray-50 dark:bg-slate-800/80 border border-gray-100 dark:border-slate-850 rounded-full flex items-center justify-center text-gray-300 dark:text-slate-650 mb-4 shadow-inner">
                        <i data-lucide="shield" class="w-8 h-8"></i>
                      </div>
                      <h3 class="text-base font-bold text-gray-800 dark:text-white mb-1">{{ $isAr ? 'لا توجد سجلات' : 'No Logs Found' }}</h3>
                      <p class="text-xs text-gray-400 dark:text-slate-450">{{ $isAr ? 'لم يتم العثور على أي عمليات مطابقة لمعايير البحث.' : 'No operations match the selected search criteria.' }}</p>
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <!-- Table Pagination Bar -->
        @if ($logs->hasPages())
          <div class="p-5 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between bg-gray-50/20 dark:bg-slate-850/10">
            <span class="text-xs text-gray-400 dark:text-slate-500 font-bold hidden sm:inline">
              {{ $isAr ? 'عرض الصفحة' : 'Showing page' }} <span class="text-gray-700 dark:text-slate-300 font-extrabold">{{ $logs->currentPage() }}</span> {{ $isAr ? 'من أصل' : 'of' }} <span class="text-gray-700 dark:text-slate-300 font-extrabold">{{ $logs->lastPage() }}</span> ({{ $isAr ? 'إجمالي' : 'total' }} {{ $logs->total() }} {{ $isAr ? 'سجل' : 'logs' }})
            </span>
            <div class="flex items-center gap-2">
              <a href="{{ $logs->previousPageUrl() }}" class="{{ $logs->onFirstPage() ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850' }} w-8 h-8 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 transition-all cursor-pointer shadow-sm">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
              </a>
              <a href="{{ $logs->nextPageUrl() }}" class="{{ !$logs->hasMorePages() ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850' }} w-8 h-8 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 transition-all cursor-pointer shadow-sm">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
              </a>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('auditManagement', () => ({
        showFilters: {{ $hasActiveFilters ? 'true' : 'false' }},
      }));
    });

    document.addEventListener('DOMContentLoaded', async function () {
        const ApexCharts = await window.loadApexCharts();
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#6b7280';
        const gridColor = isDark ? '#1e293b' : '#f3f4f6';
        
        const trendDataRaw = @json($trendData ?? []);
        const actionStatsRaw = @json($actionStats ? $actionStats->take(5) : []);
        const actionLabels = @json($actionLabels);

        // 1. Trend Area Chart (Enriched to show double series for deep operational & security insights)
        if (document.querySelector("#trendChart") && trendDataRaw.length > 0) {
            const trendOptions = {
                chart: {
                    type: 'area',
                    height: 250,
                    fontFamily: 'Cairo, sans-serif',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent'
                },
                series: [
                    {
                        name: '{{ $isAr ? "إجمالي العمليات" : "Total Operations" }}',
                        data: trendDataRaw.map(d => d.total)
                    },
                    {
                        name: '{{ $isAr ? "محاولات دخول فاشلة" : "Failed Logins" }}',
                        data: trendDataRaw.map(d => d.failed)
                    }
                ],
                xaxis: {
                    categories: trendDataRaw.map(d => d.formattedDate || d.date),
                    labels: { 
                        style: { colors: textColor, fontSize: '11px', fontFamily: 'Cairo' },
                        formatter: function(value, timestamp, opts) {
                            if (typeof opts === 'undefined' || !opts.w) {
                                return value;
                            }
                            const idx = opts.index;
                            const total = opts.w.globals.labels.length;
                            // Explicitly force show the first label, the last label (today's date), transition of a new month (starts with 01/ or 1/), and every 5th label
                            if (idx === 0 || idx === total - 1 || value.startsWith('01/') || value.startsWith('1/') || idx % 5 === 0) {
                                return value;
                            }
                            return '';
                        }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: { 
                        style: { colors: textColor, fontSize: '11px', fontFamily: 'Cairo' }
                    }
                },
                grid: {
                    borderColor: gridColor,
                    strokeDashArray: 3,
                    yaxis: { lines: { show: true } },
                    xaxis: { lines: { show: false } }
                },
                dataLabels: { enabled: false },
                stroke: { 
                    curve: 'smooth', 
                    width: [3, 2],
                    dashArray: [0, 4]
                },
                colors: ['#0d9488', '#e11d48'], // Teal for general, Rose/Red for failed security logins
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: [0.35, 0.2],
                        opacityTo: [0.02, 0.01],
                        stops: [0, 100]
                    }
                },
                legend: {
                    show: true,
                    position: 'top',
                    horizontalAlign: 'right', // Align legend to the right to flow beautifully in RTL
                    fontFamily: 'Cairo',
                    fontSize: '12px',
                    labels: { colors: textColor },
                    markers: { radius: 12 }
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: { formatter: (val) => val + ' {{ $isAr ? "عملية" : "operations" }}' }
                }
            };
            const trendChart = new ApexCharts(document.querySelector("#trendChart"), trendOptions);
            trendChart.render();
        }

        // 2. Action Distribution Bar Chart
        if (document.querySelector("#actionDistributionChart") && actionStatsRaw.length > 0) {
            const actionOptions = {
                chart: {
                    type: 'bar',
                    height: 220,
                    fontFamily: 'Cairo, sans-serif',
                    toolbar: { show: false },
                    background: 'transparent'
                },
                series: [{
                    name: '{{ $isAr ? "العمليات" : "Operations" }}',
                    data: actionStatsRaw.map(d => d.count)
                }],
                xaxis: {
                    categories: actionStatsRaw.map(d => actionLabels[d.action] || d.action),
                    labels: { style: { colors: textColor, fontSize: '10px', fontFamily: 'Cairo' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: { 
                        style: { colors: textColor, fontSize: '11px', fontFamily: 'Cairo' }
                    }
                },
                grid: {
                    borderColor: gridColor,
                    strokeDashArray: 3,
                    yaxis: { lines: { show: true } },
                    xaxis: { lines: { show: false } }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '40%',
                        distributed: true,
                    }
                },
                colors: ['#0d9488', '#6366f1', '#e11d48', '#d97706', '#9333ea'],
                dataLabels: { enabled: false },
                legend: { show: false },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: { formatter: (val) => val + ' {{ $isAr ? "عملية" : "operations" }}' }
                }
            };
            const actionChart = new ApexCharts(document.querySelector("#actionDistributionChart"), actionOptions);
            actionChart.render();
        }
    });
  </script>
@endsection
