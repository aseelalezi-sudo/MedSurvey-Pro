@extends('layouts.dashboard')

@section('title', __('nav_reports') . ' - MedSurvey Pro')

@section('dashboard')
  @php
    $user = auth()->user();
    $restrictedDepartment = ($user->role === 'head_of_department' && $user->department) ? $user->department : null;
    
    // Fetch departments list from settings
    $settingsService = app(\App\Services\SettingsService::class);
    $allSettings = $settingsService->getAll($user->tenantId);
    $departments = collect($allSettings['departments'] ?? [])->where('isActive', true)->pluck('name')->all();
    
    $dateFilter = request()->query('dateFilter', 'all');
    $selectedDept = request()->query('department', 'all');
    $startDate = request()->query('startDate', '');
    $endDate = request()->query('endDate', '');
    
    $reportDepartmentLabel = ($restrictedDepartment || ($selectedDept !== 'all')) 
      ? ($restrictedDepartment ?? $selectedDept) 
      : __('reports_filter_all_depts');
  @endphp

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('reportsComponent', () => ({
        dateFilter: '{{ $dateFilter }}',
        department: '{{ $restrictedDepartment ?? $selectedDept }}',
        startDate: '{{ $startDate }}',
        endDate: '{{ $endDate }}',
        restrictedDept: @json($restrictedDepartment),
        exportingReport: null,
        
        applyFilters() {
          const params = new URLSearchParams();
          params.set('dateFilter', this.dateFilter);
          params.set('department', this.department);
          if (this.dateFilter === 'custom') {
            if (this.startDate) params.set('startDate', this.startDate);
            if (this.endDate) params.set('endDate', this.endDate);
          }
          window.location.search = params.toString();
        },
        
        setDateFilter(val) {
          this.dateFilter = val;
          if (val !== 'custom') {
            this.applyFilters();
          }
        },
        
        handleExportPDF(type, action) {
          this.exportingReport = type + '_' + action;
          
          const printWindow = window.open('', '_blank', 'width=800,height=600');
          if (!printWindow) {
            alert('{{ __('reports_alert_popup_blocked') }}');
            this.exportingReport = null;
            return;
          }
          
          const stats = @json($stats);
          const tickets = @json($tickets);
          const hospitalName = @json($allSettings['hospital']['name'] ?: __('reports_default_hospital'));
          const operatingTitle = @json($allSettings['hospital']['operatingTitle'] ?: __('reports_default_operating'));
          const logo = @json($allSettings['hospital']['logo'] ?: '');
          const language = '{{ app()->getLocale() }}';
          const isAr = language === 'ar';
          
          const translations = {
            score_excellent: '{{ __('excellent') }}',
            score_good: '{{ __('good') }}',
            score_average: '{{ __('average') }}',
            score_poor: '{{ __('poor') }}',
            report_executive_title: '{{ __('report_executive_title') }}',
            report_executive_subtitle: '{{ __('report_executive_subtitle') }}',
            total_responses: '{{ __('total_responses') }}',
            satisfaction_rate: '{{ __('satisfaction_rate') }}',
            nps_score: '{{ __('nps_score') }}',
            response_rate: '{{ __('response_rate') }}',
            satisfaction_distribution: '{{ __('satisfaction_distribution') }}',
            level: '{{ __('level') }}',
            count: '{{ __('count') }}',
            percentage: '{{ __('percentage') }}',
            category_satisfaction: '{{ __('category_satisfaction') }}',
            category: '{{ __('category') }}',
            department_satisfaction_comparative: '{{ __('department_satisfaction_comparative') }}',
            department: '{{ __('department') }}',
            system_description: '{{ __('system_description') }}',
            confidential_report: '{{ __('confidential_report') }}',
            page: '{{ __('page') }}',
            report_departments_title: '{{ __('report_departments_title') }}',
            report_departments_desc: '{{ __('report_departments_desc') }}',
            dept_eval_table: '{{ __('dept_eval_table') }}',
            medical_department: '{{ __('medical_department') }}',
            received_responses: '{{ __('received_responses') }}',
            measured_satisfaction: '{{ __('measured_satisfaction') }}',
            visual_representation: '{{ __('visual_representation') }}',
            satisfaction_level: '{{ __('satisfaction_level') }}',
            system_dept_reports: '{{ __('system_dept_reports') }}',
            all_rights_reserved: '{{ __('all_rights_reserved') }}',
            report_categories_title: '{{ __('report_categories_title') }}',
            report_categories_desc: '{{ __('report_categories_desc') }}',
            quality_satisfaction_levels: '{{ __('quality_satisfaction_levels') }}',
            criteria_category: '{{ __('criteria_category') }}',
            overall_measured_satisfaction: '{{ __('overall_measured_satisfaction') }}',
            achieved_quality_level: '{{ __('achieved_quality_level') }}',
            system_quality_reports: '{{ __('system_quality_reports') }}',
            report_tickets_title: '{{ __('report_tickets_title') }}',
            date_label: '{{ __('date_label') }}',
            ticket_monitoring_desc: '{{ __('ticket_monitoring_desc') }}',
            total_registered_tickets: '{{ __('total_registered_tickets') }}',
            open_waiting_tickets: '{{ __('open_waiting_tickets') }}',
            active_processing_tickets: '{{ __('active_processing_tickets') }}',
            resolved_closed_tickets: '{{ __('resolved_closed_tickets') }}',
            tickets_details_list: '{{ __('tickets_details_list') }}',
            patient_complainant: '{{ __('patient_complainant') }}',
            concerned_dept: '{{ __('concerned_dept') }}',
            reason_complaint: '{{ __('reason_complaint') }}',
            ticket_status: '{{ __('ticket_status') }}',
            priority: '{{ __('priority') }}',
            submission_date: '{{ __('submission_date') }}',
            open: '{{ __('open') }}',
            in_progress: '{{ __('in_progress') }}',
            resolved: '{{ __('resolved') }}',
            very_high: '{{ __('very_high') }}',
            normal: '{{ __('normal') }}',
            predictive_report_file: '{{ __('predictive_report_file') }}',
            predictive_report_title: '{{ __('predictive_report_title') }}',
            predictive_report_desc: '{{ __('predictive_report_desc') }}',
            ai_reading_summary: '{{ __('ai_reading_summary') }}',
            predictive_ai_analysis: '{{ __('predictive_ai_analysis') }}',
            sensitivity_indicators_depts: '{{ __('sensitivity_indicators_depts') }}',
            target_dept: '{{ __('target_dept') }}',
            current_satisfaction: '{{ __('current_satisfaction') }}',
            predictive_risk_level: '{{ __('predictive_risk_level') }}',
            ai_proactive_recommendation: '{{ __('ai_proactive_recommendation') }}',
            low_risk: '{{ __('low_risk') }}',
            rec_low_risk: '{{ __('rec_low_risk') }}',
            rec_high_risk: '{{ __('rec_high_risk') }}',
            medium_risk: '{{ __('medium_risk') }}',
            rec_medium_risk: '{{ __('rec_medium_risk') }}',
            system_predictive_reports: '{{ __('system_predictive_reports') }}',
            report_date: '{{ __('report_date') }}',
            export_department: '{{ __('export_department') }}',
            pdf_certified_copy: '{{ __('pdf_certified_copy') }}'
          };
    
          const t = (key, defaultValue) => {
            return translations[key] || defaultValue;
          };
          
          const reportDepartmentLabel = '{{ $reportDepartmentLabel }}';
          
          const escapeHtml = (str) => {
            if (str == null) return '';
            return String(str)
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
          };
    
          const getSatisfactionLevel = (score) => {
            if (score >= 85) return t('score_excellent', 'ممتاز');
            if (score >= 70) return t('score_good', 'جيد');
            if (score >= 50) return t('score_average', 'متوسط');
            return t('score_poor', 'ضعيف');
          };
          
          let html = '';
          
          if (type === 'executive') {
            const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
            const cleanReportTitle = t('report_executive_title', 'تقرير_الملخص_التنفيذي_ورضا_المرضى_الشامل').replace(/\s+/g, '_');
            const dateStr = new Date().toISOString().slice(0, 10);
            const docTitle = action === 'pdf' 
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_executive_title', 'تقرير الملخص التنفيذي ورضا المرضى الشامل')} - ${hospitalName}`;
    
            html = `
              <!DOCTYPE html>
              <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
              <head>
                <meta charset="UTF-8">
                <title>${docTitle}</title>
                <style>
                  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
                  * { box-sizing: border-box; margin: 0; padding: 0; }
                  body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; background-color: #ffffff; line-height: 1.6; }
                  .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
                  .header-right { display: flex; align-items: center; gap: 15px; }
                  .logo-placeholder { width: 50px; height: 50px; background: linear-gradient(135deg, #0d9488, #10b981); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; }
                  .hospital-info h1 { font-size: 18px; font-weight: 800; color: #0f172a; }
                  .hospital-info p { font-size: 11px; color: #64748b; margin-top: 2px; }
                  .header-left { text-align: ${isAr ? 'left' : 'right'}; }
                  .report-meta { font-size: 12px; color: #64748b; }
                  .report-meta strong { color: #0f172a; }
                  .report-title-banner { text-align: center; background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 16px; margin-bottom: 30px; }
                  .report-title-banner h2 { font-size: 22px; font-weight: 900; }
                  .report-title-banner p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
                  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
                  .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; text-align: center; }
                  .stat-card .value { font-size: 26px; font-weight: 800; color: #0d9488; }
                  .stat-card .label { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 5px; }
                  .section-title { font-size: 15px; font-weight: 800; color: #0f172a; border-right: 4px solid #0d9488; padding-right: 10px; margin-bottom: 15px; }
                  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
                  table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                  th { background-color: #0d9488; color: white; font-weight: 700; padding: 10px; text-align: center; }
                  td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
                  tr:nth-child(even) { background-color: #f8fafc; }
                  .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
                  .badge-excellent { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
                  .badge-good { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
                  .badge-average { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
                  .badge-poor { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
                  .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                  .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
                  @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                </style>
              </head>
              <body>
                <div class="header-container">
                  <div class="header-right">
                    ${logo ? `<img src="${logo}" alt="Logo" style="height: 50px; width: auto; max-width: 150px; object-fit: contain;" />` : `<div class="logo-placeholder">⚕️</div>`}
                    <div class="hospital-info">
                      <h1>${escapeHtml(hospitalName)}</h1>
                      <p>${escapeHtml(operatingTitle)}</p>
                    </div>
                  </div>
                  <div class="header-left">
                    <div class="report-meta">
                      <p><strong>${t('report_date', 'تاريخ التقرير')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
                      <p><strong>${t('export_department', 'القسم المستهدف')}:</strong> ${reportDepartmentLabel}</p>
                      ${action === 'pdf' ? `<p style="margin-top: 5px; color: #0d9488; font-weight: bold;">${t('pdf_certified_copy', '💾 نسخة إلكترونية معتمدة بصيغة PDF')}</p>` : ''}
                    </div>
                  </div>
                </div>
                <div class="report-title-banner">
                  <h2>${t('report_executive_title', 'تقرير الملخص التنفيذي ورضا المرضى الشامل')}</h2>
                  <p>${t('report_executive_subtitle', 'تحليل شامل ومفصل لمستويات رضا المستفيدين ومقاييس الأداء لخدمات الرعاية الصحية')}</p>
                </div>
                <div class="stats-grid">
                  <div class="stat-card">
                    <div class="value">${stats.totalResponses}</div>
                    <div class="label">${t('total_responses', 'إجمالي الاستجابات')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value">${stats.averageScore}%</div>
                    <div class="label">${t('satisfaction_rate', 'معدل الرضا العام')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value">${stats.npsScore}</div>
                    <div class="label">${t('nps_score', 'مؤشر التوصية والولاء (NPS)')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value">${stats.responseRate}%</div>
                    <div class="label">${t('response_rate', 'معدل استجابة المرضى')}</div>
                  </div>
                </div>
                <div class="grid-2">
                  <div>
                    <h3 class="section-title">${t('satisfaction_distribution', 'توزيع مستوى رضا المرضى')}</h3>
                    <table>
                      <thead>
                        <tr>
                          <th>${t('level', 'المستوى')}</th>
                          <th>${t('count', 'العدد')}</th>
                          <th>${t('percentage', 'النسبة')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${stats.satisfactionDistribution.map(item => {
                          let badgeClass = 'badge-good';
                          if (item.level.includes(t('score_excellent', 'ممتاز')) || item.level.toLowerCase().includes('excellent')) badgeClass = 'badge-excellent';
                          else if (item.level.includes(t('score_average', 'متوسط')) || item.level.toLowerCase().includes('average')) badgeClass = 'badge-average';
                          else if (item.level.includes(t('score_poor', 'ضعيف')) || item.level.toLowerCase().includes('poor')) badgeClass = 'badge-poor';
                          return `
                            <tr>
                              <td><span class="badge ${badgeClass}">${item.level}</span></td>
                              <td>${item.count}</td>
                              <td><strong>${Math.round((item.count / stats.totalResponses) * 100)}%</strong></td>
                            </tr>
                          `;
                        }).join('')}
                      </tbody>
                    </table>
                  </div>
                  <div>
                    <h3 class="section-title">${t('category_satisfaction', 'الرضا حسب فئات الخدمة')}</h3>
                    <table>
                      <thead>
                        <tr>
                          <th>${t('category', 'الفئة الخدمية')}</th>
                          <th>${t('satisfaction_rate', 'معدل الرضا')}</th>
                          <th>${t('level', 'التقييم العام')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${stats.categoryScores.map(cat => `
                          <tr>
                            <td><strong>${escapeHtml(cat.category)}</strong></td>
                            <td><span style="color: #0d9488; font-weight: bold;">${cat.score}%</span></td>
                            <td>${getSatisfactionLevel(cat.score)}</td>
                          </tr>
                        `).join('')}
                      </tbody>
                    </table>
                  </div>
                </div>
                <h3 class="section-title">${t('department_satisfaction_comparative', 'التقييم المقارن للأقسام الطبية')}</h3>
                <table>
                  <thead>
                    <tr>
                      <th>${t('department', 'القسم الطبي')}</th>
                      <th>${t('total_responses', 'عدد الاستجابات')}</th>
                      <th>${t('satisfaction_rate', 'معدل الرضا العام')}</th>
                      <th>${t('level', 'مستوى الأداء')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${stats.departmentScores.map(dept => {
                      let style = 'color: #10b981; font-weight: bold;';
                      if (dept.score < 50) style = 'color: #ef4444; font-weight: bold;';
                      else if (dept.score < 75) style = 'color: #f59e0b; font-weight: bold;';
                      return `
                        <tr>
                          <td><strong>${escapeHtml(dept.name)}</strong></td>
                          <td>${dept.count}</td>
                          <td><span style="${style}">${dept.score}%</span></td>
                          <td><strong>${getSatisfactionLevel(dept.score)}</strong></td>
                        </tr>
                      `;
                    }).join('')}
                  </tbody>
                </table>
                <div class="footer">
                  <p>MedSurvey Pro - ${t('system_description', 'النظام الذكي المتكامل لاستبيانات رضا واستجابات المرضى ومؤشرات الأداء')}</p>
                  <p>© ${new Date().getFullYear()} ${escapeHtml(hospitalName)} | ${t('confidential_report', 'تقرير سري ومحمي للاستخدام الداخلي فقط')}</p>
                </div>
                <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
              </body>
              </html>
            `;
          }
          else if (type === 'departments') {
            const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
            const cleanReportTitle = t('report_departments_title', 'تقرير_تقييم_الأقسام_والمقارنات_الإدارية').replace(/\s+/g, '_');
            const dateStr = new Date().toISOString().slice(0, 10);
            const docTitle = action === 'pdf' 
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_departments_title', 'تقرير تقييم الأقسام والمقارنات الإدارية')} - ${hospitalName}`;
    
            html = `
              <!DOCTYPE html>
              <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
              <head>
                <meta charset="UTF-8">
                <title>${docTitle}</title>
                <style>
                  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
                  * { box-sizing: border-box; margin: 0; padding: 0; }
                  body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; line-height: 1.6; }
                  .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #6366f1; padding-bottom: 15px; margin-bottom: 25px; }
                  .hospital-info h1 { font-size: 16px; font-weight: 800; }
                  .report-meta { font-size: 11px; color: #64748b; }
                  .report-title-banner { text-align: center; background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                  .report-title-banner h2 { font-size: 18px; font-weight: 900; }
                  table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
                  th { background-color: #6366f1; color: white; font-weight: bold; padding: 10px; }
                  td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
                  tr:nth-child(even) { background-color: #f8fafc; }
                  .bar-container { display: flex; align-items: center; gap: 10px; justify-content: flex-start; }
                  .bar-outer { width: 100px; height: 10px; background-color: #e2e8f0; border-radius: 5px; overflow: hidden; }
                  .bar-inner { height: 100%; border-radius: 5px; }
                  .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
                  @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                  .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                </style>
              </head>
              <body>
                <div class="header-container">
                  <div style="display: flex; align-items: center; gap: 15px;">
                    ${logo ? `<img src="${logo}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />` : ''}
                    <div class="hospital-info">
                      <h1>${hospitalName}</h1>
                      <p>${operatingTitle}</p>
                    </div>
                  </div>
                  <div class="report-meta">
                    <p><strong>${t('report_date', 'التاريخ')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
                    ${action === 'pdf' ? `<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">${t('pdf_certified_copy', '💾 نسخة إلكترونية معتمدة بصيغة PDF')}</p>` : ''}
                  </div>
                </div>
                <div class="report-title-banner">
                  <h2>${t('report_departments_title', 'تقرير تقييم الأقسام والمقارنات الإدارية')}</h2>
                  <p>${t('report_departments_desc', 'تحليل مقارن لمستويات رضا المستفيدين والشكاوى الواردة حسب التوزيع المكاني للأقسام')}</p>
                </div>
                <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #6366f1; padding-right: 8px;">${t('dept_eval_table', 'جدول تقييم وترتيب الأقسام')}</h3>
                <table>
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>${t('medical_department', 'القسم الطبي')}</th>
                      <th>${t('received_responses', 'عدد الاستجابات المستلمة')}</th>
                      <th>${t('measured_satisfaction', 'معدل الرضا المقاس')}</th>
                      <th>${t('visual_representation', 'التمثيل البصري للنسبة')}</th>
                      <th>${t('satisfaction_level', 'مستوى الرضا')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${stats.departmentScores.map((dept, idx) => {
                      let barColor = '#10b981';
                      if (dept.score < 50) barColor = '#ef4444';
                      else if (dept.score < 75) barColor = '#f59e0b';
                      return `
                        <tr>
                          <td>${idx + 1}</td>
                          <td><strong>${escapeHtml(dept.name)}</strong></td>
                          <td>${dept.count}</td>
                          <td><strong style="color: ${barColor}">${dept.score}%</strong></td>
                          <td>
                            <div class="bar-container">
                              <div class="bar-outer">
                                <div class="bar-inner" style="width: ${dept.score}%; background-color: ${barColor};"></div>
                              </div>
                              <span>${dept.score}%</span>
                            </div>
                          </td>
                          <td><strong>${getSatisfactionLevel(dept.score)}</strong></td>
                        </tr>
                      `;
                    }).join('')}
                  </tbody>
                </table>
                <div class="footer">
                  <p>${t('system_dept_reports', 'MedSurvey Pro - نظام تقارير الأقسام والتحليلات المقارنة')}</p>
                  <p>© ${new Date().getFullYear()} ${t('all_rights_reserved', 'جميع الحقوق محفوظة لـ')} ${hospitalName}</p>
                </div>
                <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
              </body>
              </html>
            `;
          }
          else if (type === 'categories') {
            const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
            const cleanReportTitle = t('report_categories_title', 'تقرير_فئات_ومعايير_جودة_الخدمات_الصحية').replace(/\s+/g, '_');
            const dateStr = new Date().toISOString().slice(0, 10);
            const docTitle = action === 'pdf' 
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_categories_title', 'تقرير فئات ومعايير جودة الخدمات الصحية')} - ${hospitalName}`;
    
            html = `
              <!DOCTYPE html>
              <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
              <head>
                <meta charset="UTF-8">
                <title>${docTitle}</title>
                <style>
                  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
                  * { box-sizing: border-box; margin: 0; padding: 0; }
                  body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; line-height: 1.6; }
                  .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #10b981; padding-bottom: 15px; margin-bottom: 25px; }
                  .hospital-info h1 { font-size: 16px; font-weight: 800; }
                  .report-title-banner { text-align: center; background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                  .report-title-banner h2 { font-size: 18px; font-weight: 900; }
                  table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
                  th { background-color: #10b981; color: white; font-weight: bold; padding: 10px; }
                  td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
                  tr:nth-child(even) { background-color: #f8fafc; }
                  .bar-outer { width: 100%; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: 5px; }
                  .bar-inner { height: 100%; border-radius: 4px; }
                  .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
                  @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                  .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                </style>
              </head>
              <body>
                <div class="header-container">
                  <div style="display: flex; align-items: center; gap: 15px;">
                    ${logo ? `<img src="${logo}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />` : ''}
                    <div class="hospital-info">
                      <h1>${hospitalName}</h1>
                      <p>${operatingTitle}</p>
                    </div>
                  </div>
                  <div class="report-meta" style="font-size: 11px; color: #64748b;">
                    <p><strong>${t('report_date', 'التاريخ')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
                    ${action === 'pdf' ? `<p style="margin-top: 5px; color: #10b981; font-weight: bold;">${t('pdf_certified_copy', '💾 نسخة إلكترونية معتمدة بصيغة PDF')}</p>` : ''}
                  </div>
                </div>
                <div class="report-title-banner">
                  <h2>${t('report_categories_title', 'تقرير فئات ومعايير جودة الخدمات الصحية')}</h2>
                  <p>${t('report_categories_desc', 'تحليل نقاط القوة والضعف لجميع نقاط الاتصال وتجارب الرعاية الصحية للمستفيدين')}</p>
                </div>
                <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #10b981; padding-right: 8px;">${t('quality_satisfaction_levels', 'مستويات الجودة والرضا لخدمات الرعاية')}</h3>
                <table>
                  <thead>
                    <tr>
                      <th>${t('criteria_category', 'المعايير والفئة الخدمية')}</th>
                      <th>${t('overall_measured_satisfaction', 'معدل الرضا العام المقاس')}</th>
                      <th>${t('achieved_quality_level', 'مستوى الجودة المحقق')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${stats.categoryScores.map(cat => {
                      let barColor = '#10b981';
                      if (cat.score < 50) barColor = '#ef4444';
                      else if (cat.score < 75) barColor = '#f59e0b';
                      return `
                        <tr>
                          <td style="text-align: ${isAr ? 'right' : 'left'}; padding-right: 15px;">
                            <strong>${cat.category}</strong>
                            <div class="bar-outer">
                              <div class="bar-inner" style="width: ${cat.score}%; background-color: ${barColor};"></div>
                            </div>
                          </td>
                          <td><strong style="color: ${barColor}; font-size: 14px;">${cat.score}%</strong></td>
                          <td><strong>${getSatisfactionLevel(cat.score)}</strong></td>
                        </tr>
                      `;
                    }).join('')}
                  </tbody>
                </table>
                <div class="footer">
                  <p>${t('system_quality_reports', 'MedSurvey Pro - نظام تقارير الجودة ومقاييس الأداء لخدمات الرعاية')}</p>
                  <p>© ${new Date().getFullYear()} ${t('all_rights_reserved', 'جميع الحقوق محفوظة لـ')} ${escapeHtml(hospitalName)}</p>
                </div>
                <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
              </body>
              </html>
            `;
          }
          else if (type === 'tickets') {
            const total = tickets.length;
            const open = tickets.filter(t => t.status === 'open').length;
            const inProgress = tickets.filter(t => t.status === 'in_progress').length;
            const resolved = tickets.filter(t => t.status === 'resolved').length;
            
            const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
            const cleanReportTitle = t('report_tickets_title', 'تقرير_البلاغات_وتذاكر_المتابعة_الفورية_للشكاوى').replace(/\s+/g, '_');
            const dateStr = new Date().toISOString().slice(0, 10);
            const docTitle = action === 'pdf' 
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('report_tickets_title', 'تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى')} - ${hospitalName}`;
    
            html = `
              <!DOCTYPE html>
              <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
              <head>
                <meta charset="UTF-8">
                <title>${docTitle}</title>
                <style>
                  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
                  * { box-sizing: border-box; margin: 0; padding: 0; }
                  body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; line-height: 1.6; }
                  .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #ef4444; padding-bottom: 15px; margin-bottom: 25px; }
                  .hospital-info h1 { font-size: 16px; font-weight: 800; }
                  .report-title-banner { text-align: center; background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                  .report-title-banner h2 { font-size: 18px; font-weight: 900; }
                  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
                  .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; text-align: center; }
                  .stat-card .value { font-size: 22px; font-weight: 800; }
                  .stat-card .label { font-size: 10px; color: #64748b; margin-top: 5px; }
                  table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
                  th { background-color: #ef4444; color: white; font-weight: bold; padding: 10px; }
                  td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
                  tr:nth-child(even) { background-color: #f8fafc; }
                  .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-weight: bold; font-size: 9px; }
                  .status-open { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
                  .status-progress { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
                  .status-resolved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
                  .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
                  @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                  .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                </style>
              </head>
              <body>
                <div class="header-container">
                  <div style="display: flex; align-items: center; gap: 15px;">
                    ${logo ? `<img src="${logo}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />` : ''}
                    <div class="hospital-info">
                      <h1>${hospitalName}</h1>
                      <p>${operatingTitle}</p>
                    </div>
                  </div>
                  <div class="report-meta" style="font-size: 11px; color: #64748b;">
                    <p><strong>${t('date_label', 'التاريخ:')}</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
                    ${action === 'pdf' ? `<p style="margin-top: 5px; color: #ef4444; font-weight: bold;">${t('pdf_certified_copy', '💾 نسخة إلكترونية معتمدة بصيغة PDF')}</p>` : ''}
                  </div>
                </div>
                <div class="report-title-banner">
                  <h2>${t('report_tickets_title', 'تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى')}</h2>
                  <p>${t('ticket_monitoring_desc', 'مراقبة وتحليل البلاغات الفورية التي تسجلها آليات المتابعة الاستباقية لضمان الاستجابة السريعة لمشاكل المرضى')}</p>
                </div>
                <div class="stats-grid">
                  <div class="stat-card">
                    <div class="value" style="color: #ef4444;">${total}</div>
                    <div class="label">${t('total_registered_tickets', 'إجمالي البلاغات المسجلة')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value" style="color: #dc2626;">${open}</div>
                    <div class="label">${t('open_waiting_tickets', 'تذاكر مفتوحة وقيد الانتظار')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value" style="color: #d97706;">${inProgress}</div>
                    <div class="label">${t('active_processing_tickets', 'بلاغات قيد المعالجة النشطة')}</div>
                  </div>
                  <div class="stat-card">
                    <div class="value" style="color: #16a34a;">${resolved}</div>
                    <div class="label">${t('resolved_closed_tickets', 'بلاغات تم حلها وإغلاقها')}</div>
                  </div>
                </div>
                <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #ef4444; padding-right: 8px;">${t('tickets_details_list', 'قائمة تفاصيل البلاغات والحالات المستلمة')}</h3>
                <table>
                  <thead>
                    <tr>
                      <th>${t('patient_complainant', 'المريض ومقدم الشكوى')}</th>
                      <th>${t('concerned_dept', 'القسم المعني')}</th>
                      <th>${t('reason_complaint', 'السبب والشكوى')}</th>
                      <th>${t('ticket_status', 'حالة التذكرة')}</th>
                      <th>${t('priority', 'الأولوية')}</th>
                      <th>${t('submission_date', 'تاريخ التقديم')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${tickets.map(ticket => {
                      let statusClass = 'status-open';
                      let statusText = t('open', 'مفتوح');
                      if (ticket.status === 'in_progress') { statusClass = 'status-progress'; statusText = t('in_progress', 'قيد المعالجة'); }
                      else if (ticket.status === 'resolved') { statusClass = 'status-resolved'; statusText = t('resolved', 'تم الحل'); }
                      return `
                        <tr>
                          <td><strong>${escapeHtml(ticket.patientName)}</strong><br><span style="font-size: 9px; color: #64748b;">${escapeHtml(ticket.patientPhone)}</span></td>
                          <td><strong>${escapeHtml(ticket.department)}</strong></td>
                          <td style="text-align: ${isAr ? 'right' : 'left'}; max-width: 200px;">${escapeHtml(ticket.description)}</td>
                          <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                          <td><span style="color: ${ticket.priority === 'high' ? '#ef4444' : '#64748b'}; font-weight: bold;">${ticket.priority === 'high' ? t('very_high', 'عالية جداً') : t('normal', 'عادية')}</span></td>
                          <td>${new Date(ticket.createdAt).toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</td>
                        </tr>
                      `;
                    }).join('')}
                  </tbody>
                </table>
                <div class="footer">
                  <p>${t('system_ticket_reports', 'MedSurvey Pro - إدارة ومراقبة الجودة والاستجابة الاستباقية للشكاوى')}</p>
                  <p>© ${new Date().getFullYear()} ${t('all_rights_reserved', 'جميع الحقوق محفوظة لـ')} ${escapeHtml(hospitalName)}</p>
                </div>
                <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
              </body>
              </html>
            `;
          }
          else if (type === 'predictive') {
            const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
            const cleanReportTitle = t('predictive_report_file', 'تقرير_نظام_الإنذار_المبكر_ومؤشرات_التنبؤ_الذكي_AI').replace(/\s+/g, '_');
            const dateStr = new Date().toISOString().slice(0, 10);
            const docTitle = action === 'pdf' 
              ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
              : `${t('predictive_report_title', 'تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI)')} - ${hospitalName}`;
    
            html = `
              <!DOCTYPE html>
              <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
              <head>
                <meta charset="UTF-8">
                <title>${docTitle}</title>
                <style>
                  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
                  * { box-sizing: border-box; margin: 0; padding: 0; }
                  body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; line-height: 1.6; }
                  .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #6366f1; padding-bottom: 15px; margin-bottom: 25px; }
                  .hospital-info h1 { font-size: 16px; font-weight: 800; }
                  .report-title-banner { text-align: center; background: linear-gradient(135deg, #4f46e5, #4f46e5); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                  .report-title-banner h2 { font-size: 18px; font-weight: 900; }
                  .risk-alert-box { background: #eef2ff; border-right: 5px solid #4f46e5; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 12px; }
                  .risk-alert-box h4 { font-weight: 800; color: #4f46e5; margin-bottom: 5px; }
                  table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
                  th { background-color: #4f46e5; color: white; font-weight: bold; padding: 10px; }
                  td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
                  tr:nth-child(even) { background-color: #f8fafc; }
                  .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
                  @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                  .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                </style>
              </head>
              <body>
                <div class="header-container">
                  <div style="display: flex; align-items: center; gap: 15px;">
                    ${logo ? `<img src="${logo}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />` : ''}
                    <div class="hospital-info">
                      <h1>${escapeHtml(hospitalName)}</h1>
                      <p>${escapeHtml(operatingTitle)}</p>
                    </div>
                  </div>
                  <div class="report-meta" style="font-size: 11px; color: #64748b;">
                    <p><strong>${t('date_label', 'التاريخ:')}</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
                    ${action === 'pdf' ? `<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">${t('pdf_certified_copy', '💾 نسخة إلكترونية معتمدة بصيغة PDF')}</p>` : ''}
                  </div>
                </div>
                <div class="report-title-banner">
                  <h2>${t('predictive_report_title', 'تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI)')}</h2>
                  <p>${t('predictive_report_desc', 'تحليلات تنبؤية للتحذير الاستباقي قبل تراجع مستويات الجودة وتحديد الأقسام والخدمات الأكثر حساسية لعوامل تراجع رضا المرضى')}</p>
                </div>
                <div class="risk-alert-box">
                  <h4>💡 ${t('ai_reading_summary', 'ملخص القراءة التحليلية لنظام الذكاء الاصطناعي')}</h4>
                  <p>${t('predictive_ai_analysis', 'بناءً على النماذج التنبؤية وتحليل الاتجاهات الإحصائية الأخيرة، يُظهر النظام استقراراً عاماً لمعدل الرضا، مع وجود حساسية متوسطة لبعض فئات الخدمة مثل فترات الانتظار ومستوى الرعاية من التمريض. نوصي بالاستجابة الاستباقية لتوصيات الأقسام المدرجة أدناه لتجنب أي تراجع مستقبلي.')}</p>
                </div>
                <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #4f46e5; padding-right: 8px;">${t('sensitivity_indicators_depts', 'مؤشرات الحساسية والأقسام الأكثر عرضة لتراجع الرضا')}</h3>
                <table>
                  <thead>
                    <tr>
                      <th>${t('target_dept', 'القسم المستهدف')}</th>
                      <th>${t('current_satisfaction', 'معدل الرضا الحالي')}</th>
                      <th>${t('predictive_risk_level', 'مستوى المخاطر التنبؤي')}</th>
                      <th>${t('ai_proactive_recommendation', 'التوصية الاستباقية للذكاء الاصطناعي')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${stats.departmentScores.map(dept => {
                      let riskLevel = t('low_risk', 'منخفضة');
                      let riskColor = '#10b981';
                      let rec = t('rec_low_risk', 'الاستمرار في الحفاظ على مستوى الجودة الحالي وتعزيز التتميز.');
                      if (dept.score < 60) {
                        riskLevel = t('very_high', 'عالية جداً');
                        riskColor = '#ef4444';
                        rec = t('rec_high_risk', 'التدخل العاجل ومراجعة إجراءات تذاكر البلاغات والشكاوى لموظفي هذا القسم فوراً.');
                      } else if (dept.score < 75) {
                        riskLevel = t('medium_risk', 'متوسطة');
                        riskColor = '#f59e0b';
                        rec = t('rec_medium_risk', 'تكثيف المتابعة وتقديم دورات تنشيطية لتحسين كفاءة تقديم الخدمة وتقليص فترات الانتظار.');
                      }
                      return `
                        <tr>
                          <td><strong>${escapeHtml(dept.name)}</strong></td>
                          <td><strong style="color: ${riskColor};">${dept.score}%</strong></td>
                          <td><strong style="color: ${riskColor};">${riskLevel}</strong></td>
                          <td style="text-align: ${isAr ? 'right' : 'left'}; max-width: 250px;">${rec}</td>
                        </tr>
                      `;
                    }).join('')}
                  </tbody>
                </table>
                <div class="footer">
                  <p>${t('system_predictive_reports', 'MedSurvey Pro - نظام التنبؤ الذكي وتحليلات الإنذار الاستباقي للرعاية الصحية')}</p>
                  <p>© ${new Date().getFullYear()} ${t('all_rights_reserved', 'جميع الحقوق محفوظة لـ')} ${escapeHtml(hospitalName)}</p>
                </div>
                <div class="page-footer">${escapeHtml(hospitalName)} | MedSurvey Pro</div>
              </body>
              </html>
            `;
          }
          
          printWindow.document.write(html);
          printWindow.document.close();
          
          // Fire-and-forget audit event via internal call
          fetch('{{ route('dashboard.audit.events') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
              action: action === 'print' ? 'print_report' : 'export_report',
              messageKey: action === 'print' ? 'audit.details.print_report' : 'audit.details.export_report',
              params: {
                reportType: type,
                department: this.department || 'all',
                dateRange: this.dateFilter
              }
            })
          }).catch(() => {});
          
          requestAnimationFrame(() => {
            printWindow.print();
            this.exportingReport = null;
          });
        }
      }));
    });
  </script>

  <div x-data="reportsComponent" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-start select-none">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
      <div class="flex items-center gap-3">
        <a href="{{ route('dashboard.index') }}" 
           class="p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-850 rounded-xl transition-all shadow-sm cursor-pointer"
        >
          <i data-lucide="{{ app()->getLocale() === 'ar' ? 'arrow-right' : 'arrow-left' }}" class="w-5 h-5 text-gray-500 dark:text-slate-400"></i>
        </a>
        <div>
          <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
            <span>{{ __('reports_header_title') }}</span>
            <span class="text-xs bg-teal-150 dark:bg-teal-950/20 text-teal-700 dark:text-teal-450 font-bold px-2.5 py-1 rounded-full border border-teal-200 dark:border-teal-900/40">
              {{ __('reports_header_badge') }}
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-gray-400 dark:text-slate-400 mt-1">
            {{ __('reports_header_desc') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Interactive Filters Grid -->
    <div class="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl p-4 mb-8 shadow-sm">
      <div class="flex items-center gap-2.5 text-sm font-bold text-gray-800 dark:text-white mb-4 pb-2 border-b border-gray-50 dark:border-slate-800/80">
        <i data-lucide="filter" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        <span>{{ __('reports_filter_title') }}</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Date Filter Buttons -->
        <div class="space-y-1.5 text-start">
          <label class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_date_range') }}</span>
          </label>
          <div class="grid grid-cols-3 md:grid-cols-5 gap-1.5">
            <button @click="setDateFilter('all')" type="button"
                    :class="dateFilter === 'all' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">
              {{ __('reports_filter_all') }}
            </button>
            <button @click="setDateFilter('week')" type="button"
                    :class="dateFilter === 'week' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">
              {{ __('reports_filter_week') }}
            </button>
            <button @click="setDateFilter('month')" type="button"
                    :class="dateFilter === 'month' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">
              {{ __('reports_filter_month') }}
            </button>
            <button @click="setDateFilter('quarter')" type="button"
                    :class="dateFilter === 'quarter' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">
              {{ __('reports_filter_quarter') }}
            </button>
            <button @click="setDateFilter('custom')" type="button"
                    :class="dateFilter === 'custom' ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900/60 shadow-sm' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'"
                    class="py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer">
              {{ __('reports_filter_custom') }}
            </button>
          </div>
        </div>

        <!-- Department Selection Dropdown -->
        <div class="space-y-1.5 text-start">
          <label class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
            <i data-lucide="building-2" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_dept') }}</span>
          </label>
          <select x-model="department" 
                  @change="applyFilters()"
                  :disabled="!!restrictedDept"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm cursor-pointer"
          >
            <option value="all">{{ __('reports_filter_all_depts') }}</option>
            @foreach($departments as $dept)
              <option value="{{ $dept }}">{{ $dept }}</option>
            @endforeach
          </select>
          <template x-if="!!restrictedDept">
            <p class="text-[11px] font-bold text-teal-600 dark:text-teal-400 animate-pulse mt-1 select-none">
              {{ __('reports_filter_dept_restricted') }}
            </p>
          </template>
        </div>
      </div>

      <!-- Custom Date Fields -->
      <div x-show="dateFilter === 'custom'" 
           x-transition:enter="ease-out duration-300"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-50 dark:border-slate-850"
           x-cloak>
        <div class="space-y-1.5 text-start">
          <label class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_date_from') }}</span>
          </label>
          <input type="date" x-model="startDate" 
                 class="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200">
        </div>
        <div class="space-y-1.5 text-start">
          <label class="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span>{{ __('reports_filter_date_to') }}</span>
          </label>
          <input type="date" x-model="endDate" 
                 class="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200">
        </div>
        <div class="flex items-end">
          <button @click="applyFilters()" type="button"
                  class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer">
            {{ __('reports_filter_apply') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Stats overview -->
    @if(isset($stats))
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-teal-50/50 dark:bg-teal-950/10 p-4 border border-teal-100 dark:border-teal-900/30 rounded-2xl mb-8">
        <div class="text-center">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_total_responses') }}</span>
          <span class="text-lg font-black text-teal-800 dark:text-teal-300 font-mono">{{ $stats['totalResponses'] ?? 0 }} {{ __('reports_stat_response_word') }}</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_overall_satisfaction') }}</span>
          <span class="text-lg font-black text-teal-800 dark:text-teal-300 font-mono">{{ $stats['averageScore'] ?? 0 }}%</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_nps_score') }}</span>
          <span class="text-lg font-black text-teal-800 dark:text-teal-300 font-mono">{{ $stats['npsScore'] ?? 0 }}</span>
        </div>
        <div class="text-center border-r border-teal-100 dark:border-teal-900/30">
          <span class="block text-[10px] text-teal-600 dark:text-teal-400 font-bold mb-1">{{ __('reports_stat_data_status') }}</span>
          <span class="text-sm font-black text-teal-800 dark:text-teal-300 flex items-center justify-center gap-1.5 mt-0.5">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 dark:text-emerald-405 shrink-0"></i>
            <span>{{ __('reports_stat_processed_updated') }}</span>
          </span>
        </div>
      </div>
    @endif

    <!-- Cards list -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      @foreach([
        [
          'type' => 'executive',
          'title' => __('reports_card1_title'),
          'desc' => __('reports_card1_desc'),
          'icon' => 'file-text',
          'color' => 'text-teal-600 dark:text-teal-400',
          'bgGradient' => 'from-teal-500/10 to-teal-600/10 dark:from-teal-950/20 dark:to-teal-900/10 hover:from-teal-500/20 hover:to-teal-600/20',
          'border' => 'border-teal-100 hover:border-teal-300 dark:border-slate-800 dark:hover:border-teal-900',
        ],
        [
          'type' => 'departments',
          'title' => __('reports_card2_title'),
          'desc' => __('reports_card2_desc'),
          'icon' => 'building-2',
          'color' => 'text-indigo-600 dark:text-indigo-400',
          'bgGradient' => 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20',
          'border' => 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900',
        ],
        [
          'type' => 'categories',
          'title' => __('reports_card3_title'),
          'desc' => __('reports_card3_desc'),
          'icon' => 'trending-up',
          'color' => 'text-emerald-600 dark:text-emerald-400',
          'bgGradient' => 'from-emerald-500/10 to-emerald-600/10 dark:from-emerald-950/20 dark:to-emerald-900/10 hover:from-emerald-500/20 hover:to-emerald-600/20',
          'border' => 'border-emerald-100 hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-900',
        ],
        [
          'type' => 'tickets',
          'title' => __('reports_card4_title'),
          'desc' => __('reports_card4_desc'),
          'icon' => 'circle-alert',
          'color' => 'text-red-600 dark:text-red-400',
          'bgGradient' => 'from-red-500/10 to-red-600/10 dark:from-red-950/20 dark:to-red-900/10 hover:from-red-500/20 hover:to-red-600/20',
          'border' => 'border-red-100 hover:border-red-300 dark:border-slate-800 dark:hover:border-red-900',
        ],
        [
          'type' => 'predictive',
          'title' => __('reports_card5_title'),
          'desc' => __('reports_card5_desc'),
          'icon' => 'brain',
          'color' => 'text-indigo-600 dark:text-indigo-400',
          'bgGradient' => 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20',
          'border' => 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900',
        ]
      ] as $card)
        <div class="bg-white dark:bg-slate-900 border rounded-2xl p-6 transition-all hover:shadow-lg flex flex-col justify-between {{ $card['border'] }}">
          <div class="space-y-3">
            <div class="flex items-start justify-between">
              <div class="p-3 rounded-xl bg-linear-to-br {{ $card['bgGradient'] }}">
                <i data-lucide="{{ $card['icon'] }}" class="w-6 h-6 {{ $card['color'] }}"></i>
              </div>
              <span class="text-[10px] bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 font-bold px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-800 flex items-center gap-1 shadow-sm select-none">
                <i data-lucide="award" class="w-3.5 h-3.5 text-amber-500"></i>
                <span>{{ __('reports_certified_official') }}</span>
              </span>
            </div>
            
            <h3 class="font-black text-base text-gray-800 dark:text-white">{{ $card['title'] }}</h3>
            <p class="text-xs text-gray-550 dark:text-slate-400 leading-relaxed">{{ $card['desc'] }}</p>
          </div>

          <div class="pt-5 border-t border-gray-100 dark:border-slate-850 mt-5 flex flex-col sm:flex-row items-center gap-3">
            <!-- PDF Export Button -->
            <button @click="handleExportPDF('{{ $card['type'] }}', 'pdf')"
                    :disabled="exportingReport === '{{ $card['type'] }}_pdf' || exportingReport === '{{ $card['type'] }}_print'"
                    type="button"
                    class="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-indigo-600 to-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
            >
              <template x-if="exportingReport === '{{ $card['type'] }}_pdf'">
                <div class="flex items-center gap-2">
                  <i data-lucide="loader" class="w-4 h-4 animate-spin"></i>
                  <span>{{ __('reports_exporting') }}</span>
                </div>
              </template>
              <template x-if="exportingReport !== '{{ $card['type'] }}_pdf'">
                <div class="flex items-center gap-2">
                  <i data-lucide="file-down" class="w-4 h-4"></i>
                  <span>{{ __('reports_export_pdf') }}</span>
                </div>
              </template>
            </button>

            <!-- Print Button -->
            <button @click="handleExportPDF('{{ $card['type'] }}', 'print')"
                    :disabled="exportingReport === '{{ $card['type'] }}_pdf' || exportingReport === '{{ $card['type'] }}_print'"
                    type="button"
                    class="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-teal-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
            >
              <template x-if="exportingReport === '{{ $card['type'] }}_print'">
                <div class="flex items-center gap-2">
                  <i data-lucide="loader" class="w-4 h-4 animate-spin"></i>
                  <span>{{ __('reports_printing') }}</span>
                </div>
              </template>
              <template x-if="exportingReport !== '{{ $card['type'] }}_print'">
                <div class="flex items-center gap-2">
                  <i data-lucide="printer" class="w-4 h-4"></i>
                  <span>{{ __('reports_print_now') }}</span>
                </div>
              </template>
            </button>
          </div>
        </div>
      @endforeach
    </div>

  </div>
@endsection
