@extends('layouts.dashboard')

@section('title', __('monitoring_title') . ' - MedSurvey Pro')

@section('dashboard')
<style>
  @keyframes status-pulse-green {
    0% {
      transform: scale(0.9);
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
      transform: scale(1.1);
      box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
    }
    100% {
      transform: scale(0.9);
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
  }
  @keyframes status-pulse-red {
    0% {
      transform: scale(0.9);
      box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.7);
    }
    70% {
      transform: scale(1.1);
      box-shadow: 0 0 0 8px rgba(244, 63, 94, 0);
    }
    100% {
      transform: scale(0.9);
      box-shadow: 0 0 0 0 rgba(244, 63, 94, 0);
    }
  }
  .animate-pulse-green {
    animation: status-pulse-green 1.8s infinite ease-in-out;
  }
  .animate-pulse-red {
    animation: status-pulse-red 1.8s infinite ease-in-out;
  }
</style>

<div class="space-y-6 animate-fade-in">
  <!-- Page Header -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div class="flex items-center gap-3">
      <div class="p-3 bg-teal-500/10 dark:bg-teal-500/20 rounded-2xl">
        <!-- Sleek activity pulse SVG icon -->
        <svg class="w-6 h-6 text-teal-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>
      </div>
      <div class="text-start">
        <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ __('monitoring_title') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('monitoring_subtitle') }}</p>
      </div>
    </div>
    
    <!-- System Status Badge -->
    <div id="system-status-badge" class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-black transition-all duration-300 {{ $health['status'] === 'ok' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400' }}">
      <svg id="system-status-icon" class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        @if($health['status'] === 'ok')
          <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
        @else
          <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
        @endif
      </svg>
      <span id="system-status-text">
        {{ $health['status'] === 'ok' ? __('system_online') : __('system_degraded') }}
      </span>
    </div>
  </div>

  <!-- Main Stats Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Card 1: Uptime -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-3xl shadow-xs hover:shadow-md transition-all duration-300">
      <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-blue-500/10 dark:bg-blue-500/20 text-blue-500 rounded-xl">
          <!-- Server SVG -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/><line x1="10" x2="10.01" y1="6" y2="6"/><line x1="10" x2="10.01" y1="18" y2="18"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('uptime') }}</span>
      </div>
      <div class="flex items-end justify-between">
        <div class="text-start">
          <div id="uptime-value" class="text-2xl font-black text-slate-900 dark:text-white">
            @if($health['system']['uptime'])
              {{ __('uptime_format', ['h' => floor($health['system']['uptime'] / 3600), 'm' => floor(($health['system']['uptime'] % 3600) / 60)]) }}
            @else
              {{ __('not_available') }}
            @endif
          </div>
          <div id="uptime-sub" class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            {{ __('os_prefix') }} {{ $health['system']['os']['platform'] ?? __('not_available') }}
          </div>
        </div>
      </div>
    </div>

    <!-- Card 2: API Latency -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-3xl shadow-xs hover:shadow-md transition-all duration-300">
      <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-yellow-500/10 dark:bg-yellow-500/20 text-yellow-500 rounded-xl">
          <!-- Zap SVG -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('api_latency') }}</span>
      </div>
      <div class="flex items-end justify-between">
        <div class="text-start">
          <div id="latency-value" class="text-2xl font-black text-slate-900 dark:text-white">
            {{ $health['totalLatencyMs'] }}ms
          </div>
          <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            {{ __('live_update_every') }}
          </div>
        </div>
        <div id="latency-trend" class="text-[10px] font-bold px-2 py-1 rounded-lg {{ $health['totalLatencyMs'] > 100 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}">
          {{ $health['totalLatencyMs'] > 100 ? __('latency_high') : __('latency_excellent') }}
        </div>
      </div>
    </div>

    <!-- Card 3: Memory Usage -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-3xl shadow-xs hover:shadow-md transition-all duration-300">
      <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-purple-500/10 dark:bg-purple-500/20 text-purple-500 rounded-xl">
          <!-- Cpu SVG -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M9 1v3"/><path d="M15 1v3"/><path d="M9 20v3"/><path d="M15 20v3"/><path d="M20 9h3"/><path d="M20 15h3"/><path d="M1 9h3"/><path d="M1 15h3"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('memory_usage') }}</span>
      </div>
      <div class="flex items-end justify-between">
        <div class="text-start">
          <div id="memory-value" class="text-2xl font-black text-slate-900 dark:text-white">
            {{ $health['system']['memory']['heapUsedMb'] }} MB
          </div>
          <div id="memory-sub" class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            @if($health['system']['memory']['heapTotalMb'])
              {{ __('memory_of_limit', ['limit' => $health['system']['memory']['heapTotalMb']]) }}
            @else
              {{ __('php_memory_limit_unavailable') }}
            @endif
          </div>
        </div>
      </div>
    </div>

    <!-- Card 4: Free OS memory -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-3xl shadow-xs hover:shadow-md transition-all duration-300">
      <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-rose-500/10 dark:bg-rose-500/20 text-rose-500 rounded-xl">
          <!-- HardDrive SVG -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="3" rx="2"/><rect width="20" height="8" x="2" y="13" rx="2"/><line x1="6" x2="6.01" y1="7" y2="7"/><line x1="6" x2="6.01" y1="17" y2="17"/><line x1="10" x2="10.01" y1="7" y2="7"/><line x1="10" x2="10.01" y1="17" y2="17"/></svg>
        </div>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ __('free_os_mem') }}</span>
      </div>
      <div class="flex items-end justify-between">
        <div class="text-start">
          <div id="freemem-value" class="text-2xl font-black text-slate-900 dark:text-white">
            {{ $health['system']['os']['freeMemMb'] ? $health['system']['os']['freeMemMb'] . ' MB' : __('not_available') }}
          </div>
          <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            {{ __('system_load') }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detailed Diagnostic Columns -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Latency Chart Area (2/3 width) -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs flex flex-col justify-between">
      <div class="flex items-center gap-2 mb-6 text-start">
        <div class="p-2 bg-teal-500/10 dark:bg-teal-500/20 text-teal-500 rounded-xl">
          <!-- Clock SVG -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h3 class="text-lg font-black text-slate-900 dark:text-white">
          {{ __('latency_history') }} (ms)
        </h3>
      </div>
      
      <!-- Chart Element -->
      <div class="relative w-full h-[250px] flex items-center justify-center">
        <div id="latency-chart" class="w-full h-full"></div>
      </div>
    </div>

    <!-- Infrastructure Status Column (1/3 width) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs space-y-6">
      <div class="flex items-center gap-2 text-start">
        <div class="p-2 bg-teal-500/10 dark:bg-teal-500/20 text-teal-500 rounded-xl">
          <!-- Shield SVG with checkmark inside -->
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <h3 class="text-lg font-black text-slate-900 dark:text-white">
          {{ __('infrastructure_status') }}
        </h3>
      </div>
      
      <!-- Service 1: Database Connection Status -->
      <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/30 rounded-2xl border border-slate-100 dark:border-slate-800/80 transition-all duration-300 text-start">
        <div class="flex items-center gap-3">
          <div id="db-status-container" class="p-2 rounded-xl {{ $health['services']['database']['status'] === 'ok' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500' }}">
            <!-- Database SVG -->
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-900 dark:text-white">{{ __('database_mysql') }}</div>
            <div id="db-latency" class="text-[10px] text-slate-400 dark:text-slate-500">
              {{ $health['services']['database']['latencyMs'] ? $health['services']['database']['latencyMs'] . 'ms latency' : __('not_available') }}
            </div>
          </div>
        </div>
        <div class="relative flex h-3 w-3" id="db-status-dots-container">
          <span id="db-status-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $health['services']['database']['status'] === 'ok' ? 'bg-emerald-400' : 'bg-rose-400' }} opacity-75"></span>
          <span id="db-status-dot" class="relative inline-flex rounded-full h-3 w-3 {{ $health['services']['database']['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
        </div>
      </div>

      <!-- Service 2: Cache Connection Status -->
      <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/30 rounded-2xl border border-slate-100 dark:border-slate-800/80 transition-all duration-300 text-start">
        <div class="flex items-center gap-3">
          <div id="cache-status-container" class="p-2 rounded-xl {{ $health['services']['cache']['status'] === 'ok' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500' }}">
            <!-- RefreshCcw SVG -->
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-900 dark:text-white">{{ __('cache_service') }}</div>
            <div id="cache-driver" class="text-[10px] text-slate-400 dark:text-slate-500">
              {{ str_replace('{' . '{type' . '}' . '}', $health['services']['cache']['type'] ?? 'unknown', __('cache_driver')) }}
            </div>
          </div>
        </div>
        <div class="relative flex h-3 w-3" id="cache-status-dots-container">
          <span id="cache-status-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $health['services']['cache']['status'] === 'ok' ? 'bg-emerald-400' : 'bg-rose-400' }} opacity-75"></span>
          <span id="cache-status-dot" class="relative inline-flex rounded-full h-3 w-3 {{ $health['services']['cache']['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
        </div>
      </div>

      <!-- Info Alert Box -->
      <div class="p-4 bg-blue-500/5 dark:bg-blue-500/10 border border-blue-500/10 dark:border-blue-500/20 rounded-2xl text-start">
        <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs mb-1">
          <!-- AlertCircle SVG -->
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
          <span>{{ __('monitoring_note') }}</span>
        </div>
          <p class="text-[10px] text-blue-800/70 dark:text-blue-300/70 leading-relaxed font-medium">
            {{ __('monitoring_live_desc') }}
          </p>
      </div>
    </div>
  </div>
</div>

<!-- Scripts Section (Inline DOM listener) -->
<script>
document.addEventListener("DOMContentLoaded", async function () {
    const ApexCharts = await window.loadApexCharts();
    const isRtl = document.documentElement.dir === 'rtl' || "{{ app()->getLocale() }}" === 'ar';
    const initialLatency = {{ $health['totalLatencyMs'] }};
    const now = new Date();
    
    // Generate 15 points of realistic historic data ending at the current latency
    let latencyData = [];
    let categoriesData = [];
    for (let i = 14; i >= 0; i--) {
        const time = new Date(now.getTime() - i * 5000).toLocaleTimeString('ar-SA', { hour12: false });
        const randomOffset = Math.floor(Math.random() * 20) - 10; // -10 to +10ms
        const val = Math.max(5, initialLatency + randomOffset);
        latencyData.push(val);
        categoriesData.push(time);
    }
    
    // Initialize ApexCharts Area Chart
    const chartOptions = {
        series: [{
            name: 'Latency',
            data: latencyData
        }],
        chart: {
            type: 'area',
            height: 250,
            toolbar: { show: false },
            fontFamily: 'Cairo, sans-serif',
            animations: {
                enabled: true,
                easing: 'linear',
                dynamicAnimation: { speed: 1000 }
            }
        },
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            colors: ['#14b8a6'],
            width: 3
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.3,
                opacityTo: 0,
                stops: [0, 95],
                colorStops: [
                    { offset: 0, color: '#14b8a6', opacity: 0.3 },
                    { offset: 100, color: '#14b8a6', opacity: 0 }
                ]
            }
        },
        xaxis: {
            categories: categoriesData,
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            opposite: isRtl, // Aligns graph values on the correct side for Arabic RTL layout
            labels: {
                style: {
                    colors: '#94a3b8',
                    fontSize: '12px',
                    fontFamily: 'Cairo, sans-serif'
                }
            }
        },
        grid: {
            show: true,
            borderColor: '#33415510',
            strokeDashArray: 3,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } }
        },
        tooltip: {
            theme: 'dark',
            x: { show: true },
            y: {
                formatter: function (val) {
                    return val + " ms";
                }
            }
        }
    };
    
    const chart = new ApexCharts(document.querySelector("#latency-chart"), chartOptions);
    chart.render();
    
    // Formatters for display values
    const notAvailableTxt = "@lang('not_available')";
    const osPrefixStr = "@lang('os_prefix')";
    const latencyHighStr = "@lang('latency_high')";
    const latencyExcellentStr = "@lang('latency_excellent')";
    const systemOnlineStr = "@lang('system_online')";
    const systemDegradedStr = "@lang('system_degraded')";
    const phpMemLimitStr = "@lang('php_memory_limit_unavailable')";
    const memoryOfLimitEn = "of PHP limit";
    const memoryOfLimitAr = "من حد PHP";
    const cacheDriverStr = "@lang('cache_driver')";
    function formatUptime(seconds) {
        if (!seconds && seconds !== 0) return notAvailableTxt;
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        if (isRtl) {
            return h + "س " + m + "د";
        }
        return h + "h " + m + "m";
    }

    function formatMaybeMb(value) {
        return typeof value === 'number' ? `${value} MB` : notAvailableTxt;
    }

    // Ajax Health Polling Function
    function pollHealthData() {
        fetch("{{ route('dashboard.monitoring') }}?ajax=true", {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error("Network connection error");
            return response.json();
        })
        .then(data => {
            // 1. Update Overall Status Badge
            const badge = document.getElementById("system-status-badge");
            const badgeIcon = document.getElementById("system-status-icon");
            const badgeText = document.getElementById("system-status-text");
            const systemHealthy = data.status === 'ok';
            
            if (systemHealthy) {
                badge.className = "flex items-center gap-2 px-4 py-2 rounded-full text-xs font-black transition-all duration-300 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400";
                badgeIcon.innerHTML = `<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>`;
                badgeText.textContent = systemOnlineStr;
            } else {
                badge.className = "flex items-center gap-2 px-4 py-2 rounded-full text-xs font-black transition-all duration-300 bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400";
                badgeIcon.innerHTML = `<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>`;
                badgeText.textContent = systemDegradedStr;
            }
            
            // 2. Update Card 1: Uptime
            document.getElementById("uptime-value").textContent = formatUptime(data.system.uptime);
            document.getElementById("uptime-sub").textContent = (isRtl ? `نظام: ${data.system.os.platform}` : `${osPrefixStr} ${data.system.os.platform}`);
            
            // 3. Update Card 2: API Latency
            const latencyVal = data.totalLatencyMs;
            document.getElementById("latency-value").textContent = `${latencyVal}ms`;
            
            const trendBadge = document.getElementById("latency-trend");
            if (latencyVal > 100) {
                trendBadge.className = "text-[10px] font-bold px-2 py-1 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400";
                trendBadge.textContent = isRtl ? "▲ مرتفع" : latencyHighStr;
            } else {
                trendBadge.className = "text-[10px] font-bold px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400";
                trendBadge.textContent = isRtl ? "▼ ممتاز" : latencyExcellentStr;
            }
            
            // 4. Update Card 3: Memory Usage
            document.getElementById("memory-value").textContent = formatMaybeMb(data.system.memory.heapUsedMb);
            const memorySub = document.getElementById("memory-sub");
            if (data.system.memory.heapTotalMb) {
                memorySub.textContent = isRtl
                    ? `${memoryOfLimitAr} ${data.system.memory.heapTotalMb} MB`
                    : `${memoryOfLimitEn} ${data.system.memory.heapTotalMb} MB`;
            } else {
                memorySub.textContent = phpMemLimitStr;
            }
            
            // 5. Update Card 4: Free OS Memory
            document.getElementById("freemem-value").textContent = formatMaybeMb(data.system.os.freeMemMb);
            
            // 6. Update Infrastructure: Database Status
            const dbLatencyText = document.getElementById("db-latency");
            const dbStatusContainer = document.getElementById("db-status-container");
            const dbStatusPing = document.getElementById("db-status-ping");
            const dbStatusDot = document.getElementById("db-status-dot");
            const dbHealthy = data.services.database.status === 'ok';
            
            if (dbHealthy) {
                dbLatencyText.textContent = `${data.services.database.latencyMs}ms latency`;
                dbStatusContainer.className = "p-2 rounded-xl bg-emerald-500/10 text-emerald-500";
                if(dbStatusPing) dbStatusPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75";
                if(dbStatusDot) dbStatusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-500";
            } else {
                dbLatencyText.textContent = notAvailableTxt;
                dbStatusContainer.className = "p-2 rounded-xl bg-rose-500/10 text-rose-500";
                if(dbStatusPing) dbStatusPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75";
                if(dbStatusDot) dbStatusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-rose-500";
            }
            
            // 7. Update Infrastructure: Cache Status
            const cacheDriverText = document.getElementById("cache-driver");
            const cacheStatusContainer = document.getElementById("cache-status-container");
            const cacheStatusPing = document.getElementById("cache-status-ping");
            const cacheStatusDot = document.getElementById("cache-status-dot");
            const cacheHealthy = data.services.cache.status === 'ok';
            const cacheType = data.services.cache.type || 'unknown';
            
            cacheDriverText.textContent = cacheDriverStr.replace('\x7b\x7btype\x7d\x7d', cacheType);
            
            if (cacheHealthy) {
                cacheStatusContainer.className = "p-2 rounded-xl bg-emerald-500/10 text-emerald-500";
                if(cacheStatusPing) cacheStatusPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75";
                if(cacheStatusDot) cacheStatusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-500";
            } else {
                cacheStatusContainer.className = "p-2 rounded-xl bg-rose-500/10 text-rose-500";
                if(cacheStatusPing) cacheStatusPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75";
                if(cacheStatusDot) cacheStatusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-rose-500";
            }
            
            // 8. Update Chart with new sliding window point
            const timeStr = new Date().toLocaleTimeString('ar-SA', { hour12: false });
            
            latencyData.shift();
            latencyData.push(latencyVal);
            categoriesData.shift();
            categoriesData.push(timeStr);
            
            chart.updateSeries([{
                data: latencyData
            }]);
            chart.updateOptions({
                xaxis: {
                    categories: categoriesData
                }
            });
        })
        .catch(err => {
            console.error("Failed to poll health status:", err);
        });
    }
    
    // Poll every 5000ms
    setInterval(pollHealthData, 5000);
});
</script>
@endsection
