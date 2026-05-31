<form method="POST" action="{{ $action }}" class="space-y-5">
  @csrf
  @if (($method ?? 'POST') !== 'POST')
    @method($method)
  @endif

  {{-- Validation errors --}}
  @if ($errors->any())
    <div class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-400">
      <i data-lucide="alert-circle" class="h-5 w-5 shrink-0"></i>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <div class="grid gap-4 sm:grid-cols-2">
    <input name="name" value="{{ old('name') }}" placeholder="الاسم الكامل" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15" required>
    <input name="username" value="{{ old('username') }}" placeholder="اسم الدخول" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15" dir="ltr" required>
    <input name="email" type="email" value="{{ old('email') }}" placeholder="البريد الإلكتروني" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15" dir="ltr">
    <div class="relative">
      <input name="password" type="password" placeholder="كلمة المرور" autocomplete="new-password" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15" dir="ltr" required>
      <button type="button" onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'; this.querySelector('i').dataset.lucide = this.previousElementSibling.type === 'password' ? 'eye' : 'eye-off'" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
        <i data-lucide="eye" class="h-5 w-5"></i>
      </button>
    </div>
    <select name="role" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15">
      <option value="staff" @selected(old('role', 'staff') === 'staff')>موظف</option>
      <option value="head_of_department" @selected(old('role') === 'head_of_department')>رئيس قسم</option>
      <option value="unit_manager" @selected(old('role') === 'unit_manager')>مدير وحدة</option>
      <option value="admin" @selected(old('role') === 'admin')>مدير نظام</option>
      @if(auth()->user()?->role === 'super_admin')
        <option value="super_admin" @selected(old('role') === 'super_admin')>مدير عام</option>
      @endif
    </select>
    <input name="department" value="{{ old('department') }}" list="departments-list" placeholder="القسم" class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-purple-950/15">
  </div>

  {{-- Permissions preview --}}
  <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
    <h4 class="mb-3 text-sm font-bold text-slate-600 dark:text-slate-400">الصلاحيات المتوقعة</h4>
    <div class="space-y-2 text-xs">
      @php $role = old('role', 'staff'); @endphp
      @if ($role === 'super_admin')
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>التحكم الكامل في النظام</span></div>
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>إدارة المستخدمين والصلاحيات</span></div>
      @elseif ($role === 'admin')
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>إدارة الاستبيانات</span></div>
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>عرض جميع التقارير</span></div>
        <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="h-4 w-4"></i><span>إدارة المستخدمين (بدون صلاحية)</span></div>
      @elseif ($role === 'head_of_department')
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>عرض تقارير القسم</span></div>
        <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="h-4 w-4"></i><span>إدارة الاستبيانات</span></div>
      @elseif ($role === 'unit_manager')
        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"><i data-lucide="check" class="h-4 w-4"></i><span>عرض وتصدير التقارير</span></div>
        <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="h-4 w-4"></i><span>إدارة الاستبيانات</span></div>
      @else
        <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="h-4 w-4"></i><span>عرض التقارير (محدود جداً)</span></div>
        <div class="flex items-center gap-2 text-red-500"><i data-lucide="x" class="h-4 w-4"></i><span>إدارة الاستبيانات</span></div>
      @endif
    </div>
  </div>

  <p class="rounded-2xl bg-slate-50 p-4 text-xs font-bold leading-6 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
    كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل مع حروف كبيرة وصغيرة وأرقام ورمز.
  </p>

  <div class="flex items-center gap-2">
    <input type="hidden" name="isActive" value="0">
    <input type="checkbox" name="isActive" value="1" id="isActiveCheck" checked class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
    <label for="isActiveCheck" class="text-sm font-bold text-slate-600 dark:text-slate-300">حساب نشط</label>
  </div>

  <div class="flex justify-end gap-2">
    <button type="button" onclick="this.closest('[x-data]')?.__x.$data.createOpen = false" class="rounded-xl border-2 border-slate-200 px-4 py-3 text-sm font-black text-slate-500 dark:border-slate-800">إلغاء</button>
    <button class="rounded-xl bg-linear-to-r from-purple-600 to-indigo-600 px-5 py-3 text-sm font-black text-white shadow-lg">{{ $submitLabel ?? 'حفظ' }}</button>
  </div>
</form>