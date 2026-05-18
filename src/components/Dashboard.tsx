import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { DashboardStats, SurveyResponse, Ticket } from '../types';
import { ticketsAPI } from '../api/client';
import { useTranslation } from 'react-i18next';
import { useThemeStore } from '../store/useThemeStore';
import { useSettingsStore } from '../store/useSettingsStore';
import { useResponsesStore } from '../store/useResponsesStore';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  PieChart,
  Pie,
  Cell,
  LineChart,
  Line,
  RadarChart,
  PolarGrid,
  PolarAngleAxis,
  PolarRadiusAxis,
  Radar,
} from 'recharts';
import SafeResponsiveContainer from './SafeResponsiveContainer';
import {
  Users,
  TrendingUp,
  Target,
  Percent,
  BarChart3,
  ArrowUp,
  ArrowDown,
  Clock,
  Building2,
  ClipboardList,
  UserCog,
  Settings,
  AlertCircle,
  Trophy,
  Award,
  Medal,
  Star,
  Brain,
  Sparkles,
} from 'lucide-react';

import { rolePermissions, useAuthStore } from '../store/useAuthStore';
import { maskPhoneNumber } from '../utils/securityUtils';

export default function Dashboard() {
  const navigate = useNavigate();
  const { currentUser } = useAuthStore();
  const { stats: storeStats, responses, loadDashboardData } = useResponsesStore();

  // Load dashboard data on mount
  useEffect(() => {
    const dept = currentUser?.role === 'head_of_department' ? (currentUser.department ?? undefined) : undefined;
    loadDashboardData(dept);
  }, [currentUser, loadDashboardData]);

  // Provide a default stats object to avoid null checks downstream
  const stats: DashboardStats = storeStats || {
    totalResponses: 0, averageScore: 0, npsScore: 0, responseRate: 0,
    departmentScores: [], trendData: [], categoryScores: [],
    satisfactionDistribution: [], hourlyStats: [], dayStats: [],
  };

  const onViewResponses = () => navigate('/dashboard/responses');
  const onViewTickets = () => navigate('/dashboard/tickets');
  const onViewSurveys = () => navigate('/dashboard/surveys');
  const onViewUsers = () => navigate('/dashboard/users');
  const onViewSettings = () => navigate('/dashboard/settings');
  const onViewPredictive = () => navigate('/dashboard/predictive');
  const { t, i18n } = useTranslation();
  const { theme } = useThemeStore();
  const { settings } = useSettingsStore();
  const permissions = currentUser ? rolePermissions[currentUser.role] : null;

  const isDark = theme === 'dark';

  const getScoreColor = (score: number) => {
    if (score >= 85) return 'text-green-600 dark:text-green-400';
    if (score >= 70) return 'text-blue-600 dark:text-blue-400';
    if (score >= 50) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
  };

  const getScoreBg = (score: number) => {
    if (score >= 85) return 'from-green-500 to-emerald-500';
    if (score >= 70) return 'from-blue-500 to-indigo-500';
    if (score >= 50) return 'from-amber-500 to-orange-500';
    return 'from-red-500 to-rose-500';
  };

  const recentResponses = responses.slice(0, 5);

  const nameResponsesCount = responses.filter((r: SurveyResponse) => r.patientInfo.name?.trim()).length;
  const phoneResponsesCount = responses.filter((r: SurveyResponse) => r.patientInfo.phone?.trim()).length;
  const nameResponsesRate = responses.length ? Math.round((nameResponsesCount / responses.length) * 100) : 0;
  const phoneResponsesRate = responses.length ? Math.round((phoneResponsesCount / responses.length) * 100) : 0;

  const radarData = stats.categoryScores.map((c: { category: string; score: number }) => ({
    category: c.category,
    score: c.score,
    fullMark: 100,
  }));

  const [openTickets, setOpenTickets] = useState<Ticket[]>([]);

  useEffect(() => {
    ticketsAPI.getAll({ status: 'open' }).then(data => {
      setOpenTickets(data as Ticket[]);
    }).catch(() => {});
  }, []);

  // Memoize pie chart data to prevent re-renders
  const pieData = useMemo(() => stats.satisfactionDistribution, [stats.satisfactionDistribution]);
  const pieCells = useMemo(() => 
    pieData.map((entry: { level: string; count: number; color: string }, index: number) => (
      <Cell key={`cell-${index}`} fill={entry.color} stroke="none" />
    )), 
    [pieData]
  );

  const filteredDeptScores = useMemo(() => {
    if (currentUser?.role === 'head_of_department' && currentUser.department) {
      return stats.departmentScores.filter(
        (d: { name: string; score: number; count: number }) => d.name.trim().toLowerCase() === currentUser.department!.trim().toLowerCase()
      );
    }
    return stats.departmentScores;
  }, [stats.departmentScores, currentUser]);

  // Compute active predictive early warning alerts
  const predictiveCount = useMemo(() => {
    const deptGroups: Record<string, SurveyResponse[]> = {};
    responses.forEach((r: SurveyResponse) => {
      const dept = r.department;
      if (!deptGroups[dept]) deptGroups[dept] = [];
      deptGroups[dept].push(r);
    });

    const activated = settings.activatedPredictivePlans || [];

    let activeWarnings = 0;

    Object.entries(deptGroups).forEach(([dept, deptResponses]) => {
      if (activated.includes(dept)) return; // Exclude approved plans!

      const sorted = [...deptResponses].sort((a, b) => new Date(a.submittedAt).getTime() - new Date(b.submittedAt).getTime());
      if (sorted.length < 6) return;

      const halfSize = Math.min(10, Math.floor(sorted.length / 2));
      const currentPeriod = sorted.slice(-halfSize);
      const previousPeriod = sorted.slice(-2 * halfSize, -halfSize);

      if (currentPeriod.length === 0 || previousPeriod.length === 0) return;

      const currentAvg = currentPeriod.reduce((sum, r) => sum + r.overallScore, 0) / currentPeriod.length;
      const previousAvg = previousPeriod.reduce((sum, r) => sum + r.overallScore, 0) / previousPeriod.length;

      const drop = previousAvg - currentAvg;
      if (drop >= 8) {
        activeWarnings++;
      }
    });

    return activeWarnings;
  }, [responses, settings]);

  return (
    <div>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* High Priority Alerts */}
        {openTickets.length > 0 && (
          <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800/40 rounded-2xl p-4 mb-6 flex items-center justify-between animate-pulse-soft">
            <div className="flex items-center gap-3 text-start">
              <div className="w-10 h-10 bg-red-100 dark:bg-red-950/40 rounded-xl flex items-center justify-center">
                <AlertCircle className="w-6 h-6 text-red-600 dark:text-red-400" />
              </div>
              <div>
                <p className="font-bold text-red-800 dark:text-red-300 text-sm">{t('alerts_need_attention', { count: openTickets.length })}</p>
                <p className="text-red-600 dark:text-red-400 text-xs mt-0.5">{t('alerts_review_tickets')}</p>
              </div>
            </div>
            <button 
              onClick={onViewTickets}
              type="button"
              className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-colors cursor-pointer"
            >
              {t('view_tickets')}
            </button>
          </div>
        )}

        {/* AI Predictive Analytics Alert */}
        <div className="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-4 sm:p-5 mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between border border-indigo-500/20 relative overflow-hidden group gap-4">
          {/* Glowing background */}
          <div className="absolute -right-5 -top-5 w-24 h-24 bg-indigo-500 rounded-full blur-[40px] opacity-20 group-hover:opacity-30 transition-opacity pointer-events-none" />
          <div className="flex items-center gap-3 relative">
            <div className="w-10 h-10 bg-indigo-500/10 border border-indigo-500/20 rounded-xl flex items-center justify-center text-indigo-400 flex-shrink-0 relative">
              <Brain className="w-5 h-5 animate-pulse-soft" />
              {predictiveCount > 0 && (
                <span className="absolute -top-1.5 -right-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-rose-500 text-[9px] text-white font-extrabold ring-2 ring-slate-900 animate-pulse">
                  {predictiveCount}
                </span>
              )}
            </div>
            <div className="text-start">
              <p className="font-bold text-sm flex items-center flex-wrap gap-1.5 leading-none">
                <Sparkles className="w-3.5 h-3.5 text-indigo-300 animate-spin-slow" />
                <span>{t('ai_dashboard_status', 'نظام التنبؤ والتحليل الاستباقي (AI)')}</span>
                {predictiveCount > 0 && (
                  <span className="bg-rose-500/20 text-rose-300 text-[9px] font-extrabold px-2 py-0.5 rounded-full border border-rose-500/30 animate-pulse flex items-center gap-1">
                    <span className="w-1 h-1 rounded-full bg-rose-500 animate-ping" />
                    {predictiveCount === 1 
                      ? 'إنذار تراجع نشط' 
                      : predictiveCount === 2 
                        ? 'إنذاران تراجع نشطان' 
                        : `${predictiveCount} إنذارات تراجع نشطة`}
                  </span>
                )}
              </p>
              <p className="text-indigo-200/70 text-xs mt-1 leading-normal">
                {t('ai_dashboard_status_desc', 'مراقبة السلوك الزمني لتقييمات المرضى والتنبؤ بنسب الرضا والإنذار المبكر للأقسام قبل حدوث التراجع.')}
              </p>
            </div>
          </div>
          <button 
            onClick={() => onViewPredictive()}
            type="button"
            className="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition-all shadow-lg shadow-indigo-950 cursor-pointer relative"
          >
            {t('view_ai_predictions', 'استعراض التوقعات والإنذارات')}
          </button>
        </div>

        {/* Department Filter Notice */}
        {currentUser?.role === 'head_of_department' && currentUser.department && (
          <div className="bg-gradient-to-l from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border border-blue-200 dark:border-blue-900/40 rounded-2xl p-4 mb-6 flex items-center gap-3 animate-slide-up text-start">
            <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center flex-shrink-0">
              <Building2 className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div className="flex-1">
              <p className="font-bold text-blue-800 dark:text-blue-300 text-sm">{t('dept_data_only', { dept: currentUser.department })}</p>
              <p className="text-blue-600 dark:text-blue-400 text-xs mt-0.5">{t('viewing_dept_stats')}</p>
            </div>
            <div className="bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 px-3 py-1.5 rounded-xl text-xs font-bold">
              {t('response_count', { count: stats.totalResponses })}
            </div>
          </div>
        )}

        {stats.totalResponses > 0 ? (
          <>
            {/* Identity Data Summary */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-start">
              <div 
                onClick={onViewResponses}
                className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm p-5 cursor-pointer hover:shadow-md hover:border-teal-200 dark:hover:border-teal-700 transition-all group"
              >
                <div className="flex items-center justify-between mb-3">
                  <div>
                    <p className="text-sm text-gray-500 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">{t('responses_with_name')}</p>
                    <div className="text-2xl font-black text-gray-900 dark:text-white mt-1">{nameResponsesCount}</div>
                  </div>
                  <div className="px-3 py-1 rounded-full bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 text-sm font-bold">
                    {nameResponsesRate}%
                  </div>
                </div>
                <div className="w-full h-2 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                  <div className="h-full bg-gradient-to-r from-teal-500 to-emerald-500 rounded-full transition-all duration-500" style={{ width: `${nameResponsesRate}%` }} />
                </div>
              </div>

              <div 
                onClick={onViewResponses}
                className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm p-5 cursor-pointer hover:shadow-md hover:border-blue-200 dark:hover:border-blue-700 transition-all group"
              >
                <div className="flex items-center justify-between mb-3">
                  <div>
                    <p className="text-sm text-gray-500 dark:text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{t('responses_with_phone')}</p>
                    <div className="text-2xl font-black text-gray-900 dark:text-white mt-1">{phoneResponsesCount}</div>
                  </div>
                  <div className="px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 text-sm font-bold">
                    {phoneResponsesRate}%
                  </div>
                </div>
                <div className="w-full h-2 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                  <div className="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-500" style={{ width: `${phoneResponsesRate}%` }} />
                </div>
              </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 text-start">
              {[
                {
                  label: t('total_responses'),
                  value: stats.totalResponses,
                  icon: Users,
                  color: 'from-blue-500 to-indigo-500',
                  shadow: 'shadow-blue-200 dark:shadow-blue-900/20',
                  change: '',
                  up: false,
                  onClick: onViewResponses,
                },
                {
                  label: t('satisfaction_rate'),
                  value: `${stats.averageScore}%`,
                  icon: TrendingUp,
                  color: getScoreBg(stats.averageScore),
                  shadow: 'shadow-teal-200 dark:shadow-teal-900/20',
                  change: `${stats.averageScore - (stats.previousAverageScore || 0)}%`,
                  up: stats.averageScore >= (stats.previousAverageScore || 0),
                  onClick: onViewResponses,
                },
                {
                  label: t('nps_indicator'),
                  value: stats.npsScore,
                  icon: Target,
                  color: stats.npsScore >= 50 ? 'from-green-500 to-emerald-500' : stats.npsScore >= 0 ? 'from-amber-500 to-orange-500' : 'from-red-500 to-rose-500',
                  shadow: 'shadow-green-200 dark:shadow-green-900/20',
                  change: `${stats.npsScore - (stats.previousNpsScore || 0)}`,
                  up: stats.npsScore >= (stats.previousNpsScore || 0),
                  onClick: onViewResponses,
                },
                {
                  label: t('response_rate'),
                  value: `${stats.responseRate}%`,
                  icon: Percent,
                  color: 'from-purple-500 to-violet-500',
                  shadow: 'shadow-purple-200 dark:shadow-purple-900/20',
                  change: `${stats.responseRate - (stats.previousResponseRate || 100)}%`,
                  up: stats.responseRate >= (stats.previousResponseRate || 100),
                  onClick: onViewResponses,
                },
              ].map((stat, i) => (
                <div
                  key={i}
                  onClick={stat.onClick}
                  className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-gray-100 dark:border-slate-800/80 shadow-sm hover:shadow-md transition-all animate-slide-up cursor-pointer hover:-translate-y-1"
                  style={{ animationDelay: `${i * 100}ms` }}
                >
                  <div className="flex items-start justify-between mb-4">
                    <div className={`w-12 h-12 bg-gradient-to-br ${stat.color} rounded-xl flex items-center justify-center shadow-lg ${stat.shadow}`}>
                      <stat.icon className="w-6 h-6 text-white" />
                    </div>
                    {stat.change ? (
                      <div className={`flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-full ${
                        stat.up ? 'bg-green-50 dark:bg-green-950/35 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-950/35 text-red-700 dark:text-red-400'
                      }`}>
                        {stat.up ? <ArrowUp className="w-2.5 h-2.5" /> : <ArrowDown className="w-2.5 h-2.5" />}
                        {stat.change}
                      </div>
                    ) : <div className="w-14" />}
                  </div>
                  <div className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white">{stat.value}</div>
                  <div className="text-[10px] text-gray-500 dark:text-slate-400 mt-1 uppercase tracking-wider">{stat.label}</div>
                </div>
              ))}
            </div>

            {/* Charts Row 1 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 text-start">
              {/* Trend Chart */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-2">
                    <BarChart3 className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                    <h3 className="font-bold text-gray-800 dark:text-white">{t('weekly_trend')}</h3>
                  </div>
                </div>
                <SafeResponsiveContainer width="100%" height={280}>
                  <LineChart data={stats.trendData}>
                    <CartesianGrid strokeDasharray="3 3" stroke={isDark ? 'rgba(255,255,255,0.05)' : '#f0f0f0'} />
                    <XAxis dataKey="date" tick={{ fontSize: 12, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <YAxis tick={{ fontSize: 12, fill: isDark ? '#94a3b8' : '#64748b' }} domain={[0, 100]} />
                    <Tooltip
                      contentStyle={{
                        borderRadius: '12px',
                        border: 'none',
                        boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
                        fontFamily: 'Cairo',
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                      }}
                      formatter={(value) => [`${value ?? 0}%`, t('satisfaction_rate_label')]}
                    />
                    <Line
                      type="monotone"
                      dataKey="score"
                      stroke="#0d9488"
                      strokeWidth={3}
                      dot={{ fill: '#0d9488', strokeWidth: 2, r: 5 }}
                      activeDot={{ r: 8, fill: '#0d9488' }}
                    />
                  </LineChart>
                </SafeResponsiveContainer>
              </div>

              {/* Satisfaction Distribution */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                  <Target className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                  <h3 className="font-bold text-gray-800 dark:text-white">{t('satisfaction_distribution')}</h3>
                </div>
                <div className="flex items-center justify-center" style={{ minHeight: 280 }}>
                  <SafeResponsiveContainer width="100%" height={280}>
                    <PieChart>
                      <Pie
                        data={pieData}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={100}
                        paddingAngle={2}
                        dataKey="count"
                        nameKey="level"
                        isAnimationActive={false}
                        startAngle={90}
                        endAngle={-270}
                      >
                        {pieCells}
                      </Pie>
                      <Tooltip
                        contentStyle={{
                          borderRadius: '12px',
                          border: 'none',
                          boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
                          fontFamily: 'Cairo',
                          backgroundColor: isDark ? '#1e293b' : '#ffffff',
                          color: isDark ? '#f8fafc' : '#0f172a',
                        }}
                        formatter={(value, name) => [`${value ?? 0} ${t('response_label')}`, name]}
                      />
                    </PieChart>
                  </SafeResponsiveContainer>
                </div>
                <div className="flex items-center justify-center gap-4 flex-wrap mt-2">
                  {stats.satisfactionDistribution.map((item, i) => (
                    <div key={i} className="flex items-center gap-2">
                      <div className="w-3 h-3 rounded-full" style={{ backgroundColor: item.color }} />
                      <span className="text-sm text-gray-600 dark:text-slate-300">{item.level} ({item.count})</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Charts Row 2 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 text-start">
              {/* Department Scores */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                  <Building2 className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                  <h3 className="font-bold text-gray-800 dark:text-white">{t('dept_satisfaction')}</h3>
                </div>
                <SafeResponsiveContainer width="100%" height={300}>
                  {filteredDeptScores.length > 0 ? (
                    <BarChart data={filteredDeptScores} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke={isDark ? 'rgba(255,255,255,0.05)' : '#f0f0f0'} />
                      <XAxis type="number" domain={[0, 100]} tick={{ fontSize: 11, fill: isDark ? '#94a3b8' : '#64748b' }} />
                      <YAxis dataKey="name" type="category" tick={{ fontSize: 11, fill: isDark ? '#94a3b8' : '#64748b' }} width={100} />
                      <Tooltip
                        contentStyle={{
                          borderRadius: '12px',
                          border: 'none',
                          boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
                          fontFamily: 'Cairo',
                          backgroundColor: isDark ? '#1e293b' : '#ffffff',
                          color: isDark ? '#f8fafc' : '#0f172a',
                        }}
                        formatter={(value) => [`${value ?? 0}%`, t('satisfaction_rate_label')]}
                      />
                      <Bar
                        dataKey="score"
                        fill="#0d9488"
                        radius={[0, 8, 8, 0]}
                        barSize={20}
                      />
                    </BarChart>
                  ) : (
                    <div className="flex items-center justify-center h-full w-full bg-gray-50 dark:bg-slate-800/40 rounded-xl border border-dashed border-gray-200 dark:border-slate-700">
                      <span className="text-gray-400 dark:text-slate-500 font-medium text-sm">{t('no_dept_data')}</span>
                    </div>
                  )}
                </SafeResponsiveContainer>
              </div>

              {/* Radar Chart */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                  <Target className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                  <h3 className="font-bold text-gray-800 dark:text-white">{t('category_analysis')}</h3>
                </div>
                <SafeResponsiveContainer width="100%" height={300}>
                  <RadarChart data={radarData}>
                    <PolarGrid stroke={isDark ? 'rgba(255,255,255,0.08)' : '#e5e7eb'} />
                    <PolarAngleAxis dataKey="category" tick={{ fontSize: 12, fontFamily: 'Cairo', fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <PolarRadiusAxis angle={90} domain={[0, 100]} tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <Radar
                      name={t('performance')}
                      dataKey="score"
                      stroke="#0d9488"
                      fill="#0d9488"
                      fillOpacity={0.3}
                      strokeWidth={2}
                    />
                    <Tooltip
                      contentStyle={{
                        borderRadius: '12px',
                        border: 'none',
                        boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
                        fontFamily: 'Cairo',
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                      }}
                    />
                  </RadarChart>
                </SafeResponsiveContainer>
              </div>
            </div>

            {/* Advanced Reporting: Time-based Analysis */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 text-start">
              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                  <Clock className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                  <h3 className="font-bold text-gray-800 dark:text-white">{t('hourly_analysis')}</h3>
                </div>
                <SafeResponsiveContainer width="100%" height={250}>
                  <BarChart data={stats.hourlyStats}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={isDark ? 'rgba(255,255,255,0.05)' : '#f0f0f0'} />
                    <XAxis dataKey="hour" tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <YAxis domain={[0, 100]} tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <Tooltip
                      contentStyle={{
                        borderRadius: '12px',
                        border: 'none',
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                      }}
                    />
                    <Bar dataKey="score" fill="#0d9488" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </SafeResponsiveContainer>
                <p className="text-[10px] text-gray-400 dark:text-slate-500 mt-4 text-center italic">
                  {t('hourly_hint')}
                </p>
              </div>

              <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
                <div className="flex items-center gap-2 mb-6">
                  <TrendingUp className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                  <h3 className="font-bold text-gray-800 dark:text-white">{t('daily_quality')}</h3>
                </div>
                <SafeResponsiveContainer width="100%" height={250}>
                  <BarChart data={stats.dayStats}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={isDark ? 'rgba(255,255,255,0.05)' : '#f0f0f0'} />
                    <XAxis dataKey="day" tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <YAxis domain={[0, 100]} tick={{ fontSize: 10, fill: isDark ? '#94a3b8' : '#64748b' }} />
                    <Tooltip
                      contentStyle={{
                        borderRadius: '12px',
                        border: 'none',
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                      }}
                    />
                    <Bar dataKey="score" fill="#6366f1" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </SafeResponsiveContainer>
                <p className="text-[10px] text-gray-400 dark:text-slate-500 mt-4 text-center italic">
                  {t('daily_hint')}
                </p>
              </div>
            </div>
          </>
        ) : (
          <div className="bg-white dark:bg-slate-900 rounded-2xl p-10 border border-gray-100 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center text-center mb-8">
            <div className="w-20 h-20 bg-gray-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center mb-4">
              <BarChart3 className="w-10 h-10 text-gray-300 dark:text-slate-500" />
            </div>
            <h3 className="text-xl font-bold text-gray-800 dark:text-white mb-2">{t('no_data_available')}</h3>
            <p className="text-gray-500 dark:text-slate-400 max-w-md">
              {t('no_data_desc')}
            </p>
          </div>
        )}

        {/* Quick Actions */}
        {(permissions?.canManageSurveys || permissions?.canManageUsers) && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-start">
            {permissions?.canManageSurveys && (
              <div className="bg-gradient-to-br from-teal-600 to-emerald-600 rounded-2xl p-6 text-white shadow-lg shadow-teal-200 dark:shadow-teal-950/20">
                <div className="flex items-center justify-between flex-wrap gap-4">
                  <div>
                    <h3 className="text-lg font-bold mb-1">{t('manage_surveys')}</h3>
                    <p className="text-teal-100 text-sm">{t('manage_surveys_desc')}</p>
                  </div>
                  <button
                    onClick={onViewSurveys}
                    type="button"
                    className="bg-white text-teal-600 px-5 py-2.5 rounded-xl font-bold hover:bg-teal-50 transition-colors flex items-center gap-2 cursor-pointer"
                  >
                    <ClipboardList className="w-5 h-5" />
                    {t('manage')}
                  </button>
                </div>
              </div>
            )}
            {permissions?.canManageUsers && (
              <div className="bg-gradient-to-br from-purple-600 to-indigo-600 rounded-2xl p-6 text-white shadow-lg shadow-purple-200 dark:shadow-purple-950/20">
                <div className="flex items-center justify-between flex-wrap gap-4">
                  <div>
                    <h3 className="text-lg font-bold mb-1">{t('manage_users')}</h3>
                    <p className="text-purple-100 text-sm">{t('manage_users_desc')}</p>
                  </div>
                  <button
                    onClick={onViewUsers}
                    type="button"
                    className="bg-white text-purple-600 px-5 py-2.5 rounded-xl font-bold hover:bg-purple-50 transition-colors flex items-center gap-2 cursor-pointer"
                  >
                    <UserCog className="w-5 h-5" />
                    {t('manage')}
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Settings Quick Action */}
        {permissions?.canManageSurveys && (
          <div className="bg-gradient-to-br from-gray-600 to-gray-700 rounded-2xl p-6 text-white mb-6 shadow-lg shadow-gray-200 dark:shadow-slate-950/20">
            <div className="flex items-center justify-between flex-wrap gap-4 text-start">
              <div>
                <h3 className="text-lg font-bold mb-1">{t('general_settings')}</h3>
                <p className="text-gray-200 text-sm">{t('general_settings_desc')}</p>
              </div>
              <button
                onClick={onViewSettings}
                type="button"
                className="bg-white text-gray-700 px-5 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition-colors flex items-center gap-2 cursor-pointer"
              >
                <Settings className="w-5 h-5" />
                {t('settings')}
              </button>
            </div>
          </div>
        )}

        {/* Gamification: Hall of Fame (Leaderboard) */}
        <div className="mb-6 text-start">
          <div className="flex items-center gap-2 mb-4">
            <Star className="w-6 h-6 text-yellow-500 fill-yellow-500" />
            <h3 className="font-bold text-gray-800 dark:text-white text-lg uppercase tracking-tight">{t('honor_board')}</h3>
          </div>
          {currentUser?.role === 'head_of_department' && currentUser.department ? (() => {
            const myDeptIndex = stats.departmentScores.findIndex(
              d => d.name.trim().toLowerCase() === currentUser.department!.trim().toLowerCase()
            );
            const myDeptData = myDeptIndex !== -1 ? stats.departmentScores[myDeptIndex] : null;

            if (!myDeptData) {
              return (
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm p-6 text-center max-w-md">
                  <Building2 className="w-8 h-8 text-gray-300 dark:text-slate-500 mx-auto mb-2" />
                  <p className="text-gray-500 dark:text-slate-400 text-xs">لا توجد بيانات ترتيب متاحة لقسم {currentUser.department} بعد.</p>
                </div>
              );
            }

            const rank = myDeptIndex + 1;
            const colors = [
              'from-yellow-400 to-amber-600', // Gold
              'from-slate-300 to-slate-500',  // Silver
              'from-orange-400 to-amber-700', // Bronze
            ];
            const Icons = [Trophy, Award, Medal];
            const IconComp = Icons[myDeptIndex] || Award;
            const gradientColor = colors[myDeptIndex] || 'from-teal-500 to-emerald-600';

            return (
              <div className="relative group overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm p-5 transition-all hover:-translate-y-1 hover:shadow-md max-w-md">
                <div className={`absolute top-0 right-0 w-16 h-16 bg-gradient-to-br ${gradientColor} opacity-10 rounded-bl-full pointer-events-none`} />
                <div className="flex items-center gap-4">
                  <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${gradientColor} flex items-center justify-center text-white shadow-lg`}>
                    <IconComp className="w-6 h-6" />
                  </div>
                  <div>
                    <p className="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{t('rank', { num: rank })}</p>
                    <h4 className="font-black text-gray-900 dark:text-white text-lg">{currentUser.department}</h4>
                  </div>
                </div>
                <div className="mt-4 flex items-end justify-between">
                  <div>
                    <p className="text-[10px] text-gray-400 dark:text-slate-500 font-bold mb-1 uppercase">{t('satisfaction_rate_label')}</p>
                    <div className="text-2xl font-black text-gray-900 dark:text-white">{myDeptData.score}%</div>
                  </div>
                  <div className="flex -space-x-1">
                    {[1, 2, 3, 4, 5].map((s) => (
                      <Star key={s} className={`w-3 h-3 ${s <= Math.round(myDeptData.score / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700'}`} />
                    ))}
                  </div>
                </div>
              </div>
            );
          })() : (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {stats.departmentScores.slice(0, 3).map((dept, index) => {
                const colors = [
                  'from-yellow-400 to-amber-600', // Gold
                  'from-slate-300 to-slate-500',  // Silver
                  'from-orange-400 to-amber-700', // Bronze
                ];
                const Icons = [Trophy, Award, Medal];
                const IconComp = Icons[index] || Award;

                return (
                  <div key={dept.name} className="relative group overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm p-5 transition-all hover:-translate-y-1 hover:shadow-md">
                    <div className={`absolute top-0 right-0 w-16 h-16 bg-gradient-to-br ${colors[index]} opacity-10 rounded-bl-full`} />
                    <div className="flex items-center gap-4">
                      <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${colors[index]} flex items-center justify-center text-white shadow-lg`}>
                        <IconComp className="w-6 h-6" />
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{t('rank', { num: index + 1 })}</p>
                        <h4 className="font-black text-gray-900 dark:text-white text-lg">{t('dept_prefix', { name: dept.name })}</h4>
                      </div>
                    </div>
                    <div className="mt-4 flex items-end justify-between">
                      <div>
                        <p className="text-[10px] text-gray-400 dark:text-slate-500 font-bold mb-1 uppercase">{t('satisfaction_rate_label')}</p>
                        <div className="text-2xl font-black text-gray-900 dark:text-white">{dept.score}%</div>
                      </div>
                      <div className="flex -space-x-1">
                        {[1, 2, 3, 4, 5].map((s) => (
                          <Star key={s} className={`w-3 h-3 ${s <= Math.round(dept.score / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700'}`} />
                        ))}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Recent Responses */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-2">
              <Clock className="w-5 h-5 text-teal-600 dark:text-teal-400" />
              <h3 className="font-bold text-gray-800 dark:text-white">{t('recent_responses')}</h3>
            </div>
            <button
              onClick={() => navigate('/dashboard/responses')}
              type="button"
              className="text-sm text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium cursor-pointer"
            >
              {t('view_all')}
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 dark:border-slate-800">
                  <th className="text-right py-3 px-4 text-sm font-bold text-gray-500 dark:text-slate-400">{t('reviewer')}</th>
                  <th className="text-right py-3 px-4 text-sm font-bold text-gray-500 dark:text-slate-400">{t('department')}</th>
                  <th className="text-right py-3 px-4 text-sm font-bold text-gray-500 dark:text-slate-400 hidden sm:table-cell">{t('visit_type')}</th>
                  <th className="text-right py-3 px-4 text-sm font-bold text-gray-500 dark:text-slate-400">{t('rating')}</th>
                  <th className="text-right py-3 px-4 text-sm font-bold text-gray-500 dark:text-slate-400 hidden md:table-cell">{t('date')}</th>
                </tr>
              </thead>
              <tbody>
                {recentResponses.map((resp: SurveyResponse) => (
                  <tr key={resp.id} className="border-b border-gray-50 dark:border-slate-800/60 hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="py-3 px-4 text-sm">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400 font-bold text-xs flex-shrink-0 border border-teal-100 dark:border-teal-800/30">
                          {resp.patientInfo.name ? resp.patientInfo.name.charAt(0) : '?'}
                        </div>
                        <div className="space-y-0.5">
                          <div className={`font-bold text-sm ${resp.patientInfo.name ? 'text-gray-900 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500 italic'}`}>
                            {resp.patientInfo.name || t('anonymous')}
                          </div>
                          {resp.patientInfo.phone && (
                            <div className="text-[10px] text-teal-600 dark:text-teal-400 font-bold" dir="ltr">
                              {maskPhoneNumber(resp.patientInfo.phone)}
                            </div>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="py-3 px-4 text-sm text-gray-700 dark:text-slate-300 font-medium">{resp.department}</td>
                    <td className="py-3 px-4 text-sm text-gray-600 dark:text-slate-400 hidden sm:table-cell">{resp.patientInfo.visitType}</td>
                    <td className="py-3 px-4">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-2 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                          <div
                            className={`h-full rounded-full ${
                              resp.overallScore >= 85 ? 'bg-green-500' :
                              resp.overallScore >= 70 ? 'bg-blue-500' :
                              resp.overallScore >= 50 ? 'bg-amber-500' : 'bg-red-500'
                            }`}
                            style={{ width: `${resp.overallScore}%` }}
                          />
                        </div>
                        <span className={`text-sm font-bold ${getScoreColor(resp.overallScore)}`}>
                          {resp.overallScore}%
                        </span>
                      </div>
                    </td>
                    <td className="py-3 px-4 text-sm text-gray-500 dark:text-slate-400 hidden md:table-cell">
                      {new Date(resp.submittedAt).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
