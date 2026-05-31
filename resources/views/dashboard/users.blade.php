@extends('layouts.dashboard')

@section('title', 'إدارة المستخدمين - MedSurvey Pro')

@section('dashboard')
  @php
    $isAr = app()->getLocale() === 'ar';
    $roleLabels = [
      'super_admin' => 'مدير عام',
      'admin' => 'مدير نظام',
      'unit_manager' => 'مدير وحدة',
      'head_of_department' => 'رئيس قسم',
      'staff' => 'موظف استقبال',
    ];
    $roleColors = [
      'super_admin' => 'from-purple-500 to-indigo-500',
      'admin' => 'from-blue-500 to-cyan-500',
      'unit_manager' => 'from-teal-500 to-cyan-600',
      'head_of_department' => 'from-green-500 to-emerald-500',
      'staff' => 'from-amber-500 to-orange-500',
    ];
  @endphp

  <div x-data="userManagement()" class="text-start animate-fade-in" x-cloak>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-linear-to-r from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-200 dark:shadow-none">
            <i data-lucide="users" class="w-5 h-5 text-white"></i>
          </div>
          <div class="flex flex-col gap-0.5">
            <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">إدارة الحسابات والمستخدمين</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $users->total() }} مستخدم مسجل</p>
          </div>
        </div>
        <button
          @click="openCreateModal()"
          type="button"
          class="flex items-center gap-2 bg-linear-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:shadow-lg transition-all cursor-pointer"
        >
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span class="hidden sm:inline">مستخدم جديد</span>
          <span class="sm:hidden">جديد</span>
        </button>
      </div>

      <!-- Filters -->
      <form method="GET" action="{{ route('dashboard.users') }}" class="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="absolute {{ $isAr ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
          <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="بحث بالاسم أو اسم المستخدم..."
            class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white placeholder-gray-500"
          />
        </div>
        <div class="flex items-center gap-2">
          <i data-lucide="shield" class="w-4 h-4 text-gray-400"></i>
          <select
            name="role"
            onchange="this.form.submit()"
            class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white"
          >
            <option value="">جميع الأدوار</option>
            <option value="super_admin" @selected(request('role') === 'super_admin')>مدير عام</option>
            <option value="admin" @selected(request('role') === 'admin')>مدير نظام</option>
            <option value="unit_manager" @selected(request('role') === 'unit_manager')>مدير وحدة</option>
            <option value="head_of_department" @selected(request('role') === 'head_of_department')>رئيس قسم</option>
            <option value="staff" @selected(request('role') === 'staff')>موظف</option>
          </select>
        </div>
        <button type="submit" class="hidden"></button>
      </form>

      <!-- Error / Success Alerts -->
      @if ($errors->any())
        <div class="mb-4 flex flex-col gap-1 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">
          <div class="flex items-center gap-2 font-bold mb-1">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
            يوجد بعض الأخطاء:
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
            ];
          @endphp
          <div
            class="h-full bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col {{ !$user->isActive ? 'opacity-60' : '' }}"
          >
            <!-- User Header -->
            <div class="p-5 bg-linear-to-r {{ $roleColorClass }} text-white relative">
              <div class="absolute top-3 {{ $isAr ? 'left-3' : 'right-3' }}">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $user->isActive ? 'bg-white/20' : 'bg-red-500/50' }}">
                  {{ $user->isActive ? 'نشط' : 'معطل' }}
                </span>
              </div>
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center text-2xl font-bold">
                  {{ $initial }}
                </div>
                <div class="flex-1 min-w-0 flex flex-col items-start w-full">
                  <h3 class="font-bold text-lg truncate w-full text-right" dir="auto">{{ $user->name }}</h3>
                  <p class="text-white/70 text-sm truncate w-full text-right" dir="ltr">{{ '@' . $user->username }}</p>
                </div>
              </div>
            </div>

            <!-- User Details -->
            <div class="p-4 space-y-3">
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-300">
                <i data-lucide="mail" class="w-4 h-4 text-gray-400"></i>
                <span class="truncate w-full text-right" dir="ltr">{{ $user->email ?: 'غير متوفر' }}</span>
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
                  <span class="font-medium truncate text-right {{ $roleTextClass }}">
                    {{ $roleLabels[$user->role] ?? $user->role }}
                  </span>
                  @if($user->role === 'head_of_department' && $user->department)
                    <span class="text-gray-300 dark:text-slate-600 shrink-0">•</span>
                    <i data-lucide="building-2" class="w-4 h-4 text-gray-400 shrink-0"></i>
                    <span class="truncate text-gray-600 dark:text-slate-300 text-right">{{ $user->department }}</span>
                  @endif
                </div>
              </div>
              <div class="min-h-5 flex items-center gap-2 text-xs text-gray-400 dark:text-slate-500">
                @if($user->lastLogin)
                  <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                  <span>آخر دخول: {{ $user->lastLogin->format('Y-m-d H:i') }}</span>
                @else
                  <i data-lucide="calendar" class="w-3.5 h-3.5 invisible"></i>
                @endif
              </div>
            </div>

            <!-- Actions -->
            <div class="px-4 pb-4 flex items-center gap-2 mt-auto">
              <button
                type="button"
                @click="openEditModal({{ json_encode($userPayload) }})"
                @disabled(auth()->user()->role !== 'super_admin' && $user->role === 'super_admin')
                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-750 rounded-xl transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                تعديل
              </button>
              
              <button
                type="button"
                @click="openPasswordModal({{ json_encode($userPayload) }})"
                @disabled(auth()->user()->role !== 'super_admin' && $user->role === 'super_admin')
                class="flex items-center justify-center p-2 rounded-xl bg-indigo-100 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-950/50 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                title="تغيير كلمة المرور"
              >
                <i data-lucide="key-round" class="w-4 h-4"></i>
              </button>

              @if($user->id !== auth()->id() && (auth()->user()->role === 'super_admin' || $user->role !== 'super_admin'))
                <form method="POST" action="{{ route('dashboard.users.toggle', $user->id) }}" class="inline-block m-0">
                  @csrf
                  @method('PATCH')
                  <button
                    type="submit"
                    class="flex items-center justify-center p-2 rounded-xl transition-colors cursor-pointer {{ $user->isActive ? 'bg-amber-100 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-950/50' : 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-950/50' }}"
                    title="{{ $user->isActive ? 'تعطيل' : 'تفعيل' }}"
                  >
                    <i data-lucide="{{ $user->isActive ? 'user-x' : 'user-check' }}" class="w-4 h-4"></i>
                  </button>
                </form>
                <button
                  type="button"
                  @click="openDeleteModal('{{ $user->id }}')"
                  class="flex items-center justify-center p-2 rounded-xl bg-red-100 dark:bg-red-950/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-950/50 transition-colors cursor-pointer"
                  title="حذف"
                >
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              @else
                <button disabled class="flex items-center justify-center p-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="user-x" class="w-4 h-4"></i></button>
                <button disabled class="flex items-center justify-center p-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-slate-600 cursor-not-allowed"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
              @endif
            </div>
          </div>
        @empty
          <div class="col-span-full text-center py-16">
            <i data-lucide="users" class="w-16 h-16 text-gray-200 dark:text-slate-750 mx-auto mb-4"></i>
            <p class="text-gray-500 dark:text-slate-400">لا يوجد مستخدمون مطابقون لبحثك.</p>
          </div>
        @endforelse
      </div>

      <div class="mt-6">
        {{ $users->links() }}
      </div>
    </div>

    <!-- User Modal (Create/Edit) -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
      <div @click.away="showModal = false" class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-scale-in border border-gray-150 dark:border-slate-800">
        <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
          <h2 class="text-xl font-bold text-gray-800 dark:text-white" x-text="editingUser ? 'تعديل المستخدم' : 'إضافة مستخدم جديد'"></h2>
          <button @click="showModal = false" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <form method="POST" :action="editingUser ? '{{ url('/dashboard/users') }}/' + editingUser.id : '{{ route('dashboard.users.store') }}'" class="p-6 space-y-4" autocomplete="off">
          @csrf
          <template x-if="editingUser">
            <input type="hidden" name="_method" value="PUT">
          </template>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              الاسم الكامل <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="name"
              x-model="formData.name"
              placeholder="اكتب الاسم هنا"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              اسم الدخول (Username) <span x-show="!editingUser" class="text-red-500">*</span>
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
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              البريد الإلكتروني <span class="text-red-500">*</span>
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
          </div>

          <div x-show="!editingUser">
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
              كلمة المرور <span class="text-red-500"> *</span>
            </label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password"
                placeholder="أدخل كلمة مرور قوية"
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
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">الصلاحية</label>
              <select
                name="role"
                x-model="formData.role"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <option value="staff">موظف</option>
                <option value="head_of_department">رئيس قسم</option>
                <option value="unit_manager">مدير وحدة</option>
                <option value="admin">مدير نظام</option>
                @if(auth()->user()->role === 'super_admin')
                  <option value="super_admin">مدير عام</option>
                @endif
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">القسم المرتبط</label>
              <select
                name="department"
                x-model="formData.department"
                :disabled="formData.role !== 'head_of_department'"
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:focus:ring-purple-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed"
              >
                <option value="">لا يوجد قسم</option>
                @foreach ($departments as $department)
                  <option value="{{ $department }}">{{ $department }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <!-- Role Permissions Preview -->
          <div class="bg-gray-50 dark:bg-slate-950 rounded-xl p-4 border border-gray-100 dark:border-slate-800">
            <h4 class="text-sm font-bold text-gray-600 dark:text-slate-400 mb-3">صلاحيات هذا الدور:</h4>
            <div class="space-y-2 text-xs">
              <template x-if="formData.role === 'super_admin'">
                <div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400"><i data-lucide="check" class="w-4 h-4"></i><span>وصول كامل لكافة إعدادات وبيانات النظام</span></div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400 mt-1"><i data-lucide="check" class="w-4 h-4"></i><span>إدارة جميع المستخدمين</span></div>
                </div>
              </template>
              <template x-if="formData.role === 'admin'">
                <div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400"><i data-lucide="check" class="w-4 h-4"></i><span>إدارة الاستبيانات وإرسالها</span></div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400 mt-1"><i data-lucide="check" class="w-4 h-4"></i><span>الاطلاع على جميع التقارير وتصديرها</span></div>
                  <div class="flex items-center gap-2 text-red-500 mt-1"><i data-lucide="x" class="w-4 h-4"></i><span>لا يمكنه إدارة المستخدمين والمدراء</span></div>
                </div>
              </template>
              <template x-if="formData.role === 'head_of_department'">
                <div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400"><i data-lucide="check" class="w-4 h-4"></i><span>الاطلاع على تقارير وتذاكر القسم الخاص به فقط</span></div>
                  <div class="flex items-center gap-2 text-red-500 mt-1"><i data-lucide="x" class="w-4 h-4"></i><span>لا يمكنه إدارة أو إرسال الاستبيانات</span></div>
                </div>
              </template>
              <template x-if="formData.role === 'unit_manager'">
                <div>
                  <div class="flex items-center gap-2 text-green-600 dark:text-green-400"><i data-lucide="check" class="w-4 h-4"></i><span>الاطلاع على كافة التقارير للوحدة وتصديرها</span></div>
                  <div class="flex items-center gap-2 text-red-500 mt-1"><i data-lucide="x" class="w-4 h-4"></i><span>لا يمكنه التعديل أو إرسال الاستبيانات</span></div>
                </div>
              </template>
              <template x-if="formData.role === 'staff'">
                <div>
                  <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="w-4 h-4"></i><span>وصول محدود للتقارير بناءً على القسم</span></div>
                  <div class="flex items-center gap-2 text-red-500 mt-1"><i data-lucide="x" class="w-4 h-4"></i><span>لا يمكنه إدارة أي إعدادات أو استبيانات</span></div>
                </div>
              </template>
            </div>
          </div>

          <div class="flex items-center gap-3 pt-4">
            <button
              type="button"
              @click="showModal = false"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              إلغاء
            </button>
            <button
              type="submit"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 text-white font-bold shadow-lg shadow-purple-200 dark:shadow-none hover:shadow-xl transition-all cursor-pointer"
            >
              <i data-lucide="check" class="w-5 h-5"></i>
              <span x-text="editingUser ? 'حفظ التعديلات' : 'إضافة المستخدم'"></span>
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
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">تغيير كلمة المرور</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1" x-text="'@' + (passwordUser ? passwordUser.username : '')"></p>
          </div>
          <button @click="showPasswordModal = false" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <form method="POST" action="{{ url('/dashboard/change-password') }}" class="p-6 space-y-4">
          @csrf
          <input type="hidden" name="user_id" :value="passwordUser ? passwordUser.id : ''">
          
          @php
            $roleName = match(auth()->user()->role) {
                'super_admin' => 'كمدير عام',
                'admin' => 'كمدير نظام',
                'unit_manager' => 'كمدير وحدة',
                'head_of_department' => 'كرئيس قسم',
                default => 'كموظف',
            };
          @endphp
          <div x-show="passwordUser && ('{{ auth()->id() }}' === passwordUser?.id || ({{ in_array(auth()->user()->role, ['super_admin', 'admin']) ? 'true' : 'false' }} && '{{ auth()->id() }}' !== passwordUser?.id))">
            <div>
              <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">
                <span x-text="'{{ auth()->id() }}' === passwordUser?.id ? 'كلمة المرور الحالية' : 'كلمة المرور الخاصة بك {{ $roleName }}'"></span> <span class="text-red-500">*</span>
              </label>
              <div class="relative" x-data="{ show: false }">
                <input
                  :type="show ? 'text' : 'password'"
                  name="currentPassword"
                  placeholder="أدخل كلمة مرور حسابك لتأكيد العملية"
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
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">كلمة المرور الجديدة</label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password"
                placeholder="أدخل كلمة المرور الجديدة"
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
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">تأكيد كلمة المرور</label>
            <div class="relative" x-data="{ show: false }">
              <input
                :type="show ? 'text' : 'password'"
                name="password_confirmation"
                placeholder="أعد إدخال كلمة المرور"
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
          </div>

          <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/35 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 leading-relaxed text-center">
            سيتم إنهاء جلسات هذا المستخدم الحالية، وسيحتاج إلى تسجيل الدخول بكلمة المرور الجديدة.
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              type="button"
              @click="showPasswordModal = false"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              إلغاء
            </button>
            <button
              type="submit"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-lg shadow-indigo-200 dark:shadow-none transition-all cursor-pointer"
            >
              تحديث كلمة المرور
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
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">تأكيد حذف المستخدم</h3>
          <p class="text-gray-500 dark:text-slate-400 text-sm mb-6">هل أنت متأكد من رغبتك بحذف هذا المستخدم نهائياً؟ لا يمكن التراجع عن هذا الإجراء.</p>
          <div class="flex items-center gap-3">
            <button
              @click="showDeleteModal = false"
              type="button"
              class="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              إلغاء
            </button>
            <form method="POST" :action="'{{ url('/dashboard/users') }}/' + userToDelete" class="flex-1 m-0">
              @csrf
              @method('DELETE')
              <button
                type="submit"
                class="w-full px-4 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 transition-colors cursor-pointer"
              >
                حذف نهائي
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('userManagement', () => ({
        showModal: false,
        showPasswordModal: false,
        showDeleteModal: false,
        editingUser: null,
        passwordUser: null,
        userToDelete: null,
        showPassword: false,
        showPassword2: false,
        formData: {
          name: '',
          username: '',
          email: '',
          role: 'staff',
          department: ''
        },

        openCreateModal() {
          this.editingUser = null;
          this.formData = {
            name: '',
            username: '',
            email: '',
            role: 'staff',
            department: ''
          };
          this.showPassword = false;
          this.showModal = true;
        },

        openEditModal(user) {
          this.editingUser = user;
          this.formData = {
            name: user.name,
            username: user.username,
            email: user.email,
            role: user.role,
            department: user.department || ''
          };
          this.showPassword = false;
          this.showModal = true;
        },

        openPasswordModal(user) {
          this.passwordUser = user;
          this.showPassword2 = false;
          this.showPasswordModal = true;
        },

        openDeleteModal(userId) {
          this.userToDelete = userId;
          this.showDeleteModal = true;
        },
      }));
    });
  </script>
@endsection
