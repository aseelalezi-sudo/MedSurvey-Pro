@extends('layouts.dashboard')

@section('title', __('manage_users').' - MedSurvey Pro')

@section('dashboard')
  @php
    $isAr = app()->getLocale() === 'ar';
    $roleLabels = [
      'super_admin' => __('role_super_admin'),
      'admin' => __('role_admin'),
      'unit_manager' => __('role_unit_manager'),
      'head_of_department' => __('role_head'),
      'staff' => __('role_staff'),
    ];
    $shortRoleLabels = [
      'super_admin' => __('role_super_admin'),
      'admin' => __('role_admin'),
      'unit_manager' => __('role_unit_manager'),
      'head_of_department' => __('role_head'),
      'staff' => __('role_staff'),
    ];
    $ui = [
      'pageTitle' => $isAr ? 'إدارة الحسابات والمستخدمين' : 'Account & User Management',
      'registeredUsers' => $isAr ? 'مستخدم مسجل' : 'registered users',
      'newUser' => $isAr ? 'مستخدم جديد' : 'New User',
      'newShort' => $isAr ? 'جديد' : 'New',
      'searchPlaceholder' => $isAr ? 'بحث بالاسم أو اسم المستخدم...' : 'Search by name or username...',
      'allRoles' => $isAr ? 'جميع الأدوار' : 'All Roles',
      'errorsTitle' => $isAr ? 'يوجد بعض الأخطاء:' : 'Some errors were found:',
      'active' => $isAr ? 'نشط' : 'Active',
      'inactive' => $isAr ? 'معطل' : 'Disabled',
      'notAvailable' => $isAr ? 'غير متوفر' : 'Not available',
      'lastLogin' => $isAr ? 'آخر دخول' : 'Last login',
      'edit' => $isAr ? 'تعديل' : 'Edit',
      'changePassword' => __('user_password_modal_title'),
      'deactivate' => $isAr ? 'تعطيل' : 'Deactivate',
      'activate' => $isAr ? 'تفعيل' : 'Activate',
      'delete' => $isAr ? 'حذف' : 'Delete',
      'noUsers' => $isAr ? 'لا يوجد مستخدمون مطابقون لبحثك.' : 'No users match your search.',
      'editUser' => $isAr ? 'تعديل المستخدم' : 'Edit User',
      'createUser' => $isAr ? 'إضافة مستخدم جديد' : 'Add New User',
      'fullName' => $isAr ? 'الاسم الكامل' : 'Full Name',
      'namePlaceholder' => $isAr ? 'اكتب الاسم هنا' : 'Enter full name',
      'username' => $isAr ? 'اسم الدخول (Username)' : 'Username',
      'email' => $isAr ? 'البريد الإلكتروني' : 'Email',
      'password' => $isAr ? 'كلمة المرور' : 'Password',
      'strongPassword' => $isAr ? 'أدخل كلمة مرور قوية' : 'Enter a strong password',
      'role' => $isAr ? 'الصلاحية' : 'Role',
      'linkedDepartment' => $isAr ? 'القسم المرتبط' : 'Linked Department',
      'noDepartment' => $isAr ? 'لا يوجد قسم' : 'No Department',
      'rolePermissions' => $isAr ? 'صلاحيات هذا الدور:' : 'Role permissions:',
      'cancel' => $isAr ? 'إلغاء' : 'Cancel',
      'saveChanges' => $isAr ? 'حفظ التعديلات' : 'Save Changes',
      'addUser' => $isAr ? 'إضافة المستخدم' : 'Add User',
      'yourPassword' => $isAr ? 'كلمة المرور الخاصة بك' : 'Your password',
      'currentPasswordConfirm' => $isAr ? 'أدخل كلمة مرور حسابك لتأكيد العملية' : 'Enter your account password to confirm',
      'deleteTitle' => $isAr ? 'تأكيد حذف المستخدم' : 'Confirm User Deletion',
      'deleteDesc' => $isAr ? 'هل أنت متأكد من رغبتك بحذف هذا المستخدم نهائياً؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to permanently delete this user? This action cannot be undone.',
      'deleteForever' => $isAr ? 'حذف نهائي' : 'Delete Permanently',
    ];
    $rolePermissionLines = [
      'super_admin' => [
        ['ok' => true, 'text' => $isAr ? 'وصول كامل لكافة إعدادات وبيانات النظام' : 'Full access to all system settings and data'],
        ['ok' => true, 'text' => $isAr ? 'إدارة جميع المستخدمين' : 'Manage all users'],
      ],
      'admin' => [
        ['ok' => true, 'text' => $isAr ? 'إدارة الاستبيانات وإرسالها' : 'Manage and send surveys'],
        ['ok' => true, 'text' => $isAr ? 'الاطلاع على جميع التقارير وتصديرها' : 'View and export all reports'],
        ['ok' => false, 'text' => $isAr ? 'لا يمكنه إدارة المستخدمين والمدراء' : 'Cannot manage users and admins'],
      ],
      'head_of_department' => [
        ['ok' => true, 'text' => $isAr ? 'الاطلاع على تقارير وتذاكر القسم الخاص به فقط' : 'View only their department reports and tickets'],
        ['ok' => false, 'text' => $isAr ? 'لا يمكنه إدارة أو إرسال الاستبيانات' : 'Cannot manage or send surveys'],
      ],
      'unit_manager' => [
        ['ok' => true, 'text' => $isAr ? 'الاطلاع على كافة التقارير للوحدة وتصديرها' : 'View and export all unit reports'],
        ['ok' => false, 'text' => $isAr ? 'لا يمكنه التعديل أو إرسال الاستبيانات' : 'Cannot edit or send surveys'],
      ],
      'staff' => [
        ['ok' => false, 'text' => $isAr ? 'وصول محدود للتقارير بناءً على القسم' : 'Limited report access based on department'],
        ['ok' => false, 'text' => $isAr ? 'لا يمكنه إدارة أي إعدادات أو استبيانات' : 'Cannot manage settings or surveys'],
      ],
    ];
    $roleColors = [
      'super_admin' => 'from-purple-500 to-indigo-500',
      'admin' => 'from-blue-500 to-cyan-500',
      'unit_manager' => 'from-teal-500 to-cyan-600',
      'head_of_department' => 'from-green-500 to-emerald-500',
      'staff' => 'from-amber-500 to-orange-500',
    ];
    $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
    $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];

    $permTranslations = $isAr ? [
      'groups' => [
        'dashboard' => 'لوحة القيادة',
        'surveys' => 'الاستبيانات',
        'responses' => 'الاستجابات',
        'tickets' => 'التذاكر',
        'reports' => 'التقارير والتحليلات',
        'predictive' => 'التحليل التنبؤي',
        'hall-of-fame' => 'لوحة الشرف',
        'users' => 'المستخدمون',
        'settings' => 'الإعدادات',
        'operations' => 'العمليات والسجلات',
      ],
      'perms' => [
        'dashboard.view' => 'عرض لوحة القيادة',
        'surveys.view' => 'عرض الاستبيانات',
        'surveys.create' => 'إنشاء استبيان',
        'surveys.update' => 'تعديل الاستبيان',
        'surveys.delete' => 'حذف الاستبيان',
        'surveys.duplicate' => 'نسخ الاستبيان',
        'surveys.toggle-status' => 'تفعيل/إيقاف الاستبيان',
        'responses.view' => 'عرض الاستجابات',
        'responses.view-contact' => 'عرض بيانات التواصل',
        'responses.export' => 'تصدير الاستجابات',
        'responses.print' => 'طباعة الاستجابات',
        'tickets.view' => 'عرض التذاكر',
        'tickets.update' => 'تحديث التذكرة',
        'tickets.delete' => 'حذف التذكرة',
        'tickets.change-status' => 'تغيير حالة التذكرة',
        'tickets.add-note' => 'إضافة ملاحظة',
        'tickets.assign' => 'تعيين التذكرة',
        'reports.view' => 'عرض التقارير',
        'predictive.view' => 'عرض التحليلات التنبؤية',
        'predictive.manage' => 'إدارة التحليلات التنبؤية',
        'hall-of-fame.view' => 'عرض لوحة الشرف',
        'users.view' => 'عرض المستخدمين',
        'users.create' => 'إضافة مستخدم',
        'users.update' => 'تعديل مستخدم',
        'users.delete' => 'حذف مستخدم',
        'users.manage-roles' => 'إدارة الأدوار',
        'users.manage-permissions' => 'تعديل الصلاحيات المباشرة',
        'settings.view' => 'عرض الإعدادات',
        'settings.update' => 'تعديل الإعدادات',
        'operations.audit-logs.view' => 'سجلات النظام',
        'operations.monitoring.view' => 'مراقبة النظام',
        'operations.error-logs.view' => 'سجلات الأخطاء',
        'operations.error-logs.delete' => 'مسح سجلات الأخطاء',
        'operations.backups.view' => 'عرض النسخ الاحتياطية',
        'operations.backups.create' => 'إنشاء نسخة',
        'operations.backups.delete' => 'حذف النسخة',
        'operations.backups.download' => 'تحميل النسخة',
      ]
    ] : [];
  @endphp

  <div x-data='userManagement({ isAr: @json($isAr), rolePermissions: @json($rolePermissions) })' class="text-start animate-fade-in" x-cloak>
    <div x-show="toast.show" x-transition.opacity.duration.300ms class="fixed top-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-2xl border px-6 py-3 text-sm font-bold shadow-xl"
         :class="toast.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300'" style="display: none;">
      <i data-lucide="check-circle-2" x-show="toast.type === 'success'" class="h-5 w-5"></i>
      <i data-lucide="alert-circle" x-show="toast.type === 'error'" class="h-5 w-5"></i>
      <span x-text="toast.message"></span>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-linear-to-r from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-200 dark:shadow-none">
            <i data-lucide="users" class="w-5 h-5 text-white"></i>
          </div>
          <div class="flex flex-col gap-0.5">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ $ui['pageTitle'] }}</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400">
              <span class="stat-number-tight" title="{{ $formatNumber($users->total()) }}">{{ $compactNumber($users->total()) }}</span> {{ $ui['registeredUsers'] }}
            </p>
          </div>
        </div>
        @can('users.create')
        <button
          @click="openCreateModal()"
          type="button"
          class="flex items-center gap-2 bg-linear-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:shadow-lg transition-all cursor-pointer"
        >
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span class="hidden sm:inline">{{ $ui['newUser'] }}</span>
          <span class="sm:hidden">{{ $ui['newShort'] }}</span>
        </button>
        @endcan
      </div>

      <!-- Filters -->
      <form id="usersFilterForm" method="GET" action="{{ route('dashboard.users') }}" @submit.prevent="submitUserFilters()" class="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="absolute {{ $isAr ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
          <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            @keydown.enter.prevent="submitUserFilters()"
            placeholder="{{ $ui['searchPlaceholder'] }}"
            class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white placeholder-gray-500"
          />
        </div>
        <div class="flex items-center gap-2">
          <i data-lucide="shield" class="w-4 h-4 text-gray-400"></i>
          <select
            name="role"
            @change="submitUserFilters()"
            class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white"
          >
            <option value="">{{ $ui['allRoles'] }}</option>
            @foreach($shortRoleLabels as $role => $label)
              <option value="{{ $role }}" @selected(request('role') === $role)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="hidden"></button>
      </form>

      <div id="users-content" class="relative">
        <div x-show="isRefreshing" x-cloak class="absolute inset-0 z-20 flex items-start justify-center rounded-3xl bg-white/60 pt-16 backdrop-blur-[1px] dark:bg-slate-950/55">
          <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-purple-600 dark:text-purple-400"></i>
        </div>

      <!-- Error / Success Alerts -->
      @if ($errors->any())
        <div class="mb-4 flex flex-col gap-1 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">
          <div class="flex items-center gap-2 font-bold mb-1">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
            {{ $ui['errorsTitle'] }}
          </div>
          <ul class="list-disc list-inside space-y-1 pr-6">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      @if (session('error'))
        <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">
          <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
          {{ session('error') }}
        </div>
      @endif
      @if (session('success'))
        <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-600 text-sm">
          <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
          {{ session('success') }}
        </div>
      @endif

      <!-- Users Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($users as $i => $user)
          @php
            $roleColorClass = $roleColors[$user->role] ?? 'from-gray-500 to-slate-500';
            $initial = mb_substr($user->name ?: $user->username, 0, 1);
            
            $userPayload = [
              'id' => $user->id,
              'name' => $user->name,
              'username' => $user->username,
              'email' => $user->email,
              'role' => $user->role,
              'department' => $user->department,
              'isActive' => (bool) $user->isActive,
              'permissions' => $user->permissions->pluck('name')->toArray(),
            ];
          @endphp
          <div
            class="h-full bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col {{ !$user->isActive ? 'opacity-60' : '' }}"
          >
            <!-- User Header -->
            <div class="p-5 bg-linear-to-r {{ $roleColorClass }} text-white relative">
              <div class="absolute top-3 {{ $isAr ? 'left-3' : 'right-3' }}">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $user->isActive ? 'bg-white/20' : 'bg-red-500/50' }}">
                  {{ $user->isActive ? $ui['active'] : $ui['inactive'] }}
                </span>
              </div>
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center text-2xl font-bold">
                  {{ $initial }}
                </div>
                <div class="flex-1 min-w-0 flex flex-col items-start text-start">
                  <h3 class="font-bold text-lg truncate">{{ $user->name }}</h3>
                  <p class="text-white/70 text-sm truncate">{{ '@' . $user->username }}</p>
                </div>
              </div>
            </div>

            <!-- User Details -->
            <div class="p-4 space-y-3">
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-300">
                <i data-lucide="mail" class="w-4 h-4 text-gray-400"></i>
                <span class="truncate">{{ $user->email ?: $ui['notAvailable'] }}</span>
              </div>
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-300">
                <i data-lucide="shield" class="w-4 h-4 text-gray-400 shrink-0"></i>
                <div class="flex items-center gap-2 flex-1 min-w-0 justify-start">
                  @php
                    $roleTextClass = 'text-gray-600 dark:text-slate-300';
                    if (str_contains($roleColorClass, 'purple')) $roleTextClass = 'text-purple-600 dark:text-purple-400';
                    elseif (str_contains($roleColorClass, 'blue')) $roleTextClass = 'text-blue-600 dark:text-blue-400';
                    elseif (str_contains($roleColorClass, 'green')) $roleTextClass = 'text-green-600 dark:text-green-400';
                    elseif (str_contains($roleColorClass, 'amber')) $roleTextClass = 'text-amber-600 dark:text-amber-400';
                  @endphp
                  <span class="font-medium truncate text-start {{ $roleTextClass }}">
                    {{ $roleLabels[$user->role] ?? $user->role }}
                  </span>
                  @if($user->role === 'head_of_department' && $user->department)
                    <span class="text-gray-300 dark:text-slate-600 shrink-0">•</span>
                    <i data-lucide="building-2" class="w-4 h-4 text-gray-400 shrink-0"></i>
                    <span class="truncate text-gray-600 dark:text-slate-300 text-start">{{ __($user->department) }}</span>
                  @endif
                </div>
              </div>
              <div class="min-h-5 flex items-center gap-2 text-xs text-gray-400 dark:text-slate-500">
                @if($user->lastLogin)
                  <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                  <span>{{ $ui['lastLogin'] }}: {{ $user->lastLogin->format('Y-m-d H:i') }}</span>
                @else
                  <i data-lucide="calendar" class="w-3.5 h-3.5 invisible"></i>
                @endif
              </div>
            </div>

            <!-- Actions -->
            <div class="px-4 pb-4 flex items-center gap-2 mt-auto">
              @can('users.update')
              <button
                type="button"
                @click="openEditModal({{ json_encode($userPayload) }})"
                @disabled(auth()->user()->role !== 'super_admin' && $user->role === 'super_admin')
                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-750 rounded-xl transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                {{ $ui['edit'] }}
              </button>
              
              <button
                type="button"
                @click="openPasswordModal({{ json_encode($userPayload) }})"
                @disabled(auth()->user()->role !== 'super_admin' && $user->role === 'super_admin')
                class="flex items-center justify-center p-2 rounded-xl bg-indigo-100 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-950/50 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                title="{{ $ui['changePassword'] }}"
              >
                <i data-lucide="key-round" class="w-4 h-4"></i>
              </button>
              @endcan

              @if($user->id !== auth()->id() && (auth()->user()->role === 'super_admin' || $user->role !== 'super_admin'))
                @can('users.update')
                <form method="POST" action="{{ route('dashboard.users.toggle', $user->id) }}" @submit.prevent="submitUserAction($event.target, '{{ $user->isActive ? ($isAr ? 'تم تعطيل المستخدم' : 'User deactivated') : ($isAr ? 'تم تفعيل المستخدم' : 'User activated') }}')" class="inline-block m-0">
                  @csrf
                  @method('PATCH')
                  <button
                    type="submit"
                    class="flex items-center justify-center p-2 rounded-xl transition-colors cursor-pointer {{ $user->isActive ? 'bg-amber-100 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-950/50' : 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-950/50' }}"
                    title="{{ $user->isActive ? $ui['deactivate'] : $ui['activate'] }}"
                  >
                    <i data-lucide="{{ $user->isActive ? 'user-x' : 'user-check' }}" class="w-4 h-4"></i>
                  </button>
                </form>
                @endcan
                @can('users.delete')
                <button
                  type="button"
                  @click="openDeleteModal('{{ $user->id }}')"
                  class="flex items-center justify-center p-2 rounded-xl bg-red-100 dark:bg-red-950/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-950/50 transition-colors cursor-pointer"
                  title="{{ $ui['delete'] }}"
                >
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
                @endcan
              @else
                @can('users.update')
                <button disabled class="flex items-center justify-center p-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="user-x" class="w-4 h-4"></i></button>
                @endcan
                @can('users.delete')
                <button disabled class="flex items-center justify-center p-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                @endcan
              @endif
            </div>
          </div>
        @empty
          <div class="col-span-full text-center py-16">
            <i data-lucide="users" class="w-16 h-16 text-gray-200 dark:text-slate-750 mx-auto mb-4"></i>
            <p class="text-gray-500 dark:text-slate-400">{{ $ui['noUsers'] }}</p>
          </div>
        @endforelse
      </div>

      <div class="mt-6">
        {{ $users->links() }}
      </div>
      </div>
    </div>

    <!-- User Modal (Create/Edit) -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
      <div @click.away="showModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-scale-in border border-gray-150 dark:border-slate-800">
        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
          <h2 class="text-xl font-bold text-gray-800 dark:text-white" x-text="editingUser ? @js($ui['editUser']) : @js($ui['createUser'])"></h2>
          <button @click="showModal = false" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <form method="POST" :action="editingUser ? '{{ url('/dashboard/users') }}/' + editingUser.id : '{{ route('dashboard.users.store') }}'" @submit.prevent="submitUserAction($event.target, editingUser ? '{{ $isAr ? 'تم تحديث المستخدم بنجاح' : 'User updated successfully' }}' : '{{ $isAr ? 'تم إنشاء المستخدم بنجاح' : 'User created successfully' }}', () => { showModal = false; })" class="p-6 space-y-4" autocomplete="off">
          @csrf
          <template x-if="editingUser">
            <input type="hidden" name="_method" value="PUT">
          </template>



          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              {{ $ui['fullName'] }} <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="name"
              x-model="formData.name"
              placeholder="{{ $ui['namePlaceholder'] }}"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
              required
            />
            <template x-if="fieldErrors.name">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.name[0]"></p>
            </template>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              {{ $ui['username'] }} <span x-show="!editingUser" class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="username"
              x-model="formData.username"
              placeholder="username"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400 read-only:opacity-50"
              dir="ltr"
              :readonly="editingUser"
              :required="!editingUser"
            />
            <template x-if="fieldErrors.username">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.username[0]"></p>
            </template>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              {{ $ui['email'] }} <span class="text-red-500">*</span>
            </label>
            <input
              type="email"
              name="email"
              x-model="formData.email"
              placeholder="email@example.com"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
              dir="ltr"
              required
            />
            <template x-if="fieldErrors.email">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.email[0]"></p>
            </template>
          </div>

          <div x-show="!editingUser">
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              {{ $ui['password'] }} <span class="text-red-500"> *</span>
            </label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password"
                placeholder="{{ $ui['strongPassword'] }}"
                class="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
                dir="ltr"
                :disabled="editingUser"
                :required="!editingUser"
              />
              <button
                type="button"
                @click="show = !show"
                class="absolute {{ $isAr ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
              >
                <i data-lucide="eye-off" class="w-5 h-5" x-show="show"></i>
                <i data-lucide="eye" class="w-5 h-5" x-show="!show"></i>
              </button>
            </div>
            <template x-if="fieldErrors.password">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.password[0]"></p>
            </template>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ $ui['role'] }}</label>
              <select
                name="role"
                x-model="formData.role"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <option value="staff">{{ $shortRoleLabels['staff'] }}</option>
                <option value="head_of_department">{{ $shortRoleLabels['head_of_department'] }}</option>
                <option value="unit_manager">{{ $shortRoleLabels['unit_manager'] }}</option>
                <option value="admin">{{ $shortRoleLabels['admin'] }}</option>
                @if(auth()->user()->role === 'super_admin')
                  <option value="super_admin">{{ $shortRoleLabels['super_admin'] }}</option>
                @endif
              </select>
              <template x-if="fieldErrors.role">
                <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.role[0]"></p>
              </template>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ $ui['linkedDepartment'] }}</label>
              <select
                name="department"
                x-model="formData.department"
                :disabled="formData.role !== 'head_of_department'"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <option value="">{{ $ui['noDepartment'] }}</option>
                @foreach ($departments as $department)
                  <option value="{{ $department }}">{{ __($department) }}</option>
                @endforeach
              </select>
              <template x-if="fieldErrors.department">
                <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.department[0]"></p>
              </template>
            </div>
          </div>

          <div class="bg-gray-50 dark:bg-slate-950 rounded-xl p-4 border border-gray-100 dark:border-slate-800">
            <h4 class="text-sm font-bold text-gray-600 dark:text-slate-400 mb-3">{{ $isAr ? 'الصلاحيات الفردية (Direct Permissions)' : 'Direct Permissions' }}</h4>
            <div class="space-y-4">
              @foreach($permissionTree as $group => $permissions)
                <div class="space-y-2">
                  <h5 class="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase tracking-wider">{{ $permTranslations['groups'][$group] ?? $group }}</h5>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($permissions as $permission)
                      <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-800 p-1.5 rounded-lg transition-colors">
                        <input type="checkbox" name="direct_permissions[]" value="{{ $permission->name }}"
                          :checked="isInherited('{{ $permission->name }}') || formData.direct_permissions.includes('{{ $permission->name }}')"
                          :disabled="isInherited('{{ $permission->name }}')"
                          @change="togglePermission('{{ $permission->name }}')"
                          class="w-4 h-4 text-purple-600 rounded border-gray-300 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                        <span class="truncate" title="{{ $permission->name }}">{{ $permTranslations['perms'][$permission->name] ?? $permission->name }}</span>
                      </label>
                    @endforeach
                  </div>
                </div>
              @endforeach
            </div>
            <div class="mt-3 text-xs text-amber-600 dark:text-amber-400 font-medium">
              <i data-lucide="info" class="w-3.5 h-3.5 inline-block -mt-0.5 mr-1"></i>
              {{ $isAr ? 'هذه الصلاحيات يتم منحها للمستخدم مباشرة بالإضافة إلى صلاحيات دوره الأساسي.' : 'These permissions are granted directly to the user in addition to their role permissions.' }}
            </div>
          </div>

          <div class="sticky bottom-0 bg-white dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 flex items-center gap-3 p-6 -mx-6 -mb-6 mt-4 rounded-b-2xl z-10">
            <button
              type="button"
              @click="showModal = false"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              {{ $ui['cancel'] }}
            </button>
            <button
              type="submit"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 text-white font-bold shadow-lg shadow-purple-200 dark:shadow-none hover:shadow-xl transition-all cursor-pointer"
            >
              <i data-lucide="check" class="w-5 h-5"></i>
              <span x-text="editingUser ? @js($ui['saveChanges']) : @js($ui['addUser'])"></span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Change Password Modal -->
    <div x-show="showPasswordModal" style="display: none;" class="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
      <div @click.away="showPasswordModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full animate-scale-in border border-gray-150 dark:border-slate-800">
        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ __('user_password_modal_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1" x-text="'@' + (passwordUser ? passwordUser.username : '')"></p>
          </div>
          <button @click="showPasswordModal = false" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <form method="POST" action="{{ url('/dashboard/change-password') }}" @submit.prevent="submitUserAction($event.target, '{{ $isAr ? 'تم تغيير كلمة المرور بنجاح' : 'Password changed successfully' }}', () => { showPasswordModal = false; })" class="p-6 space-y-4">
          @csrf
          <input type="hidden" name="user_id" :value="passwordUser ? passwordUser.id : ''">
          


          @php
            $roleName = match(auth()->user()->role) {
                'super_admin' => $isAr ? 'كمدير عام' : 'as Super Admin',
                'admin' => $isAr ? 'كمدير نظام' : 'as Admin',
                'unit_manager' => $isAr ? 'كمدير وحدة' : 'as Unit Manager',
                'head_of_department' => $isAr ? 'كرئيس قسم' : 'as Head of Department',
                default => $isAr ? 'كموظف' : 'as Staff',
            };
          @endphp
          <div x-show="passwordUser && ('{{ auth()->id() }}' === passwordUser?.id || ({{ in_array(auth()->user()->role, ['super_admin', 'admin']) ? 'true' : 'false' }} && '{{ auth()->id() }}' !== passwordUser?.id))">
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                <span x-text="'{{ auth()->id() }}' === passwordUser?.id ? @js(__('user_password_current_label')) : @js($ui['yourPassword'].' '.$roleName)"></span> <span class="text-red-500">*</span>
              </label>
              <div class="relative" x-data="{ show: false }">
                <input
                  :type="show ? 'text' : 'password'"
                  name="currentPassword"
                  placeholder="{{ $ui['currentPasswordConfirm'] }}"
                  class="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
                  dir="ltr"
                  :required="passwordUser && ('{{ auth()->id() }}' === passwordUser?.id || ({{ in_array(auth()->user()->role, ['super_admin', 'admin']) ? 'true' : 'false' }} && '{{ auth()->id() }}' !== passwordUser?.id))"
                />
                <button
                  type="button"
                  @click="show = !show"
                  class="absolute {{ $isAr ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <i data-lucide="eye-off" class="w-5 h-5" x-show="show"></i>
                  <i data-lucide="eye" class="w-5 h-5" x-show="!show"></i>
                </button>
              </div>
              <template x-if="fieldErrors.currentPassword">
                <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.currentPassword[0]"></p>
              </template>
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ __('user_password_new_label') }}</label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password"
                placeholder="{{ __('user_password_new_placeholder') }}"
                class="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
                dir="ltr"
                required
                minlength="6"
              />
              <button
                type="button"
                @click="show = !show"
                class="absolute {{ $isAr ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
              >
                <i data-lucide="eye-off" class="w-5 h-5" x-show="show"></i>
                <i data-lucide="eye" class="w-5 h-5" x-show="!show"></i>
              </button>
            </div>
            <template x-if="fieldErrors.password">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.password[0]"></p>
            </template>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ __('user_password_confirm_label') }}</label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password_confirmation"
                placeholder="{{ __('user_password_confirm_placeholder') }}"
                class="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
                dir="ltr"
                required
                minlength="6"
              />
              <button
                type="button"
                @click="show = !show"
                class="absolute {{ $isAr ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer"
              >
                <i data-lucide="eye-off" class="w-5 h-5" x-show="show"></i>
                <i data-lucide="eye" class="w-5 h-5" x-show="!show"></i>
              </button>
            </div>
            <template x-if="fieldErrors.password_confirmation">
              <p class="text-[11px] text-red-500 mt-1.5 font-bold text-start" x-text="fieldErrors.password_confirmation[0]"></p>
            </template>
          </div>

          <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/35 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 leading-relaxed text-center">
            {{ __('user_password_session_note') }}
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              type="button"
              @click="showPasswordModal = false"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              {{ $ui['cancel'] }}
            </button>
            <button
              type="submit"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-lg shadow-indigo-200 dark:shadow-none transition-all cursor-pointer"
            >
              {{ __('user_password_save_btn') }}
              <i data-lucide="key-round" class="w-5 h-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" style="display: none;" class="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div @click.away="showDeleteModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-6 animate-scale-in border border-gray-150 dark:border-slate-800">
        <div class="text-center">
          <div class="w-16 h-16 bg-red-100 dark:bg-red-950/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="trash-2" class="w-8 h-8 text-red-500"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">{{ $ui['deleteTitle'] }}</h3>
          <p class="text-gray-500 dark:text-slate-400 text-sm mb-6">{{ $ui['deleteDesc'] }}</p>
          <div class="flex items-center gap-3">
            <button
              @click="showDeleteModal = false"
              type="button"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              {{ $ui['cancel'] }}
            </button>
            <form method="POST" :action="'{{ url('/dashboard/users') }}/' + userToDelete" @submit.prevent="submitUserAction($event.target, '{{ $isAr ? 'تم حذف المستخدم بنجاح' : 'User deleted successfully' }}', () => { showDeleteModal = false; })" class="flex-1 m-0">
              @csrf
              @method('DELETE')
              <button
                type="submit"
                class="w-full px-4 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 transition-colors cursor-pointer"
              >
                {{ $ui['deleteForever'] }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>

@endsection
