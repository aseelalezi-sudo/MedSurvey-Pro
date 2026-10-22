/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface ReportsInitial {
  dateFilter: string;
  department: string;
  startDate: string;
  endDate: string;
  restrictedDept: string | null;
  stats: any;
  comparisonStats: any;
  trendData: any[];
  deptTrends: any[];
  tickets: any;
  ticketStats: any;
  changes: { averageScore: number; npsScore: number; totalResponses: number };
  hospitalName: string;
  operatingTitle: string;
  logo: string;
  language: string;
  auditEventsUrl: string;
  csrf: string;
  i18n: Record<string, string>;
}

const byId = (id: string): any => document.getElementById(id);

document.addEventListener('alpine:init', () => {
  Alpine.data('reportsComponent', (initial: ReportsInitial): any => {
    const L = (key: string): string => initial.i18n[key] ?? key;

    return {
      dateFilter: initial.dateFilter,
      department: initial.department,
      startDate: initial.startDate,
      endDate: initial.endDate,
      restrictedDept: initial.restrictedDept,
      exportingReport: null,
      loadingReports: false,
      chartsInitialized: false,
      stats: initial.stats,
      comparisonStats: initial.comparisonStats,
      trendData: initial.trendData,
      deptTrends: initial.deptTrends,
      tickets: initial.tickets,
      ticketStats: initial.ticketStats,
      changes: {
        averageScore: initial.changes.averageScore,
        npsScore: initial.changes.npsScore,
        totalResponses: initial.changes.totalResponses,
      },
      charts: { donut: null, trend: null, dept: null } as Record<string, any>,

      init() {
        this.$nextTick(() => {
          this.initCharts();
        });
      },

      initCharts() {
        this.chartsInitialized = true;

        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? '#1e293b' : '#f1f5f9';

        Object.values(this.charts).forEach((chart: any) => {
          if (chart && typeof chart.destroy === 'function') chart.destroy();
        });
        this.charts = { donut: null, trend: null, dept: null };

        // Load ApexCharts dynamically
        (window as any).loadApexCharts().then((ApexCharts: any) => {
          // 1. Satisfaction Donut Chart
          const donutEl = byId('satisfactionDonutChart');
          if (donutEl) {
            const dist = this.stats.satisfactionDistribution || [];
            this.charts.donut = new ApexCharts(donutEl, {
              chart: { type: 'donut', fontFamily: 'Cairo, sans-serif', foreColor: textColor },
              labels: dist.map((d: any) => d.level),
              series: dist.map((d: any) => d.count),
              colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
              stroke: { show: false },
              dataLabels: {
                enabled: true,
                formatter: (val: any) => Math.round(val) + '%',
                style: { fontSize: '11px', fontWeight: 'bold' },
              },
              legend: { position: 'bottom', fontSize: '11px', fontWeight: 600 },
              plotOptions: {
                pie: {
                  donut: {
                    size: '60%',
                    labels: {
                      show: true,
                      name: { show: true },
                      value: { show: true, fontSize: '14px', fontWeight: 'bold' },
                      total: {
                        show: true,
                        label: L('reports_satisfaction_distribution'),
                        fontSize: '11px',
                        fontWeight: 700,
                      },
                    },
                  },
                },
              },
              tooltip: { y: { formatter: (val: any) => val + ' ' + L('count') } },
              responsive: [{ breakpoint: 480, options: { chart: { width: '100%' }, legend: { position: 'bottom' } } }],
            });
            this.charts.donut.render();
          }

          // 2. Monthly Trend Line Chart
          const trendEl = byId('monthlyTrendChart');
          if (trendEl) {
            const trendData = this.trendData || [];
            const months = trendData.map((d: any) => d.label + ' ' + d.year);
            this.charts.trend = new ApexCharts(trendEl, {
              chart: {
                type: 'line',
                fontFamily: 'Cairo, sans-serif',
                foreColor: textColor,
                toolbar: { show: true, tools: { download: true, pan: false, reset: false } },
                zoom: { enabled: false },
              },
              series: [
                {
                  name: L('reports_stat_overall_satisfaction'),
                  data: trendData.map((d: any) => d.averageScore),
                  color: '#0d9488',
                },
                { name: L('reports_stat_nps_score'), data: trendData.map((d: any) => d.npsScore), color: '#6366f1' },
              ],
              xaxis: { categories: months, labels: { rotate: -45, style: { fontSize: '10px', fontWeight: 600 } } },
              yaxis: { min: 0, max: 100, labels: { formatter: (val: any) => Math.round(val) } },
              grid: { borderColor: gridColor },
              stroke: { curve: 'smooth', width: 3 },
              markers: { size: 5, hover: { size: 7 } },
              dataLabels: {
                enabled: true,
                style: { fontSize: '10px', fontWeight: 'bold' },
                background: { enabled: true, foreColor: '#fff', padding: 2, borderRadius: 2, borderWidth: 0 },
              },
              tooltip: { y: { formatter: (val: any) => val + '%' } },
              legend: { position: 'top', fontSize: '11px', fontWeight: 600 },
            });
            this.charts.trend.render();
          }

          // 3. Department Comparison Bar Chart
          const deptEl = byId('deptComparisonChart');
          if (deptEl) {
            const deptData = this.deptTrends || [];
            const deptNames = deptData.map((d: any) => d.name);
            this.charts.dept = new ApexCharts(deptEl, {
              chart: {
                type: 'bar',
                fontFamily: 'Cairo, sans-serif',
                foreColor: textColor,
                toolbar: { show: true, tools: { download: true } },
              },
              series: [
                { name: L('reports_current_period'), data: deptData.map((d: any) => d.currentScore), color: '#0d9488' },
                {
                  name: L('reports_previous_period'),
                  data: deptData.map((d: any) => d.previousScore),
                  color: '#94a3b8',
                },
              ],
              xaxis: { categories: deptNames, labels: { rotate: -20, style: { fontSize: '10px', fontWeight: 600 } } },
              yaxis: { min: 0, max: 100, labels: { formatter: (val: any) => Math.round(val) + '%' } },
              grid: { borderColor: gridColor },
              plotOptions: {
                bar: { horizontal: false, columnWidth: '55%', borderRadius: 4, dataLabels: { position: 'top' } },
              },
              dataLabels: {
                enabled: true,
                formatter: (val: any) => Math.round(val) + '%',
                style: { fontSize: '9px', fontWeight: 'bold' },
              },
              tooltip: { y: { formatter: (val: any) => val + '%' } },
              legend: { position: 'top', fontSize: '11px', fontWeight: 600 },
              responsive: [{ breakpoint: 768, options: { chart: { height: 350 } } }],
            });
            this.charts.dept.render();
          }
        });
      },

      buildFilterParams() {
        const params = new URLSearchParams();
        params.set('dateFilter', this.dateFilter);
        params.set('department', this.department);
        if (this.dateFilter === 'custom') {
          if (this.startDate) params.set('startDate', this.startDate);
          if (this.endDate) params.set('endDate', this.endDate);
        }

        return params;
      },

      async applyFilters() {
        const params = this.buildFilterParams();
        const qs = params.toString();

        this.loadingReports = true;
        window.history.pushState({}, '', `${window.location.pathname}${qs ? `?${qs}` : ''}`);

        try {
          const response = await fetch(`${window.location.pathname}${qs ? `?${qs}` : ''}`, {
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          });

          if (!response.ok) throw new Error('Failed to filter reports');

          const data = await response.json();
          this.stats = data.stats || {};
          this.comparisonStats = data.comparisonStats || {};
          this.trendData = data.trendData || [];
          this.deptTrends = data.deptTrends || [];
          this.tickets = data.tickets || [];
          this.ticketStats = data.ticketStats || { total: 0, open: 0, inProgress: 0, resolved: 0, detailLimit: 1000 };
          this.changes = data.changes || { averageScore: 0, npsScore: 0, totalResponses: 0 };

          this.$nextTick(() => {
            this.initCharts();
            if (window.lucide) window.lucide.createIcons();
          });
        } catch (error) {
          console.error(error);
        } finally {
          this.loadingReports = false;
        }
      },

      setDateFilter(val: any) {
        this.dateFilter = val;
        if (val !== 'custom') {
          this.applyFilters();
        }
      },

      formatNumber(value: any, fractionDigits = 0) {
        return new Intl.NumberFormat('en-US', {
          minimumFractionDigits: fractionDigits,
          maximumFractionDigits: fractionDigits,
        }).format(Number(value || 0));
      },

      compactNumber(value: any) {
        const number = Number(value || 0);
        const abs = Math.abs(number);

        if (abs >= 1000000) {
          return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
        }

        if (abs >= 1000) {
          return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
        }

        return this.formatNumber(number);
      },

      handleExportPDF(type: any, action: any) {
        this.exportingReport = type + '_' + action;

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
          alert(L('reports_alert_popup_blocked'));
          this.exportingReport = null;
          return;
        }

        const stats = this.stats || {};
        const tickets = this.tickets || [];
        const hospitalName = initial.hospitalName;
        const operatingTitle = initial.operatingTitle;
        const logo = initial.logo;
        const language = initial.language;
        const isAr = language === 'ar';

        const translations: Record<string, string> = {
          score_excellent: L('excellent'),
          score_good: L('good'),
          score_average: L('average'),
          score_poor: L('poor'),
          report_executive_title: L('report_executive_title'),
          report_executive_subtitle: L('report_executive_subtitle'),
          total_responses: L('total_responses'),
          satisfaction_rate: L('satisfaction_rate'),
          nps_score: L('nps_score'),
          response_rate: L('response_rate'),
          satisfaction_distribution: L('satisfaction_distribution'),
          level: L('level'),
          count: L('count'),
          percentage: L('percentage'),
          category_satisfaction: L('category_satisfaction'),
          category: L('category'),
          department_satisfaction_comparative: L('department_satisfaction_comparative'),
          department: L('department'),
          system_description: L('system_description'),
          confidential_report: L('confidential_report'),
          page: L('page'),
          report_departments_title: L('report_departments_title'),
          report_departments_desc: L('report_departments_desc'),
          dept_eval_table: L('dept_eval_table'),
          medical_department: L('medical_department'),
          received_responses: L('received_responses'),
          measured_satisfaction: L('measured_satisfaction'),
          visual_representation: L('visual_representation'),
          satisfaction_level: L('satisfaction_level'),
          system_dept_reports: L('system_dept_reports'),
          all_rights_reserved: L('all_rights_reserved'),
          report_categories_title: L('report_categories_title'),
          report_categories_desc: L('report_categories_desc'),
          quality_satisfaction_levels: L('quality_satisfaction_levels'),
          criteria_category: L('criteria_category'),
          overall_measured_satisfaction: L('overall_measured_satisfaction'),
          achieved_quality_level: L('achieved_quality_level'),
          system_quality_reports: L('system_quality_reports'),
          report_tickets_title: L('report_tickets_title'),
          date_label: L('date_label'),
          ticket_monitoring_desc: L('ticket_monitoring_desc'),
          total_registered_tickets: L('total_registered_tickets'),
          open_waiting_tickets: L('open_waiting_tickets'),
          active_processing_tickets: L('active_processing_tickets'),
          resolved_closed_tickets: L('resolved_closed_tickets'),
          tickets_details_list: L('tickets_details_list'),
          patient_complainant: L('patient_complainant'),
          concerned_dept: L('concerned_dept'),
          reason_complaint: L('reason_complaint'),
          ticket_status: L('ticket_status'),
          priority: L('priority'),
          submission_date: L('submission_date'),
          open: L('open'),
          in_progress: L('in_progress'),
          resolved: L('resolved'),
          very_high: L('very_high'),
          normal: L('normal'),
          predictive_report_file: L('predictive_report_file'),
          predictive_report_title: L('predictive_report_title'),
          predictive_report_desc: L('predictive_report_desc'),
          ai_reading_summary: L('ai_reading_summary'),
          predictive_ai_analysis: L('predictive_ai_analysis'),
          sensitivity_indicators_depts: L('sensitivity_indicators_depts'),
          target_dept: L('target_dept'),
          current_satisfaction: L('current_satisfaction'),
          predictive_risk_level: L('predictive_risk_level'),
          ai_proactive_recommendation: L('ai_proactive_recommendation'),
          low_risk: L('low_risk'),
          rec_low_risk: L('rec_low_risk'),
          rec_high_risk: L('rec_high_risk'),
          medium_risk: L('medium_risk'),
          rec_medium_risk: L('rec_medium_risk'),
          system_predictive_reports: L('system_predictive_reports'),
          report_date: L('report_date'),
          export_department: L('export_department'),
          pdf_certified_copy: L('pdf_certified_copy'),
        };

        const t = (key: any, defaultValue?: any) => {
          return translations[key] || defaultValue;
        };

        const formatReportNumber = (value: any, fractionDigits = 0) =>
          new Intl.NumberFormat('en-US', {
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
          }).format(Number(value || 0));

        const reportDepartmentLabel =
          this.restrictedDept ||
          (this.department && this.department !== 'all' ? this.department : L('reports_filter_all_depts'));

        const escapeHtml = (str: any) => {
          if (str == null) return '';
          return String(str)
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
        };

        const getSatisfactionLevel = (score: any) => {
          if (score >= 85) return t('score_excellent', 'Excellent');
          if (score >= 70) return t('score_good', 'Good');
          if (score >= 50) return t('score_average', 'Average');
          return t('score_poor', 'Poor');
        };

        let html = '';

        if (type === 'executive') {
          const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
          const cleanReportTitle = t('report_executive_title', 'Executive_Summary_Report').replace(/\s+/g, '_');
          const dateStr = new Date().toISOString().slice(0, 10);
          const docTitle =
            action === 'pdf'
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_executive_title', 'Executive Summary Report')} - ${hospitalName}`;

          html = `<!DOCTYPE html><html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}"><head><meta charset="UTF-8"><title>${docTitle}</title><style>
              *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;padding:25px;color:#1e293b;background-color:#fff;line-height:1.6}
              .header-container{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #0d9488;padding-bottom:20px;margin-bottom:30px}
              .header-right{display:flex;align-items:center;gap:15px}.logo-placeholder{width:50px;height:50px;background:linear-gradient(135deg,#0d9488,#10b981);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;font-weight:bold}
              .hospital-info h1{font-size:18px;font-weight:800;color:#0f172a}.hospital-info p{font-size:11px;color:#64748b;margin-top:2px}.header-left{text-align:${isAr ? 'left' : 'right'}}
              .report-meta{font-size:12px;color:#64748b}.report-meta strong{color:#0f172a}
              .report-title-banner{text-align:center;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;padding:25px;border-radius:16px;margin-bottom:30px}
              .report-title-banner h2{font-size:22px;font-weight:900}.report-title-banner p{font-size:12px;opacity:0.8;margin-top:5px}
              .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:30px}
              .stat-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;text-align:center}
              .stat-card .value{font-size:26px;font-weight:800;color:#0d9488}.stat-card .label{font-size:11px;color:#64748b;font-weight:600;margin-top:5px}
              .section-title{font-size:15px;font-weight:800;color:#0f172a;border-right:4px solid #0d9488;padding-right:10px;margin-bottom:15px}
              .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:25px;margin-bottom:30px}
              table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}th{background-color:#0d9488;color:#fff;font-weight:700;padding:10px;text-align:center}
              td{padding:10px;border:1px solid #e2e8f0;text-align:center}tr:nth-child(even){background-color:#f8fafc}
              .badge{display:inline-block;padding:3px 8px;border-radius:12px;font-size:10px;font-weight:700}
              .badge-excellent{background-color:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
              .badge-good{background-color:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
              .badge-average{background-color:#fffbeb;color:#b45309;border:1px solid #fde68a}
              .badge-poor{background-color:#fef2f2;color:#b91c1c;border:1px solid #fca5a5}
              .footer{margin-top:40px;text-align:center;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:15px}
              .page-footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:5px}
              @media print{body{padding:0;margin:0}.report-title-banner{ -webkit-print-color-adjust:exact;print-color-adjust:exact}th{ -webkit-print-color-adjust:exact;print-color-adjust:exact}}
            </style></head><body>
              <div class="header-container"><div class="header-right">${logo ? `<img src="${logo}" alt="Logo" style="height:50px;width:auto;max-width:150px;object-fit:contain"/>` : '<div class="logo-placeholder">⚕️</div>'}<div class="hospital-info"><h1>${escapeHtml(hospitalName)}</h1><p>${escapeHtml(operatingTitle)}</p></div></div><div class="header-left"><div class="report-meta"><p><strong>${t('report_date')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p><p><strong>${t('export_department')}:</strong> ${reportDepartmentLabel}</p>${action === 'pdf' ? `<p style="margin-top:5px;color:#0d9488;font-weight:bold">${t('pdf_certified_copy')}</p>` : ''}</div></div></div>
              <div class="report-title-banner"><h2>${t('report_executive_title')}</h2><p>${t('report_executive_subtitle')}</p></div>
              <div class="stats-grid">
                <div class="stat-card"><div class="value">${formatReportNumber(stats.totalResponses)}</div><div class="label">${t('total_responses')}</div></div>
                <div class="stat-card"><div class="value">${formatReportNumber(stats.averageScore, 1)}%</div><div class="label">${t('satisfaction_rate')}</div></div>
                <div class="stat-card"><div class="value">${formatReportNumber(stats.npsScore, 1)}</div><div class="label">${t('nps_score')}</div></div>
                <div class="stat-card"><div class="value">${formatReportNumber(stats.responseRate, 1)}%</div><div class="label">${t('response_rate')}</div></div>
              </div>
              <div class="grid-2"><div><h3 class="section-title">${t('satisfaction_distribution')}</h3><table><thead><tr><th>${t('level')}</th><th>${t('count')}</th><th>${t('percentage')}</th></tr></thead><tbody>${stats.satisfactionDistribution
                .map((item: any) => {
                  let badgeClass = 'badge-good';
                  if (item.level.includes(t('score_excellent')) || item.level.toLowerCase().includes('excellent'))
                    badgeClass = 'badge-excellent';
                  else if (item.level.includes(t('score_average')) || item.level.toLowerCase().includes('average'))
                    badgeClass = 'badge-average';
                  else if (item.level.includes(t('score_poor')) || item.level.toLowerCase().includes('poor'))
                    badgeClass = 'badge-poor';
                  return `<tr><td><span class="badge ${badgeClass}">${item.level}</span></td><td>${formatReportNumber(item.count)}</td><td><strong>${Math.round((item.count / stats.totalResponses) * 100)}%</strong></td></tr>`;
                })
                .join('')}</tbody></table></div>
              <div><h3 class="section-title">${t('category_satisfaction')}</h3><table><thead><tr><th>${t('category')}</th><th>${t('satisfaction_rate')}</th><th>${t('level')}</th></tr></thead><tbody>${stats.categoryScores.map((cat: any) => `<tr><td><strong>${escapeHtml(cat.category)}</strong></td><td><span style="color:#0d9488;font-weight:bold">${cat.score}%</span></td><td>${getSatisfactionLevel(cat.score)}</td></tr>`).join('')}</tbody></table></div></div>
              <h3 class="section-title">${t('department_satisfaction_comparative')}</h3><table><thead><tr><th>${t('department')}</th><th>${t('total_responses')}</th><th>${t('satisfaction_rate')}</th><th>${t('level')}</th></tr></thead><tbody>${stats.departmentScores
                .map((dept: any) => {
                  let style = 'color:#10b981;font-weight:bold';
                  if (dept.score < 50) style = 'color:#ef4444;font-weight:bold';
                  else if (dept.score < 75) style = 'color:#f59e0b;font-weight:bold';
                  return `<tr><td><strong>${escapeHtml(dept.name)}</strong></td><td>${formatReportNumber(dept.count)}</td><td><span style="${style}">${formatReportNumber(dept.score, 1)}%</span></td><td><strong>${getSatisfactionLevel(dept.score)}</strong></td></tr>`;
                })
                .join('')}</tbody></table>
              <div class="footer"><p>MedSurvey Pro - ${t('system_description')}</p><p>© ${new Date().getFullYear()} ${escapeHtml(hospitalName)} | ${t('confidential_report')}</p></div>
              <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
            </body></html>`;
        } else if (type === 'departments') {
          const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
          const cleanReportTitle = t('report_departments_title').replace(/\s+/g, '_');
          const dateStr = new Date().toISOString().slice(0, 10);
          const docTitle =
            action === 'pdf'
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_departments_title')} - ${hospitalName}`;
          html = `<!DOCTYPE html><html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}"><head><meta charset="UTF-8"><title>${docTitle}</title><style>
              *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;padding:25px;color:#1e293b;line-height:1.6}
              .header-container{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #6366f1;padding-bottom:15px;margin-bottom:25px}
              .hospital-info h1{font-size:16px;font-weight:800}.report-meta{font-size:11px;color:#64748b}
              .report-title-banner{text-align:center;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px}
              .report-title-banner h2{font-size:18px;font-weight:900}
              table{width:100%;border-collapse:collapse;margin-top:15px;font-size:12px}th{background-color:#6366f1;color:#fff;font-weight:bold;padding:10px}
              td{padding:10px;border:1px solid #e2e8f0;text-align:center}tr:nth-child(even){background-color:#f8fafc}
              .bar-container{display:flex;align-items:center;gap:10px;justify-content:flex-start}.bar-outer{width:100px;height:10px;background-color:#e2e8f0;border-radius:5px;overflow:hidden}.bar-inner{height:100%;border-radius:5px}
              .footer{margin-top:40px;text-align:center;font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:15px}
              .page-footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:5px}
              @media print{body{padding:0;margin:0}.report-title-banner{ -webkit-print-color-adjust:exact;print-color-adjust:exact}th{ -webkit-print-color-adjust:exact;print-color-adjust:exact}}
            </style></head><body>
              <div class="header-container"><div style="display:flex;align-items:center;gap:15px">${logo ? `<img src="${logo}" alt="Logo" style="height:45px;width:auto;max-width:120px;object-fit:contain"/>` : ''}<div class="hospital-info"><h1>${hospitalName}</h1><p>${operatingTitle}</p></div></div><div class="report-meta"><p><strong>${t('report_date')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>${action === 'pdf' ? `<p style="margin-top:5px;color:#6366f1;font-weight:bold">${t('pdf_certified_copy')}</p>` : ''}</div></div>
              <div class="report-title-banner"><h2>${t('report_departments_title')}</h2><p>${t('report_departments_desc')}</p></div>
              <h3 style="font-size:14px;margin-bottom:10px;border-right:4px solid #6366f1;padding-right:8px">${t('dept_eval_table')}</h3>
              <table><thead><tr><th>#</th><th>${t('medical_department')}</th><th>${t('received_responses')}</th><th>${t('measured_satisfaction')}</th><th>${t('visual_representation')}</th><th>${t('satisfaction_level')}</th></tr></thead><tbody>${stats.departmentScores
                .map((dept: any, idx: any) => {
                  let barColor = '#10b981';
                  if (dept.score < 50) barColor = '#ef4444';
                  else if (dept.score < 75) barColor = '#f59e0b';
                  return `<tr><td>${idx + 1}</td><td><strong>${escapeHtml(dept.name)}</strong></td><td>${formatReportNumber(dept.count)}</td><td><strong style="color:${barColor}">${formatReportNumber(dept.score, 1)}%</strong></td><td><div class="bar-container"><div class="bar-outer"><div class="bar-inner" style="width:${dept.score}%;background-color:${barColor}"></div></div><span>${formatReportNumber(dept.score, 1)}%</span></div></td><td><strong>${getSatisfactionLevel(dept.score)}</strong></td></tr>`;
                })
                .join('')}</tbody></table>
              <div class="footer"><p>${t('system_dept_reports')}</p><p>© ${new Date().getFullYear()} ${t('all_rights_reserved')} ${hospitalName}</p></div>
              <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
            </body></html>`;
        } else if (type === 'categories') {
          const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
          const cleanReportTitle = t('report_categories_title').replace(/\s+/g, '_');
          const dateStr = new Date().toISOString().slice(0, 10);
          const docTitle =
            action === 'pdf'
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_categories_title')} - ${hospitalName}`;
          html = `<!DOCTYPE html><html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}"><head><meta charset="UTF-8"><title>${docTitle}</title><style>
              *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;padding:25px;color:#1e293b;line-height:1.6}
              .header-container{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #10b981;padding-bottom:15px;margin-bottom:25px}.hospital-info h1{font-size:16px;font-weight:800}
              .report-title-banner{text-align:center;background:linear-gradient(135deg,#059669,#10b981);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px}
              .report-title-banner h2{font-size:18px;font-weight:900}
              table{width:100%;border-collapse:collapse;margin-top:15px;font-size:12px}th{background-color:#10b981;color:#fff;font-weight:bold;padding:10px}
              td{padding:10px;border:1px solid #e2e8f0;text-align:center}tr:nth-child(even){background-color:#f8fafc}
              .bar-outer{width:100%;height:8px;background-color:#e2e8f0;border-radius:4px;overflow:hidden;margin-top:5px}.bar-inner{height:100%;border-radius:4px}
              .footer{margin-top:40px;text-align:center;font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:15px}
              .page-footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:5px}
              @media print{body{padding:0;margin:0}.report-title-banner{ -webkit-print-color-adjust:exact;print-color-adjust:exact}th{ -webkit-print-color-adjust:exact;print-color-adjust:exact}}
            </style></head><body>
              <div class="header-container"><div style="display:flex;align-items:center;gap:15px">${logo ? `<img src="${logo}" alt="Logo" style="height:45px;width:auto;max-width:120px;object-fit:contain"/>` : ''}<div class="hospital-info"><h1>${hospitalName}</h1><p>${operatingTitle}</p></div></div><div class="report-meta" style="font-size:11px;color:#64748b"><p><strong>${t('report_date')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>${action === 'pdf' ? `<p style="margin-top:5px;color:#10b981;font-weight:bold">${t('pdf_certified_copy')}</p>` : ''}</div></div>
              <div class="report-title-banner"><h2>${t('report_categories_title')}</h2><p>${t('report_categories_desc')}</p></div>
              <h3 style="font-size:14px;margin-bottom:10px;border-right:4px solid #10b981;padding-right:8px">${t('quality_satisfaction_levels')}</h3>
              <table><thead><tr><th>${t('criteria_category')}</th><th>${t('overall_measured_satisfaction')}</th><th>${t('achieved_quality_level')}</th></tr></thead><tbody>${stats.categoryScores
                .map((cat: any) => {
                  let barColor = '#10b981';
                  if (cat.score < 50) barColor = '#ef4444';
                  else if (cat.score < 75) barColor = '#f59e0b';
                  return `<tr><td style="text-align:${isAr ? 'right' : 'left'};padding-right:15px"><strong>${cat.category}</strong><div class="bar-outer"><div class="bar-inner" style="width:${cat.score}%;background-color:${barColor}"></div></div></td><td><strong style="color:${barColor};font-size:14px">${cat.score}%</strong></td><td><strong>${getSatisfactionLevel(cat.score)}</strong></td></tr>`;
                })
                .join('')}</tbody></table>
              <div class="footer"><p>${t('system_quality_reports')}</p><p>© ${new Date().getFullYear()} ${t('all_rights_reserved')} ${escapeHtml(hospitalName)}</p></div>
              <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
            </body></html>`;
        } else if (type === 'tickets') {
          const ticketStats = this.ticketStats || {};
          const total = Number(ticketStats.total ?? tickets.length);
          const open = Number(ticketStats.open ?? tickets.filter((t: any) => t.status === 'open').length);
          const inProgress = Number(
            ticketStats.inProgress ?? tickets.filter((t: any) => t.status === 'in_progress').length,
          );
          const resolved = Number(ticketStats.resolved ?? tickets.filter((t: any) => t.status === 'resolved').length);
          const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
          const cleanReportTitle = t('report_tickets_title').replace(/\s+/g, '_');
          const dateStr = new Date().toISOString().slice(0, 10);
          const docTitle =
            action === 'pdf'
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_tickets_title')} - ${hospitalName}`;
          html = `<!DOCTYPE html><html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}"><head><meta charset="UTF-8"><title>${docTitle}</title><style>
              *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;padding:25px;color:#1e293b;line-height:1.6}
              .header-container{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #ef4444;padding-bottom:15px;margin-bottom:25px}.hospital-info h1{font-size:16px;font-weight:800}
              .report-title-banner{text-align:center;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px}
              .report-title-banner h2{font-size:18px;font-weight:900}
              .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}.stat-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:15px;text-align:center}
              .stat-card .value{font-size:22px;font-weight:800}.stat-card .label{font-size:10px;color:#64748b;margin-top:5px}
              table{width:100%;border-collapse:collapse;margin-top:15px;font-size:11px}th{background-color:#ef4444;color:#fff;font-weight:bold;padding:10px}
              td{padding:10px;border:1px solid #e2e8f0;text-align:center}tr:nth-child(even){background-color:#f8fafc}
              .status-badge{display:inline-block;padding:3px 8px;border-radius:12px;font-weight:bold;font-size:9px}
              .status-open{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5}.status-progress{background:#fef3c7;color:#b45309;border:1px solid #fde68a}.status-resolved{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
              .footer{margin-top:40px;text-align:center;font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:15px}
              .page-footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:5px}
              @media print{body{padding:0;margin:0}.report-title-banner{ -webkit-print-color-adjust:exact;print-color-adjust:exact}th{ -webkit-print-color-adjust:exact;print-color-adjust:exact}}
            </style></head><body>
              <div class="header-container"><div style="display:flex;align-items:center;gap:15px">${logo ? `<img src="${logo}" alt="Logo" style="height:45px;width:auto;max-width:120px;object-fit:contain"/>` : ''}<div class="hospital-info"><h1>${hospitalName}</h1><p>${operatingTitle}</p></div></div><div class="report-meta" style="font-size:11px;color:#64748b"><p><strong>${t('date_label')}</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>${action === 'pdf' ? `<p style="margin-top:5px;color:#ef4444;font-weight:bold">${t('pdf_certified_copy')}</p>` : ''}</div></div>
              <div class="report-title-banner"><h2>${t('report_tickets_title')}</h2><p>${t('ticket_monitoring_desc')}</p></div>
              <div class="stats-grid"><div class="stat-card"><div class="value" style="color:#ef4444">${formatReportNumber(total)}</div><div class="label">${t('total_registered_tickets')}</div></div><div class="stat-card"><div class="value" style="color:#dc2626">${formatReportNumber(open)}</div><div class="label">${t('open_waiting_tickets')}</div></div><div class="stat-card"><div class="value" style="color:#d97706">${formatReportNumber(inProgress)}</div><div class="label">${t('active_processing_tickets')}</div></div><div class="stat-card"><div class="value" style="color:#16a34a">${formatReportNumber(resolved)}</div><div class="label">${t('resolved_closed_tickets')}</div></div></div>
              <h3 style="font-size:14px;margin-bottom:10px;border-right:4px solid #ef4444;padding-right:8px">${t('tickets_details_list')}</h3>
              <table><thead><tr><th>${t('patient_complainant')}</th><th>${t('concerned_dept')}</th><th>${t('reason_complaint')}</th><th>${t('ticket_status')}</th><th>${t('priority')}</th><th>${t('submission_date')}</th></tr></thead><tbody>${tickets
                .map((ticket: any) => {
                  let statusClass = 'status-open',
                    statusText = t('open');
                  if (ticket.status === 'in_progress') {
                    statusClass = 'status-progress';
                    statusText = t('in_progress');
                  } else if (ticket.status === 'resolved') {
                    statusClass = 'status-resolved';
                    statusText = t('resolved');
                  }
                  return `<tr><td><strong>${escapeHtml(ticket.patientName)}</strong><br><span style="font-size:9px;color:#64748b">${escapeHtml(ticket.patientPhone)}</span></td><td><strong>${escapeHtml(ticket.department)}</strong></td><td style="text-align:${isAr ? 'right' : 'left'};max-width:200px">${escapeHtml(ticket.description)}</td><td><span class="status-badge ${statusClass}">${statusText}</span></td><td><span style="color:${ticket.priority === 'high' ? '#ef4444' : '#64748b'};font-weight:bold">${ticket.priority === 'high' ? t('very_high') : t('normal')}</span></td><td>${new Date(ticket.createdAt).toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</td></tr>`;
                })
                .join('')}</tbody></table>
              <div class="footer"><p>MedSurvey Pro - ${t('system_ticket_reports', 'Ticket Reports')}</p><p>© ${new Date().getFullYear()} ${t('all_rights_reserved')} ${escapeHtml(hospitalName)}</p></div>
              <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
            </body></html>`;
        } else if (type === 'predictive') {
          const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
          const cleanReportTitle = t('predictive_report_file').replace(/\s+/g, '_');
          const dateStr = new Date().toISOString().slice(0, 10);
          const docTitle =
            action === 'pdf'
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('predictive_report_title')} - ${hospitalName}`;
          html = `<!DOCTYPE html><html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}"><head><meta charset="UTF-8"><title>${docTitle}</title><style>
              *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;padding:25px;color:#1e293b;line-height:1.6}
              .header-container{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #6366f1;padding-bottom:15px;margin-bottom:25px}.hospital-info h1{font-size:16px;font-weight:800}
              .report-title-banner{text-align:center;background:linear-gradient(135deg,#4f46e5,#4f46e5);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px}
              .report-title-banner h2{font-size:18px;font-weight:900}.risk-alert-box{background:#eef2ff;border-right:5px solid #4f46e5;padding:15px;border-radius:8px;margin-bottom:25px;font-size:12px}
              .risk-alert-box h4{font-weight:800;color:#4f46e5;margin-bottom:5px}
              table{width:100%;border-collapse:collapse;margin-top:15px;font-size:12px}th{background-color:#4f46e5;color:#fff;font-weight:bold;padding:10px}
              td{padding:10px;border:1px solid #e2e8f0;text-align:center}tr:nth-child(even){background-color:#f8fafc}
              .footer{margin-top:40px;text-align:center;font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:15px}
              .page-footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:5px}
              @media print{body{padding:0;margin:0}.report-title-banner{ -webkit-print-color-adjust:exact;print-color-adjust:exact}th{ -webkit-print-color-adjust:exact;print-color-adjust:exact}}
            </style></head><body>
              <div class="header-container"><div style="display:flex;align-items:center;gap:15px">${logo ? `<img src="${logo}" alt="Logo" style="height:45px;width:auto;max-width:120px;object-fit:contain"/>` : ''}<div class="hospital-info"><h1>${escapeHtml(hospitalName)}</h1><p>${escapeHtml(operatingTitle)}</p></div></div><div class="report-meta" style="font-size:11px;color:#64748b"><p><strong>${t('date_label')}</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>${action === 'pdf' ? `<p style="margin-top:5px;color:#6366f1;font-weight:bold">${t('pdf_certified_copy')}</p>` : ''}</div></div>
              <div class="report-title-banner"><h2>${t('predictive_report_title')}</h2><p>${t('predictive_report_desc')}</p></div>
              <div class="risk-alert-box"><h4>💡 ${t('ai_reading_summary')}</h4><p>${t('predictive_ai_analysis')}</p></div>
              <h3 style="font-size:14px;margin-bottom:10px;border-right:4px solid #4f46e5;padding-right:8px">${t('sensitivity_indicators_depts')}</h3>
              <table><thead><tr><th>${t('target_dept')}</th><th>${t('current_satisfaction')}</th><th>${t('predictive_risk_level')}</th><th>${t('ai_proactive_recommendation')}</th></tr></thead><tbody>${stats.departmentScores
                .map((dept: any) => {
                  let riskLevel = t('low_risk'),
                    riskColor = '#10b981',
                    rec = t('rec_low_risk');
                  if (dept.score < 60) {
                    riskLevel = t('very_high');
                    riskColor = '#ef4444';
                    rec = t('rec_high_risk');
                  } else if (dept.score < 75) {
                    riskLevel = t('medium_risk');
                    riskColor = '#f59e0b';
                    rec = t('rec_medium_risk');
                  }
                  return `<tr><td><strong>${escapeHtml(dept.name)}</strong></td><td><strong style="color:${riskColor}">${dept.score}%</strong></td><td><strong style="color:${riskColor}">${riskLevel}</strong></td><td style="text-align:${isAr ? 'right' : 'left'};max-width:250px">${rec}</td></tr>`;
                })
                .join('')}</tbody></table>
              <div class="footer"><p>${t('system_predictive_reports')}</p><p>© ${new Date().getFullYear()} ${t('all_rights_reserved')} ${escapeHtml(hospitalName)}</p></div>
              <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
            </body></html>`;
        }

        printWindow.document.write(html);
        printWindow.document.close();

        fetch(initial.auditEventsUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': initial.csrf },
          body: JSON.stringify({
            action: action === 'print' ? 'print_report' : 'export_report',
            messageKey: action === 'print' ? 'audit.details.print_report' : 'audit.details.export_report',
            params: { reportType: type, department: this.department || 'all', dateRange: this.dateFilter },
          }),
        }).catch(() => {});

        requestAnimationFrame(() => {
          printWindow.print();
          this.exportingReport = null;
        });
      },
    };
  });
});
