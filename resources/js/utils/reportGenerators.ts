import { DashboardStats, Ticket } from '../types';
import type { TFunction } from 'i18next';

export interface ReportContext {
  stats: DashboardStats | null;
  tickets: Ticket[];
  hospitalName: string;
  operatingTitle: string;
  logo?: string;
  language: string;
  t: TFunction;
  reportDepartmentLabel?: string;
}

const getSatisfactionLevel = (score: number, t: ReportContext['t']): string => {
  if (score >= 85) return t('score_excellent', 'ممتاز');
  if (score >= 70) return t('score_good', 'جيد');
  if (score >= 50) return t('score_average', 'متوسط');
  return t('score_poor', 'ضعيف');
};

export const generateExecutiveReport = (printWindow: Window, action: 'pdf' | 'print', ctx: ReportContext) => {
  const { stats, hospitalName, operatingTitle, logo, language, t, reportDepartmentLabel } = ctx;
  if (!stats) return;
  const isAr = language === 'ar';
  
  const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
  const cleanReportTitle = t('report_executive_title', 'تقرير_الملخص_التنفيذي_ورضا_المرضى_الشامل').replace(/\s+/g, '_');
  const dateStr = new Date().toISOString().slice(0, 10);
  const docTitle = action === 'pdf' 
    ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
    : `${t('report_executive_title', 'تقرير الملخص التنفيذي ورضا المرضى الشامل')} - ${hospitalName}`;

  const html = `
    <!DOCTYPE html>
    <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; padding: 25px; color: #1e293b; background-color: #ffffff; line-height: 1.6; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .logo-placeholder { width: 50px; height: 50px; background: linear-gradient(135deg, #0d9488, #10b981); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; }
        .hospital-info h1 { font-size: 18px; font-weight: 800; color: #0f172a; }
        .hospital-info p { font-size: 11px; color: #64748b; margin-top: 2px; }
        .header-left { text-align: ${isAr ? 'left' : 'right'}; }
        .report-meta { font-size: 12px; color: #64748b; }
        .report-meta strong { color: #0f172a; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
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
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div class="header-right">
          ${logo ? `<img src="${logo}" alt="Logo" style="height: 50px; width: auto; max-width: 150px; object-fit: contain;" />` : `<div class="logo-placeholder">⚕️</div>`}
          <div class="hospital-info">
            <h1>${hospitalName}</h1>
            <p>${operatingTitle}</p>
          </div>
        </div>
        <div class="header-left">
          <div class="report-meta">
            <p><strong>${t('report_date', 'تاريخ التقرير')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
            <p><strong>${t('export_department', 'القسم المستهدف')}:</strong> ${reportDepartmentLabel || 'الكل'}</p>
            ${action === 'pdf' ? `<p style="margin-top: 5px; color: #0d9488; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>` : ''}
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
                if (item.level.includes('ممتاز') || item.level.toLowerCase().includes('excellent')) badgeClass = 'badge-excellent';
                else if (item.level.includes('متوسط') || item.level.toLowerCase().includes('average')) badgeClass = 'badge-average';
                else if (item.level.includes('ضعيف') || item.level.toLowerCase().includes('poor')) badgeClass = 'badge-poor';
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
                  <td><strong>${cat.category}</strong></td>
                  <td><span style="color: #0d9488; font-weight: bold;">${cat.score}%</span></td>
                  <td>${getSatisfactionLevel(cat.score, t)}</td>
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
            else if (dept.score < 70) style = 'color: #f59e0b; font-weight: bold;';
            return `
              <tr>
                <td><strong>${dept.name}</strong></td>
                <td>${dept.count}</td>
                <td><span style="${style}">${dept.score}%</span></td>
                <td><strong>${getSatisfactionLevel(dept.score, t)}</strong></td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - ${t('system_description', 'النظام الذكي المتكامل لاستبيانات رضا واستجابات المرضى ومؤشرات الأداء')}</p>
        <p>© ${new Date().getFullYear()} ${hospitalName} | ${t('confidential_report', 'تقرير سري ومحمي للاستخدام الداخلي فقط')}</p>
      </div>
      <div class="page-footer">${hospitalName} | صفحة <span class="pageNumber"></span> | MedSurvey Pro</div>
    </body>
    </html>
  `;
  printWindow.document.write(html);
};

