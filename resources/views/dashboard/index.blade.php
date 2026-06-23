@extends('layouts.dashboard')

@section('title', __('dashboard_title').' - MedSurvey Pro')

@php
  $totalResponses = $advancedStats['totalResponses'] ?? $stats['responses'];
  $averageScore = $advancedStats['averageScore'] ?? $stats['averageScore'];
  $npsScore = $advancedStats['npsScore'] ?? 0;
  $responseRate = $advancedStats['responseRate'] ?? 100;
  $departmentScores = collect($advancedStats['departmentScores'] ?? []);
  $topDepartments = collect($honorBoardDepartments ?? []);

  $prevAverageScore = $advancedStats['previousAverageScore'] ?? $averageScore;
  $avgTrend = $averageScore - $prevAverageScore;
  $avgTrendColor = $avgTrend >= 0 ? 'text-green-500 bg-green-50 dark:bg-green-500/10' : 'text-red-500 bg-red-50 dark:bg-red-500/10';

  $prevNpsScore = $advancedStats['previousNpsScore'] ?? $npsScore;
  $npsTrend = $npsScore - $prevNpsScore;
  $npsTrendColor = $npsTrend >= 0 ? 'text-green-500 bg-green-50 dark:bg-green-500/10' : 'text-red-500 bg-red-50 dark:bg-red-500/10';

  $prevResponseRate = $advancedStats['previousResponseRate'] ?? $responseRate;
  $rateTrend = $responseRate - $prevResponseRate;
  $rateTrendColor = $rateTrend >= 0 ? 'text-green-500 bg-green-50 dark:bg-green-500/10' : 'text-red-500 bg-red-50 dark:bg-red-500/10';

  $settingsService = app(\App\Services\SettingsService::class);
  $allSettings = $settingsService->getAll(auth()->user()->tenantId);
  $activatedPlans = $allSettings['activatedPredictivePlans'] ?? [];
  $unactivatedWarningsCount = collect($predictive['alerts'] ?? [])->filter(fn ($alert) => !in_array($alert['department'], $activatedPlans))->count();
  $formatNumber = [\App\Support\NumberFormatter::class, 'format'];
  $compactNumber = [\App\Support\NumberFormatter::class, 'compact'];
@endphp

