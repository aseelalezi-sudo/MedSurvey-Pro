import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useSettingsStore } from '../store/useSettingsStore';
import { useAuthStore } from '../store/useAuthStore';
import { responsesAPI, ticketsAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('ReportsPage');

import { DashboardStats, Ticket } from '../types';
import { calculateDashboardStats } from '../data/statsUtils';
import {
  FileText,
  TrendingUp,
  Building2,
  AlertCircle,
  Brain,
  Printer,
  Calendar,
  Filter,
  ArrowLeft,
  Loader2,
  CheckCircle2,
  Award,
  FileDown,
} from 'lucide-react';

type ReportType = 'executive' | 'departments' | 'categories' | 'tickets' | 'predictive';

export default function ReportsPage() {
  const navigate = useNavigate();
  const onBack = () => navigate('/dashboard');
  const { t, i18n } = useTranslation();
  const { settings } = useSettingsStore();
  const { currentUser } = useAuthStore();
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  
  // Interactive filters
  const [dateRange, setDateRange] = useState<'all' | 'week' | 'month' | 'quarter' | 'custom'>('all');
  const [selectedDepartment, setSelectedDepartment] = useState<string>('all');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [departments, setDepartments] = useState<string[]>([]);
  
  // Exporting state
  const [exportingReport, setExportingReport] = useState<string | null>(null);
  const restrictedDepartment = currentUser?.role === 'head_of_department' ? currentUser.department : undefined;
  const effectiveDepartment = restrictedDepartment || (selectedDepartment !== 'all' ? selectedDepartment : undefined);
  const reportDepartmentLabel = effectiveDepartment || t('export_all_departments', 'كل الأقسام');

  useEffect(() => {
    if (restrictedDepartment && selectedDepartment !== restrictedDepartment) {
      setSelectedDepartment(restrictedDepartment);
    }
  }, [restrictedDepartment, selectedDepartment]);

  useEffect(() => {
    loadData();
  }, [dateRange, selectedDepartment, startDate, endDate, restrictedDepartment]);

  const loadData = async () => {
    if (dateRange === 'custom' && (!startDate || !endDate)) {
      return;
    }
    setLoading(true);
    try {
      // Load filtered responses
      const res = await responsesAPI.getAll({
        exportAll: true,
        department: effectiveDepartment,
        dateFilter: dateRange !== 'all' ? dateRange : undefined,
        startDate: dateRange === 'custom' ? startDate : undefined,
        endDate: dateRange === 'custom' ? endDate : undefined,
      });
      
      const loadedResponses = res.data;
      
      // Calculate stats based on filtered responses
      const computedStats = calculateDashboardStats(loadedResponses);
      setStats(computedStats);

      // Load all departments for filter
      const statsRes = await responsesAPI.getStats();
      setDepartments(restrictedDepartment ? [restrictedDepartment] : statsRes.departmentScores.map((d: { name: string }) => d.name));

      // Load tickets
      const ticketsRes = await ticketsAPI.getAll({ department: effectiveDepartment });
      setTickets(ticketsRes);
    } catch (err) {
      logger.error('Failed to load reports data:', err);
    } finally {
      setLoading(false);
    }
  };

  const getSatisfactionLevel = (score: number): string => {
    if (score >= 85) return t('score_excellent', 'ممتاز');
    if (score >= 70) return t('score_good', 'جيد');
    if (score >= 50) return t('score_average', 'متوسط');
    return t('score_poor', 'ضعيف');
  };

  // 1. Executive Summary Report Template
  const generateExecutiveReport = (printWindow: Window, action: 'pdf' | 'print') => {
    if (!stats) return;
    const isAr = i18n.language === 'ar';
    const hospitalName = settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي';
    const operatingTitle = settings.hospital.operatingTitle || 'خير من يعتني واكثر من يهتم';
    
    // Set Document Title for File Name Suggestion in Export
    const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
    const cleanReportTitle = t('report_executive_title', 'تقرير_الملخص_التنفيذي_ورضا_المرضى_الشامل').replace(/\s+/g, '_');
    const dateStr = new Date().toISOString().slice(0, 10);
    const docTitle = action === 'pdf' 
      ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
      : `${t('report_executive_title', 'تقرير الملخص التنفيذي ورضا المرضى الشامل')} - ${hospitalName}`;

    const html = `
      <!DOCTYPE html>
      <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${i18n.language}">
      <head>
        <meta charset="UTF-8">
        <title>${docTitle}</title>
        <style>
          @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
          * { box-sizing: border-box; margin: 0; padding: 0; }
          body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
            padding: 25px;
            color: #1e293b;
            background-color: #ffffff;
            line-height: 1.6;
          }
          .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #0d9488;
            padding-bottom: 20px;
            margin-bottom: 30px;
          }
          .header-right { display: flex; align-items: center; gap: 15px; }
          .logo-placeholder {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0d9488, #10b981);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
          }
          .hospital-info h1 { font-size: 18px; font-weight: 800; color: #0f172a; }
          .hospital-info p { font-size: 11px; color: #64748b; margin-top: 2px; }
          .header-left { text-align: ${isAr ? 'left' : 'right'}; }
          .report-meta { font-size: 12px; color: #64748b; }
          .report-meta strong { color: #0f172a; }
          
          .report-title-banner {
            text-align: center;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
          }
          .report-title-banner h2 { font-size: 22px; font-weight: 900; }
          .report-title-banner p { font-size: 12px; opacity: 0.8; margin-top: 5px; }

          .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
          }
          .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
          }
          .stat-card .value { font-size: 26px; font-weight: 800; color: #0d9488; }
          .stat-card .label { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 5px; }

          .section-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            border-right: 4px solid #0d9488;
            padding-right: 10px;
            margin-bottom: 15px;
          }
          .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
          }
          
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
          }
          th {
            background-color: #0d9488;
            color: white;
            font-weight: 700;
            padding: 10px;
            text-align: center;
          }
          td {
            padding: 10px;
            border: 1px solid #e2e8f0;
            text-align: center;
          }
          tr:nth-child(even) { background-color: #f8fafc; }
          
          .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
          }
          .badge-excellent { bg-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
          .badge-good { bg-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
          .badge-average { bg-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
          .badge-poor { bg-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

          .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
          }
          @media print {
            body { padding: 0; }
            .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          }
        </style>
      </head>
      <body>
        <div class="header-container">
          <div class="header-right">
            <div class="logo-placeholder">⚕️</div>
            <div class="hospital-info">
              <h1>${hospitalName}</h1>
              <p>${operatingTitle}</p>
            </div>
          </div>
          <div class="header-left">
            <div class="report-meta">
              <p><strong>${t('report_date', 'تاريخ التقرير')}:</strong> ${new Date().toLocaleDateString(isAr ? 'ar-SA' : 'en-US')}</p>
              <p><strong>${t('export_department', 'القسم المستهدف')}:</strong> ${reportDepartmentLabel}</p>
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
              else if (dept.score < 70) style = 'color: #f59e0b; font-weight: bold;';
              
              return `
                <tr>
                  <td><strong>${dept.name}</strong></td>
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
          <p>© ${new Date().getFullYear()} ${hospitalName} | ${t('confidential_report', 'تقرير سري ومحمي للاستخدام الداخلي فقط')}</p>
        </div>
      </body>
      </html>
    `;
    printWindow.document.write(html);
  };

  // 2. Departments Report Template
  const generateDepartmentsReport = (printWindow: Window, action: 'pdf' | 'print') => {
    if (!stats) return;
    const isAr = i18n.language === 'ar';
    const hospitalName = settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي';
    
    // Set Document Title for File Name Suggestion in Export
    const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
    const cleanReportTitle = t('report_departments_title', 'تقرير_تقييم_الأقسام_والمقارنات_الإدارية').replace(/\s+/g, '_');
    const dateStr = new Date().toISOString().slice(0, 10);
    const docTitle = action === 'pdf' 
      ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
      : `${t('report_departments_title', 'تقرير تقييم الأقسام والمقارنات الإدارية')} - ${hospitalName}`;

    const html = `
      <!DOCTYPE html>
      <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${i18n.language}">
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
          .bar-outer { width: 100px; height: 10px; bg-color: #e2e8f0; border-radius: 5px; overflow: hidden; background: #e2e8f0; }
          .bar-inner { height: 100%; border-radius: 5px; }
          
          .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        </style>
      </head>
      <body>
        <div class="header-container">
          <div class="hospital-info">
            <h1>${hospitalName}</h1>
            <p>${settings.hospital.operatingTitle || 'الرعاية الطبية الموثوقة'}</p>
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
              let barColor = '#10b981'; // Green
              if (dept.score < 50) barColor = '#ef4444'; // Red
              else if (dept.score < 70) barColor = '#f59e0b'; // Amber
              
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
                  <td><strong>${getSatisfactionLevel(dept.score)}</strong></td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>

        <div class="footer">
          <p>MedSurvey Pro - نظام تقارير الأقسام والتحليلات المقارنة</p>
          <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
        </div>
      </body>
      </html>
    `;
    printWindow.document.write(html);
  };

  // 3. Category/Quality Report Template
  const generateCategoriesReport = (printWindow: Window, action: 'pdf' | 'print') => {
    if (!stats) return;
    const isAr = i18n.language === 'ar';
    const hospitalName = settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي';
    
    // Set Document Title for File Name Suggestion in Export
    const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
    const cleanReportTitle = t('report_categories_title', 'تقرير_فئات_ومعايير_جودة_الخدمات_الصحية').replace(/\s+/g, '_');
    const dateStr = new Date().toISOString().slice(0, 10);
    const docTitle = action === 'pdf' 
      ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
      : `${t('report_categories_title', 'تقرير فئات ومعايير جودة الخدمات الصحية')} - ${hospitalName}`;

    const html = `
      <!DOCTYPE html>
      <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${i18n.language}">
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
          
          .bar-outer { width: 100%; height: 8px; bg-color: #e2e8f0; border-radius: 4px; overflow: hidden; background: #e2e8f0; margin-top: 5px; }
          .bar-inner { height: 100%; border-radius: 4px; }
          
          .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        </style>
      </head>
      <body>
        <div class="header-container">
          <div class="hospital-info">
            <h1>${hospitalName}</h1>
            <p>${settings.hospital.operatingTitle || 'الرعاية الطبية الموثوقة'}</p>
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
                  <td><strong>${getSatisfactionLevel(cat.score)}</strong></td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>

        <div class="footer">
          <p>MedSurvey Pro - نظام تقارير الجودة ومقاييس الأداء لخدمات الرعاية</p>
          <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${hospitalName}</p>
        </div>
      </body>
      </html>
    `;
    printWindow.document.write(html);
  };

  // 4. Action Tickets Report Template
  const generateTicketsReport = (printWindow: Window, action: 'pdf' | 'print') => {
    const isAr = i18n.language === 'ar';
    const hospitalName = settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي';
    
    // Status counts
    const total = tickets.length;
    const open = tickets.filter(t => t.status === 'open').length;
    const inProgress = tickets.filter(t => t.status === 'in_progress').length;
    const resolved = tickets.filter(t => t.status === 'resolved').length;
    
    // Set Document Title for File Name Suggestion in Export
    const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
    const cleanReportTitle = t('report_tickets_title', 'تقرير_البلاغات_وتذاكر_المتابعة_الفورية_للشكاوى').replace(/\s+/g, '_');
    const dateStr = new Date().toISOString().slice(0, 10);
    const docTitle = action === 'pdf' 
      ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
      : `${t('report_tickets_title', 'تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى')} - ${hospitalName}`;

    const html = `
      <!DOCTYPE html>
      <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${i18n.language}">
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
          
          .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        </style>
      </head>
      <body>
        <div class="header-container">
          <div class="hospital-info">
            <h1>${hospitalName}</h1>
            <p>${settings.hospital.operatingTitle || 'الرعاية الطبية الموثوقة'}</p>
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
      </body>
      </html>
    `;
    printWindow.document.write(html);
  };

  // 5. AI Predictive Warning Report Template
  const generatePredictiveReport = (printWindow: Window, action: 'pdf' | 'print') => {
    if (!stats) return;
    const isAr = i18n.language === 'ar';
    const hospitalName = settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي';
    
    // Set Document Title for File Name Suggestion in Export
    const cleanHospitalName = hospitalName.replace(/\s+/g, '_');
    const cleanReportTitle = 'تقرير_نظام_الإنذار_المبكر_ومؤشرات_التنبؤ_الذكي_AI'.replace(/\s+/g, '_');
    const dateStr = new Date().toISOString().slice(0, 10);
    const docTitle = action === 'pdf' 
      ? `${cleanReportTitle}_${cleanHospitalName}_${dateStr}`
      : `تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI) - ${hospitalName}`;

    const html = `
      <!DOCTYPE html>
      <html dir="${isAr ? 'rtl' : 'ltr'}" lang="${i18n.language}">
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
          
          .risk-alert-box {
            background: #eef2ff;
            border-right: 5px solid #4f46e5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 12px;
          }
          .risk-alert-box h4 { font-weight: 800; color: #4f46e5; margin-bottom: 5px; }
          
          table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
          th { background-color: #4f46e5; color: white; font-weight: bold; padding: 10px; }
          td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
          tr:nth-child(even) { background-color: #f8fafc; }
          
          .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        </style>
      </head>
      <body>
        <div class="header-container">
          <div class="hospital-info">
            <h1>${hospitalName}</h1>
            <p>${settings.hospital.operatingTitle || 'الرعاية الطبية الموثوقة'}</p>
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
      </body>
      </html>
    `;
    printWindow.document.write(html);
  };

  const handleExportPDF = (type: ReportType, action: 'pdf' | 'print') => {
    setExportingReport(`${type}_${action}`);
    
    // Create iframe/popup window for perfect print
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      alert('يرجى السماح بالنوافذ المنبثقة لإصدار التقرير');
      setExportingReport(null);
      return;
    }

    setTimeout(() => {
      if (type === 'executive') generateExecutiveReport(printWindow, action);
      else if (type === 'departments') generateDepartmentsReport(printWindow, action);
      else if (type === 'categories') generateCategoriesReport(printWindow, action);
      else if (type === 'tickets') generateTicketsReport(printWindow, action);
      else if (type === 'predictive') generatePredictiveReport(printWindow, action);

      printWindow.document.close();
      
      // Trigger print after styles load
      printWindow.onload = () => {
        printWindow.print();
        setExportingReport(null);
      };
      
      // Fallback if onload doesn't fire immediately
      setTimeout(() => {
        printWindow.print();
        setExportingReport(null);
      }, 500);

    }, 300);
  };

  const reportCards: { type: ReportType; title: string; desc: string; icon: typeof FileText; color: string; bgGradient: string; border: string }[] = [
    {
      type: 'executive',
      title: 'تقرير الملخص التنفيذي ورضا المرضى الشامل',
      desc: 'تحليل شامل ومفصل لمستويات رضا المستفيدين ومقاييس الأداء لجميع فئات وقطاعات الخدمة بشكل مدمج واحترافي ممتاز.',
      icon: FileText,
      color: 'text-teal-600 dark:text-teal-400',
      bgGradient: 'from-teal-500/10 to-teal-600/10 dark:from-teal-950/20 dark:to-teal-900/10 hover:from-teal-500/20 hover:to-teal-600/20',
      border: 'border-teal-100 hover:border-teal-300 dark:border-slate-800 dark:hover:border-teal-900',
    },
    {
      type: 'departments',
      title: 'تقرير أداء ومقارنة الأقسام الطبية والمستفيدين',
      desc: 'تقرير يوضح الفروقات الإحصائية بين الأقسام الطبية المختلفة لتحديد أفضل الأقسام أداءً والأقسام الأكثر تراجعاً.',
      icon: Building2,
      color: 'text-indigo-600 dark:text-indigo-400',
      bgGradient: 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20',
      border: 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900',
    },
    {
      type: 'categories',
      title: 'تقرير فئات جودة الخدمات ونقاط الاتصال المشتركة',
      desc: 'تقرير تفصيلي يوضح جودة الأداء لكل فئة خدمية بشكل مستقل (الاستقبال، الرعاية، نظافة المرافق، سرعة الصيدلية).',
      icon: TrendingUp,
      color: 'text-emerald-600 dark:text-emerald-400',
      bgGradient: 'from-emerald-500/10 to-emerald-600/10 dark:from-emerald-950/20 dark:to-emerald-900/10 hover:from-emerald-500/20 hover:to-emerald-600/20',
      border: 'border-emerald-100 hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-900',
    },
    {
      type: 'tickets',
      title: 'تقرير البلاغات الفورية وإدارة شكاوى المستفيدين',
      desc: 'تقرير شامل عن كفاءة الاستجابة السريعة للشكاوى، وحالة تذاكر المتابعة الفورية، ونسب حل المشكلات المسجلة.',
      icon: AlertCircle,
      color: 'text-red-600 dark:text-red-400',
      bgGradient: 'from-red-500/10 to-red-600/10 dark:from-red-950/20 dark:to-red-900/10 hover:from-red-500/20 hover:to-red-600/20',
      border: 'border-red-100 hover:border-red-300 dark:border-slate-800 dark:hover:border-red-900',
    },
    {
      type: 'predictive',
      title: 'تقرير نظام الإنذار المبكر وتحليلات التنبؤ الذكي',
      desc: 'تقرير استباقي مصنف بمخاطر الجودة وتنبؤات تراجع رضا المرضى للتدخل السريع بناءً على معايير الذكاء الاصطناعي.',
      icon: Brain,
      color: 'text-indigo-600 dark:text-indigo-400',
      bgGradient: 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20',
      border: 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900',
    }
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-start">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            type="button"
            className="p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-850 rounded-xl transition-all shadow-sm cursor-pointer"
          >
            <ArrowLeft className="w-5 h-5 text-gray-500 dark:text-slate-400" />
          </button>
          <div>
            <h1 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
              <span>نظام التقارير والتحليلات الفاخرة</span>
              <span className="text-xs bg-teal-100 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 font-bold px-2.5 py-1 rounded-full border border-teal-200 dark:border-teal-900/40">النسخة الاحترافية (v2.0)</span>
            </h1>
            <p className="text-xs sm:text-sm text-gray-400 dark:text-slate-400 mt-1">اصدار وطباعة التقارير الرسمية المصدقة وتصديرها بصيغة PDF بدعم لغوي كامل وتنسيق راقٍ</p>
          </div>
        </div>
      </div>

      {/* Interactive Filters Grid */}
      <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 mb-8 shadow-sm">
        <div className="flex items-center gap-2.5 text-sm font-bold text-gray-800 dark:text-white mb-4 pb-2 border-b border-gray-50 dark:border-slate-800">
          <Filter className="w-4 h-4 text-teal-600 dark:text-teal-400" />
          <span>تخصيص مدخلات التقارير قبل التصدير:</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {/* Date Filter */}
          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
              <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
              <span>النطاق الزمني للمدخلات</span>
            </label>
            <div className="grid grid-cols-3 md:grid-cols-5 gap-1.5">
              {[
                { value: 'all', label: 'الكل' },
                { value: 'week', label: 'أسبوع' },
                { value: 'month', label: 'شهر' },
                { value: 'quarter', label: 'ربع سنوي' },
                { value: 'custom', label: 'مخصص 📅' },
              ].map(opt => (
                <button
                  key={opt.value}
                  onClick={() => setDateRange(opt.value as any)}
                  type="button"
                  className={`py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer ${
                    dateRange === opt.value
                      ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900 shadow-sm'
                      : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750'
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Department Filter */}
          <div className="space-y-1.5 text-start">
            <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
              <Building2 className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
              <span>فرز وتخصيص حسب القسم الطبي</span>
            </label>
            <select
              value={restrictedDepartment || selectedDepartment}
              onChange={e => setSelectedDepartment(e.target.value)}
              disabled={!!restrictedDepartment}
              className="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm"
            >
              <option value="all">كل الأقسام الطبية المتاحة</option>
              {departments.map(d => (
                <option key={d} value={d}>{d}</option>
              ))}
            </select>
            {restrictedDepartment && (
              <p className="text-[11px] font-bold text-teal-600 dark:text-teal-400">
                يتم تقييد التقارير والطباعة تلقائيا على قسمك فقط.
              </p>
            )}
          </div>
        </div>

        {/* Custom date range fields */}
        {dateRange === 'custom' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-50 dark:border-slate-850 animate-slide-down">
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
                <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
                <span>من تاريخ (بداية النطاق)</span>
              </label>
              <input
                type="date"
                value={startDate}
                onChange={e => setStartDate(e.target.value)}
                className="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"
              />
            </div>
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
                <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
                <span>إلى تاريخ (نهاية النطاق)</span>
              </label>
              <input
                type="date"
                value={endDate}
                onChange={e => setEndDate(e.target.value)}
                className="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"
              />
            </div>
          </div>
        )}
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center py-20 gap-3">
          <Loader2 className="w-10 h-10 text-teal-600 animate-spin" />
          <p className="text-sm font-bold text-gray-500 dark:text-slate-400">جاري معالجة الإحصائيات وبناء قاعدة البيانات التفاعلية...</p>
        </div>
      ) : (
        <div className="space-y-6 text-start">
          {/* Quick Stats overview */}
          {stats && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 bg-teal-50/50 dark:bg-teal-950/10 p-4 border border-teal-100 dark:border-teal-900/30 rounded-2xl mb-4">
              <div className="text-center">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">إجمالي السجلات المفحوصة</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.totalResponses} استجابة</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">معدل الرضا العام</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.averageScore}%</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">مؤشر NPS التراكمي</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.npsScore}</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">حالة البيانات</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300 flex items-center justify-center gap-1">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 dark:text-emerald-450" />
                  <span>معالجة ومحدثة</span>
                </span>
              </div>
            </div>
          )}

          {/* Cards list */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {reportCards.map(card => {
              const Icon = card.icon;
              const isExportingPdf = exportingReport === `${card.type}_pdf`;
              const isExportingPrint = exportingReport === `${card.type}_print`;
              
              return (
                <div
                  key={card.type}
                  className={`bg-white dark:bg-slate-900 border rounded-2xl p-6 transition-all hover:shadow-lg flex flex-col justify-between ${card.border}`}
                >
                  <div className="space-y-3">
                    <div className="flex items-start justify-between">
                      <div className={`p-3 rounded-xl bg-gradient-to-br ${card.bgGradient}`}>
                        <Icon className={`w-6 h-6 ${card.color}`} />
                      </div>
                      <span className="text-[10px] bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 font-bold px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-800 flex items-center gap-1 shadow-sm">
                        <Award className="w-3.5 h-3.5 text-amber-500" />
                        <span>معتمد رسمي</span>
                      </span>
                    </div>
                    
                    <h3 className="font-black text-base text-gray-800 dark:text-white">{card.title}</h3>
                    <p className="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">{card.desc}</p>
                  </div>

                  <div className="pt-5 border-t border-gray-100 dark:border-slate-800 mt-5 flex flex-col sm:flex-row items-center gap-3">
                    {/* PDF Export Button */}
                    <button
                      onClick={() => handleExportPDF(card.type, 'pdf')}
                      disabled={isExportingPdf || isExportingPrint}
                      type="button"
                      className="w-full sm:flex-1 flex items-center justify-center gap-2 bg-gradient-to-l from-indigo-600 to-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
                    >
                      {isExportingPdf ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>جاري التصدير...</span>
                        </>
                      ) : (
                        <>
                          <FileDown className="w-4 h-4" />
                          <span>تصدير كـ PDF</span>
                        </>
                      )}
                    </button>

                    {/* Print Button */}
                    <button
                      onClick={() => handleExportPDF(card.type, 'print')}
                      disabled={isExportingPdf || isExportingPrint}
                      type="button"
                      className="w-full sm:flex-1 flex items-center justify-center gap-2 bg-gradient-to-l from-teal-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
                    >
                      {isExportingPrint ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>جاري الطباعة...</span>
                        </>
                      ) : (
                        <>
                          <Printer className="w-4 h-4" />
                          <span>طباعة فورية</span>
                        </>
                      )}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
