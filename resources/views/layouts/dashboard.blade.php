@php
  $hideHeader = true;
@endphp

@extends('layouts.web')

@php
  $user = auth()->user();
  $nameParts = preg_split('/\s+/', trim((string) $user->name));
  $initials = count($nameParts) >= 2
      ? mb_substr($nameParts[0], 0, 1).mb_substr($nameParts[count($nameParts) - 1], 0, 1)
      : mb_substr((string) $user->name, 0, 2);
  $initials = mb_strtoupper($initials ?: '?');

  $roleGradients = [
      'super_admin' => 'from-violet-500 to-indigo-600',
      'admin' => 'from-teal-500 to-emerald-600',
      'unit_manager' => 'from-teal-500 to-cyan-600',
      'head_of_department' => 'from-orange-500 to-amber-600',
      'staff' => 'from-blue-500 to-sky-600',
  ];
  $roleGradient = $roleGradients[$user->role] ?? 'from-gray-500 to-slate-600';

  $roleBadges = [
      'super_admin' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/20 dark:text-violet-400 border-violet-200 dark:border-violet-900/40',
      'admin' => 'bg-teal-50 text-teal-700 dark:bg-teal-950/20 dark:text-teal-400 border-teal-200 dark:border-teal-900/40',
      'unit_manager' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/20 dark:text-cyan-400 border-cyan-200 dark:border-cyan-900/40',
      'head_of_department' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/20 dark:text-orange-400 border-orange-200 dark:border-orange-900/40',
      'staff' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400 border-blue-200 dark:border-blue-900/40',
  ];
  $roleBadgeStyle = $roleBadges[$user->role] ?? 'bg-gray-50 text-gray-700 dark:bg-gray-950/20 dark:text-gray-400 border-gray-200 dark:border-gray-900/40';

  $roleLabels = [
      'super_admin' => __('role_super_admin'),
      'admin' => __('role_admin'),
      'unit_manager' => __('role_unit_manager'),
      'head_of_department' => __('role_head'),
      'staff' => __('role_staff'),
  ];
  $roleLabel = $roleLabels[$user->role] ?? $user->role;
  $showLanguageToggle = ($settings['appearance']['showLanguageToggle'] ?? true) !== false;
  $compactCount = function ($value): string {
      $value = (float) $value;
      $abs = abs($value);

      if ($abs >= 1000000) {
          return rtrim(rtrim(number_format($value / 1000000, $abs >= 10000000 ? 0 : 1), '0'), '.').'M';
      }

      if ($abs >= 1000) {
          return rtrim(rtrim(number_format($value / 1000, $abs >= 10000 ? 0 : 1), '0'), '.').'K';
      }

      return number_format($value, 0);
  };

  $canManage = in_array($user->role, ['super_admin', 'admin'], true);
  $canReport = in_array($user->role, ['super_admin', 'admin', 'unit_manager', 'head_of_department'], true);

  $links = [
      ['group' => __('nav_group_analytics'), 'items' => [
          ['label' => __('nav_dashboard'), 'route' => 'dashboard.index', 'icon' => 'bar-chart-3', 'show' => $user->role !== 'staff', 'badge' => null],
          ['label' => __('nav_predictive'), 'route' => 'dashboard.predictive', 'icon' => 'brain', 'show' => $canReport, 'badge' => $predictiveCount > 0 ? $predictiveCount : null],
          ['label' => __('nav_reports'), 'route' => 'dashboard.reports', 'icon' => 'trending-up', 'show' => $canReport, 'badge' => null],
      ]],
      ['group' => __('nav_group_feedback'), 'items' => [
          ['label' => __('nav_tickets'), 'route' => 'dashboard.tickets', 'icon' => 'circle-alert', 'show' => $canReport, 'badge' => $openTicketsCount > 0 ? $openTicketsCount : null],
          ['label' => __('nav_responses'), 'route' => 'dashboard.responses', 'icon' => 'file-text', 'show' => true, 'badge' => null],
          ['label' => __('nav_honor'), 'route' => 'dashboard.hall-of-fame', 'icon' => 'trophy', 'show' => $user->role !== 'staff', 'badge' => null],
      ]],
      ['group' => __('nav_group_management'), 'items' => [
          ['label' => __('nav_surveys'), 'route' => 'dashboard.surveys', 'icon' => 'clipboard-list', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_users'), 'route' => 'dashboard.users', 'icon' => 'user-cog', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_audit'), 'route' => 'dashboard.audit', 'icon' => 'shield-alert', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_monitoring'), 'route' => 'dashboard.monitoring', 'icon' => 'activity', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_error_logs'), 'route' => 'dashboard.error-logs', 'icon' => 'bug', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_backups'), 'route' => 'dashboard.backups', 'icon' => 'database', 'show' => $canManage, 'badge' => null],
          ['label' => __('nav_settings'), 'route' => 'dashboard.settings', 'icon' => 'settings', 'show' => $canManage, 'badge' => null],
      ]],
  ];

  $isRtl = app()->getLocale() === 'ar';
  $sidebarAlignClass = $isRtl ? 'right-0 border-l' : 'left-0 border-r';
  $mobileMenuTransitionClass = $isRtl 
      ? 'translate-x-full md:translate-x-0' 
      : '-translate-x-full md:translate-x-0';
  $mainPaddingClass = $isRtl 
      ? 'sidebarCollapsed ? "md:pr-20" : "md:pr-64"'
      : 'sidebarCollapsed ? "md:pl-20" : "md:pl-64"';
  $activeBorderClass = $isRtl ? 'border-r-2' : 'border-l-2';
