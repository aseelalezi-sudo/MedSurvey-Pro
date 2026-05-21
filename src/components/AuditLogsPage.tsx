import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { auditAPI, AuditFilters, AuditStats } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('AuditLogsPage');

import { AuditLog } from '../store/useAuthStore';
import { useThemeStore } from '../store/useThemeStore';
import {
  Shield,
  Search,
  Calendar,
  SlidersHorizontal,
  RefreshCw,
  User,
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
  AlertTriangle,
  Activity,
  UserCheck,
} from 'lucide-react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  BarChart,
  Bar,
  Cell,
} from 'recharts';
import SafeResponsiveContainer from './SafeResponsiveContainer';

const ACTION_MAP: Record<string, { label: string; color: string; bg: string }> = {
  login: { label: 'تسجيل دخول ناجح', color: 'text-green-700 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-950/25 border-green-100 dark:border-green-900/30' },
  login_failed: { label: 'محاولة دخول فاشلة', color: 'text-red-700 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30 animate-pulse' },
  logout: { label: 'تسجيل خروج', color: 'text-gray-600 dark:text-slate-400', bg: 'bg-gray-50 dark:bg-slate-800/40 border-gray-100 dark:border-slate-700/50' },
  create_user: { label: 'إنشاء مستخدم جديد', color: 'text-purple-700 dark:text-purple-400', bg: 'bg-purple-50 dark:bg-purple-950/25 border-purple-100 dark:border-purple-900/30' },
  update_user: { label: 'تحديث بيانات مستخدم', color: 'text-blue-700 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/25 border-blue-100 dark:border-blue-900/30' },
  change_user_password: { label: 'تغيير كلمة مرور مستخدم', color: 'text-indigo-700 dark:text-indigo-400', bg: 'bg-indigo-50 dark:bg-indigo-950/25 border-indigo-100 dark:border-indigo-900/30' },
  delete_user: { label: 'حذف مستخدم', color: 'text-rose-700 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/25 border-rose-100 dark:border-rose-900/30' },
  activate_user: { label: 'تفعيل مستخدم', color: 'text-emerald-700 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/25 border-emerald-100 dark:border-emerald-900/30' },
  deactivate_user: { label: 'تعطيل مستخدم', color: 'text-slate-700 dark:text-slate-300', bg: 'bg-slate-50 dark:bg-slate-800/40 border-slate-100 dark:border-slate-700/50' },
  create_survey: { label: 'إنشاء استبيان', color: 'text-teal-700 dark:text-teal-400', bg: 'bg-teal-50 dark:bg-teal-950/25 border-teal-100 dark:border-teal-900/30' },
  update_survey: { label: 'تعديل استبيان', color: 'text-sky-700 dark:text-sky-400', bg: 'bg-sky-50 dark:bg-sky-950/25 border-sky-100 dark:border-sky-900/30' },
  delete_survey: { label: 'حذف استبيان', color: 'text-red-700 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30' },
  update_settings: { label: 'تعديل الإعدادات العامة', color: 'text-orange-700 dark:text-orange-400', bg: 'bg-orange-50 dark:bg-orange-950/25 border-orange-100 dark:border-orange-900/30' },
  update_ticket: { label: 'تحديث حالة بلاغ', color: 'text-amber-700 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/25 border-amber-100 dark:border-amber-900/30' },
  delete_response: { label: 'حذف استبيان مريض', color: 'text-red-700 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-950/25 border-red-100 dark:border-red-900/30' },
  export_responses: { label: 'تصدير الاستجابات', color: 'text-cyan-700 dark:text-cyan-400', bg: 'bg-cyan-50 dark:bg-cyan-950/25 border-cyan-100 dark:border-cyan-900/30' },
  export_report: { label: 'تصدير تقرير', color: 'text-indigo-700 dark:text-indigo-400', bg: 'bg-indigo-50 dark:bg-indigo-950/25 border-indigo-100 dark:border-indigo-900/30' },
  print_report: { label: 'طباعة تقرير', color: 'text-fuchsia-700 dark:text-fuchsia-400', bg: 'bg-fuchsia-50 dark:bg-fuchsia-950/25 border-fuchsia-100 dark:border-fuchsia-900/30' },
};

