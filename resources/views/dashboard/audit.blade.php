<![CDATA[@extends('layouts.dashboard')

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
      'delete_backup' => ['bg' => 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30', 'text' => 'text-red-700 dark:text-red-400'],
      'server_backup_restore' => ['bg' => 'bg-amber-50 dark:bg-amber-950/25 border-amber-100 dark:border-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400'],
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
      'delete_backup' => $isAr ? 'حذف نسخة احتياطية' : 'Delete Backup',
      'server_backup_restore' => $isAr ? 'استعادة قاعدة البيانات (خادم الإنتاج)' : 'Server Database Restore',
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
    $auditSettingLabels = $isAr ? [
      'user.username' => 'اسم المستخدم',
      'user.name' => 'الاسم',
      'user.email' => 'البريد الإلكتروني',
      'user.role' => 'الدور',
      'user.department' => 'القسم',
      'user.isActive' => 'حالة المستخدم',
      'user.password' => 'كلمة المرور',
      'survey.title' => 'عنوان الاستبيان',
      'survey.description' => 'وصف الاستبيان',
      'survey.isActive' => 'حالة الاستبيان',
      'survey.requireName' => 'إلزام اسم المراجع',
      'survey.requirePhone' => 'إلزام رقم الجوال',
      'survey.assignedDepartments' => 'الأقسام المرتبطة',
      'survey.tips' => 'النصائح',
      'survey.sections' => 'أقسام الاستبيان',
      'survey.sections.title' => 'عنوان القسم',
      'survey.sections.description' => 'وصف القسم',
      'survey.sections.icon' => 'أيقونة القسم',
      'survey.sections.sortOrder' => 'ترتيب القسم',
      'survey.questions' => 'أسئلة الاستبيان',
      'survey.questions.type' => 'نوع السؤال',
      'survey.questions.title' => 'نص السؤال',
      'survey.questions.description' => 'وصف السؤال',
      'survey.questions.required' => 'السؤال إلزامي',
      'survey.questions.category' => 'تصنيف السؤال',
      'survey.questions.options' => 'خيارات السؤال',
      'survey.questions.followUp' => 'إعدادات السؤال التابع',
      'survey.questions.sortOrder' => 'ترتيب السؤال',
      'ticket.status' => 'حالة التذكرة',
      'ticket.resolutionNotes' => 'ملاحظات الحل',
      'ticket.assignedTo' => 'مسندة إلى',
      'ticket.resolvedAt' => 'وقت الحل',
      'hospital.name' => 'اسم المستشفى',
      'hospital.shortName' => 'الاسم المختصر',
      'hospital.logo' => 'شعار المستشفى',
      'hospital.address' => 'العنوان',
      'hospital.phone' => 'رقم الهاتف',
      'hospital.email' => 'البريد الإلكتروني',
      'hospital.website' => 'الموقع الإلكتروني',
      'hospital.description' => 'وصف المستشفى',
      'hospital.workingHours' => 'ساعات العمل',
      'hospital.operatingTitle' => 'العنوان التشغيلي',
      'hospital.welcomeMessage' => 'رسالة الترحيب',
      'departments' => 'الأقسام',
      'departments.name' => 'اسم القسم',
      'departments.color' => 'لون القسم',
      'departments.isActive' => 'حالة القسم',
      'ageGroups' => 'الفئات العمرية',
      'ageGroups.label' => 'اسم الفئة العمرية',
      'ageGroups.isActive' => 'حالة الفئة العمرية',
      'visitTypes' => 'أنواع الزيارة',
      'visitTypes.label' => 'اسم نوع الزيارة',
      'visitTypes.isActive' => 'حالة نوع الزيارة',
      'surveySettings.allowAnonymous' => 'السماح بالاستبيانات المجهولة',
      'surveySettings.requireAllQuestions' => 'إلزام جميع الأسئلة',
      'surveySettings.requireName' => 'إلزام اسم المراجع',
      'surveySettings.requirePhone' => 'إلزام رقم الجوال',
      'surveySettings.showProgressBar' => 'إظهار شريط التقدم',
      'surveySettings.enableThankYouPage' => 'تفعيل صفحة الشكر',
      'surveySettings.thankYouMessage' => 'رسالة الشكر',
      'appearance.primaryColor' => 'اللون الأساسي',
      'appearance.secondaryColor' => 'اللون الثانوي',
      'appearance.fontFamily' => 'نوع الخط',
      'appearance.showLanguageToggle' => 'إظهار تبديل اللغة',
      'backupSettings.schedule' => 'وقت النسخ الاحتياطي',
      'backupSettings.retentionDays' => 'مدة الاحتفاظ بالنسخ',
      'backupSettings.compressGzip' => 'ضغط النسخ الاحتياطية',
      'backupSettings.backupDir' => 'مسار النسخ الاحتياطي',
      'archiveSettings.enabled' => 'تفعيل الأرشفة التلقائية',
      'archiveSettings.schedule' => 'وقت الأرشفة',
      'archiveSettings.retentionYears' => 'مدة الاحتفاظ بالبيانات',
    ] : [
      'user.username' => 'Username',
      'user.name' => 'Name',
      'user.email' => 'Email',
      'user.role' => 'Role',
      'user.department' => 'Department',
      'user.isActive' => 'User status',
      'user.password' => 'Password',
      'survey.title' => 'Survey title',
      'survey.description' => 'Survey description',
      'survey.isActive' => 'Survey status',
      'survey.requireName' => 'Require visitor name',
      'survey.requirePhone' => 'Require phone number',
      'survey.assignedDepartments' => 'Assigned departments',
      'survey.tips' => 'Tips',
      'survey.sections' => 'Survey sections',
      'survey.sections.title' => 'Section title',
      'survey.sections.description' => 'Section description',
      'survey.sections.icon' => 'Section icon',
      'survey.sections.sortOrder' => 'Section order',
      'survey.questions' => 'Survey questions',
      'survey.questions.type' => 'Question type',
      'survey.questions.title' => 'Question text',
      'survey.questions.description' => 'Question description',
      'survey.questions.required' => 'Required question',
      'survey.questions.category' => 'Question category',
      'survey.questions.options' => 'Question options',
      'survey.questions.followUp' => 'Follow-up settings',
      'survey.questions.sortOrder' => 'Question order',
      'ticket.status' => 'Ticket status',
      'ticket.resolutionNotes' => 'Resolution notes',
      'ticket.assignedTo' => 'Assigned to',
      'ticket.resolvedAt' => 'Resolved at',
      'hospital.name' => 'Hospital name',
      'hospital.shortName' => 'Short name',
      'hospital.logo' => 'Hospital logo',
      'hospital.address' => 'Address',
      'hospital.phone' => 'Phone number',
      'hospital.email' => 'Email address',
      'hospital.website' => 'Website',
      'hospital.description' => 'Hospital description',
      'hospital.workingHours' => 'Working hours',
      'hospital.operatingTitle' => 'Operating title',
      'hospital.welcomeMessage' => 'Welcome message',
      'departments' => 'Departments',
      'departments.name' => 'Department name',
      'departments.color' => 'Department color',
      'departments.isActive' => 'Department status',
      'ageGroups' => 'Age groups',
      'ageGroups.label' => 'Age group label',
      'ageGroups.isActive' => 'Age group status',
      'visitTypes' => 'Visit types',
      'visitTypes.label' => 'Visit type label',
      'visitTypes.isActive' => 'Visit type status',
      'surveySettings.allowAnonymous' => 'Allow anonymous surveys',
      'surveySettings.requireAllQuestions' => 'Require all questions',
      'surveySettings.requireName' => 'Require visitor name',
      'surveySettings.requirePhone' => 'Require phone number',
      'surveySettings.showProgressBar' => 'Show progress bar',
      'surveySettings.enableThankYouPage' => 'Enable thank-you page',
      'surveySettings.thankYouMessage' => 'Thank-you message',
      'appearance.primaryColor' => 'Primary color',
      'appearance.secondaryColor' => 'Secondary color',
      'appearance.fontFamily' => 'Font family',
      'appearance.showLanguageToggle' => 'Show language toggle',
      'backupSettings.schedule' => 'Backup schedule',
      'backupSettings.retentionDays' => 'Backup retention days',
      'backupSettings.compressGzip' => 'Compress backups',
      'backupSettings.backupDir' => 'Backup directory',
      'archiveSettings.enabled' => 'Automatic archiving',
      'archiveSettings.schedule' => 'Archive schedule',
      'archiveSettings.retentionYears' => 'Data retention years',
    ];
    $hasActiveFilters = request('action') || request('start_date') || request('end_date');
    $searchPadding = $isAr ? 'pr-10 pl-4' : 'pl-10 pr-4';
    $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
    $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];

    $translateDetails = function($details) use ($isAr, $roleLabels) {
        if (!$details) return '—';
        $decoded = json_decode($details, true);
        if ($decoded && isset($decoded['messageKey'])) {
            $key = $decoded['messageKey'];
            $rawParams = $decoded['params'] ?? [];
            
            // Translate key using Laravel translation (it will return the string with {{name}} placeholders)
            $template = __($key);
            
            // If the translation key doesn't exist, format it like AJAX
            if ($template === $key) {
                $paramStr = collect($rawParams)
                    ->reject(fn($v, $k) => in_array($k, ['changes', 'settingsChanges', 'method', 'path', 'status']) || is_array($v) || is_object($v) || $v === null)
                    ->implode(', ');
                return $paramStr ? $key . ': ' . $paramStr : $key;
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
                if (is_array($v)) {
                    continue;
                }
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
                // Replace placeholders
                $result = preg_replace('/\{\{\s*' . preg_quote($k, '/') . '\s*\}\}/', $v, $result);
                $result = preg_replace('/:' . preg_quote($k, '/') . '\b/', $v, $result);
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
            <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="w-5 h-5"></i>
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
            onclick="refreshAuditLogs()"
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
            <div class="stat-number text-2xl font-black text-gray-900 dark:text-white" title="{{ $formatNumber($totalLogs) }}">{{ $compactNumber($totalLogs) }}</div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-0.5">{{ $isAr ? 'إجمالي العمليات' : 'Total Operations' }}</p>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-purple-50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/30 rounded-xl flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0 shadow-sm">
            <i data-lucide="user-check" class="w-6 h-6"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-black text-gray-900 dark:text-white truncate">
              {{ $mostActiveUser && $mostActiveUser->user ? $mostActiveUser->user->name . ' (' . $compactNumber($mostActiveUser->cnt) . ' ' . ($isAr ? 'عملية' : 'operations') . ')' : ($isAr ? 'لا يوجد' : 'None') }}
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
              {{ $mostCommonAction ? ($actionLabels[$mostCommonAction->action] ?? $mostCommonAction->action) . ' (' . $compactNumber($mostCommonAction->cnt) . ')' : ($isAr ? 'لا يوجد' : 'None') }}
            </div>
            <p class="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-1">{{ $isAr ? 'الإجراء الأكثر شيوعاً' : 'Most Common Action' }}</p>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div class="w-12 h-12 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 rounded-xl flex items-center justify-center text-red-600 dark:text-red-450 shrink-0 shadow-sm">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
          </div>
          <div>
            <div class="stat-number text-2xl font-black text-red-700 dark:text-red-400" title="{{ $formatNumber($failedLogins) }}">{{ $compactNumber($failedLogins) }}</div>
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
      <div class="relative z-0 bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden">
        
        <!-- Filters Bar - NO FORM, use AJAX -->
        <div class="p-5 border-b border-gray-100 dark:border-slate-800/80 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-gray-50/50 dark:bg-slate-850/20" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
          
          <div class="flex-1 flex items-center gap-2 min-w-0">
            <!-- Search Input Container -->
            <div class="relative flex-1">
              <i data-lucide="search" class="absolute {{ $isAr ? 'right-3.5' : 'left-3.5' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
              <input
                type="text"
                id="audit-search-input"
                name="search"
                aria-label="{{ $isAr ? 'البحث' : 'Search' }}"
                value="{{ request('search') }}"
                placeholder="{{ $isAr ? 'البحث بالاسم أو اسم المستخدم أو تفاصيل العملية...' : 'Search by name, username, or operation details...' }}"
                class="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl {{ $isAr ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all text-start placeholder-gray-400 dark:placeholder-gray-550"
              />
            </div>
            <!-- Search Button -->
            <button type="button" onclick="handleAuditSearch()" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded-xl text-sm font-bold transition-all shadow-sm cursor-pointer whitespace-nowrap">
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

            <button
              onclick="resetAuditFilters()"
              type="button"
              class="text-xs text-gray-500 dark:text-slate-400 hover:text-red-600 px-2 py-1 transition-all cursor-pointer whitespace-nowrap"
            >
              {{ $isAr ? 'إعادة ضبط' : 'Reset' }}
            </button>
          </div>

        </div>

        <!-- Advanced Filters Panel -->
        <div x-show="showFilters" x-cloak class="p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50/30 dark:bg-slate-900/20 grid grid-cols-1 md:grid-cols-3 gap-4 animate-slide-down">
          <!-- Action Filter -->
          <div>
            <label for="audit-action-filter" class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'نوع الإجراء' : 'Action Type' }}</label>
            <select
              id="audit-action-filter"
              onchange="handleAuditFilterChange()"
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
            <label for="audit-start-date" class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'من تاريخ' : 'From Date' }}</label>
            <div class="relative">
              <div class="flex min-h-[36px] w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-900 transition dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-gray-400 dark:text-slate-500"></i>
                <span class="font-mono text-sm font-bold" dir="ltr" id="audit-start-date-label">{{ request('start_date') ?: 'YYYY-MM-DD' }}</span>
              </div>
              <input
                type="date"
                id="audit-start-date"
                value="{{ request('start_date') }}"
                max="{{ now()->toDateString() }}"
                dir="ltr"
                lang="en-CA"
                aria-label="{{ $isAr ? 'من تاريخ' : 'From Date' }}"
                onchange="handleAuditFilterChange()"
                onclick="typeof this.showPicker === 'function' ? this.showPicker() : null"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
              />
            </div>
          </div>

          <!-- End Date -->
          <div>
            <label for="audit-end-date" class="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">{{ $isAr ? 'إلى تاريخ' : 'To Date' }}</label>
            <div class="relative">
              <div class="flex min-h-[36px] w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-900 transition dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <i data-lucide="calendar" class="h-4 w-4 shrink-0 text-gray-400 dark:text-slate-500"></i>
                <span class="font-mono text-sm font-bold" dir="ltr" id="audit-end-date-label">{{ request('end_date') ?: 'YYYY-MM-DD' }}</span>
              </div>
              <input
                type="date"
                id="audit-end-date"
                value="{{ request('end_date') }}"
                max="{{ now()->toDateString() }}"
                dir="ltr"
                lang="en-CA"
                aria-label="{{ $isAr ? 'إلى تاريخ' : 'To Date' }}"
                onchange="handleAuditFilterChange()"
                onclick="typeof this.showPicker === 'function' ? this.showPicker() : null"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
              />
            </div>
          </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="overflow-x-auto">
          <table class="w-full min-w-max {{ $isAr ? 'text-right' : 'text-left' }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
            <thead>
              <tr class="border-b border-gray-100 dark:border-slate-800 bg-gray-50/20 dark:bg-slate-850/40">
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'المسؤول عن العملية' : 'Actor' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'نوع الإجراء' : 'Action Type' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'التفاصيل والوصف' : 'Details' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'الجهاز ومصدر الاتصال' : 'Device & Source' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider whitespace-nowrap">{{ $isAr ? 'التاريخ والوقت' : 'Date & Time' }}</th>
              </tr>
            </thead>
            <tbody id="audit-logs-tbody" class="divide-y divide-gray-100 dark:divide-slate-800/80">
              @forelse ($logs as $log)
                @php
                  $badge = $actionBadges[$log->action] ?? ['bg' => 'border-gray-200 dark:border-slate-700', 'text' => 'text-gray-700 dark:text-slate-300'];
                  $roleBadge = $log->user && $log->user->role ? ($roleBadgeColors[$log->user->role] ?? '') : '';
                  $initial = $log->user ? mb_substr($log->user->name ?: $log->user->username, 0, 1) : 'S';
                  $details = $log->details;
                  $detailsPayload = json_decode((string) $log->details, true);
                  $auditChanges = $detailsPayload['params']['changes'] ?? ($detailsPayload['params']['settingsChanges'] ?? []);
                  $auditChangeCount = is_array($auditChanges) ? count($auditChanges) : 0;
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
                    <div class="max-w-[200px] sm:max-w-[300px] md:max-w-md lg:max-w-xl" style="overflow-wrap: anywhere; word-break: break-word; white-space: normal;">
                      <p class="text-gray-700 dark:text-slate-300 leading-relaxed font-medium text-xs text-start">
                        {{ $translateDetails($log->details) }}
                        @if($auditChangeCount > 0)
                          ({{ $isAr ? 'عدد التغييرات' : 'changes' }}: {{ $compactNumber($auditChangeCount) }})
                        @endif
                      </p>
                    </div>
                    @if($auditChangeCount > 0)
                      <button
                        type="button"
                        data-changes="{{ rawurlencode(json_encode($auditChanges, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
                        onclick="openAuditSettingsDetailsFromButton(this)"
                        class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-teal-100 bg-teal-50 px-2.5 py-1 text-[10px] font-black text-teal-700 transition hover:border-teal-200 hover:bg-teal-100 dark:border-teal-900/40 dark:bg-teal-950/20 dark:text-teal-300 dark:hover:bg-teal-950/35"
                      >
                        <i data-lucide="list-tree" class="h-3.5 w-3.5"></i>
                        <span>{{ $isAr ? 'عرض تفاصيل التغيير' : 'View change details' }}</span>
                        <span class="stat-badge rounded-full bg-white px-1.5 py-0.5 text-[9px] text-teal-700 dark:bg-slate-900 dark:text-teal-300" title="{{ $formatNumber($auditChangeCount) }}">{{ $compactNumber($auditChangeCount) }}</span>
                      </button>
                    @endif
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
                <tr id="audit-no-logs-row">
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
        <div id="audit-pagination-bar" class="p-5 border-t border-gray-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 bg-gray-50/20 dark:bg-slate-850/10">
          <span id="audit-pagination-info" class="text-xs text-gray-400 dark:text-slate-500 font-bold hidden sm:block">
            {{ $isAr ? 'عرض الصفحة' : 'Showing page' }} <span class="stat-number-tight text-gray-700 dark:text-slate-300 font-extrabold" title="{{ $formatNumber($logs->currentPage()) }}">{{ $compactNumber($logs->currentPage()) }}</span> {{ $isAr ? 'من أصل' : 'of' }} <span class="stat-number-tight text-gray-700 dark:text-slate-300 font-extrabold" title="{{ $formatNumber($logs->lastPage()) }}">{{ $compactNumber($logs->lastPage()) }}</span> ({{ $isAr ? 'إجمالي' : 'total' }} <span class="stat-number-tight" title="{{ $formatNumber($logs->total()) }}">{{ $compactNumber($logs->total()) }}</span> {{ $isAr ? 'سجل' : 'logs' }})
          </span>
          <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 sm:pb-0 scrollbar-hide max-w-full">
              <button
                id="audit-prev-page"
                onclick="handleAuditPageChange({{ $logs->currentPage() - 1 }})"
                class="{{ $logs->onFirstPage() ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850' }} w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 transition-all cursor-pointer shadow-sm shrink-0"
              >
                <i data-lucide="{{ $isAr ? 'chevron-right' : 'chevron-left' }}" class="w-4 h-4"></i>
              </button>

              <div id="audit-pagination-numbers" class="flex items-center gap-1.5 shrink-0">
                <!-- JS will inject numbers here -->
              </div>

              <button
                id="audit-next-page"
                onclick="handleAuditPageChange({{ $logs->currentPage() + 1 }})"
                class="{{ !$logs->hasMorePages() ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850' }} w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 transition-all cursor-pointer shadow-sm shrink-0"
              >
                <i data-lucide="{{ $isAr ? 'chevron-left' : 'chevron-right' }}" class="w-4 h-4"></i>
              </button>
            </div>
            
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600 dark:text-slate-400 font-medium whitespace-nowrap">
                {{ $isAr ? 'انتقل لصفحة:' : 'Go to page:' }}
              </span>
              <input 
                type="number" 
                id="audit-page-jump-input"
                name="audit-page-jump"
                aria-label="{{ $isAr ? 'انتقل لصفحة' : 'Go to page' }}"
                min="1" 
                max="{{ $logs->lastPage() }}" 
                class="w-16 h-9 px-2 text-center text-sm font-bold rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition-colors appearance-none"
                placeholder="#"
                onkeydown="if(event.key === 'Enter') handleAuditPageChange(this.value)"
              >
              <button 
                type="button" 
                onclick="handleAuditPageChange(document.getElementById('audit-page-jump-input').value)"
                class="flex items-center justify-center px-3 h-9 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-colors text-sm font-bold shadow-sm cursor-pointer"
              >
                {{ $isAr ? 'انتقال' : 'Go' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div
    id="audit-settings-details-modal"
    class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
    onclick="if (event.target === this) closeAuditSettingsDetails()"
  >
    <div class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
      <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-800">
        <div class="min-w-0 text-start">
          <h3 class="text-base font-black text-gray-900 dark:text-white">{{ $isAr ? 'تفاصيل التغيير' : 'Change Details' }}</h3>
          <p class="mt-1 text-xs font-bold text-gray-400 dark:text-slate-500">{{ $isAr ? 'مقارنة القيم قبل العملية وبعدها' : 'Before and after values for this operation' }}</p>
        </div>
        <button
          type="button"
          onclick="closeAuditSettingsDetails()"
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-100 text-gray-400 transition hover:bg-gray-50 hover:text-gray-700 dark:border-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
        >
          <i data-lucide="x" class="h-4 w-4"></i>
        </button>
      </div>
      <div id="audit-settings-details-body" class="overflow-y-auto p-5"></div>
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
                    horizontalAlign: '{{ $isAr ? "right" : "left" }}',
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

    // ========== AJAX Audit Log Functions ==========
    const auditLogRoute = '{{ route('dashboard.audit') }}';
    const isAuditRtl = {{ $isAr ? 'true' : 'false' }};
    let currentAuditPage = 1;

    const auditActionLabels = @json($actionLabels);
    const auditRoleLabels = @json($roleLabels);
    const auditMessageLabels = {
        'audit.details.update_survey': @json(__('audit.details.update_survey')),
        'audit.details.create_survey': @json(__('audit.details.create_survey')),
        'audit.details.delete_survey': @json(__('audit.details.delete_survey')),
        'audit.details.update_ticket': @json(__('audit.details.update_ticket')),
        'audit.details.create_ticket': @json(__('audit.details.create_ticket')),
        'audit.details.delete_ticket': @json(__('audit.details.delete_ticket')),
        'audit.details.login': @json(__('audit.details.login')),
        'audit.details.login_failed': @json(__('audit.details.login_failed')),
        'audit.details.logout': @json(__('audit.details.logout')),
        'audit.details.update_settings': @json(__('audit.details.update_settings')),
        'audit.details.update_profile': @json(__('audit.details.update_profile')),
        'audit.details.update_password': @json(__('audit.details.update_password')),
        'audit.details.create_user': @json(__('audit.details.create_user')),
        'audit.details.update_user': @json(__('audit.details.update_user')),
        'audit.details.delete_user': @json(__('audit.details.delete_user')),
        'audit.details.export': @json(__('audit.details.export')),
        'audit.details.report_generated': @json(__('audit.details.report_generated')),
        'audit.details.view_report': @json(__('audit.details.view_report')),
        'audit.details.download_report': @json(__('audit.details.download_report')),
    };
    const auditRoleBadgeColors = @json($roleBadgeColors);
    const auditActionBadges = @json($actionBadges);
    const auditSettingLabels = @json($auditSettingLabels);
    const formatAuditNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));
    const compactAuditNumber = (value) => {
        const number = Number(value || 0);
        const abs = Math.abs(number);

        if (abs >= 1000000) {
            return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
        }

        if (abs >= 1000) {
            return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
        }

        return formatAuditNumber(number);
    };

    function getAuditFilters() {
        const search = document.getElementById('audit-search-input')?.value || '';
        const action = document.getElementById('audit-action-filter')?.value || '';
        const startDate = document.getElementById('audit-start-date')?.value || '';
        const endDate = document.getElementById('audit-end-date')?.value || '';
        return { search, action, start_date: startDate, end_date: endDate };
    }

    function handleAuditSearch() {
        currentAuditPage = 1;
        fetchAuditLogs();
    }

    function handleAuditFilterChange() {
        // Update date labels
        const startDate = document.getElementById('audit-start-date');
        const endDate = document.getElementById('audit-end-date');
        document.getElementById('audit-start-date-label').textContent = startDate.value || 'YYYY-MM-DD';
        document.getElementById('audit-end-date-label').textContent = endDate.value || 'YYYY-MM-DD';
        
        currentAuditPage = 1;
        fetchAuditLogs();
    }

    function handleAuditPageChange(page) {
        if (page < 1) return;
        currentAuditPage = page;
        fetchAuditLogs();
    }

    function resetAuditFilters() {
        document.getElementById('audit-search-input').value = '';
        document.getElementById('audit-action-filter').value = '';
        document.getElementById('audit-start-date').value = '';
        document.getElementById('audit-end-date').value = '';
        document.getElementById('audit-start-date-label').textContent = 'YYYY-MM-DD';
        document.getElementById('audit-end-date-label').textContent = 'YYYY-MM-DD';
        currentAuditPage = 1;
        fetchAuditLogs();
    }

    function refreshAuditLogs() {
        currentAuditPage = 1;
        fetchAuditLogs();
    }

    function fetchAuditLogs() {
        const tbody = document.getElementById('audit-logs-tbody');
        if (!tbody) return;

        // Show loading state
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-12">
                    <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mx-auto"></div>
                </td>
            </tr>
        `;

        const filters = getAuditFilters();
        const params = new URLSearchParams({
            ajax: 'true',
            page: currentAuditPage,
            ...(filters.search ? { search: filters.search } : {}),
            ...(filters.action ? { action: filters.action } : {}),
            ...(filters.start_date ? { start_date: filters.start_date } : {}),
            ...(filters.end_date ? { end_date: filters.end_date } : {}),
        });

        fetch(`${auditLogRoute}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Connection failed');
            return res.json();
        })
        .then(data => {
            renderAuditTableRows(data.logs);
            renderAuditPagination(data.pagination);
        })
        .catch(err => {
            console.error('Failed to load audit logs:', err);
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-12 text-rose-500 font-bold">
                        {{ $isAr ? 'حدث خطأ أثناء تحميل السجلات.' : 'An error occurred while loading logs.' }}
                    </td>
                </tr>
            `;
        });
    }

    function renderAuditTableRows(logs) {
        const tbody = document.getElementById('audit-logs-tbody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = `
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
            `;
            return;
        }

        tbody.innerHTML = logs.map(log => {
            const badge = auditActionBadges[log.action] || { bg: 'border-gray-200 dark:border-slate-700', text: 'text-gray-700 dark:text-slate-300' };
            const user = log.user || null;
            const userName = user ? (user.name || user.username || '{{ $isAr ? 'مستخدم غير معروف' : 'Unknown User' }}') : '{{ $isAr ? 'مستخدم غير معروف' : 'Unknown User' }}';
            const username = user ? user.username : 'system';
            const initial = user ? (user.name || user.username || 'S').charAt(0) : 'S';
            const role = user && user.role ? user.role : null;
            const roleBadgeColor = role ? (auditRoleBadgeColors[role] || '') : '';
            const roleLabel = role ? (auditRoleLabels[role] || role) : '';
            
            const actionLabel = auditActionLabels[log.action] || log.action;
            const deviceName = log.deviceName || '{{ $isAr ? 'جهاز غير معروف' : 'Unknown Device' }}';
            const ipAddress = log.ipAddress || '{{ $isAr ? 'IP غير معروف' : 'Unknown IP' }}';
            
            // Format timestamp
            let timestamp = '—';
            if (log.timestamp) {
                const date = new Date(log.timestamp);
                timestamp = date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' }) 
                    + ', ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            }

            // Translate details (client-side approximation for AJAX)
            let detailsText = log.details || '—';
            let auditChanges = [];
            try {
                const decoded = JSON.parse(log.details);
                if (decoded && decoded.messageKey) {
                    auditChanges = Array.isArray(decoded.params?.changes)
                        ? decoded.params.changes
                        : (Array.isArray(decoded.params?.settingsChanges) ? decoded.params.settingsChanges : []);
                    
                    let template = auditMessageLabels[decoded.messageKey] || decoded.messageKey;
                    
                    if (template !== decoded.messageKey && decoded.params) {
                        for (const [k, v] of Object.entries(decoded.params)) {
                            // Replace placeholders
                            const regexBraces = new RegExp('\\{\\{\\s*' + k + '\\s*\\}\\}', 'g');
                            const regexColon = new RegExp(':' + k + '\\b', 'g');
                            template = template.replace(regexBraces, String(v)).replace(regexColon, String(v));
                        }
                        detailsText = template;
                    } else if (template === decoded.messageKey && decoded.params) {
                        const paramStr = Object.entries(decoded.params)
                            .filter(([key, value]) => !['changes', 'settingsChanges', 'method', 'path', 'status'].includes(key) && !Array.isArray(value) && value !== null && typeof value !== 'object')
                            .map(([, value]) => value)
                            .join(', ');
                        if (paramStr) detailsText = decoded.messageKey + ': ' + paramStr;
                        else detailsText = decoded.messageKey;
                    } else {
                        detailsText = decoded.messageKey;
                    }
                    
                    if (auditChanges.length > 0) {
                        detailsText += ` ({{ $isAr ? 'عدد التغييرات' : 'changes' }}: ${compactAuditNumber(auditChanges.length)})`;
                    }
                }
            } catch (e) {
                // Use raw details string
            }

            const settingsDetailsButton = auditChanges.length > 0
                ? `<button type="button" data-changes="${encodeURIComponent(JSON.stringify(auditChanges))}" onclick="openAuditSettingsDetailsFromButton(this)" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-teal-100 bg-teal-50 px-2.5 py-1 text-[10px] font-black text-teal-700 transition hover:border-teal-200 hover:bg-teal-100 dark:border-teal-900/40 dark:bg-teal-950/20 dark:text-teal-300 dark:hover:bg-teal-950/35"><i data-lucide="list-tree" class="h-3.5 w-3.5"></i><span>{{ $isAr ? 'عرض تفاصيل التغيير' : 'View change details' }}</span><span class="stat-badge rounded-full bg-white px-1.5 py-0.5 text-[9px] text-teal-700 dark:bg-slate-900 dark:text-teal-300" title="${formatAuditNumber(auditChanges.length)}">${compactAuditNumber(auditChanges.length)}</span></button>`
                : '';

            return `
                <tr class="border-b border-gray-50 dark:border-slate-800/80 hover:bg-gray-50/50 dark:hover:bg-slate-850/40 transition-colors">
                    <td class="py-3.5 px-5 text-sm whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-linear-to-r from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">
                                ${user ? initial : '<i data-lucide="user" class="w-4 h-4"></i>'}
                            </div>
                            <div class="flex flex-col text-start">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900 dark:text-white">${escapeAuditHtml(userName)}</span>
                                    ${role && roleBadgeColor ? `<span class="text-[9px] font-extrabold px-2 py-0.5 rounded border ${roleBadgeColor}">${escapeAuditHtml(roleLabel)}</span>` : ''}
                                </div>
                                <span class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5 font-bold" dir="ltr">@${escapeAuditHtml(username)}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-3.5 px-5 text-sm text-center whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border whitespace-nowrap ${badge.bg} ${badge.text}">
                            ${escapeAuditHtml(actionLabel)}
                        </span>
                    </td>
                    <td class="py-3.5 px-5 text-sm max-w-2xl text-start">
                        <div class="max-w-[200px] sm:max-w-[300px] md:max-w-md lg:max-w-xl" style="overflow-wrap: anywhere; word-break: break-word; white-space: normal;">
                            <p class="text-gray-700 dark:text-slate-300 leading-relaxed font-medium text-xs text-start">
                                ${escapeAuditHtml(detailsText)}
                            </p>
                        </div>
                        ${settingsDetailsButton}
                    </td>
                    <td class="py-3.5 px-5 text-xs text-gray-500 dark:text-slate-400 min-w-40 text-start whitespace-nowrap">
                        <div class="flex items-start gap-2">
                            <i data-lucide="monitor-smartphone" class="w-4 h-4 text-teal-600 dark:text-teal-400 mt-0.5 shrink-0"></i>
                            <div class="space-y-1 text-start">
                                <div class="font-bold text-gray-700 dark:text-slate-300">${escapeAuditHtml(deviceName)}</div>
                                <div class="font-mono text-[10px]" dir="ltr">${escapeAuditHtml(ipAddress)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3.5 px-5 text-xs text-gray-400 dark:text-slate-500 font-bold whitespace-nowrap text-left" dir="ltr">
                        ${escapeAuditHtml(timestamp)}
                    </td>
                </tr>
            `;
        }).join('');

        // Re-initialize lucide icons for new rows
        if (window.lucide && window.lucide.createIcons) {
            window.lucide.createIcons();
        }
    }

    function renderAuditPagination(pagination) {
        const bar = document.getElementById('audit-pagination-bar');
        if (!bar) return;

        const infoEl = document.getElementById('audit-pagination-info');
        if (infoEl) {
            const infoText = isAuditRtl
                ? `{{ $isAr ? 'عرض الصفحة' : 'Showing page' }} <span class="text-gray-700 dark:text-slate-300 font-extrabold" title="${formatAuditNumber(pagination.page)}">${compactAuditNumber(pagination.page)}</span> {{ $isAr ? 'من أصل' : 'of' }} <span class="text-gray-700 dark:text-slate-300 font-extrabold" title="${formatAuditNumber(pagination.totalPages)}">${compactAuditNumber(pagination.totalPages)}</span> ({{ $isAr ? 'إجمالي' : 'total' }} <span title="${formatAuditNumber(pagination.total)}">${compactAuditNumber(pagination.total)}</span> {{ $isAr ? 'سجل' : 'logs' }})`
                : `Showing page <span class="text-gray-700 dark:text-slate-300 font-extrabold" title="${formatAuditNumber(pagination.page)}">${compactAuditNumber(pagination.page)}</span> of <span class="text-gray-700 dark:text-slate-300 font-extrabold" title="${formatAuditNumber(pagination.totalPages)}">${compactAuditNumber(pagination.totalPages)}</span> (total <span title="${formatAuditNumber(pagination.total)}">${compactAuditNumber(pagination.total)}</span> logs)`;
            infoEl.innerHTML = infoText;
        }

        const prevBtn = document.getElementById('audit-prev-page');
        const nextBtn = document.getElementById('audit-next-page');

        if (prevBtn) {
            prevBtn.onclick = () => handleAuditPageChange(pagination.page - 1);
            prevBtn.classList.toggle('opacity-40', pagination.page <= 1);
            prevBtn.classList.toggle('cursor-not-allowed', pagination.page <= 1);
            prevBtn.classList.toggle('pointer-events-none', pagination.page <= 1);
        }

        if (nextBtn) {
            nextBtn.onclick = () => handleAuditPageChange(pagination.page + 1);
            nextBtn.classList.toggle('opacity-40', pagination.page >= pagination.totalPages);
            nextBtn.classList.toggle('cursor-not-allowed', pagination.page >= pagination.totalPages);
            nextBtn.classList.toggle('pointer-events-none', pagination.page >= pagination.totalPages);
        }

        const numbersContainer = document.getElementById('audit-pagination-numbers');
        if (numbersContainer) {
            let html = '';
            const currentPage = pagination.page;
            const lastPage = pagination.totalPages;
            
            const addPage = (p) => {
                if (p === currentPage) {
                    html += `<span aria-current="page" class="shrink-0"><span class="flex items-center justify-center w-9 h-9 rounded-xl bg-linear-to-r from-teal-500 to-emerald-600 text-white text-sm font-bold shadow-md shadow-teal-200 dark:shadow-none">${p}</span></span>`;
                } else {
                    html += `<button type="button" onclick="handleAuditPageChange(${p})" class="flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-300 dark:hover:border-teal-700 transition-all shadow-sm text-sm font-bold shrink-0 cursor-pointer">${p}</button>`;
                }
            };
            
            const addDots = () => {
                html += `<span class="shrink-0"><span class="flex items-center justify-center w-9 h-9 text-sm font-bold text-gray-400 dark:text-slate-500">...</span></span>`;
            };

            if (lastPage <= 7) {
                for (let i = 1; i <= lastPage; i++) addPage(i);
            } else {
                if (currentPage <= 4) {
                    for (let i = 1; i <= 5; i++) addPage(i);
                    addDots();
                    addPage(lastPage);
                } else if (currentPage >= lastPage - 3) {
                    addPage(1);
                    addDots();
                    for (let i = lastPage - 4; i <= lastPage; i++) addPage(i);
                } else {
                    addPage(1);
                    addDots();
                    addPage(currentPage - 1);
                    addPage(currentPage);
                    addPage(currentPage + 1);
                    addDots();
                    addPage(lastPage);
                }
            }
            numbersContainer.innerHTML = html;
        }

        // Hide pagination bar if no pages
        if (pagination.totalPages <= 1) {
            bar.classList.add('hidden');
            bar.classList.remove('flex');
        } else {
            bar.classList.remove('hidden');
            bar.classList.add('flex');
        }

        const jumpInput = document.getElementById('audit-page-jump-input');
        if (jumpInput) {
            jumpInput.max = pagination.totalPages;
            jumpInput.value = '';
        }
    }

    function escapeAuditHtml(text) {
        if (!text) return '';
        const map = {
            '&': '\u0026amp;',
            '<': '\u0026lt;',
            '>': '\u0026gt;',
            '"': '\u0026quot;',
            "'": '\u0026#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function getAuditSettingLabel(item) {
        const path = String(item?.path || '');
        if (path && auditSettingLabels[path]) {
            return auditSettingLabels[path];
        }

        const parts = path.split('.').filter(Boolean);
        if (parts[0] === 'survey') {
            const field = parts[parts.length - 1];
            if (parts.includes('questions') && auditSettingLabels[`survey.questions.${field}`]) {
                return auditSettingLabels[`survey.questions.${field}`];
            }

            if (parts.includes('sections') && auditSettingLabels[`survey.sections.${field}`]) {
                return auditSettingLabels[`survey.sections.${field}`];
            }
        }

        if (parts.length >= 2) {
            const collectionField = `${parts[0]}.${parts[parts.length - 1]}`;
            if (auditSettingLabels[collectionField]) {
                return auditSettingLabels[collectionField];
            }

            const parentPath = parts.slice(0, 2).join('.');
            if (auditSettingLabels[parentPath]) {
                return auditSettingLabels[parentPath];
            }
        }

        if (parts.length >= 1 && auditSettingLabels[parts[0]]) {
            return auditSettingLabels[parts[0]];
        }

        return item?.label || path || '-';
    }

    function formatAuditChangeValue(value, path = '') {
        const text = String(value ?? '—');
        const normalized = text.toLowerCase();

        if (normalized === 'true') {
            return isAuditRtl ? 'مفعل / نعم' : 'Enabled / Yes';
        }

        if (normalized === 'false') {
            return isAuditRtl ? 'معطل / لا' : 'Disabled / No';
        }

        if (text === '[protected]') {
            return isAuditRtl ? 'قيمة محمية' : 'Protected value';
        }

        if (text === '[changed]') {
            return isAuditRtl ? 'تم التغيير' : 'Changed';
        }

        if (text === '[embedded image]' || text === '[stored image]') {
            return isAuditRtl ? 'صورة محفوظة' : 'Stored image';
        }

        return text;
    }

    window.openAuditSettingsDetailsFromButton = function (button) {
        try {
            const encoded = button?.dataset?.changes || '%5B%5D';
            const changes = JSON.parse(decodeURIComponent(encoded));
            openAuditSettingsDetails(changes);
        } catch (error) {
            console.error('Failed to parse audit settings changes', error);
            openAuditSettingsDetails([]);
        }
    };

    window.openAuditSettingsDetails = function (changes) {
        const modal = document.getElementById('audit-settings-details-modal');
        const body = document.getElementById('audit-settings-details-body');
        if (!modal || !body) return;

        const list = Array.isArray(changes) ? changes : [];
        if (list.length === 0) {
            body.innerHTML = `
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-5 text-center text-sm font-bold text-gray-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
                    {{ $isAr ? 'لا توجد تفاصيل تغيير مسجلة لهذه العملية.' : 'No change details were recorded for this operation.' }}
                </div>
            `;
        } else {
            body.innerHTML = `
                <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-teal-100 bg-teal-50 px-4 py-3 text-start dark:border-teal-900/40 dark:bg-teal-950/20">
                    <span class="text-xs font-black text-teal-700 dark:text-teal-300">{{ $isAr ? 'عدد الحقول المتغيرة' : 'Changed fields' }}</span>
                    <span class="stat-badge rounded-full bg-white px-2 py-1 text-xs font-black text-teal-700 dark:bg-slate-900 dark:text-teal-300" title="${formatAuditNumber(list.length)}">${compactAuditNumber(list.length)}</span>
                </div>
                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-slate-800">
                    <table class="w-full min-w-[760px] border-collapse text-xs">
                        <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:bg-slate-950 dark:text-slate-500">
                            <tr>
                                <th class="w-[30%] px-3 py-2 text-start">{{ $isAr ? 'الحقل' : 'Field' }}</th>
                                <th class="w-[35%] px-3 py-2 text-start">{{ $isAr ? 'قبل' : 'Before' }}</th>
                                <th class="w-[35%] px-3 py-2 text-start">{{ $isAr ? 'بعد' : 'After' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                            ${list.map((item) => `
                                <tr class="align-top">
                                    <td class="bg-gray-50/60 px-3 py-3 font-black text-gray-700 dark:bg-slate-950/40 dark:text-slate-200">
                                        <div class="break-words">${escapeAuditHtml(getAuditSettingLabel(item))}</div>
                                        <div class="mt-1 break-all font-mono text-[10px] font-bold text-gray-400 dark:text-slate-500" dir="ltr">${escapeAuditHtml(item.path || '')}</div>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-slate-300">
                                        <div class="max-h-32 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-red-50/60 p-2 leading-5 dark:bg-red-950/10">${escapeAuditHtml(formatAuditChangeValue(item.before, item.path))}</div>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-slate-300">
                                        <div class="max-h-32 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-emerald-50/70 p-2 leading-5 dark:bg-emerald-950/10">${escapeAuditHtml(formatAuditChangeValue(item.after, item.path))}</div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    };

    window.closeAuditSettingsDetails = function () {
        const modal = document.getElementById('audit-settings-details-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    };

    document.addEventListener('DOMContentLoaded', () => {
        renderAuditPagination({
            page: {{ $logs->currentPage() }},
            totalPages: {{ $logs->lastPage() }},
            total: {{ $logs->total() }}
        });
    });
  </script>
@endsection]]>