@endphp

@section('content')
  <style>
    /* Prevent sidebar flash on page load */
    [x-cloak] { display: none !important; }

    @media (min-width: 768px) {
      .dashboard-sidebar {
        width: 16rem;
      }

      html.sidebar-collapsed-preload .dashboard-sidebar {
        width: 5rem;
      }

      html[dir="rtl"] .dashboard-main {
        padding-right: 16rem;
      }

      html.sidebar-collapsed-preload[dir="rtl"] .dashboard-main {
        padding-right: 5rem;
      }

      html[dir="ltr"] .dashboard-main {
        padding-left: 16rem;
      }

      html.sidebar-collapsed-preload[dir="ltr"] .dashboard-main {
        padding-left: 5rem;
      }
    }
  </style>
  <div
    x-data="{
      sidebarCollapsed: JSON.parse(localStorage.getItem('sidebar_collapsed') || 'false'),
      mobileMenuOpen: false,
      profileOpen: false,
      changePasswordOpen: {{ $errors->has('currentPassword') || $errors->has('password') ? 'true' : 'false' }},
      toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebar_collapsed', JSON.stringify(this.sidebarCollapsed));
        document.documentElement.classList.toggle('sidebar-collapsed-preload', this.sidebarCollapsed);
        this.$nextTick(() => window.lucide && lucide.createIcons());
      }
    }"
    class="min-h-screen bg-gray-50 text-gray-900 transition-colors duration-300 dark:bg-[#080b11] dark:text-slate-100 flex overflow-x-hidden"
  >
    <div x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false" class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm md:hidden"></div>

    <aside
      :class="[
        sidebarCollapsed ? 'md:w-20' : 'md:w-64',
        mobileMenuOpen ? 'translate-x-0' : '{{ $mobileMenuTransitionClass }}'
      ]"
      class="dashboard-sidebar fixed bottom-0 top-0 z-50 flex w-72 flex-col border-gray-100 bg-white shadow-lg transition-all duration-300 ease-in-out dark:border-slate-800/85 dark:bg-slate-900 md:shadow-none {{ $sidebarAlignClass }}"
    >
      <div class="flex h-16 items-center justify-between border-b border-gray-100 px-4 dark:border-slate-800/80">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 overflow-hidden">
          <img src="/system-logo.png" alt="MedSurvey Pro" class="h-10 w-10 min-w-10 rounded-xl object-cover">
          <div x-show="!sidebarCollapsed" class="animate-fade-in text-start">
            <span class="block text-sm font-black leading-none text-gray-950 dark:text-white">MedSurvey Pro</span>
            <span class="mt-1 block text-[9px] font-bold leading-none text-gray-400 dark:text-slate-500">{{ __('control_panel') }}</span>
          </div>
        </a>
        <button @click="mobileMenuOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-50 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-slate-200 md:hidden">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <nav id="sidebar-nav" class="scrollbar-hide flex-1 select-none space-y-6 overflow-y-auto px-3 py-4"
        x-init="
          $nextTick(() => {
            const savedScroll = sessionStorage.getItem('sidebar_scroll_position');
            if (savedScroll) {
              $el.scrollTop = parseInt(savedScroll, 10);
            }
            const activeLink = $el.querySelector('.border-teal-600, .dark\\:border-teal-500');
            if (activeLink) {
              activeLink.scrollIntoView({ block: 'center', behavior: 'auto' });
            }
          });
          $el.addEventListener('scroll', () => {
            sessionStorage.setItem('sidebar_scroll_position', $el.scrollTop);
          });
        "
      >
        @foreach ($links as $group)
          @php $visible = collect($group['items'])->where('show', true); @endphp
          @continue($visible->isEmpty())
          <div class="space-y-1.5">
            <h5 x-show="!sidebarCollapsed" class="animate-fade-in px-3 text-start text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">
              {{ $group['group'] }}
            </h5>
            <div x-show="sidebarCollapsed" class="mx-2 h-px bg-gray-100 dark:bg-slate-800/60"></div>
            <div class="space-y-1">
              @foreach ($visible as $link)
                @php $active = request()->routeIs($link['route']); @endphp
                <a
                  href="{{ route($link['route']) }}"
                  @click="
                    const nav = document.getElementById('sidebar-nav');
                    if (nav) sessionStorage.setItem('sidebar_scroll_position', nav.scrollTop);
                    mobileMenuOpen = false
                  "
                  class="group relative flex w-full items-center gap-3.5 rounded-xl px-3 py-2.5 text-start text-xs font-semibold transition-all sm:text-sm {{ $active ? $activeBorderClass.' border-teal-600 bg-teal-50/70 font-black text-teal-700 dark:border-teal-500 dark:bg-teal-950/20 dark:text-teal-400' : 'text-gray-500 hover:bg-gray-50 hover:text-teal-600 dark:text-slate-400 dark:hover:bg-slate-850 dark:hover:text-teal-400' }}"
                  :class="sidebarCollapsed ? 'justify-center animate-pulse-none' : ''"
                  title="{{ $link['label'] }}"
                >
                  <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4 min-w-4 {{ $active ? 'text-teal-600 dark:text-teal-400' : '' }}"></i>
                  <span x-show="!sidebarCollapsed" class="animate-fade-in truncate">{{ $link['label'] }}</span>
                  
                  @if (!empty($link['badge']))
                    <span 
                      :class="sidebarCollapsed ? 'top-1.5 {{ $isRtl ? 'right-1.5' : 'left-1.5' }}' : '{{ $isRtl ? 'left-3' : 'right-3' }} top-1/2 -translate-y-1/2'"
                      class="stat-badge absolute flex min-h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] text-white font-black ring-2 ring-white dark:ring-slate-900 animate-pulse"
                      title="{{ number_format((float) $link['badge']) }}"
                    >
                      {{ $compactCount($link['badge']) }}
                    </span>
                  @endif
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      </nav>

      <div class="hidden border-t border-gray-100 p-3 dark:border-slate-800/80 md:block">
        <button
          type="button"
          @click="toggleSidebar()"
          class="flex w-full items-center justify-center rounded-xl border border-gray-100 p-2.5 text-xs font-extrabold text-gray-400 shadow-sm transition-all hover:border-gray-200 hover:bg-gray-50 hover:text-gray-600 dark:border-slate-800/60 dark:hover:border-slate-700 dark:hover:bg-slate-850 dark:hover:text-slate-200"
        >
          <span x-show="!sidebarCollapsed" class="flex items-center gap-2.5 select-none">
            @if ($isRtl)
              <i data-lucide="chevron-right" class="h-4 w-4"></i>
            @else
              <i data-lucide="chevron-left" class="h-4 w-4"></i>
            @endif
            {{ __('collapse_sidebar') }}
          </span>
          <span x-show="sidebarCollapsed">
            @if ($isRtl)
              <i data-lucide="chevron-left" class="h-4 w-4"></i>
            @else
              <i data-lucide="chevron-right" class="h-4 w-4"></i>
            @endif
          </span>
        </button>
      </div>
    </aside>

    <div :class="sidebarCollapsed ? '{{ $isRtl ? 'md:pr-20' : 'md:pl-20' }}' : '{{ $isRtl ? 'md:pr-64' : 'md:pl-64' }}'" class="dashboard-main flex min-h-screen min-w-0 flex-1 flex-col transition-all duration-300 ease-in-out">
      <header class="sticky top-0 z-35 border-b border-gray-100 bg-white/95 shadow-sm backdrop-blur-md transition-colors dark:border-slate-800/80 dark:bg-slate-900/95">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-2 px-4 sm:h-16 sm:px-6 lg:px-8">
          <div class="flex min-w-0 items-center gap-2 sm:gap-4">
            <button @click="mobileMenuOpen = true" class="rounded-xl border border-gray-100 bg-white p-2 text-gray-500 shadow-sm hover:text-gray-700 dark:border-slate-800 dark:bg-slate-900 dark:hover:text-slate-300 md:hidden">
              <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <div class="flex min-w-0 max-w-[58vw] items-center gap-2 sm:max-w-[360px] sm:gap-2.5">
              @if(!empty($settings['hospital']['logo']))
                <div class="relative flex shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white p-0.5 shadow-md dark:border-slate-600">
                  <img src="{{ $settings['hospital']['logo'] }}" alt="{{ $settings['hospital']['name'] ?? '' }}" class="h-6 w-auto max-w-[60px] rounded-md object-contain sm:h-7 sm:max-w-[80px]">
                </div>
              @else
                <div class="flex h-8 w-8 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 text-teal-600 shadow-sm dark:border-teal-800/40 dark:bg-teal-950/40 dark:text-teal-400">
                  <i data-lucide="heart" class="h-4 w-4"></i>
                </div>
              @endif
              <div class="flex min-w-0 flex-col gap-0.5 overflow-hidden text-start">
                <span class="block whitespace-nowrap text-xs font-black leading-snug text-gray-900 dark:text-white sm:hidden">{{ $settings['hospital']['shortName'] ?? ($settings['hospital']['name'] ?? 'MedSurvey') }}</span>
                <span class="hidden whitespace-nowrap text-sm font-black leading-snug text-gray-900 dark:text-white sm:block">{{ $settings['hospital']['name'] ?? 'MedSurvey Pro' }}</span>
                <span class="block truncate text-[9px] leading-snug text-gray-400 dark:text-slate-400 sm:text-[10px]">{{ $settings['hospital']['operatingTitle'] ?? __('settings_placeholder_operating_hospital') }}</span>
              </div>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-1.5 sm:gap-2.5">
            <a href="{{ route('home') }}" class="hidden items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-gray-500 transition-all hover:bg-teal-50 hover:text-teal-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-teal-400 sm:flex">
              <i data-lucide="home" class="h-4 w-4"></i>
              {{ __('homepage') }}
            </a>
            <button @click="toggleTheme()" class="rounded-xl border border-gray-100 bg-white p-2 text-gray-500 shadow-sm hover:text-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-teal-400 cursor-pointer flex items-center justify-center">
              <svg x-show="theme === 'light'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
              <svg x-show="theme === 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
            </button>

            @if($showLanguageToggle)
            <!-- Language Switcher -->
            <div class="flex items-center gap-1">
              @if(app()->getLocale() === 'ar')
                <a href="{{ route('set-locale', 'en') }}" class="flex items-center gap-1.5 rounded-xl border border-gray-100 bg-white p-2 text-gray-500 shadow-sm hover:text-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-teal-400 cursor-pointer" title="English">
                  <i data-lucide="globe" class="h-4 w-4"></i>
                  <span class="hidden sm:inline text-xs font-bold">English</span>
                </a>
              @else
                <a href="{{ route('set-locale', 'ar') }}" class="flex items-center gap-1.5 rounded-xl border border-gray-100 bg-white p-2 text-gray-500 shadow-sm hover:text-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-teal-400 cursor-pointer" title="العربية">
                  <i data-lucide="globe" class="h-4 w-4"></i>
                  <span class="hidden sm:inline text-xs font-bold">العربية</span>
                </a>
              @endif
            </div>
            @endif

            <div class="relative" @click.away="profileOpen = false">
              <button @click="profileOpen = !profileOpen" class="flex items-center gap-2.5 rounded-full sm:rounded-xl border border-gray-150 dark:border-slate-800 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-850 p-1 sm:pr-3.5 sm:pl-2.5 transition-all shadow-sm group select-none cursor-pointer">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-linear-to-tr {{ $roleGradient }} text-[11px] font-black text-white shadow-md shadow-indigo-100 dark:shadow-none">{{ $initials }}</div>
                <div class="hidden sm:flex min-w-0 flex-col text-start">
                  <span class="text-xs font-black text-gray-800 dark:text-slate-200 leading-snug group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors font-bold truncate">{{ $user->name }}</span>
                  <span class="text-[9px] text-gray-400 dark:text-slate-500 font-bold truncate">{{ '@' . $user->username }}</span>
                </div>
                <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400 hidden sm:block transition-transform duration-250" :class="profileOpen ? 'rotate-180 text-teal-600' : ''"></i>
              </button>

              <div x-show="profileOpen" x-cloak class="absolute end-0 top-full mt-2 w-72 rounded-2xl border border-slate-200/60 bg-white/95 backdrop-blur-md py-3 shadow-2xl dark:border-slate-800/80 dark:bg-slate-900 animate-scale-in origin-top">
                <div class="mb-1 border-b border-slate-100 px-4 py-3 dark:border-slate-800 flex flex-col items-center text-center">
                  <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-linear-to-tr {{ $roleGradient }} text-lg font-black text-white shadow-lg shadow-indigo-100 dark:shadow-none mb-3">{{ $initials }}</div>
                  <h4 class="truncate text-sm font-black text-slate-900 dark:text-white max-w-full">{{ $user->name }}</h4>
                  <span class="text-xs text-gray-400 dark:text-slate-500 font-semibold mt-0.5 max-w-full">{{ '@' . $user->username }}</span>
                  
                  <div class="mt-2.5 flex flex-wrap gap-1.5 justify-center">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border shadow-sm {{ $roleBadgeStyle }}">{{ $roleLabel }}</span>
                    @if ($user->department)
                      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border border-teal-100 dark:border-teal-900/35 bg-teal-50/50 dark:bg-teal-950/15 text-teal-700 dark:text-teal-400 shadow-sm">{{ __($user->department) }}</span>
                    @endif
                  </div>
                </div>

                <div class="px-4 py-2 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider mt-2 text-start">
                  {{ __('user_profile_details') }}
                </div>
                <div class="px-4 py-1.5 space-y-2 text-start">
                  <div class="flex items-center gap-2.5 text-xs text-gray-600 dark:text-slate-400">
                    <i data-lucide="mail" class="w-3.5 h-3.5 text-gray-400"></i>
                    <span class="truncate font-semibold">{{ $user->email ?? __('none') }}</span>
                  </div>
                  @if($user->lastLogin)
                    <div class="flex items-center gap-2.5 text-[11px] text-gray-500 dark:text-slate-400 font-bold">
                      <i data-lucide="clock" class="w-3.5 h-3.5 text-gray-400"></i>
                      <span>
                        {{ __('last_login') }}: {{ $user->lastLogin->locale(app()->getLocale())->isoFormat('LL') }}
                      </span>
                    </div>
                  @endif
                </div>

                <div class="h-px bg-gray-50 dark:bg-slate-850/60 my-2.5"></div>

                <div class="px-2 space-y-1">
                  <button
                    @click="profileOpen = false; changePasswordOpen = true; $nextTick(() => window.lucide && lucide.createIcons())"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-850 cursor-pointer"
                  >
                    <i data-lucide="key-round" class="h-3.5 w-3.5 text-slate-400"></i>
                    <span>{{ __('user_action_change_password') }}</span>
                  </button>

                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-black text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-500/10 cursor-pointer">
                      <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
                      <span>{{ __('logout') }}</span>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
          @yield('dashboard')
        </div>
      </main>
    </div>

    <!-- Change Password Modal -->
    <div
      x-show="changePasswordOpen"
      x-cloak
      class="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in text-start"
      @keydown.escape.window="changePasswordOpen = false"
    >
      <div
        @click.away="changePasswordOpen = false"
        class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full animate-scale-in border border-gray-150 dark:border-slate-800 overflow-hidden"
      >
        <div class="p-6 border-b border-gray-100 dark:border-slate-850/60 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ __('user_password_modal_title') }}</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">@<span>{{ $user->username }}</span></p>
          </div>
          <button @click="changePasswordOpen = false" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 cursor-pointer">
            <i data-lucide="x" class="w-6 h-6"></i>
          </button>
        </div>

        <form method="POST" action="{{ route('dashboard.change-password') }}" class="p-6 space-y-4" x-data="{ showCurrent: false, showNew: false }">
          @csrf

          @if($errors->has('currentPassword') || $errors->has('password'))
            <div class="flex items-center gap-2 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/40 rounded-xl px-4 py-3 text-red-600 dark:text-red-400 text-sm">
              <i data-lucide="circle-alert" class="w-5 h-5 shrink-0"></i>
              <span class="font-bold">{{ $errors->first('currentPassword') ?: $errors->first('password') }}</span>
            </div>
          @endif

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ __('user_password_current_label') }}</label>
            <div class="relative font-sans">
              <input
                :type="showCurrent ? 'text' : 'password'"
                name="currentPassword"
                required
                placeholder="{{ __('user_password_current_placeholder') }}"
                class="w-full px-4 py-3 pl-12 pr-4 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-400 font-semibold"
                dir="ltr"
              />
              <button
                type="button"
                @click="showCurrent = !showCurrent"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 cursor-pointer"
              >
                <i data-lucide="eye" class="w-5 h-5" x-show="!showCurrent"></i>
                <i data-lucide="eye-off" class="w-5 h-5" x-show="showCurrent" x-cloak></i>
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ __('user_password_new_label') }}</label>
            <div class="relative font-sans">
              <input
                :type="showNew ? 'text' : 'password'"
                name="password"
                required
                placeholder="{{ __('user_password_new_placeholder') }}"
                class="w-full px-4 py-3 pl-12 pr-4 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-400 font-semibold"
                dir="ltr"
              />
              <button
                type="button"
                @click="showNew = !showNew"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 cursor-pointer"
              >
                <i data-lucide="eye" class="w-5 h-5" x-show="!showNew"></i>
                <i data-lucide="eye-off" class="w-5 h-5" x-show="showNew" x-cloak></i>
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{{ __('user_password_confirm_label') }}</label>
            <input
              :type="showNew ? 'text' : 'password'"
              name="password_confirmation"
              required
              placeholder="{{ __('user_password_confirm_placeholder') }}"
              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-400 font-semibold font-sans"
              dir="ltr"
            />
          </div>

          <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/35 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 leading-relaxed font-semibold">
            {{ __('user_password_session_note') }}
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              type="button"
              @click="changePasswordOpen = false"
              class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-750 text-gray-600 dark:text-slate-300 font-bold hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
            >
              {{ __('user_cancel') }}
            </button>
            <button
              type="submit"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 text-white font-black shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all cursor-pointer"
            >
              <i data-lucide="key-round" class="w-4 h-4"></i>
              <span>{{ __('user_password_save_btn') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