const ROLE_MAP: Record<string, { label: string; color: string }> = {
  super_admin: { label: 'مدير عام للنظام', color: 'text-purple-700 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/25 border-purple-200 dark:border-purple-900/30' },
  admin: { label: 'مدير استبيانات', color: 'text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/25 border-blue-200 dark:border-blue-900/30' },
  head_of_department: { label: 'رئيس قسم', color: 'text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/25 border-indigo-200 dark:border-indigo-900/30' },
  staff: { label: 'موظف', color: 'text-teal-700 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/25 border-teal-200 dark:border-teal-900/30' },
};

const AUDIT_PARAM_LABELS: Record<string, Record<string, string>> = {
  status: {
    open: 'مفتوحة',
    in_progress: 'قيد المعالجة',
    resolved: 'تم الحل',
    unchanged: 'لم تتغير',
  },
  format: {
    pdf: 'PDF',
    excel: 'Excel',
    print: 'طباعة',
  },
  dateRange: {
    all: 'كل الفترات',
    week: 'آخر أسبوع',
    month: 'آخر شهر',
    quarter: 'آخر 3 أشهر',
    custom: 'فترة مخصصة',
  },
  reportType: {
    executive: 'الملخص التنفيذي',
    departments: 'الأقسام',
    categories: 'الفئات',
    tickets: 'البلاغات',
    predictive: 'التنبؤات',
  },
  department: {
    all: 'كل الأقسام',
  },
};