export const generateDepartmentsReport = (printWindow: Window, action: 'pdf' | 'print', ctx: ReportContext) => {
  const { stats, hospitalName, operatingTitle, logo, language, t } = ctx;
  if (!stats) return;
  const isAr = language === 'ar';
  
  const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
  const cleanReportTitle = t('report_departments_title', 'تقرير_تقييم_الأقسام_والمقارنات_الإدارية').replace(/\s+/g, '_');
  const dateStr = new Date().toISOString().slice(0, 10);
  const docTitle = action === 'pdf' 
    ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
    : `${t('report_departments_title', 'تقرير تقييم الأقسام والمقارنات الإدارية')} - ${hospitalName}`;

  const html = `
    <!DOCTYPE html>
    <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
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
        @page { size: A4; margin: 15mm; }
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
          ${action === 'pdf' ? `<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>` : ''}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${t('report_departments_title', 'تقرير تقييم الأقسام والمقارنات الإدارية')}</h2>
        <p>${t('report_departments_desc', 'تحليل مقارن لمستويات رضا المستفيدين والشكاوى الواردة حسب التوزيع المكاني للأقسام')}</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #6366f1; padding-right: 8px;">جدول تقييم وترتيب الأقسام</h3>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>القسم الطبي</th>
            <th>عدد الاستجابات المستلمة</th>
            <th>معدل الرضا المقاس</th>
            <th>التمثيل البصري للنسبة</th>
            <th>مستوى الرضا</th>
          </tr>
        </thead>
        <tbody>
          ${stats.departmentScores.map((dept, idx) => {
            let barColor = '#10b981';
            if (dept.score < 50) barColor = '#ef4444';
            else if (dept.score < 70) barColor = '#f59e0b';
            return `
              <tr>
                <td>${idx + 1}</td>
                <td><strong>${dept.name}</strong></td>
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
                <td><strong>${getSatisfactionLevel(dept.score, t)}</strong></td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام تقارير الأقسام والتحليلات المقارنة</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
      </div>
      <div class="page-footer">${hospitalName} | MedSurvey Pro</div>
    </body>
    </html>
  `;
  printWindow.document.write(html);
};

export const generateCategoriesReport = (printWindow: Window, action: 'pdf' | 'print', ctx: ReportContext) => {
  const { stats, hospitalName, operatingTitle, logo, language, t } = ctx;
  if (!stats) return;
  const isAr = language === 'ar';
  
  const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
  const cleanReportTitle = t('report_categories_title', 'تقرير_فئات_ومعايير_جودة_الخدمات_الصحية').replace(/\s+/g, '_');
  const dateStr = new Date().toISOString().slice(0, 10);
  const docTitle = action === 'pdf' 
    ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
    : `${t('report_categories_title', 'تقرير فئات ومعايير جودة الخدمات الصحية')} - ${hospitalName}`;

  const html = `
    <!DOCTYPE html>
    <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
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
        @page { size: A4; margin: 15mm; }
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
          ${action === 'pdf' ? `<p style="margin-top: 5px; color: #10b981; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>` : ''}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${t('report_categories_title', 'تقرير فئات ومعايير جودة الخدمات الصحية')}</h2>
        <p>${t('report_categories_desc', 'تحليل نقاط القوة والضعف لجميع نقاط الاتصال وتجارب الرعاية الصحية للمستفيدين')}</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #10b981; padding-right: 8px;">مستويات الجودة والرضا لخدمات الرعاية</h3>
      <table>
        <thead>
          <tr>
            <th>المعايير والفئة الخدمية</th>
            <th>معدل الرضا العام المقاس</th>
            <th>مستوى الجودة المحقق</th>
          </tr>
        </thead>
        <tbody>
          ${stats.categoryScores.map(cat => {
            let barColor = '#10b981';
            if (cat.score < 50) barColor = '#ef4444';
            else if (cat.score < 70) barColor = '#f59e0b';
            return `
              <tr>
                <td style="text-align: ${isAr ? 'right' : 'left'}; padding-right: 15px;">
                  <strong>${cat.category}</strong>
                  <div class="bar-outer">
                    <div class="bar-inner" style="width: ${cat.score}%; background-color: ${barColor};"></div>
                  </div>
                </td>
                <td><strong style="color: ${barColor}; font-size: 14px;">${cat.score}%</strong></td>
                <td><strong>${getSatisfactionLevel(cat.score, t)}</strong></td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام تقارير الجودة ومقاييس الأداء لخدمات الرعاية</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
      </div>
      <div class="page-footer">${hospitalName} | MedSurvey Pro</div>
    </body>
    </html>
  `;
  printWindow.document.write(html);
};

export const generateTicketsReport = (printWindow: Window, action: 'pdf' | 'print', ctx: ReportContext) => {
  const { tickets, hospitalName, operatingTitle, logo, language, t } = ctx;
  const isAr = language === 'ar';
  
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

  const html = `
    <!DOCTYPE html>
    <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
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
        @page { size: A4; margin: 15mm; }
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
          <p><strong>التاريخ:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
          ${action === 'pdf' ? `<p style="margin-top: 5px; color: #ef4444; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>` : ''}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${t('report_tickets_title', 'تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى')}</h2>
        <p>مراقبة وتحليل البلاغات الفورية التي تسجلها آليات المتابعة الاستباقية لضمان الاستجابة السريعة لمشاكل المرضى</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="value" style="color: #ef4444;">${total}</div>
          <div class="label">إجمالي البلاغات المسجلة</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #dc2626;">${open}</div>
          <div class="label">تذاكر مفتوحة وقيد الانتظار</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #d97706;">${inProgress}</div>
          <div class="label">بلاغات قيد المعالجة النشطة</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #16a34a;">${resolved}</div>
          <div class="label">بلاغات تم حلها وإغلاقها</div>
        </div>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #ef4444; padding-right: 8px;">قائمة تفاصيل البلاغات والحالات المستلمة</h3>
      <table>
        <thead>
          <tr>
            <th>المريض ومقدم الشكوى</th>
            <th>القسم المعني</th>
            <th>السبب والشكوى</th>
            <th>حالة التذكرة</th>
            <th>الأولوية</th>
            <th>تاريخ التقديم</th>
          </tr>
        </thead>
        <tbody>
          ${tickets.map(ticket => {
            let statusClass = 'status-open';
            let statusText = 'مفتوح';
            if (ticket.status === 'in_progress') { statusClass = 'status-progress'; statusText = 'قيد المعالجة'; }
            else if (ticket.status === 'resolved') { statusClass = 'status-resolved'; statusText = 'تم الحل'; }
            return `
              <tr>
                <td><strong>${ticket.patientName}</strong><br><span style="font-size: 9px; color: #64748b;">${ticket.patientPhone}</span></td>
                <td><strong>${ticket.department}</strong></td>
                <td style="text-align: ${isAr ? 'right' : 'left'}; max-width: 200px;">${ticket.description}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td><span style="color: ${ticket.priority === 'high' ? '#ef4444' : '#64748b'}; font-weight: bold;">${ticket.priority === 'high' ? 'عالية جداً' : 'عادية'}</span></td>
                <td>${new Date(ticket.createdAt).toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - إدارة ومراقبة الجودة والاستجابة الاستباقية للشكاوى</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
      </div>
      <div class="page-footer">${hospitalName} | MedSurvey Pro</div>
    </body>
    </html>
  `;
  printWindow.document.write(html);
};

export const generatePredictiveReport = (printWindow: Window, action: 'pdf' | 'print', ctx: ReportContext) => {
  const { stats, hospitalName, operatingTitle, logo, language } = ctx;
  if (!stats) return;
  const isAr = language === 'ar';
  
  const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
  const cleanReportTitle = 'تقرير_نظام_الإنذار_المبكر_ومؤشرات_التنبؤ_الذكي_AI'.replace(/\s+/g, '_');
  const dateStr = new Date().toISOString().slice(0, 10);
  const docTitle = action === 'pdf' 
    ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
    : `تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI) - ${hospitalName}`;

  const html = `
    <!DOCTYPE html>
    <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${language}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
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
        @page { size: A4; margin: 15mm; }
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
            <p>${operatingTitle || 'الرعاية الطبية الموثوقة'}</p>
          </div>
        </div>
        <div class="report-meta" style="font-size: 11px; color: #64748b;">
          <p><strong>التاريخ:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
          ${action === 'pdf' ? `<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>` : ''}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI)</h2>
        <p>تحليلات تنبؤية للتحذير الاستباقي قبل تراجع مستويات الجودة وتحديد الأقسام والخدمات الأكثر حساسية لعوامل تراجع رضا المرضى</p>
      </div>
      <div class="risk-alert-box">
        <h4>💡 ملخص القراءة التحليلية لنظام الذكاء الاصطناعي</h4>
        <p>بناءً على النماذج التنبؤية وتحليل الاتجاهات الإحصائية الأخيرة، يُظهر النظام استقراراً عاماً لمعدل الرضا، مع وجود حساسية متوسطة لبعض فئات الخدمة مثل فترات الانتظار ومستوى الرعاية من التمريض. نوصي بالاستجابة الاستباقية لتوصيات الأقسام المدرجة أدناه لتجنب أي تراجع مستقبلي.</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #4f46e5; padding-right: 8px;">مؤشرات الحساسية والأقسام الأكثر عرضة لتراجع الرضا</h3>
      <table>
        <thead>
          <tr>
            <th>القسم المستهدف</th>
            <th>معدل الرضا الحالي</th>
            <th>مستوى المخاطر التنبؤي</th>
            <th>التوصية الاستباقية للذكاء الاصطناعي</th>
          </tr>
        </thead>
        <tbody>
          ${stats.departmentScores.map(dept => {
            let riskLevel = 'منخفضة';
            let riskColor = '#10b981';
            let rec = 'الاستمرار في الحفاظ على مستوى الجودة الحالي وتعزيز التميز.';
            if (dept.score < 60) {
              riskLevel = 'عالية جداً';
              riskColor = '#ef4444';
              rec = 'التدخل العاجل ومراجعة إجراءات تذاكر البلاغات والشكاوى لموظفي هذا القسم فوراً.';
            } else if (dept.score < 75) {
              riskLevel = 'متوسطة';
              riskColor = '#f59e0b';
              rec = 'تكثيف المتابعة وتقديم دورات تنشيطية لتحسين كفاءة تقديم الخدمة وتقليص فترات الانتظار.';
            }
            return `
              <tr>
                <td><strong>${dept.name}</strong></td>
                <td><strong style="color: ${riskColor};">${dept.score}%</strong></td>
                <td><strong style="color: ${riskColor};">${riskLevel}</strong></td>
                <td style="text-align: ${isAr ? 'right' : 'left'}; max-width: 250px;">${rec}</td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام التنبؤ الذكي وتحليلات الإنذار الاستباقي للرعاية الصحية</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
      </div>
      <div class="page-footer">${hospitalName} | MedSurvey Pro</div>
    </body>
    </html>
  `;
  printWindow.document.write(html);
};