@section('dashboard')
  <div class="space-y-6 animate-fade-in">
    @if($openTickets->isNotEmpty())
      <div class="flex flex-col gap-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-start dark:border-red-800/40 dark:bg-red-950/20 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-650 dark:bg-red-950/40 dark:text-red-400">
            <i data-lucide="circle-alert" class="h-6 w-6"></i>
          </div>
          <div>
            <p class="text-sm font-black text-red-800 dark:text-red-300" title="{{ $formatNumber($openTickets->count()) }}">{{ __('dashboard_tickets_need_followup', ['count' => $compactNumber($openTickets->count())]) }}</p>
            <p class="mt-0.5 text-xs font-bold text-red-600 dark:text-red-400">{{ __('dashboard_tickets_need_followup_desc') }}</p>
          </div>
        </div>
        <a href="{{ route('dashboard.tickets') }}" class="rounded-xl bg-red-600 px-4 py-2 text-center text-xs font-black text-white transition-colors hover:bg-red-700">{{ __('dashboard_view_tickets') }}</a>
      </div>
    @endif

    <div class="relative overflow-hidden rounded-2xl border border-indigo-500/20 bg-linear-to-r from-slate-900 via-indigo-950 to-slate-900 p-4 text-white sm:p-5">
      <div class="pointer-events-none absolute -right-5 -top-5 h-24 w-24 rounded-full bg-indigo-500 opacity-20 blur-[40px]"></div>
      <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-300">
            <i data-lucide="brain" class="h-5 w-5"></i>
            @if($unactivatedWarningsCount > 0)
              <span class="stat-badge absolute -right-1.5 -top-1.5 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black text-white ring-2 ring-slate-900" title="{{ $formatNumber($unactivatedWarningsCount) }}">
                {{ $compactNumber($unactivatedWarningsCount) }}
              </span>
            @endif
          </div>
          <div class="text-start">
            <p class="flex flex-wrap items-center gap-1.5 text-sm font-black leading-none">
              <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-300"></i>
              {{ __('dashboard_predictive_title') }}
            </p>
            <p class="mt-1 text-xs leading-5 text-indigo-200/75">{{ __('dashboard_predictive_desc') }}</p>
          </div>
        </div>
        <a href="{{ route('dashboard.predictive') }}" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-center text-xs font-black text-white shadow-lg shadow-indigo-950 transition-colors hover:bg-indigo-700">{{ __('dashboard_view_predictions') }}</a>
      </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="page-kicker">{{ __('dashboard_kicker') }}</p>
        <h1 class="page-title">{{ __('dashboard_title') }}</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ __('dashboard_desc') }}</p>
      </div>
      <a href="{{ route('survey.selection') }}" class="gradient-action">
        <i data-lucide="clipboard-list" class="h-4 w-4"></i>
        {{ __('dashboard_open_surveys') }}
      </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      @foreach ([
        ['label' => __('responses_with_name'), 'value' => $identityStats['nameCount'], 'rate' => $identityStats['nameRate'], 'color' => 'from-teal-500 to-emerald-500', 'hover' => 'group-hover:text-teal-600 dark:group-hover:text-teal-400'],
        ['label' => __('responses_with_phone'), 'value' => $identityStats['phoneCount'], 'rate' => $identityStats['phoneRate'], 'color' => 'from-blue-500 to-indigo-500', 'hover' => 'group-hover:text-blue-600 dark:group-hover:text-blue-400'],
      ] as $item)
        <a href="{{ route('dashboard.responses') }}" class="dashboard-panel group block p-5 transition-all hover:-translate-y-0.5 hover:shadow-md">
          <div class="mb-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-bold text-gray-500 transition-colors dark:text-slate-400 {{ $item['hover'] }}">{{ $item['label'] }}</p>
              <div class="stat-number mt-1 text-2xl font-black text-gray-900 dark:text-white" title="{{ $formatNumber($item['value']) }}">{{ $compactNumber($item['value']) }}</div>
            </div>
            <div class="stat-badge rounded-full bg-teal-50 px-3 py-1 text-sm font-black text-teal-700 dark:bg-teal-950/40 dark:text-teal-300" title="{{ $formatNumber($item['rate'], 1) }}%">{{ $formatNumber($item['rate'], 1) }}%</div>
          </div>
          <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800">
            <div class="h-full rounded-full bg-linear-to-r {{ $item['color'] }}" style="width: {{ $item['rate'] }}%"></div>
          </div>
        </a>
      @endforeach
    </div>

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      @foreach ([
        ['label' => __('total_responses'), 'value' => $totalResponses, 'icon' => 'users', 'color' => 'from-blue-500 to-indigo-500', 'shadow' => 'shadow-blue-200 dark:shadow-blue-900/20', 'trend' => null],
        ['label' => __('satisfaction_rate'), 'value' => $averageScore.'%', 'icon' => 'trending-up', 'color' => $averageScore >= 85 ? 'from-green-500 to-emerald-500' : ($averageScore >= 70 ? 'from-blue-500 to-indigo-500' : ($averageScore >= 50 ? 'from-amber-500 to-orange-500' : 'from-red-500 to-rose-500')), 'shadow' => 'shadow-teal-200 dark:shadow-teal-900/20', 'trend' => ['val' => abs($avgTrend).'%', 'dir' => $avgTrend >= 0 ? 'up' : 'down', 'color' => $avgTrendColor]],
        ['label' => __('nps_indicator'), 'value' => $npsScore, 'icon' => 'target', 'color' => $npsScore >= 50 ? 'from-green-500 to-emerald-500' : ($npsScore >= 0 ? 'from-amber-500 to-orange-500' : 'from-red-500 to-rose-500'), 'shadow' => 'shadow-green-200 dark:shadow-green-900/20', 'trend' => ['val' => abs($npsTrend), 'dir' => $npsTrend >= 0 ? 'up' : 'down', 'color' => $npsTrendColor]],
        ['label' => __('response_rate'), 'value' => $responseRate.'%', 'icon' => 'percent', 'color' => 'from-purple-500 to-violet-500', 'shadow' => 'shadow-purple-200 dark:shadow-purple-900/20', 'trend' => ['val' => abs($rateTrend).'%', 'dir' => $rateTrend >= 0 ? 'up' : 'down', 'color' => $rateTrendColor]],
      ] as $i => $stat)
        <a href="{{ route('dashboard.responses') }}" class="metric-card animate-slide-up text-start" style="animation-delay: {{ $i * 80 }}ms">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-linear-to-r {{ $stat['color'] }} text-white shadow-lg {{ $stat['shadow'] }}">
              <i data-lucide="{{ $stat['icon'] }}" class="h-6 w-6"></i>
            </div>
            @if(isset($stat['trend']))
              <div class="flex min-w-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black {{ $stat['trend']['color'] }}">
                <span dir="ltr">{{ $stat['trend']['val'] }}</span>
                <i data-lucide="{{ $stat['trend']['dir'] === 'up' ? 'arrow-up' : 'arrow-down' }}" class="h-3 w-3"></i>
              </div>
            @endif
          </div>
          <div class="stat-number text-2xl font-black text-gray-900 dark:text-white sm:text-3xl" title="{{ is_numeric($stat['value']) ? $formatNumber($stat['value']) : $stat['value'] }}">
            {{ is_numeric($stat['value']) ? $compactNumber($stat['value']) : $stat['value'] }}
          </div>
          <div class="mt-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-slate-400">{{ $stat['label'] }}</div>
        </a>
      @endforeach
    </div>

    @if($totalResponses > 0)

      <!-- Charts Grid -->
      <div class="space-y-6">
        <!-- Row 1: Weekly Trend & Satisfaction Distribution -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="bar-chart-3" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('weekly_trend') }}</h3>
            </div>
            <div id="weeklyTrendChart" class="w-full min-h-[300px]"></div>
          </div>

          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="pie-chart" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('satisfaction_distribution') }}</h3>
            </div>
            <div id="satisfactionDistributionChart" class="w-full min-h-[300px]"></div>
          </div>
        </div>

        <!-- Row 2: Department Satisfaction & Category Analysis -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="building-2" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('dept_satisfaction') }}</h3>
            </div>
            <div id="deptSatisfactionChart" class="w-full min-h-[320px]"></div>
          </div>

          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="target" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('category_analysis') }}</h3>
            </div>
            @if(empty($advancedStats['categoryScores']))
              <div class="w-full min-h-[320px] flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                <i data-lucide="bar-chart-2" class="w-12 h-12 mb-3 opacity-20"></i>
                <p class="text-sm font-bold">{{ app()->getLocale() === 'ar' ? 'لا توجد بيانات كافية لعرض هذا المؤشر' : 'Not enough data to display this chart' }}</p>
              </div>
            @else
              <div id="categoryRadarChart" class="w-full min-h-[320px] flex items-center justify-center"></div>
            @endif
          </div>
        </div>

        <!-- Row 3: Hourly Analysis & Daily Quality -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="clock" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('hourly_analysis') }}</h3>
            </div>
            <div id="hourlyAnalysisChart" class="w-full min-h-[280px]"></div>
          </div>

          <div class="dashboard-panel p-6 text-start">
            <div class="mb-4 flex items-center gap-2">
              <i data-lucide="trending-up" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-black text-gray-800 dark:text-white">{{ __('daily_quality') }}</h3>
            </div>
            <div id="dailyQualityChart" class="w-full min-h-[280px]"></div>
          </div>
        </div>
      </div>

      <!-- Charts Initialization and Theme Sync Script -->
      <script>
        document.addEventListener('DOMContentLoaded', async () => {
          const ApexCharts = await window.loadApexCharts();
          const isRtl = "{{ app()->getLocale() === 'ar' }}";
          const getThemeMode = () => document.documentElement.classList.contains('dark') ? 'dark' : 'light';
          
          // Data from PHP backend
          const trendData = @json($advancedStats['trendData'] ?? []);
          const satisfactionData = @json($advancedStats['satisfactionDistribution'] ?? []);
          const deptScores = @json($advancedStats['departmentScores'] ?? []);
          const categoryScores = @json($advancedStats['categoryScores'] ?? []);
          const hourlyStats = @json($advancedStats['hourlyStats'] ?? []);
          const dayStats = @json($advancedStats['dayStats'] ?? []);

          const activeTheme = getThemeMode();
          const chartsList = [];

          // Helper to create basic shared options
          const getBaseOptions = (chartType, height = 300) => ({
            chart: {
              type: chartType,
              height: height,
              toolbar: { show: false },
              fontFamily: 'Cairo, system-ui, -apple-system, sans-serif',
              background: 'transparent',
            },
            theme: {
              mode: getThemeMode(),
            },
            grid: {
              borderColor: getThemeMode() === 'dark' ? 'rgba(255, 255, 255, 0.06)' : '#f3f4f6',
            },
            states: {
              hover: { filter: { type: 'darken', value: 0.88 } }
            }
          });

          // 1. Weekly Trend (Line Chart)
          const trendCategories = trendData.map(d => d.date);
          const trendValues = trendData.map(d => d.score);
          const trendOptions = {
            ...getBaseOptions('line', 300),
            series: [{
              name: "{{ __('satisfaction_rate_label') }}",
              data: trendValues
            }],
            xaxis: {
              categories: trendCategories,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            yaxis: {
              min: 0,
              max: 100,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            colors: ['#0d9488'],
            stroke: { curve: 'smooth', width: 4 },
            markers: { size: 5, colors: ['#0d9488'], strokeWidth: 2, hover: { size: 8 } },
            tooltip: {
              y: { formatter: (val) => `${val}%` }
            }
          };
          const weeklyTrendChart = new ApexCharts(document.querySelector("#weeklyTrendChart"), trendOptions);
          weeklyTrendChart.render();
          chartsList.push(weeklyTrendChart);

          // 2. Satisfaction Distribution (Donut Chart)
          const satisfactionCounts = satisfactionData.map(d => d.count);
          const satisfactionLabels = satisfactionData.map(d => d.level);
          const satisfactionColors = satisfactionData.map(d => d.color);
          const satisfactionOptions = {
            ...getBaseOptions('donut', 300),
            series: satisfactionCounts,
            labels: satisfactionLabels,
            colors: satisfactionColors,
            stroke: { show: false },
            plotOptions: {
              pie: {
                donut: {
                  size: '65%',
                  labels: {
                    show: true,
                    name: { show: true, style: { fontWeight: 800 } },
                    value: { show: true, formatter: (val) => `${val} ${isRtl ? 'استجابة' : 'responses'}` },
                    total: {
                      show: true,
                      label: isRtl ? 'الإجمالي' : 'Total',
                      formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                    }
                  }
                }
              }
            },
            legend: {
              position: 'bottom',
              labels: { colors: getThemeMode() === 'dark' ? '#cbd5e1' : '#475569' }
            }
          };
          const satisfactionDistributionChart = new ApexCharts(document.querySelector("#satisfactionDistributionChart"), satisfactionOptions);
          satisfactionDistributionChart.render();
          chartsList.push(satisfactionDistributionChart);

          // 3. Department Satisfaction (Horizontal Bar Chart)
          const deptNames = deptScores.map(d => d.name);
          const deptValues = deptScores.map(d => d.score);
          const deptOptions = {
            ...getBaseOptions('bar', 320),
            series: [{
              name: "{{ __('satisfaction_rate_label') }}",
              data: deptValues
            }],
            plotOptions: {
              bar: {
                horizontal: true,
                barHeight: '45%',
                borderRadius: 6,
                dataLabels: { position: 'end' }
              }
            },
            colors: ['#0d9488'],
            xaxis: {
              categories: deptNames,
              min: 0,
              max: 100,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            yaxis: {
              labels: { style: { colors: '#94a3b8', fontWeight: 700 } }
            },
            tooltip: {
              y: { formatter: (val) => `${val}%` }
            }
          };
          const deptSatisfactionChart = new ApexCharts(document.querySelector("#deptSatisfactionChart"), deptOptions);
          deptSatisfactionChart.render();
          chartsList.push(deptSatisfactionChart);

          // 4. Category Analysis (Radar Chart)
          if (document.querySelector("#categoryRadarChart") && categoryScores.length > 0) {
            const categoriesLabels = categoryScores.map(d => d.category);
            const categoriesValues = categoryScores.map(d => d.score);
            const categoryOptions = {
              ...getBaseOptions('radar', 320),
              series: [{
                name: "{{ __('performance') }}",
                data: categoriesValues
              }],
              xaxis: {
                categories: categoriesLabels,
                labels: {
                  style: {
                    colors: Array(categoriesLabels.length).fill(getThemeMode() === 'dark' ? '#94a3b8' : '#64748b'),
                    fontSize: '11px',
                    fontWeight: 800
                  }
                }
              },
              colors: ['#0d9488'],
              fill: { opacity: 0.2 },
              markers: { size: 4, colors: ['#0d9488'] },
              yaxis: {
                min: 0,
                max: 100,
                show: false
              }
            };
            const categoryRadarChart = new ApexCharts(document.querySelector("#categoryRadarChart"), categoryOptions);
            categoryRadarChart.render();
            chartsList.push(categoryRadarChart);
          }

          // 5. Hourly Satisfaction (Vertical Bar Chart)
          const hourLabels = hourlyStats.map(d => d.hour);
          const hourValues = hourlyStats.map(d => d.score);
          const hourlyOptions = {
            ...getBaseOptions('bar', 280),
            series: [{
              name: "{{ __('satisfaction_rate_label') }}",
              data: hourValues
            }],
            plotOptions: {
              bar: {
                columnWidth: '55%',
                borderRadius: 4
              }
            },
            colors: ['#0d9488'],
            xaxis: {
              categories: hourLabels,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            yaxis: {
              min: 0,
              max: 100,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            tooltip: {
              y: { formatter: (val) => `${val}%` }
            }
          };
          const hourlyAnalysisChart = new ApexCharts(document.querySelector("#hourlyAnalysisChart"), hourlyOptions);
          hourlyAnalysisChart.render();
          chartsList.push(hourlyAnalysisChart);

          // 6. Daily Quality (Vertical Bar Chart)
          const dayLabels = dayStats.map(d => d.day);
          const dayValues = dayStats.map(d => d.score);
          const dailyOptions = {
            ...getBaseOptions('bar', 280),
            series: [{
              name: "{{ __('satisfaction_rate_label') }}",
              data: dayValues
            }],
            plotOptions: {
              bar: {
                columnWidth: '55%',
                borderRadius: 4
              }
            },
            colors: ['#6366f1'],
            xaxis: {
              categories: dayLabels,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            yaxis: {
              min: 0,
              max: 100,
              labels: { style: { colors: '#94a3b8', fontWeight: 600 } }
            },
            tooltip: {
              y: { formatter: (val) => `${val}%` }
            }
          };
          const dailyQualityChart = new ApexCharts(document.querySelector("#dailyQualityChart"), dailyOptions);
          dailyQualityChart.render();
          chartsList.push(dailyQualityChart);

          // Theme Synchronizer Mutation Observer
          const observer = new MutationObserver(() => {
            const currentMode = getThemeMode();
            chartsList.forEach(chart => {
              chart.updateOptions({
                theme: { mode: currentMode },
                grid: {
                  borderColor: currentMode === 'dark' ? 'rgba(255, 255, 255, 0.06)' : '#f3f4f6'
                }
              });
            });
            // Update radar chart text colors
            categoryRadarChart.updateOptions({
              xaxis: {
                labels: {
                  style: {
                    colors: Array(categoriesLabels.length).fill(currentMode === 'dark' ? '#94a3b8' : '#64748b')
                  }
                }
              }
            });
            // Update distribution chart legend text color
            satisfactionDistributionChart.updateOptions({
              legend: {
                labels: { colors: currentMode === 'dark' ? '#cbd5e1' : '#475569' }
              }
            });
          });
          observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        });
      </script>
    @else
      <div class="dashboard-panel flex flex-col items-center justify-center p-10 text-center">
        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-50 dark:bg-slate-850/50">
          <i data-lucide="bar-chart-3" class="h-10 w-10 text-gray-300 dark:text-slate-500"></i>
        </div>
        <h3 class="mb-2 text-xl font-black text-gray-800 dark:text-white">{{ __('no_data_available') }}</h3>
        <p class="max-w-md text-sm leading-7 text-gray-500 dark:text-slate-400">{{ __('no_data_desc') }}</p>
      </div>
    @endif

    @if(in_array(auth()->user()->role, ['super_admin', 'admin'], true))
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-2xl bg-linear-to-r from-teal-600 to-emerald-600 p-6 text-white shadow-lg shadow-teal-200 dark:shadow-teal-950/20">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h3 class="mb-1 text-lg font-black">{{ __('dashboard_manage_surveys_title') }}</h3>
              <p class="text-sm text-teal-100">{{ __('dashboard_manage_surveys_desc') }}</p>
            </div>
            <a href="{{ route('dashboard.surveys') }}" class="flex items-center gap-2 rounded-xl bg-white/95 px-5 py-2.5 text-sm font-black text-teal-600 shadow-md transition-all hover:bg-white hover:shadow-xl hover:-translate-y-0.5">
              <i data-lucide="clipboard-list" class="h-5 w-5"></i>
              {{ __('manage') }}
            </a>
          </div>
        </div>
        <div class="rounded-2xl bg-linear-to-r from-purple-600 to-indigo-600 p-6 text-white shadow-lg shadow-purple-200 dark:shadow-purple-950/20">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h3 class="mb-1 text-lg font-black">{{ __('dashboard_manage_users_title') }}</h3>
              <p class="text-sm text-purple-100">{{ __('dashboard_manage_users_desc') }}</p>
            </div>
            <a href="{{ route('dashboard.users') }}" class="flex items-center gap-2 rounded-xl bg-white/95 px-5 py-2.5 text-sm font-black text-purple-600 shadow-md transition-all hover:bg-white hover:shadow-xl hover:-translate-y-0.5">
              <i data-lucide="user-cog" class="h-5 w-5"></i>
              {{ __('manage') }}
            </a>
          </div>
        </div>
      </div>
    @endif

    <div>
      <div class="mb-4 flex items-center gap-2">
        <i data-lucide="star" class="h-6 w-6 fill-yellow-500 text-yellow-500"></i>
        <h3 class="text-lg font-black uppercase tracking-tight text-gray-800 dark:text-white">{{ __('honor_board') }}</h3>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @forelse($topDepartments as $index => $department)
          @php
            $colors = ['from-yellow-400 to-amber-600', 'from-slate-300 to-slate-500', 'from-orange-400 to-amber-700'];
            $icons = ['trophy', 'award', 'medal'];
          @endphp
          <div class="dashboard-panel group relative overflow-hidden p-5 transition-all hover:-translate-y-1 hover:shadow-md">
            <div class="absolute right-0 top-0 h-16 w-16 rounded-bl-full bg-linear-to-r {{ $colors[$index] ?? 'from-teal-500 to-emerald-600' }} opacity-10"></div>
            <div class="flex items-center gap-4">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-linear-to-r {{ $colors[$index] ?? 'from-teal-500 to-emerald-600' }} text-white shadow-lg">
                <i data-lucide="{{ $icons[$index] ?? 'award' }}" class="h-6 w-6"></i>
              </div>
              <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-slate-500">{{ __('dashboard_rank', ['num' => $department['rank'] ?? ($index + 1)]) }}</p>
                <h4 class="text-lg font-black text-gray-900 dark:text-white">{{ $department['name'] }}</h4>
              </div>
            </div>
            <div class="mt-4 flex items-end justify-between">
              <div>
                <p class="mb-1 text-[10px] font-bold uppercase text-gray-400 dark:text-slate-500">{{ app()->getLocale() === 'ar' ? 'نقاط الترتيب' : 'Ranking Score' }}</p>
                <div class="text-2xl font-black text-gray-900 dark:text-white">{{ $formatNumber($department['score'], 1) }}%</div>
                <p class="mt-1 text-[10px] font-bold text-gray-400 dark:text-slate-500">{{ app()->getLocale() === 'ar' ? 'متوسط التقييم' : 'Average rating' }}: {{ $formatNumber($department['rawScore'] ?? $department['score'], 1) }}%</p>
              </div>
              <div class="flex -space-x-1 space-x-reverse">
                @for($star = 1; $star <= 5; $star++)
                  <i data-lucide="star" class="h-3 w-3 {{ $star <= round($department['score'] / 20) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-200 dark:text-slate-700' }}"></i>
                @endfor
              </div>
            </div>
          </div>
        @empty
          <div class="dashboard-panel p-8 text-center text-sm text-slate-500 md:col-span-3">{{ __('dashboard_honor_board_empty') }}</div>
        @endforelse
      </div>
    </div>

    <div class="dashboard-panel overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-slate-800">
        <div class="flex items-center gap-2">
          <i data-lucide="clock" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i>
          <h3 class="font-black text-gray-800 dark:text-white">{{ __('recent_responses') }}</h3>
        </div>
        <a href="{{ route('dashboard.responses') }}" class="text-sm font-bold text-teal-600 hover:text-teal-700 dark:text-teal-400">{{ __('view_all') }}</a>
      </div>
      <div class="overflow-x-auto">
        <table class="dashboard-table min-w-[760px]">
          <thead>
            <tr>
              <th>{{ __('reviewer') }}</th>
              <th>{{ __('department') }}</th>
              <th>{{ __('visit_type') }}</th>
              <th>{{ __('score') }}</th>
              <th>{{ __('date') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($latestResponses as $response)
              <tr>
                <td>
                  <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-teal-100 bg-teal-50 text-xs font-black text-teal-600 dark:border-teal-800/30 dark:bg-teal-950/40 dark:text-teal-400">
                      {{ $response->patientName ? mb_substr($response->patientName, 0, 1) : '?' }}
                    </div>
                    <span class="font-black {{ $response->patientName ? 'text-gray-900 dark:text-slate-200' : 'text-gray-400 italic dark:text-slate-500' }}">{{ $response->patientName ?: __('visitor') }}</span>
                  </div>
                </td>
                <td class="font-bold text-slate-700 dark:text-slate-300">{{ $response->department ?? __('unspecified') }}</td>
                <td class="font-medium text-slate-500">
                  @php
                    $vt = $response->visitType;
                    if ($vt === 'inpatient') $vt = __('inpatient');
                    elseif ($vt === 'outpatient') $vt = __('outpatient');
                    elseif ($vt === 'emergency') $vt = __('emergency');
                    else $vt = $vt ?: __('unspecified');
                  @endphp
                  {{ $vt }}
                </td>
                <td>
                  <div class="flex items-center gap-2">
                    <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800">
                      <div class="h-full rounded-full {{ $response->overallScore >= 85 ? 'bg-green-500' : ($response->overallScore >= 70 ? 'bg-blue-500' : ($response->overallScore >= 50 ? 'bg-amber-500' : 'bg-red-500')) }}" style="width: {{ $response->overallScore }}%"></div>
                    </div>
                    <span class="font-black">{{ $response->overallScore }}%</span>
                  </div>
                </td>
                <td class="text-slate-500">{{ optional($response->submittedAt)->format('Y-m-d H:i') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="py-10 text-center text-slate-500">{{ __('no_data_available') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
