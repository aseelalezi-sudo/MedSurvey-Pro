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

  <div x-data="auditManagement({
    showFilters: @js((bool) $hasActiveFilters),
    route: @js(route('dashboard.audit')),
    isRtl: @js($isAr),
    texts: {
      totalOperations: @js($isAr ? 'إجمالي العمليات' : 'Total Operations'),
      failedLogins: @js($isAr ? 'محاولات دخول فاشلة' : 'Failed Logins'),
      operations: @js($isAr ? 'عملية' : 'operations'),
      legendAlign: @js($isAr ? 'right' : 'left'),
      loadError: @js($isAr ? 'حدث خطأ أثناء تحميل السجلات.' : 'An error occurred while loading logs.'),
      noLogs: @js($isAr ? 'لا توجد سجلات' : 'No Logs Found'),
      noLogsHint: @js($isAr ? 'لم يتم العثور على أي عمليات مطابقة لمعايير البحث.' : 'No operations match the selected search criteria.'),
      unknownUser: @js($isAr ? 'مستخدم غير معروف' : 'Unknown User'),
      unknownDevice: @js($isAr ? 'جهاز غير معروف' : 'Unknown Device'),
      unknownIp: @js($isAr ? 'IP غير معروف' : 'Unknown IP'),
      changesCount: @js($isAr ? 'عدد التغييرات' : 'changes'),
      viewChangeDetails: @js($isAr ? 'عرض تفاصيل التغيير' : 'View change details'),
      showingPage: @js($isAr ? 'عرض الصفحة' : 'Showing page'),
      of: @js($isAr ? 'من أصل' : 'of'),
      total: @js($isAr ? 'إجمالي' : 'total'),
      logs: @js($isAr ? 'سجل' : 'logs'),
      noChangeDetails: @js($isAr ? 'لا توجد تفاصيل تغيير مسجلة لهذه العملية.' : 'No change details were recorded for this operation.'),
      changedFields: @js($isAr ? 'عدد الحقول المتغيرة' : 'Changed fields'),
      field: @js($isAr ? 'الحقل' : 'Field'),
      before: @js($isAr ? 'قبل' : 'Before'),
      after: @js($isAr ? 'بعد' : 'After'),
    },
    trendData: @js($trendData ?? []),
    actionStats: @js($actionStats ? $actionStats->take(5)->values() : []),
    actionLabels: @js($actionLabels),
    roleLabels: @js($roleLabels),
    messageLabels: @js(collect([
      'update_survey', 'create_survey', 'delete_survey', 'update_ticket', 'create_ticket', 'delete_ticket',
      'login', 'login_failed', 'logout', 'update_settings', 'update_profile', 'update_password',
      'create_user', 'update_user', 'delete_user', 'export', 'report_generated', 'view_report', 'download_report',
    ])->mapWithKeys(fn ($key) => ['audit.details.'.$key => __('audit.details.'.$key)])),
    roleBadgeColors: @js($roleBadgeColors),
    actionBadges: @js($actionBadges),
    settingLabels: @js($auditSettingLabels),
    pagination: {
      page: {{ $logs->currentPage() }},
      totalPages: {{ $logs->lastPage() }},
      total: {{ $logs->total() }},
    },
  })" class="text-start animate-fade-in" x-cloak>
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
          <table class="w-full {{ $isAr ? 'text-right' : 'text-left' }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
            <thead>
              <tr class="border-b border-gray-100 dark:border-slate-800 bg-gray-50/20 dark:bg-slate-850/40">
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">{{ $isAr ? 'المسؤول عن العملية' : 'Actor' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">{{ $isAr ? 'نوع الإجراء' : 'Action Type' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">{{ $isAr ? 'التفاصيل والوصف' : 'Details' }}</th>
                <th class="text-start py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">{{ $isAr ? 'الجهاز ومصدر الاتصال' : 'Device & Source' }}</th>
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
                  <td class="py-3.5 px-5 text-sm">
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
                  <td class="py-3.5 px-5 text-sm text-center">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border whitespace-nowrap {{ $badge['bg'] }} {{ $badge['text'] }}">
                      {{ $actionLabels[$log->action] ?? $log->action }}
                    </span>
                  </td>

                  <!-- Details Column -->
                  <td class="py-3.5 px-5 text-sm text-start">
                    <div style="max-width: 350px; overflow-wrap: anywhere; word-break: break-word; white-space: normal;">
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
                  <td class="py-3.5 px-5 text-xs text-gray-500 dark:text-slate-400 text-start break-words">
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

@endsection