export default function AuditLogsPage() {
  const navigate = useNavigate();
  const onBack = () => navigate('/dashboard');
  const { t, i18n } = useTranslation();
  const { theme } = useThemeStore();
  const isDark = theme === 'dark';

  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [stats, setStats] = useState<AuditStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [showFilters, setShowFilters] = useState(false);

  // Pagination & Filter States
  const [page, setPage] = useState(1);
  const [limit] = useState(15);
  const [totalPages, setTotalPages] = useState(1);
  const [totalLogs, setTotalLogs] = useState(0);

  const [search, setSearch] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  // Loaded Unique Actions for filtering
  const [availableActions, setAvailableActions] = useState<string[]>([]);

  // Load logs
  const loadLogs = useCallback(async () => {
    setLoading(true);
    try {
      const filters: AuditFilters = {
        page,
        limit,
        search: search.trim() || undefined,
        action: actionFilter || undefined,
        startDate: startDate || undefined,
        endDate: endDate || undefined,
      };

      const res = await auditAPI.getAll(filters);
      setLogs(res.data);
      setTotalLogs(res.pagination.total);
      setTotalPages(res.pagination.totalPages);
    } catch (error) {
      logger.error('Failed to load audit logs:', error);
    } finally {
      setLoading(false);
    }
  }, [page, limit, search, actionFilter, startDate, endDate]);

  // Load stats
  const loadStats = async () => {
    try {
      const statsData = await auditAPI.getStats(30); // Get stats for the last 30 days
      setStats(statsData);

      // Collect all actions from stats for filter
      if (statsData.actionStats) {
        const actions = statsData.actionStats.map(s => s.action);
        setAvailableActions(Array.from(new Set(actions)));
      }
    } catch (error) {
      logger.error('Failed to load audit stats:', error);
    }
  };

  useEffect(() => {
    loadStats();
  }, []);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadLogs();
  };

  const handleResetFilters = () => {
    setSearch('');
    setActionFilter('');
    setStartDate('');
    setEndDate('');
    setPage(1);
    // Reload logs directly
    auditAPI.getAll({ page: 1, limit }).then((res) => {
      setLogs(res.data);
      setTotalLogs(res.pagination.total);
      setTotalPages(res.pagination.totalPages);
    });
  };

  const formatAuditParam = (key: string, value: unknown) => {
    if (typeof value !== 'string') return value;
    if (key === 'id' && /^[a-z0-9]{16,}$/i.test(value)) {
      return `#${value.slice(-8).toUpperCase()}`;
    }
    return AUDIT_PARAM_LABELS[key]?.[value] || value;
  };

  const translateDetails = (details: string) => {
    try {
      const parsed = JSON.parse(details) as { messageKey?: string; params?: Record<string, unknown> };
      if (parsed.messageKey) {
        const rawParams = parsed.params || {};
        const params = Object.fromEntries(
          Object.entries(rawParams).map(([key, value]) => [key, formatAuditParam(key, value)])
        );
        if (parsed.messageKey === 'audit.details.update_ticket' && !params.ticketCode && rawParams.id) {
          params.ticketCode = formatAuditParam('id', rawParams.id);
        }
        return t(parsed.messageKey, params);
      }
    } catch {
      // Older audit rows stored plain text. Keep them readable instead of hiding history.
    }
    return details
      .replace(/\b[a-z0-9]{20,}\b/gi, (value) => `#${value.slice(-8).toUpperCase()}`)
      .replace(/\bin_progress\b/g, AUDIT_PARAM_LABELS.status.in_progress)
      .replace(/\bresolved\b/g, AUDIT_PARAM_LABELS.status.resolved)
      .replace(/\bopen\b/g, AUDIT_PARAM_LABELS.status.open)
      .replace(/\bunchanged\b/g, AUDIT_PARAM_LABELS.status.unchanged);
  };

  // Stats computation
  const mostActiveUser = stats?.topUsers && stats.topUsers.length > 0 
    ? `${stats.topUsers[0].name} (${stats.topUsers[0].count} عملية)` 
    : 'لا يوجد';

  const mostCommonAction = stats?.actionStats && stats.actionStats.length > 0
    ? (() => {
        const sorted = [...stats.actionStats].sort((a, b) => b.count - a.count);
        const actionKey = sorted[0].action;
        return `${ACTION_MAP[actionKey]?.label || actionKey} (${sorted[0].count} مرة)`;
      })()
    : 'لا يوجد';

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in text-start" dir="rtl">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            type="button"
            className="w-10 h-10 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-450 hover:border-teal-200 dark:hover:border-teal-900 hover:shadow-md transition-all cursor-pointer"
          >
            <ArrowLeft className="w-5 h-5 rtl:rotate-180" />
          </button>
          <div>
            <div className="flex items-center gap-2">
              <span className="p-1.5 bg-orange-100 dark:bg-orange-950/25 rounded-lg text-orange-600 dark:text-orange-400">
                <Shield className="w-5 h-5" />
              </span>
              <h2 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">سجل العمليات والأمان (Security Audit)</h2>
            </div>
            <p className="text-xs text-gray-400 dark:text-slate-450 mt-1">تتبع نشاط المستخدمين، التعديلات الحساسة على النظام، ورصد محاولات تسجيل الدخول المشبوهة.</p>
          </div>
        </div>

        <div className="flex items-center gap-2 self-stretch sm:self-auto">
          <button
            onClick={() => {
              loadLogs();
              loadStats();
            }}
            type="button"
            className="flex-1 sm:flex-none flex items-center justify-center gap-2 text-xs bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300 px-4 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-800 transition-all font-bold cursor-pointer"
          >
            <RefreshCw className="w-4 h-4 text-gray-400 dark:text-slate-500" />
            <span>تحديث السجلات</span>
          </button>
        </div>
      </div>

      {/* Stats Cards Section */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div className="w-12 h-12 bg-teal-50 dark:bg-teal-950/20 border border-teal-100 dark:border-teal-900/30 rounded-xl flex items-center justify-center text-teal-600 dark:text-teal-400 shrink-0 shadow-sm">
            <Activity className="w-6 h-6" />
          </div>
          <div>
            <div className="text-2xl font-black text-gray-900 dark:text-white">{totalLogs}</div>
            <p className="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-0.5">مجموع العمليات المرصودة</p>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div className="w-12 h-12 bg-purple-50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/30 rounded-xl flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0 shadow-sm">
            <UserCheck className="w-6 h-6" />
          </div>
          <div className="min-w-0">
            <div className="text-sm font-black text-gray-900 dark:text-white truncate">{mostActiveUser}</div>
            <p className="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-1">المستخدم الأكثر تفاعلاً</p>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div className="w-12 h-12 bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0 shadow-sm">
            <ShieldCheck className="w-6 h-6" />
          </div>
          <div className="min-w-0">
            <div className="text-sm font-black text-gray-900 dark:text-white truncate">{mostCommonAction}</div>
            <p className="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-1">العملية الأكثر شيوعاً</p>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-5 flex items-center gap-4 shadow-sm">
          <div className="w-12 h-12 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 rounded-xl flex items-center justify-center text-red-600 dark:text-red-450 shrink-0 shadow-sm">
            <AlertTriangle className="w-6 h-6" />
          </div>
          <div>
            <div className="text-2xl font-black text-red-700 dark:text-red-400">
              {stats?.actionStats?.find(s => s.action === 'login_failed')?.count || 0}
            </div>
            <p className="text-[10px] text-gray-400 dark:text-slate-500 font-extrabold uppercase mt-0.5">محاولات دخول فاشلة</p>
          </div>
        </div>
      </div>

      {/* Graphical Dashboard & Analysis Row */}
      {stats && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          {/* Trend Chart (Volume over time) */}
          <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm p-6 lg:col-span-2">
            <div className="flex items-center gap-2 mb-6 text-start">
              <Activity className="w-5 h-5 text-teal-600 dark:text-teal-400" />
              <h3 className="font-bold text-gray-800 dark:text-white">مخطط حجم عمليات النظام والنشاط اليومي (آخر 30 يوم)</h3>
            </div>
            <SafeResponsiveContainer width="100%" height={250}>
              <AreaChart data={stats.trendData}>
                <defs>
                  <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#0d9488" stopOpacity={0.2} />
                    <stop offset="95%" stopColor="#0d9488" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={isDark ? '#1e293b' : '#f3f4f6'} />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: isDark ? '#94a3b8' : '#6b7280' }} stroke={isDark ? '#334155' : '#e2e8f0'} />
                <YAxis tick={{ fontSize: 11, fill: isDark ? '#94a3b8' : '#6b7280' }} stroke={isDark ? '#334155' : '#e2e8f0'} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    borderRadius: '12px',
                    border: isDark ? '1px solid #334155' : 'none',
                    boxShadow: '0 4px 20px rgba(0,0,0,0.1)',
                    fontFamily: 'Cairo',
                    direction: 'rtl',
                    color: isDark ? '#ffffff' : '#1e293b',
                  }}
                  itemStyle={{ color: isDark ? '#38bdf8' : '#0d9488' }}
                  formatter={(value) => [`${value} عملية`, 'نشاط العمليات']}
                />
                <Area
                  type="monotone"
                  dataKey="count"
                  stroke="#0d9488"
                  strokeWidth={3}
                  fillOpacity={1}
                  fill="url(#colorCount)"
                />
              </AreaChart>
            </SafeResponsiveContainer>
          </div>

          {/* Action Types Distribution */}
          <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm p-6 text-start">
            <div className="flex items-center gap-2 mb-6">
              <SlidersHorizontal className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <h3 className="font-bold text-gray-800 dark:text-white">توزيع العمليات المنجزة</h3>
            </div>
            <SafeResponsiveContainer width="100%" height={220}>
              <BarChart data={stats.actionStats.slice(0, 5)}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={isDark ? '#1e293b' : '#f3f4f6'} />
                <XAxis 
                  dataKey="action" 
                  tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#6b7280' }} 
                  tickFormatter={(value: string) => ACTION_MAP[value]?.label || value}
                  stroke={isDark ? '#334155' : '#e2e8f0'}
                />
                <YAxis tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#6b7280' }} stroke={isDark ? '#334155' : '#e2e8f0'} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    borderRadius: '12px',
                    border: isDark ? '1px solid #334155' : 'none',
                    boxShadow: '0 4px 20px rgba(0,0,0,0.1)',
                    fontFamily: 'Cairo',
                    direction: 'rtl',
                    color: isDark ? '#ffffff' : '#1e293b',
                  }}
                  itemStyle={{ color: isDark ? '#818cf8' : '#4f46e5' }}
                  formatter={(value) => [`${value} تكرار`, 'حجم التكرار']}
                />
                <Bar dataKey="count" fill="#4f46e5" radius={[4, 4, 0, 0]}>
                  {stats.actionStats.map((_, index) => {
                    const colors = ['#0d9488', '#6366f1', '#e11d48', '#d97706', '#9333ea'];
                    return <Cell key={`cell-${index}`} fill={colors[index % colors.length]} />;
                  })}
                </Bar>
              </BarChart>
            </SafeResponsiveContainer>
          </div>
        </div>
      )}

      {/* Filter and Table Card */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden">
        {/* Filters Top Bar */}
        <div className="p-5 border-b border-gray-100 dark:border-slate-800/80 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-gray-50/50 dark:bg-slate-850/20">
          <form onSubmit={handleSearchSubmit} className="flex-1 flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="البحث بالاسم، اسم المستخدم، أو تفاصيل العملية..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-10 pl-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all text-start placeholder-gray-400 dark:placeholder-gray-550"
              />
            </div>
            <button
              type="submit"
              className="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded-xl text-sm font-bold transition-all shadow-sm cursor-pointer"
            >
              بحث
            </button>
          </form>

          <div className="flex items-center gap-2 flex-wrap">
            <button
              onClick={() => setShowFilters(!showFilters)}
              type="button"
              className={`flex items-center gap-2 text-sm px-4 py-2 rounded-xl border font-bold transition-all cursor-pointer ${
                showFilters || actionFilter || startDate || endDate
                  ? 'border-teal-200 dark:border-teal-900/30 bg-teal-50 dark:bg-teal-950/25 text-teal-700 dark:text-teal-400'
                  : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-900 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-850'
              }`}
            >
              <SlidersHorizontal className="w-4 h-4" />
              <span>تصفية متقدمة</span>
            </button>

            {(search || actionFilter || startDate || endDate) && (
              <button
                onClick={handleResetFilters}
                type="button"
                className="text-xs text-gray-500 dark:text-slate-400 hover:text-red-600 px-2 py-1 transition-all cursor-pointer"
              >
                إعادة ضبط
              </button>
            )}
          </div>
        </div>

        {/* Advanced Filters Panel */}
        {showFilters && (
          <div className="p-5 border-b border-gray-100 dark:border-slate-800 bg-gray-50/30 dark:bg-slate-900/20 grid grid-cols-1 md:grid-cols-3 gap-4 animate-slide-down">
            {/* Action Filter */}
            <div>
              <label className="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">نوع العملية</label>
              <select
                value={actionFilter}
                onChange={(e) => {
                  setActionFilter(e.target.value);
                  setPage(1);
                }}
                className="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
              >
                <option value="">جميع العمليات</option>
                {availableActions.map(act => (
                  <option key={act} value={act}>
                    {ACTION_MAP[act]?.label || act}
                  </option>
                ))}
              </select>
            </div>

            {/* Start Date */}
            <div>
              <label className="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">من تاريخ</label>
              <div className="relative">
                <Calendar className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                <input
                  type="date"
                  value={startDate}
                  onChange={(e) => {
                    setStartDate(e.target.value);
                    setPage(1);
                  }}
                  className="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-9 pl-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
                />
              </div>
            </div>

            {/* End Date */}
            <div>
              <label className="block text-xs font-bold text-gray-500 dark:text-slate-400 mb-2">إلى تاريخ</label>
              <div className="relative">
                <Calendar className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                <input
                  type="date"
                  value={endDate}
                  onChange={(e) => {
                    setEndDate(e.target.value);
                    setPage(1);
                  }}
                  className="w-full bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white rounded-xl pr-9 pl-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all cursor-pointer"
                />
              </div>
            </div>
          </div>
        )}

        {/* Audit Logs Table */}
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-100 dark:border-slate-800 bg-gray-50/20 dark:bg-slate-850/40">
                <th className="text-right py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">المسؤول عن العملية</th>
                <th className="text-right py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">نوع الإجراء</th>
                <th className="text-right py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">التفاصيل والوصف</th>
                <th className="text-right py-3.5 px-5 text-xs font-extrabold text-gray-400 dark:text-slate-450 uppercase tracking-wider">التاريخ والوقت</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={4} className="py-20 text-center">
                    <div className="flex flex-col items-center justify-center gap-3">
                      <RefreshCw className="w-8 h-8 text-teal-600 animate-spin" />
                      <span className="text-sm font-medium text-gray-400 dark:text-slate-500">جاري تحميل سجلات الأمان والتدقيق...</span>
                    </div>
                  </td>
                </tr>
              ) : logs.length > 0 ? (
                logs.map((log) => {
                  const actionData = ACTION_MAP[log.action] || { label: log.action, color: 'text-gray-700', bg: 'bg-gray-50 border-gray-100' };
                  const roleData = log.user?.role ? ROLE_MAP[log.user.role] : null;

                  return (
                    <tr key={log.id} className="border-b border-gray-50 dark:border-slate-800/80 hover:bg-gray-50/50 dark:hover:bg-slate-850/40 transition-colors">
                      {/* User Column */}
                      <td className="py-3.5 px-5 text-sm">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-linear-to- from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">
                            {log.user ? log.user.name.charAt(0) : <User className="w-4 h-4" />}
                          </div>
                          <div>
                            <div className="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                              <span>{log.user?.name || 'مستخدم غير معروف'}</span>
                              {roleData && (
                                <span className={`text-[9px] font-extrabold px-2 py-0.5 rounded border ${roleData.color}`}>
                                  {roleData.label}
                                </span>
                              )}
                            </div>
                            <div className="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5 font-bold">@{log.user?.username || 'unknown'}</div>
                          </div>
                        </div>
                      </td>

                      {/* Action Type Column */}
                      <td className="py-3.5 px-5 text-sm">
                        <span className={`inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border ${actionData.bg} ${actionData.color}`}>
                          {actionData.label}
                        </span>
                      </td>

                      {/* Details Column */}
                      <td className="py-3.5 px-5 text-sm max-w-md">
                        <p className="text-gray-700 dark:text-slate-300 leading-relaxed font-medium break-words text-xs text-start">{translateDetails(log.details)}</p>
                      </td>

                      {/* Time Column */}
                      <td className="py-3.5 px-5 text-xs text-gray-400 dark:text-slate-500 font-bold" dir="ltr">
                        {new Date(log.timestamp).toLocaleString(i18n.language === 'ar' ? 'ar-SA' : 'en-US', {
                          year: 'numeric',
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                          second: '2-digit',
                        })}
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={4} className="py-20 text-center">
                    <div className="max-w-md mx-auto flex flex-col items-center justify-center text-center">
                      <div className="w-16 h-16 bg-gray-50 dark:bg-slate-800/80 border border-gray-100 dark:border-slate-850 rounded-full flex items-center justify-center text-gray-300 dark:text-slate-650 mb-4 shadow-inner">
                        <Shield className="w-8 h-8" />
                      </div>
                      <h3 className="text-base font-bold text-gray-800 dark:text-white mb-1">لم يتم العثور على سجلات</h3>
                      <p className="text-xs text-gray-400 dark:text-slate-450">لم يرصد النظام أي سجلات تتطابق مع شروط البحث الحالية.</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Table Pagination Bar */}
        {!loading && totalPages > 1 && (
          <div className="p-5 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between bg-gray-50/20 dark:bg-slate-850/10">
            <span className="text-xs text-gray-400 dark:text-slate-500 font-bold">
              عرض الصفحة <span className="text-gray-700 dark:text-slate-300 font-extrabold">{page}</span> من أصل <span className="text-gray-700 dark:text-slate-300 font-extrabold">{totalPages}</span> (إجمالي {totalLogs} سجل)
            </span>

            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                type="button"
                className="w-8 h-8 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer shadow-sm"
              >
                <ChevronRight className="w-4 h-4" />
              </button>

              <button
                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
                type="button"
                className="w-8 h-8 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-200 dark:hover:border-teal-850 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer shadow-sm"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
