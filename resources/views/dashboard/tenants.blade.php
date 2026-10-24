@extends('layouts.dashboard')

@section('title', (app()->getLocale() === 'ar' ? 'إدارة المنشآت' : 'Tenant Management') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $isAr = app()->getLocale() === 'ar';
    $base = url('dashboard/tenants');
    $field = 'w-full rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm font-medium text-gray-800 dark:text-white outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/30';
    $primaryBtn = 'inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 hover:bg-teal-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition cursor-pointer';
    $ghostBtn = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-bold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition cursor-pointer';
    $t = fn (string $ar, string $en) => $isAr ? $ar : $en;
  @endphp

  <div
    x-data="{ modal: null, tenant: { id: '', name: '' }, open(type, id, name) { this.tenant = { id: id, name: name }; this.modal = type; }, close() { this.modal = null; } }"
    @keydown.escape.window="close()"
    class="text-start animate-fade-in"
    x-cloak
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="p-2 bg-teal-100 dark:bg-teal-950/30 rounded-xl text-teal-600 dark:text-teal-400">
            <i data-lucide="building-2" class="w-5 h-5"></i>
          </span>
          <div>
            <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">{{ $t('إدارة المنشآت', 'Tenant Management') }}</h2>
            <p class="text-xs text-gray-400 dark:text-slate-450 mt-1">{{ $t('أنشئ منشأة، وأضف لها مديرًا، وأوقفها عند الحاجة.', 'Create a facility, give it an administrator, and suspend it when needed.') }}</p>
          </div>
        </div>
        <button type="button" @click="open('create', '', '')" class="{{ $primaryBtn }}">
          <i data-lucide="plus" class="w-4 h-4"></i>
          {{ $t('منشأة جديدة', 'New tenant') }}
        </button>
      </div>

      @if (session('success'))
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 px-4 py-3 text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ session('success') }}</div>
      @endif
      @if (session('error'))
        <div class="rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-4 py-3 text-sm font-bold text-rose-700 dark:text-rose-300">{{ session('error') }}</div>
      @endif
      @if ($errors->any())
        <div class="rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-4 py-3 text-sm font-bold text-rose-700 dark:text-rose-300">{{ $errors->first() }}</div>
      @endif

      <div class="grid grid-cols-2 gap-4 max-w-md">
        <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
          <div class="text-[11px] font-bold text-gray-400 dark:text-slate-500">{{ $t('إجمالي المنشآت', 'Total tenants') }}</div>
          <div class="mt-1 text-2xl font-black text-gray-900 dark:text-white" title="{{ $totalTenants }}">{{ \App\Support\NumberFormatter::compact($totalTenants) }}</div>
        </div>
        <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
          <div class="text-[11px] font-bold text-gray-400 dark:text-slate-500">{{ $t('النشطة', 'Active') }}</div>
          <div class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400" title="{{ $activeTenants }}">{{ \App\Support\NumberFormatter::compact($activeTenants) }}</div>
        </div>
      </div>

      <form method="GET" action="{{ route('dashboard.tenants') }}" class="flex gap-2 max-w-md">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $t('بحث باسم المنشأة', 'Search by tenant name') }}" aria-label="{{ $t('بحث', 'Search') }}" class="{{ $field }}">
        <button type="submit" class="{{ $ghostBtn }}">{{ $t('بحث', 'Search') }}</button>
      </form>

      <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-sm">
            <thead class="bg-gray-50 dark:bg-slate-950 text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">
              <tr>
                <th class="px-5 py-3 text-start">{{ $t('المنشأة', 'Tenant') }}</th>
                <th class="px-5 py-3 text-center">{{ $t('الحالة', 'Status') }}</th>
                <th class="px-5 py-3 text-center">{{ $t('المستخدمون', 'Users') }}</th>
                <th class="px-5 py-3 text-center">{{ $t('الاستبيانات', 'Surveys') }}</th>
                <th class="px-5 py-3 text-center">{{ $t('الاستجابات', 'Responses') }}</th>
                <th class="px-5 py-3 text-start">{{ $t('الإجراءات', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-800/80">
              @forelse ($tenants as $tenant)
                @php $hasData = ($tenant->users_count + $tenant->surveys_count + $tenant->responses_count + $tenant->tickets_count) > 0; @endphp
                <tr class="align-middle">
                  <td class="px-5 py-3.5">
                    <div class="font-bold text-gray-900 dark:text-white">{{ $tenant->name }}</div>
                    <div class="mt-0.5 text-[10px] font-bold text-gray-400 dark:text-slate-500" dir="ltr">{{ $tenant->createdAt?->format('Y-m-d') }}</div>
                  </td>
                  <td class="px-5 py-3.5 text-center">
                    <span class="inline-flex rounded-xl border px-2.5 py-1 text-xs font-bold {{ $tenant->isActive ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/25 dark:text-emerald-400' : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400' }}">
                      {{ $tenant->isActive ? $t('نشطة', 'Active') : $t('موقوفة', 'Suspended') }}
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-center font-bold text-gray-700 dark:text-slate-300">{{ \App\Support\NumberFormatter::compact($tenant->users_count) }}</td>
                  <td class="px-5 py-3.5 text-center font-bold text-gray-700 dark:text-slate-300">{{ \App\Support\NumberFormatter::compact($tenant->surveys_count) }}</td>
                  <td class="px-5 py-3.5 text-center font-bold text-gray-700 dark:text-slate-300">{{ \App\Support\NumberFormatter::compact($tenant->responses_count) }}</td>
                  <td class="px-5 py-3.5">
                    <div class="flex flex-wrap gap-2">
                      <button type="button" class="{{ $ghostBtn }}" @click="open('edit', @js($tenant->id), @js($tenant->name))">{{ $t('تعديل', 'Edit') }}</button>
                      <button type="button" class="{{ $ghostBtn }}" @click="open('admin', @js($tenant->id), @js($tenant->name))">{{ $t('إضافة مدير', 'Add admin') }}</button>
                      <form method="POST" action="{{ $base }}/{{ $tenant->id }}/toggle">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="{{ $ghostBtn }}">{{ $tenant->isActive ? $t('إيقاف', 'Suspend') : $t('تفعيل', 'Activate') }}</button>
                      </form>
                      @if ($hasData)
                        <button type="button" disabled title="{{ $t('لا يمكن حذف منشأة لديها بيانات. أوقفها بدلًا من ذلك.', 'A tenant with data cannot be deleted. Suspend it instead.') }}" class="{{ $ghostBtn }} opacity-40 cursor-not-allowed">{{ $t('حذف', 'Delete') }}</button>
                      @else
                        <button type="button" class="{{ $ghostBtn }} text-rose-600 dark:text-rose-400" @click="open('delete', @js($tenant->id), @js($tenant->name))">{{ $t('حذف', 'Delete') }}</button>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-5 py-14 text-center">
                    <div class="font-bold text-gray-800 dark:text-white">{{ $t('لا توجد منشآت بعد', 'No tenants yet') }}</div>
                    <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ $t('أنشئ أول منشأة لتبدأ.', 'Create the first tenant to get started.') }}</p>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{ $tenants->links() }}
    </div>

    <div x-show="modal" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="close()" role="dialog" aria-modal="true">
      <div class="w-full max-w-md rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xl text-start">

        <form x-show="modal === 'create'" method="POST" action="{{ $base }}" class="space-y-4">
          @csrf
          <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ $t('منشأة جديدة', 'New tenant') }}</h3>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('اسم المنشأة', 'Tenant name') }}
            <input type="text" name="name" required maxlength="120" class="{{ $field }} mt-1.5">
          </label>
          <div class="flex justify-end gap-2">
            <button type="button" class="{{ $ghostBtn }}" @click="close()">{{ $t('إلغاء', 'Cancel') }}</button>
            <button type="submit" class="{{ $primaryBtn }}">{{ $t('إنشاء المنشأة', 'Create tenant') }}</button>
          </div>
        </form>

        <form x-show="modal === 'edit'" method="POST" :action="'{{ $base }}/' + tenant.id" class="space-y-4">
          @csrf
          @method('PUT')
          <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ $t('تعديل المنشأة', 'Edit tenant') }}</h3>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('اسم المنشأة', 'Tenant name') }}
            <input type="text" name="name" x-model="tenant.name" required maxlength="120" class="{{ $field }} mt-1.5">
          </label>
          <div class="flex justify-end gap-2">
            <button type="button" class="{{ $ghostBtn }}" @click="close()">{{ $t('إلغاء', 'Cancel') }}</button>
            <button type="submit" class="{{ $primaryBtn }}">{{ $t('حفظ التغييرات', 'Save changes') }}</button>
          </div>
        </form>

        <form x-show="modal === 'admin'" method="POST" :action="'{{ $base }}/' + tenant.id + '/admins'" class="space-y-3" autocomplete="off">
          @csrf
          <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ $t('مدير جديد للمنشأة', 'New tenant administrator') }}</h3>
          <p class="text-xs font-bold text-teal-700 dark:text-teal-300" x-text="tenant.name"></p>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('اسم المستخدم', 'Username') }}
            <input type="text" name="username" required minlength="3" maxlength="60" dir="ltr" autocomplete="off" class="{{ $field }} mt-1.5">
          </label>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('الاسم الكامل', 'Full name') }}
            <input type="text" name="name" required maxlength="120" class="{{ $field }} mt-1.5">
          </label>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('البريد الإلكتروني (اختياري)', 'Email (optional)') }}
            <input type="email" name="email" maxlength="190" dir="ltr" class="{{ $field }} mt-1.5">
          </label>
          <label class="block text-xs font-bold text-gray-500 dark:text-slate-400">{{ $t('كلمة المرور (12 حرفاً على الأقل مع حروف وأرقام)', 'Password (12+ characters, letters and numbers)') }}
            <input type="password" name="password" required minlength="12" dir="ltr" autocomplete="new-password" class="{{ $field }} mt-1.5">
          </label>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="{{ $ghostBtn }}" @click="close()">{{ $t('إلغاء', 'Cancel') }}</button>
            <button type="submit" class="{{ $primaryBtn }}">{{ $t('إنشاء المدير', 'Create administrator') }}</button>
          </div>
        </form>

        <form x-show="modal === 'delete'" method="POST" :action="'{{ $base }}/' + tenant.id" class="space-y-4">
          @csrf
          @method('DELETE')
          <h3 class="text-lg font-black text-gray-900 dark:text-white">{{ $t('حذف المنشأة', 'Delete tenant') }}</h3>
          <p class="text-sm text-gray-600 dark:text-slate-300">
            {{ $t('سيُحذف نهائيًا:', 'This will permanently delete:') }}
            <strong x-text="tenant.name"></strong>
          </p>
          <div class="flex justify-end gap-2">
            <button type="button" class="{{ $ghostBtn }}" @click="close()">{{ $t('إلغاء', 'Cancel') }}</button>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-700 px-4 py-2.5 text-sm font-bold text-white transition cursor-pointer">{{ $t('تأكيد الحذف', 'Delete tenant') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
