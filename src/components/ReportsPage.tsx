import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useSettingsStore } from '../store/useSettingsStore';
import { useDepartmentFilter } from '../hooks/useDepartmentFilter';
import { useDateFilter, DateFilterType } from '../hooks/useDateFilter';
import { auditAPI, responsesAPI, ticketsAPI } from '../api/client';
import { createLogger } from '../utils/logger';
import {
  generateExecutiveReport,
  generateDepartmentsReport,
  generateCategoriesReport,
  generateTicketsReport,
  generatePredictiveReport
} from '../utils/reportGenerators';

const logger = createLogger('ReportsPage');

import { DashboardStats, Ticket } from '../types';
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
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  
  // Interactive filters
  const { dateFilter, setDateFilter, customStartDate, setCustomStartDate, customEndDate, setCustomEndDate, apiDateStrings } = useDateFilter('all');
  const { selectedDepartment, setSelectedDepartment, restrictedDepartment, effectiveDepartment } = useDepartmentFilter('all');
  
  const [departments, setDepartments] = useState<string[]>([]);
  
  // Exporting state
  const [exportingReport, setExportingReport] = useState<string | null>(null);
  const reportDepartmentLabel = effectiveDepartment || t('export_all_departments', 'ظƒظ„ ط§ظ„ط£ظ‚ط³ط§ظ…');

  const loadData = useCallback(async () => {
    if (dateFilter === 'custom' && (!customStartDate || !customEndDate)) {
      return;
    }
    setLoading(true);
    try {
      // Load stats from backend (correct NPS calculation)
      const statsRes = await responsesAPI.getStats({
        department: effectiveDepartment === 'all' ? undefined : effectiveDepartment,
        startDate: apiDateStrings.startDate,
        endDate: apiDateStrings.endDate,
      });
      setStats(statsRes);

      // Load all departments for filter
      setDepartments(restrictedDepartment ? [restrictedDepartment] : statsRes.departmentScores.map((d: { name: string }) => d.name));

      // Load tickets
      const ticketsRes = await ticketsAPI.getAll({ department: effectiveDepartment });
      setTickets(ticketsRes);
    } catch (err) {
      logger.error('Failed to load reports data:', err);
    } finally {
      setLoading(false);
    }
  }, [dateFilter, customStartDate, customEndDate, effectiveDepartment, restrictedDepartment, apiDateStrings]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleExportPDF = (type: ReportType, action: 'pdf' | 'print') => {
    setExportingReport("${type}_${action}");
    
    if (!stats) {
      setExportingReport(null);
      return;
    }

    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
      alert('يرجى السماح بالنوافذ المنبثقة لإصدار التقرير');
      setExportingReport(null);
      return;
    }

    const ctx = {
      stats,
      tickets,
      hospitalName: settings.hospital.name || 'مستشفى الدكتور عبدالقادر المتوكل النموذجي',
      operatingTitle: settings.hospital.operatingTitle || 'خير من يعتني واكثر من يهتم',
      logo: settings.hospital.logo,
      language: i18n.language,
      t,
      reportDepartmentLabel,
    };

    if (type === 'executive') generateExecutiveReport(printWindow, action, ctx);
    else if (type === 'departments') generateDepartmentsReport(printWindow, action, ctx);
    else if (type === 'categories') generateCategoriesReport(printWindow, action, ctx);
    else if (type === 'tickets') generateTicketsReport(printWindow, action, ctx);
    else if (type === 'predictive') generatePredictiveReport(printWindow, action, ctx);

    printWindow.document.close();

    // Fire-and-forget audit (don't block the print flow)
    auditAPI.recordEvent({
      action: action === 'print' ? 'print_report' : 'export_report',
      messageKey: action === 'print' ? 'audit.details.print_report' : 'audit.details.export_report',
      params: {
        reportType: type,
        department: effectiveDepartment || 'all',
        dateRange: dateFilter,
      },
    }).catch(() => {});

    // Call print immediately (still user-initiated)
    requestAnimationFrame(() => {
      printWindow.print();
      setExportingReport(null);
    });
  };

  const reportCards: { type: ReportType; title: string; desc: string; icon: typeof FileText; color: string; bgGradient: string; border: string }[] = [
    {
      type: 'executive',
      title: 'طھظ‚ط±ظٹط± ط§ظ„ظ…ظ„ط®طµ ط§ظ„طھظ†ظپظٹط°ظٹ ظˆط±ط¶ط§ ط§ظ„ظ…ط±ط¶ظ‰ ط§ظ„ط´ط§ظ…ظ„',
      desc: 'طھط­ظ„ظٹظ„ ط´ط§ظ…ظ„ ظˆظ…ظپطµظ„ ظ„ظ…ط³طھظˆظٹط§طھ ط±ط¶ط§ ط§ظ„ظ…ط³طھظپظٹط¯ظٹظ† ظˆظ…ظ‚ط§ظٹظٹط³ ط§ظ„ط£ط¯ط§ط، ظ„ط¬ظ…ظٹط¹ ظپط¦ط§طھ ظˆظ‚ط·ط§ط¹ط§طھ ط§ظ„ط®ط¯ظ…ط© ط¨ط´ظƒظ„ ظ…ط¯ظ…ط¬ ظˆط§ط­طھط±ط§ظپظٹ ظ…ظ…طھط§ط².',
      icon: FileText,
      color: 'text-teal-600 dark:text-teal-400',
      bgGradient: 'from-teal-500/10 to-teal-600/10 dark:from-teal-950/20 dark:to-teal-900/10 hover:from-teal-500/20 hover:to-teal-600/20',
      border: 'border-teal-100 hover:border-teal-300 dark:border-slate-800 dark:hover:border-teal-900',
    },
    {
      type: 'departments',
      title: 'طھظ‚ط±ظٹط± ط£ط¯ط§ط، ظˆظ…ظ‚ط§ط±ظ†ط© ط§ظ„ط£ظ‚ط³ط§ظ… ط§ظ„ط·ط¨ظٹط© ظˆط§ظ„ظ…ط³طھظپظٹط¯ظٹظ†',
      desc: 'طھظ‚ط±ظٹط± ظٹظˆط¶ط­ ط§ظ„ظپط±ظˆظ‚ط§طھ ط§ظ„ط¥ط­طµط§ط¦ظٹط© ط¨ظٹظ† ط§ظ„ط£ظ‚ط³ط§ظ… ط§ظ„ط·ط¨ظٹط© ط§ظ„ظ…ط®طھظ„ظپط© ظ„طھط­ط¯ظٹط¯ ط£ظپط¶ظ„ ط§ظ„ط£ظ‚ط³ط§ظ… ط£ط¯ط§ط،ظ‹ ظˆط§ظ„ط£ظ‚ط³ط§ظ… ط§ظ„ط£ظƒط«ط± طھط±ط§ط¬ط¹ط§ظ‹.',
      icon: Building2,
      color: 'text-indigo-600 dark:text-indigo-400',
      bgGradient: 'from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20',
      border: 'border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900',
    },
    {
      type: 'categories',
      title: 'طھظ‚ط±ظٹط± ظپط¦ط§طھ ط¬ظˆط¯ط© ط§ظ„ط®ط¯ظ…ط§طھ ظˆظ†ظ‚ط§ط· ط§ظ„ط§طھطµط§ظ„ ط§ظ„ظ…ط´طھط±ظƒط©',
      desc: 'طھظ‚ط±ظٹط± طھظپطµظٹظ„ظٹ ظٹظˆط¶ط­ ط¬ظˆط¯ط© ط§ظ„ط£ط¯ط§ط، ظ„ظƒظ„ ظپط¦ط© ط®ط¯ظ…ظٹط© ط¨ط´ظƒظ„ ظ…ط³طھظ‚ظ„ (ط§ظ„ط§ط³طھظ‚ط¨ط§ظ„طŒ ط§ظ„ط±ط¹ط§ظٹط©طŒ ظ†ط¸ط§ظپط© ط§ظ„ظ…ط±ط§ظپظ‚طŒ ط³ط±ط¹ط© ط§ظ„طµظٹط¯ظ„ظٹط©).',
      icon: TrendingUp,
      color: 'text-emerald-600 dark:text-emerald-400',
      bgGradient: 'from-emerald-500/10 to-emerald-600/10 dark:from-emerald-950/20 dark:to-emerald-900/10 hover:from-emerald-500/20 hover:to-emerald-600/20',
      border: 'border-emerald-100 hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-900',
    },
    {
      type: 'tickets',
      title: 'طھظ‚ط±ظٹط± ط§ظ„ط¨ظ„ط§ط؛ط§طھ ط§ظ„ظپظˆط±ظٹط© ظˆط¥ط¯ط§ط±ط© ط´ظƒط§ظˆظ‰ ط§ظ„ظ…ط³طھظپظٹط¯ظٹظ†',
      desc: 'طھظ‚ط±ظٹط± ط´ط§ظ…ظ„ ط¹ظ† ظƒظپط§ط،ط© ط§ظ„ط§ط³طھط¬ط§ط¨ط© ط§ظ„ط³ط±ظٹط¹ط© ظ„ظ„ط´ظƒط§ظˆظ‰طŒ ظˆط­ط§ظ„ط© طھط°ط§ظƒط± ط§ظ„ظ…طھط§ط¨ط¹ط© ط§ظ„ظپظˆط±ظٹط©طŒ ظˆظ†ط³ط¨ ط­ظ„ ط§ظ„ظ…ط´ظƒظ„ط§طھ ط§ظ„ظ…ط³ط¬ظ„ط©.',
      icon: AlertCircle,
      color: 'text-red-600 dark:text-red-400',
      bgGradient: 'from-red-500/10 to-red-600/10 dark:from-red-950/20 dark:to-red-900/10 hover:from-red-500/20 hover:to-red-600/20',
      border: 'border-red-100 hover:border-red-300 dark:border-slate-800 dark:hover:border-red-900',
    },
    {
      type: 'predictive',
      title: 'طھظ‚ط±ظٹط± ظ†ط¸ط§ظ… ط§ظ„ط¥ظ†ط°ط§ط± ط§ظ„ظ…ط¨ظƒط± ظˆطھط­ظ„ظٹظ„ط§طھ ط§ظ„طھظ†ط¨ط¤ ط§ظ„ط°ظƒظٹ',
      desc: 'طھظ‚ط±ظٹط± ط§ط³طھط¨ط§ظ‚ظٹ ظ…طµظ†ظپ ط¨ظ…ط®ط§ط·ط± ط§ظ„ط¬ظˆط¯ط© ظˆطھظ†ط¨ط¤ط§طھ طھط±ط§ط¬ط¹ ط±ط¶ط§ ط§ظ„ظ…ط±ط¶ظ‰ ظ„ظ„طھط¯ط®ظ„ ط§ظ„ط³ط±ظٹط¹ ط¨ظ†ط§ط،ظ‹ ط¹ظ„ظ‰ ظ…ط¹ط§ظٹظٹط± ط§ظ„ط°ظƒط§ط، ط§ظ„ط§طµط·ظ†ط§ط¹ظٹ.',
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
              <span>ظ†ط¸ط§ظ… ط§ظ„طھظ‚ط§ط±ظٹط± ظˆط§ظ„طھط­ظ„ظٹظ„ط§طھ ط§ظ„ظپط§ط®ط±ط©</span>
              <span className="text-xs bg-teal-100 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 font-bold px-2.5 py-1 rounded-full border border-teal-200 dark:border-teal-900/40">ط§ظ„ظ†ط³ط®ط© ط§ظ„ط§ط­طھط±ط§ظپظٹط© (v2.0)</span>
            </h1>
            <p className="text-xs sm:text-sm text-gray-400 dark:text-slate-400 mt-1">ط§طµط¯ط§ط± ظˆط·ط¨ط§ط¹ط© ط§ظ„طھظ‚ط§ط±ظٹط± ط§ظ„ط±ط³ظ…ظٹط© ط§ظ„ظ…طµط¯ظ‚ط© ظˆطھطµط¯ظٹط±ظ‡ط§ ط¨طµظٹط؛ط© PDF ط¨ط¯ط¹ظ… ظ„ط؛ظˆظٹ ظƒط§ظ…ظ„ ظˆطھظ†ط³ظٹظ‚ ط±ط§ظ‚ظچ</p>
          </div>
        </div>
      </div>

      {/* Interactive Filters Grid */}
      <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 mb-8 shadow-sm">
        <div className="flex items-center gap-2.5 text-sm font-bold text-gray-800 dark:text-white mb-4 pb-2 border-b border-gray-50 dark:border-slate-800">
          <Filter className="w-4 h-4 text-teal-600 dark:text-teal-400" />
          <span>طھط®طµظٹطµ ظ…ط¯ط®ظ„ط§طھ ط§ظ„طھظ‚ط§ط±ظٹط± ظ‚ط¨ظ„ ط§ظ„طھطµط¯ظٹط±:</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {/* Date Filter */}
          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
              <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
              <span>ط§ظ„ظ†ط·ط§ظ‚ ط§ظ„ط²ظ…ظ†ظٹ ظ„ظ„ظ…ط¯ط®ظ„ط§طھ</span>
            </label>
            <div className="grid grid-cols-3 md:grid-cols-5 gap-1.5">
              {[
                { value: 'all', label: 'ط§ظ„ظƒظ„' },
                { value: 'week', label: 'ط£ط³ط¨ظˆط¹' },
                { value: 'month', label: 'ط´ظ‡ط±' },
                { value: 'quarter', label: 'ط±ط¨ط¹ ط³ظ†ظˆظٹ' },
                { value: 'custom', label: 'ظ…ط®طµطµ ًں“…' },
              ].map(opt => (
                <button
                  key={opt.value}
                  onClick={() => setDateFilter(opt.value as DateFilterType)}
                  type="button"
                  className={`py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer ${
                    dateFilter === opt.value
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
              <span>ظپط±ط² ظˆطھط®طµظٹطµ ط­ط³ط¨ ط§ظ„ظ‚ط³ظ… ط§ظ„ط·ط¨ظٹ</span>
            </label>
            <select
              value={restrictedDepartment || selectedDepartment}
              onChange={e => setSelectedDepartment(e.target.value)}
              disabled={!!restrictedDepartment}
              className="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm"
            >
              <option value="all">ظƒظ„ ط§ظ„ط£ظ‚ط³ط§ظ… ط§ظ„ط·ط¨ظٹط© ط§ظ„ظ…طھط§ط­ط©</option>
              {departments.map(d => (
                <option key={d} value={d}>{d}</option>
              ))}
            </select>
            {restrictedDepartment && (
              <p className="text-[11px] font-bold text-teal-600 dark:text-teal-400">
                ظٹطھظ… طھظ‚ظٹظٹط¯ ط§ظ„طھظ‚ط§ط±ظٹط± ظˆط§ظ„ط·ط¨ط§ط¹ط© طھظ„ظ‚ط§ط¦ظٹط§ ط¹ظ„ظ‰ ظ‚ط³ظ…ظƒ ظپظ‚ط·.
              </p>
            )}
          </div>
        </div>

        {/* Custom date range fields */}
        {dateFilter === 'custom' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-50 dark:border-slate-850 animate-slide-down">
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
                <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
                <span>ظ…ظ† طھط§ط±ظٹط® (ط¨ط¯ط§ظٹط© ط§ظ„ظ†ط·ط§ظ‚)</span>
              </label>
              <input
                type="date"
                value={customStartDate}
                onChange={e => setCustomStartDate(e.target.value)}
                className="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"
              />
            </div>
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400">
                <Calendar className="w-3.5 h-3.5 text-teal-600 dark:text-teal-450" />
                <span>ط¥ظ„ظ‰ طھط§ط±ظٹط® (ظ†ظ‡ط§ظٹط© ط§ظ„ظ†ط·ط§ظ‚)</span>
              </label>
              <input
                type="date"
                value={customEndDate}
                onChange={e => setCustomEndDate(e.target.value)}
                className="w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"
              />
            </div>
          </div>
        )}
      </div>

      {loading ? (
        <div className="flex flex-col items-center justify-center py-20 gap-3">
          <Loader2 className="w-10 h-10 text-teal-600 animate-spin" />
          <p className="text-sm font-bold text-gray-500 dark:text-slate-400">ط¬ط§ط±ظٹ ظ…ط¹ط§ظ„ط¬ط© ط§ظ„ط¥ط­طµط§ط¦ظٹط§طھ ظˆط¨ظ†ط§ط، ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„طھظپط§ط¹ظ„ظٹط©...</p>
        </div>
      ) : (
        <div className="space-y-6 text-start">
          {/* Quick Stats overview */}
          {stats && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 bg-teal-50/50 dark:bg-teal-950/10 p-4 border border-teal-100 dark:border-teal-900/30 rounded-2xl mb-4">
              <div className="text-center">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">ط¥ط¬ظ…ط§ظ„ظٹ ط§ظ„ط³ط¬ظ„ط§طھ ط§ظ„ظ…ظپط­ظˆطµط©</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.totalResponses} ط§ط³طھط¬ط§ط¨ط©</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">ظ…ط¹ط¯ظ„ ط§ظ„ط±ط¶ط§ ط§ظ„ط¹ط§ظ…</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.averageScore}%</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">ظ…ط¤ط´ط± NPS ط§ظ„طھط±ط§ظƒظ…ظٹ</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300">{stats.npsScore}</span>
              </div>
              <div className="text-center border-r border-teal-100 dark:border-teal-900/30">
                <span className="block text-[10px] text-teal-600 dark:text-teal-400 font-bold">ط­ط§ظ„ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ</span>
                <span className="text-lg font-black text-teal-800 dark:text-teal-300 flex items-center justify-center gap-1">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 dark:text-emerald-450" />
                  <span>ظ…ط¹ط§ظ„ط¬ط© ظˆظ…ط­ط¯ط«ط©</span>
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
                      <div className={`p-3 rounded-xl bg-linear-to-br ${card.bgGradient}`}>
                        <Icon className={`w-6 h-6 ${card.color}`} />
                      </div>
                      <span className="text-[10px] bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 font-bold px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-800 flex items-center gap-1 shadow-sm">
                        <Award className="w-3.5 h-3.5 text-amber-500" />
                        <span>ظ…ط¹طھظ…ط¯ ط±ط³ظ…ظٹ</span>
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
                      className="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-indigo-600 to-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
                    >
                      {isExportingPdf ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>ط¬ط§ط±ظٹ ط§ظ„طھطµط¯ظٹط±...</span>
                        </>
                      ) : (
                        <>
                          <FileDown className="w-4 h-4" />
                          <span>طھطµط¯ظٹط± ظƒظ€ PDF</span>
                        </>
                      )}
                    </button>

                    {/* Print Button */}
                    <button
                      onClick={() => handleExportPDF(card.type, 'print')}
                      disabled={isExportingPdf || isExportingPrint}
                      type="button"
                      className="w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-teal-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer"
                    >
                      {isExportingPrint ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>ط¬ط§ط±ظٹ ط§ظ„ط·ط¨ط§ط¹ط©...</span>
                        </>
                      ) : (
                        <>
                          <Printer className="w-4 h-4" />
                          <span>ط·ط¨ط§ط¹ط© ظپظˆط±ظٹط©</span>
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
