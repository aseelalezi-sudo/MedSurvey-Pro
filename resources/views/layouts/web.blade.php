@php
  $primaryColor = $settings['appearance']['primaryColor'] ?? '#0d9488';
  $secondaryColor = $settings['appearance']['secondaryColor'] ?? '#10b981';
  $primaryHex = ltrim($primaryColor, '#');
  $secondaryHex = ltrim($secondaryColor, '#');
  if (strlen($primaryHex) === 3) $primaryHex = $primaryHex[0].$primaryHex[0].$primaryHex[1].$primaryHex[1].$primaryHex[2].$primaryHex[2];
  if (strlen($secondaryHex) === 3) $secondaryHex = $secondaryHex[0].$secondaryHex[0].$secondaryHex[1].$secondaryHex[1].$secondaryHex[2].$secondaryHex[2];
  $primaryR = hexdec(substr($primaryHex, 0, 2));
  $primaryG = hexdec(substr($primaryHex, 2, 2));
  $primaryB = hexdec(substr($primaryHex, 4, 2));
  $secondaryR = hexdec(substr($secondaryHex, 0, 2));
  $secondaryG = hexdec(substr($secondaryHex, 2, 2));
  $secondaryB = hexdec(substr($secondaryHex, 4, 2));
  $showLanguageToggle = ($settings['appearance']['showLanguageToggle'] ?? true) !== false;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MedSurvey Pro')</title>
    <meta name="description" content="@yield('description', __('system_description'))">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="manifest" href="/build/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    @vite(['resources/css/app.css', 'resources/js/main.ts'])

    <!-- Dynamic Theme Colors -->
    <style>
      :root {
        --color-primary: {{ $primaryColor }};
        --color-primary-rgb: {{ $primaryR }}, {{ $primaryG }}, {{ $primaryB }};
        --color-secondary: {{ $secondaryColor }};
        --color-secondary-rgb: {{ $secondaryR }}, {{ $secondaryG }}, {{ $secondaryB }};
      }
      .text-teal-600, .dark .dark\:text-teal-400 { color: var(--color-primary) !important; }
      .text-teal-500, .dark .dark\:text-teal-300 { color: var(--color-primary) !important; }
      .text-teal-700, .dark .dark\:text-teal-500 { color: var(--color-primary) !important; }
      .border-teal-600, .dark .dark\:border-teal-500 { border-color: var(--color-primary) !important; border-right-color: var(--color-primary) !important; }
      .border-teal-200, .dark .dark\:border-teal-900\/40 { border-color: rgba(var(--color-primary-rgb), 0.4) !important; }
      .border-teal-100, .dark .dark\:border-teal-900\/35 { border-color: rgba(var(--color-primary-rgb), 0.25) !important; }
      .bg-teal-50 { background-color: rgba(var(--color-primary-rgb), 0.1) !important; }
      .bg-teal-50\/70 { background-color: rgba(var(--color-primary-rgb), 0.17) !important; }
      .bg-teal-50\/50 { background-color: rgba(var(--color-primary-rgb), 0.05) !important; }
      .dark .dark\:bg-teal-950\/20 { background-color: rgba(var(--color-primary-rgb), 0.2) !important; }
      .dark .dark\:bg-teal-950\/40 { background-color: rgba(var(--color-primary-rgb), 0.4) !important; }
      .dark .dark\:bg-teal-950\/15 { background-color: rgba(var(--color-primary-rgb), 0.15) !important; }
      .hover\:text-teal-600:hover, .dark .dark\:hover\:text-teal-400:hover { color: var(--color-primary) !important; }
      .hover\:bg-teal-50:hover { background-color: rgba(var(--color-primary-rgb), 0.1) !important; }
      .group:hover .group-hover\:text-teal-600, .dark .group:hover .dark\:group-hover\:text-teal-400 { color: var(--color-primary) !important; }
      .ring-teal-500 { --tw-ring-color: var(--color-primary) !important; }
      .focus\:border-teal-500:focus { border-color: var(--color-primary) !important; }
      .focus\:ring-teal-500:focus { --tw-ring-color: var(--color-primary) !important; }
      .focus\:ring-teal-100:focus { --tw-ring-color: rgba(var(--color-primary-rgb), 0.25) !important; }
      .dark .focus\:ring-teal-950\/15:focus { --tw-ring-color: rgba(var(--color-primary-rgb), 0.15) !important; }
      .from-teal-600 { --tw-gradient-from: var(--color-primary) !important; }
      .to-emerald-600 { --tw-gradient-to: var(--color-secondary) !important; }
      .from-teal-500 { --tw-gradient-from: var(--color-primary) !important; }
      .shadow-teal-200 { box-shadow: 0 4px 6px -1px rgba(var(--color-primary-rgb), 0.2), 0 2px 4px -2px rgba(var(--color-primary-rgb), 0.1) !important; }
      .shadow-teal-500\/10 { box-shadow: 0 4px 6px -1px rgba(var(--color-primary-rgb), 0.1), 0 2px 4px -2px rgba(var(--color-primary-rgb), 0.05) !important; }
      .shadow-teal-100 { box-shadow: 0 4px 6px -1px rgba(var(--color-primary-rgb), 0.15), 0 2px 4px -2px rgba(var(--color-primary-rgb), 0.08) !important; }
      .dark .dark\:shadow-teal-950\/20 { box-shadow: 0 4px 6px -1px rgba(var(--color-primary-rgb), 0.2), 0 2px 4px -2px rgba(var(--color-primary-rgb), 0.1) !important; }
    </style>

    <!-- Pre-render theme check to prevent flashing -->
    <script>
      (function () {
        const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (theme === 'dark') {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }

        if (localStorage.getItem('sidebar_collapsed') === 'true') {
          document.documentElement.classList.add('sidebar-collapsed-preload');
        } else {
          document.documentElement.classList.remove('sidebar-collapsed-preload');
        }
      })();
    </script>
  </head>
  <body class="font-cairo text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100" x-data="{ 
    theme: localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
    toggleTheme() {
      this.theme = this.theme === 'light' ? 'dark' : 'light';
      localStorage.setItem('theme', this.theme);
      if (this.theme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    }
  }" x-init="if (typeof lucide !== 'undefined') lucide.createIcons()">
    <div class="web-shell min-h-screen">
      <!-- Header -->
      @if(!isset($hideHeader) || !$hideHeader)
      <header class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-md border-b border-slate-100 dark:border-slate-800/60 sticky top-0 z-50 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between h-16 gap-2 min-w-0">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
              
              <!-- System Branding -->
              <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl overflow-hidden drop-shadow-md">
                  <img src="/system-logo.png" alt="MedSurvey Pro" class="w-full h-full object-cover">
                </div>
                <div class="text-start hidden sm:block">
                  <h1 class="text-sm sm:text-lg font-black text-gray-900 dark:text-white leading-none">MedSurvey Pro</h1>
                  <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-slate-400 block mt-0.5">{{ __('system_description') }}</span>
                </div>
              </a>

              <!-- Elegant Divider -->
              <div class="hidden sm:block h-8 w-px bg-gray-200 dark:bg-slate-800 mx-1 sm:mx-2 shrink-0"></div>

              <!-- Hospital Branding -->
              <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                @if(!empty($settings['hospital']['logo']))
                  <div class="relative group bg-white p-0.5 rounded-lg border border-gray-200 dark:border-slate-655 shadow-md flex items-center justify-center shrink-0">
                    <img
                      src="{{ $settings['hospital']['logo'] }}"
                      alt="{{ $settings['hospital']['name'] ?? '' }}"
                      class="h-7 sm:h-9 w-auto max-w-[56px] sm:max-w-[80px] object-contain rounded-md transform group-hover:scale-105 transition-transform duration-300"
                    />
                  </div>
                @else
                  <div class="w-8 h-8 bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800/40 rounded-lg flex items-center justify-center text-teal-600 dark:text-teal-400">
                    <i data-lucide="heart" class="w-4 h-4"></i>
                  </div>
                @endif
                <div class="text-start hidden min-[360px]:block">
                  @php
                    $hospitalMobileName = $settings['hospital']['shortName'] ?? ($settings['hospital']['name'] ?? '');
                  @endphp
                  <span class="text-xs sm:hidden font-bold text-teal-700 dark:text-teal-400 block whitespace-nowrap">{{ $hospitalMobileName }}</span>
                  <span class="hidden sm:block text-sm font-bold text-teal-700 dark:text-teal-400 whitespace-nowrap">{{ $settings['hospital']['name'] ?? '' }}</span>
                  <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-slate-500 block leading-none mt-0.5 whitespace-nowrap">{{ $settings['hospital']['operatingTitle'] ?? __('settings_placeholder_operating_hospital') }}</span>
                </div>
              </div>

            </div>

            <!-- Header Controls -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
              @if($showLanguageToggle)
              <!-- Simple Language Switcher -->
              <div class="flex items-center gap-1">
                @if(app()->getLocale() === 'ar')
                  <form method="POST" action="{{ route('set-locale', 'en') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors px-2.5 py-1.5 rounded-lg hover:bg-teal-50 dark:hover:bg-slate-800 cursor-pointer font-bold">
                    <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                    <span>English</span>
                  </button>
                  </form>
                @else
                  <form method="POST" action="{{ route('set-locale', 'ar') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors px-2.5 py-1.5 rounded-lg hover:bg-teal-50 dark:hover:bg-slate-800 cursor-pointer font-bold">
                    <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                    <span>ط§ظ„ط¹ط±ط¨ظٹط©</span>
                  </button>
                  </form>
                @endif
              </div>
              @endif

              <!-- Theme Toggler -->
              <button @click="toggleTheme()" class="p-2 rounded-xl border border-slate-200/60 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900 text-slate-500 dark:text-slate-400 cursor-pointer" aria-label="{{ __('toggle_theme') }}">
                <span x-show="theme === 'light'">
                  <i data-lucide="moon" class="w-4 h-4"></i>
                </span>
                <span x-show="theme === 'dark'" style="display: none;">
                  <i data-lucide="sun" class="w-4 h-4"></i>
                </span>
              </button>

              <!-- Panel Link & Session Control -->
              @auth
                <a href="{{ route('dashboard.index') }}" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors px-2.5 sm:px-3 py-2 rounded-lg hover:bg-teal-50 dark:hover:bg-slate-800 cursor-pointer font-bold">
                  <i data-lucide="settings" class="w-4 h-4"></i>
                  <span class="hidden sm:inline">{{ __('admin_panel') }}</span>
                </a>
              @else
                <a href="{{ route('login') }}" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors px-2.5 sm:px-3 py-2 rounded-lg hover:bg-teal-50 dark:hover:bg-slate-800 cursor-pointer font-bold">
                  <i data-lucide="settings" class="w-4 h-4"></i>
                  <span class="hidden sm:inline">{{ __('admin_panel') }}</span>
                </a>
              @endauth
            </div>

          </div>
        </div>
      </header>
      @endif

      <main>
        @yield('content')
      </main>
    </div>

    <!-- Initialize Lucide Icons after DOM Load -->
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') {
          lucide.createIcons();
        }
      });
    </script>
  </body>
</html>
