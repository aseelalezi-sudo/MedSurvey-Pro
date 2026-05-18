import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { SurveyResponse } from '../types';
import { useSurveyStore } from '../store/useSurveyStore';
import { useAuthStore } from '../store/useAuthStore';
import { maskPhoneNumber } from '../utils/securityUtils';
import type { PaginatedResponse } from '../api/client';
import {
  Search,
  Filter,
  Star,
  Calendar,
  Building2,
  User,
  Activity,
  Eye,
  X,
  Phone,
  TrendingUp,
  BarChart3,
} from 'lucide-react';
import ExportModal from './ExportModal';

export default function ResponsesPage() {
  const { surveys } = useSurveyStore();
  const { hasPermission } = useAuthStore();
  const { t, i18n } = useTranslation();
  const [searchDept, setSearchDept] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [filterScore, setFilterScore] = useState('all');
  const [filterDate, setFilterDate] = useState('all');
  const [filterGender, setFilterGender] = useState('all');
  const [filterHasName, setFilterHasName] = useState(false);
  const [filterHasPhone, setFilterHasPhone] = useState(false);
  const [sortBy, setSortBy] = useState('submittedAt');
  const [order, setOrder] = useState('desc');
  const [customStartDate, setCustomStartDate] = useState('');
  const [customEndDate, setCustomEndDate] = useState('');
  const [selectedResponse, setSelectedResponse] = useState<SurveyResponse | null>(null);
  const [showFilters, setShowFilters] = useState(false);
  const [showExportModal, setShowExportModal] = useState(false);
  
  const [data, setData] = useState<SurveyResponse[]>([]);
  const [meta, setMeta] = useState({ averageScore: 0, filteredTotal: 0 });
  const [pagination, setPagination] = useState({ page: 1, limit: 50, total: 0, totalPages: 0 });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(searchDept), 300);
    return () => clearTimeout(timer);
  }, [searchDept]);

  useEffect(() => {
    setLoading(true);
    import('../api/client').then(({ responsesAPI }) => {
      responsesAPI.getAll({
        search: debouncedSearch,
        score: filterScore,
        dateFilter: filterDate,
        startDate: customStartDate,
        endDate: customEndDate,
        hasName: filterHasName ? 'true' : undefined,
        hasPhone: filterHasPhone ? 'true' : undefined,
        gender: filterGender !== 'all' ? filterGender : undefined,
        sortBy,
        order,
        page: pagination.page,
        limit: pagination.limit
      }).then((res: PaginatedResponse<SurveyResponse> & { meta?: { averageScore: number; filteredTotal: number } }) => {
        setData(res.data);
        setPagination(res.pagination);
        if (res.meta) setMeta(res.meta);
        setLoading(false);
      }).catch(() => setLoading(false));
    });
  }, [debouncedSearch, filterScore, filterDate, customStartDate, customEndDate, filterHasName, filterHasPhone, filterGender, sortBy, order, pagination.page, pagination.limit]);

  const getScoreLabel = (score: number) => {
    if (score >= 85) return { text: t('score_excellent'), color: 'bg-green-100 dark:bg-green-950/40 text-green-700 dark:text-green-400' };
    if (score >= 70) return { text: t('score_good'), color: 'bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400' };
    if (score >= 50) return { text: t('score_average'), color: 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400' };
    return { text: t('score_poor'), color: 'bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-400' };
  };

  const getQuestionTitle = (surveyId: string, questionId: string, answersObj?: Record<string, string | number | boolean | string[] | null>): string => {
    if (questionId.endsWith('_reason')) {
      return t('reason_for_low_rating_label', 'سبب تدني التقييم');
    }

    let survey = surveys.find(s => s.id === surveyId);
    if (!survey && surveys.length > 0) {
      survey = surveys[0]; // fallback
    }
    if (!survey) return questionId;

    // Flatten all questions to support index-based lookups for seed data (q1, q2...)
    const allQuestions: { id: string; title: string }[] = [];
    survey.sections.forEach(sec => {
      allQuestions.push(...sec.questions);
    });

    // 1. Direct match by ID (for actual real responses)
    const directQuestion = allQuestions.find(q => q.id === questionId);
    if (directQuestion) return directQuestion.title;

    // 2. If it's a seed question (e.g. "q1", "q2", "q13"...)
    if (/^q\d+$/.test(questionId)) {
      const index = parseInt(questionId.substring(1)) - 1;
      if (index >= 0 && index < allQuestions.length) {
        return allQuestions[index].title;
      }
    }

    // 3. Fallback: Match by sequential index of the answer key within answersObj (for regenerated IDs from updated surveys)
    if (answersObj) {
      const keys = Object.keys(answersObj).filter(k => !k.endsWith('_reason')); // ignore follow-up reason keys
      const keyIndex = keys.indexOf(questionId);
      if (keyIndex >= 0 && keyIndex < allQuestions.length) {
        return allQuestions[keyIndex].title;
      }
    }

    return questionId;
  };

  return (
    <div>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {/* Filters */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl p-4 mb-6 border border-gray-100 dark:border-slate-800/80 shadow-sm">
          <div className="flex items-center gap-3 flex-wrap">
            <div className="relative flex-1 min-w-[200px]">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                value={searchDept}
                onChange={e => setSearchDept(e.target.value)}
                placeholder={t('responses_search_placeholder')}
                className="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </div>

            <div className="flex gap-2">
              <select
                value={`${sortBy}-${order}`}
                onChange={(e) => {
                  const [s, o] = e.target.value.split('-');
                  setSortBy(s);
                  setOrder(o);
                }}
                className="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 bg-white dark:bg-slate-800 outline-none focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-900/20 cursor-pointer"
              >
                <option value="submittedAt-desc">{t('sort_newest', 'الأحدث أولاً')}</option>
                <option value="submittedAt-asc">{t('sort_oldest', 'الأقدم أولاً')}</option>
                <option value="overallScore-desc">{t('sort_highest', 'الأعلى تقييماً')}</option>
                <option value="overallScore-asc">{t('sort_lowest', 'الأقل تقييماً')}</option>
              </select>

              <button
                onClick={() => setShowFilters(!showFilters)}
                type="button"
                className="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                <Filter className="w-4 h-4" />
                {t('responses_filter')}
              </button>
              {hasPermission('canExportData') && (
                <button
                  onClick={() => setShowExportModal(true)}
                  type="button"
                  className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 font-medium hover:bg-teal-100 dark:hover:bg-teal-900/30 transition-colors text-sm cursor-pointer"
                >
                  {t('responses_export_report')}
                </button>
              )}
            </div>
          </div>

          {/* Quick Stats Bar */}
          {!loading && meta.filteredTotal > 0 && (
            <div className="mt-4 pt-4 border-t border-gray-50 dark:border-slate-800/40 flex items-center gap-6 animate-fade-in">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/40 flex items-center justify-center text-blue-600 dark:text-blue-400">
                  <BarChart3 className="w-4 h-4" />
                </div>
                <div>
                  <div className="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{t('total_responses')}</div>
                  <div className="text-sm font-black text-gray-900 dark:text-white leading-tight">{meta.filteredTotal.toLocaleString()}</div>
                </div>
              </div>
              
              <div className="w-px h-8 bg-gray-100 dark:bg-slate-800"></div>

              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400">
                  <TrendingUp className="w-4 h-4" />
                </div>
                <div>
                  <div className="text-[10px] text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider">{t('satisfaction_rate')}</div>
                  <div className="text-sm font-black text-gray-900 dark:text-white leading-tight">{meta.averageScore}%</div>
                </div>
              </div>
            </div>
          )}

          {showFilters && (
            <div className="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800/80 animate-slide-up space-y-4 text-start">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-sm text-gray-500 dark:text-slate-400">{t('responses_satisfaction_level')}</span>
                {[
                  { value: 'all', label: t('responses_all') },
                  { value: 'excellent', label: t('score_excellent') },
                  { value: 'good', label: t('score_good') },
                  { value: 'average', label: t('score_average') },
                  { value: 'poor', label: t('score_poor') },
                ].map(f => (
                  <button
                    key={f.value}
                    onClick={() => setFilterScore(f.value)}
                    type="button"
                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer ${
                      filterScore === f.value
                        ? 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400'
                        : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {f.label}
                  </button>
                ))}
              </div>

              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-sm text-gray-500 dark:text-slate-400">{t('responses_date_filter')}</span>
                {[
                  { value: 'all', label: t('responses_all') },
                  { value: 'today', label: t('responses_today') },
                  { value: 'week', label: t('responses_last_7_days') },
                  { value: 'month', label: t('responses_last_30_days') },
                  { value: 'custom', label: t('responses_custom_date') },
                ].map(f => (
                  <button
                    key={f.value}
                    onClick={() => setFilterDate(f.value)}
                    type="button"
                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer ${
                      filterDate === f.value
                        ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400'
                        : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {f.label}
                  </button>
                ))}

                {filterDate === 'custom' && (
                  <div className="flex items-center gap-2 bg-gray-50 dark:bg-slate-800/50 px-2.5 py-1 rounded-lg border border-gray-100 dark:border-slate-700">
                    <span className="text-xs text-gray-500 dark:text-slate-400">{t('responses_from')}</span>
                    <input
                      type="date"
                      value={customStartDate}
                      onChange={e => setCustomStartDate(e.target.value)}
                      className="px-2 py-1 rounded-md border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-100 outline-none"
                    />
                    <span className="text-xs text-gray-500 dark:text-slate-400">{t('responses_to')}</span>
                    <input
                      type="date"
                      value={customEndDate}
                      onChange={e => setCustomEndDate(e.target.value)}
                      className="px-2 py-1 rounded-md border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-100 outline-none"
                    />
                  </div>
                )}
              </div>

              <div className="flex items-center gap-2 flex-wrap pt-2 border-t border-gray-50 dark:border-slate-800/40">
                <span className="text-sm text-gray-500 dark:text-slate-400">{t('gender')}:</span>
                {[
                  { value: 'all', label: t('responses_all') },
                  { value: t('male'), label: t('male') },
                  { value: t('female'), label: t('female') },
                ].map(f => (
                  <button
                    key={f.value}
                    onClick={() => setFilterGender(f.value)}
                    type="button"
                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer ${
                      filterGender === f.value
                        ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400'
                        : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {f.label}
                  </button>
                ))}
              </div>

              <div className="flex items-center gap-2 flex-wrap pt-2 border-t border-gray-50 dark:border-slate-800/40">
                <span className="text-sm text-gray-500 dark:text-slate-400">{t('responses_identity_filter', 'هوية المراجع:')}</span>
                <button
                  onClick={() => setFilterHasName(!filterHasName)}
                  type="button"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer ${
                    filterHasName
                      ? 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800'
                      : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 border border-transparent'
                  }`}
                >
                  <User className="w-3.5 h-3.5" />
                  {t('responses_with_name')}
                </button>
                <button
                  onClick={() => setFilterHasPhone(!filterHasPhone)}
                  type="button"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all cursor-pointer ${
                    filterHasPhone
                      ? 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800'
                      : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 border border-transparent'
                  }`}
                >
                  <Phone className="w-3.5 h-3.5" />
                  {t('responses_with_phone')}
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Responses Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-start">
          {data.map((resp, i) => {
            const scoreLabel = getScoreLabel(resp.overallScore);
            return (
              <div
                key={resp.id}
                className="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-gray-100 dark:border-slate-800/80 shadow-sm hover:shadow-md transition-all cursor-pointer animate-slide-up"
                style={{ animationDelay: `${Math.min(i, 10) * 50}ms` }}
                onClick={() => setSelectedResponse(resp)}
              >
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400 border border-teal-100 dark:border-teal-900/50">
                      <Building2 className="w-4 h-4" />
                    </div>
                    <div className="flex flex-col">
                      <span className="font-bold text-gray-900 dark:text-white text-sm leading-tight">{resp.department}</span>
                      <span className="text-[10px] text-gray-400 dark:text-slate-400">{t('responses_medical_dept')}</span>
                    </div>
                  </div>
                  <span className={`text-[10px] font-black px-2 py-1 rounded-lg uppercase tracking-wider ${scoreLabel.color}`}>
                    {scoreLabel.text}
                  </span>
                </div>

                {/* Patient Identity Card */}
                <div className="bg-slate-50 dark:bg-slate-950/40 rounded-2xl p-3 mb-4 border border-slate-100/50 dark:border-slate-800/40">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-10 h-10 rounded-full bg-white dark:bg-slate-800 flex items-center justify-center text-teal-600 dark:text-teal-400 font-black text-sm border border-teal-100 dark:border-teal-900 shadow-sm flex-shrink-0">
                      {resp.patientInfo.name ? resp.patientInfo.name.charAt(0) : '?'}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className={`font-bold text-xs truncate ${resp.patientInfo.name ? 'text-gray-900 dark:text-slate-200' : 'text-gray-400 dark:text-slate-550 italic'}`}>
                        {resp.patientInfo.name || t('anonymous')}
                      </div>
                      {resp.patientInfo.phone && (
                        <div className="text-[10px] text-teal-600 dark:text-teal-400 font-bold flex items-center gap-1 mt-0.5" dir="ltr">
                          <Phone className="w-2.5 h-2.5" />
                          {maskPhoneNumber(resp.patientInfo.phone)}
                        </div>
                      )}
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-3 text-[10px] text-gray-500 dark:text-slate-400 pt-2 border-t border-slate-200/50 dark:border-slate-800/40">
                    <div className="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                      <User className="w-2.5 h-2.5 text-slate-400" />
                      <span>{resp.patientInfo.gender}</span>
                    </div>
                    <div className="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                      <Activity className="w-2.5 h-2.5 text-slate-400" />
                      <span>{resp.patientInfo.visitType}</span>
                    </div>
                    <div className="flex items-center gap-1 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full border border-slate-100 dark:border-slate-700">
                      <Calendar className="w-2.5 h-2.5 text-slate-400" />
                      <span>{resp.patientInfo.ageGroup}</span>
                    </div>
                  </div>
                </div>

                <div className="mb-4 px-1">
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-[10px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-tight">{t('satisfaction_rate')}</span>
                    <span className="text-sm font-black text-gray-900 dark:text-white">{resp.overallScore}%</span>
                  </div>
                  <div className="w-full h-1.5 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all duration-700 ${
                        resp.overallScore >= 85 ? 'bg-green-500' :
                        resp.overallScore >= 70 ? 'bg-blue-500' :
                        resp.overallScore >= 50 ? 'bg-amber-500' : 'bg-red-500'
                      }`}
                      style={{ width: `${resp.overallScore}%` }}
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between mt-3 pt-3 border-t border-gray-50 dark:border-slate-800/60">
                  <div className="flex items-center gap-1 text-xs text-gray-400 dark:text-slate-400">
                    <Calendar className="w-3 h-3" />
                    {new Date(resp.submittedAt).toLocaleDateString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                  </div>
                  <button type="button" className="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 cursor-pointer">
                    <Eye className="w-4 h-4" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>

        {data.length === 0 && !loading && (
          <div className="text-center py-20">
            <Search className="w-16 h-16 text-gray-200 dark:text-slate-700 mx-auto mb-4" />
            <p className="text-gray-500 dark:text-slate-400 text-lg">{t('responses_no_results')}</p>
          </div>
        )}

        {loading && (
          <div className="text-center py-20">
            <div className="w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>
        )}

        {/* Pagination Controls */}
        {pagination.totalPages > 1 && (
          <div className="flex items-center justify-between bg-white dark:bg-slate-900 px-4 py-3 border border-gray-100 dark:border-slate-800/80 sm:px-6 mt-6 rounded-xl shadow-sm text-start">
            <div className="flex flex-1 justify-between sm:hidden">
              <button
                onClick={() => setPagination(p => ({ ...p, page: Math.max(1, p.page - 1) }))}
                disabled={pagination.page === 1}
                type="button"
                className="relative inline-flex items-center rounded-md border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50 cursor-pointer"
              >
                {t('previous')}
              </button>
              <button
                onClick={() => setPagination(p => ({ ...p, page: Math.min(p.totalPages, p.page + 1) }))}
                disabled={pagination.page === pagination.totalPages}
                type="button"
                className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-50 cursor-pointer"
              >
                {t('next')}
              </button>
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700 dark:text-slate-300">
                  {t('responses_showing')} <span className="font-bold text-gray-900 dark:text-white">{(pagination.page - 1) * pagination.limit + 1}</span> {t('responses_to_pagination')} <span className="font-bold text-gray-900 dark:text-white">{Math.min(pagination.page * pagination.limit, pagination.total)}</span> {t('of')} <span className="font-bold text-gray-900 dark:text-white">{pagination.total}</span> {t('responses_result')}
                </p>
              </div>
              <div>
                <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                  <button
                    onClick={() => setPagination(p => ({ ...p, page: Math.max(1, p.page - 1) }))}
                    disabled={pagination.page === 1}
                    type="button"
                    className="relative inline-flex items-center rounded-r-md px-2.5 py-2 text-gray-400 dark:text-slate-400 ring-1 ring-inset ring-gray-300 dark:ring-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800 focus:z-20 focus:outline-offset-0 disabled:opacity-50 ml-1 cursor-pointer"
                  >
                    {t('previous')}
                  </button>
                  <button
                    onClick={() => setPagination(p => ({ ...p, page: Math.min(p.totalPages, p.page + 1) }))}
                    disabled={pagination.page === pagination.totalPages}
                    type="button"
                    className="relative inline-flex items-center rounded-l-md px-2.5 py-2 text-gray-400 dark:text-slate-400 ring-1 ring-inset ring-gray-300 dark:ring-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800 focus:z-20 focus:outline-offset-0 disabled:opacity-50 cursor-pointer"
                  >
                    {t('next')}
                  </button>
                </nav>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Response Detail Modal */}
      {selectedResponse && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in" onClick={() => setSelectedResponse(null)}>
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full max-h-[80vh] overflow-y-auto border border-gray-100 dark:border-slate-800 shadow-xl animate-scale-in" onClick={e => e.stopPropagation()}>
            <div className="p-6 border-b border-gray-100 dark:border-slate-800/80 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl">
              <h3 className="font-bold text-lg text-gray-800 dark:text-white">{t('responses_details_title')}</h3>
              <button onClick={() => setSelectedResponse(null)} type="button" className="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6 space-y-4 text-start">
              <div className="text-center mb-6">
                <div className={`inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br ${
                  selectedResponse.overallScore >= 85 ? 'from-green-500 to-emerald-500' :
                  selectedResponse.overallScore >= 70 ? 'from-blue-500 to-indigo-500' :
                  selectedResponse.overallScore >= 50 ? 'from-amber-500 to-orange-500' :
                  'from-red-500 to-rose-500'
                } shadow-xl mb-3`}>
                  <span className="text-2xl font-black text-white">{selectedResponse.overallScore}%</span>
                </div>
                <p className="text-sm text-gray-500 dark:text-slate-400">{t('satisfaction_rate')}</p>
              </div>

              {/* Name & Phone */}
              {(selectedResponse.patientInfo.name || selectedResponse.patientInfo.phone) && (
                <div className="grid grid-cols-2 gap-3">
                  {selectedResponse.patientInfo.name && (
                    <div className="bg-teal-50 dark:bg-teal-950/20 rounded-xl p-3 border border-teal-100/30">
                      <div className="text-xs text-teal-600 dark:text-teal-400 mb-1">{t('responses_name')}</div>
                      <div className="font-bold text-sm text-gray-800 dark:text-slate-100">{selectedResponse.patientInfo.name}</div>
                    </div>
                  )}
                  {selectedResponse.patientInfo.phone && (
                    <div className="bg-teal-50 dark:bg-teal-950/20 rounded-xl p-3 border border-teal-100/30">
                      <div className="text-xs text-teal-600 dark:text-teal-400 mb-1">{t('phone_number')}</div>
                      <div className="font-bold text-sm text-gray-800 dark:text-slate-100" dir="ltr">{selectedResponse.patientInfo.phone}</div>
                    </div>
                  )}
                </div>
              )}

              <div className="grid grid-cols-2 gap-3">
                <div className="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-3 border border-gray-100/30">
                  <div className="text-xs text-gray-500 dark:text-slate-400 mb-1">{t('department')}</div>
                  <div className="font-bold text-sm text-gray-800 dark:text-slate-100">{selectedResponse.department}</div>
                </div>
                <div className="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-3 border border-gray-100/30">
                  <div className="text-xs text-gray-500 dark:text-slate-400 mb-1">{t('gender')}</div>
                  <div className="font-bold text-sm text-gray-800 dark:text-slate-100">{selectedResponse.patientInfo.gender}</div>
                </div>
                <div className="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-3 border border-gray-100/30">
                  <div className="text-xs text-gray-500 dark:text-slate-400 mb-1">{t('age_group')}</div>
                  <div className="font-bold text-sm text-gray-800 dark:text-slate-100">{selectedResponse.patientInfo.ageGroup}</div>
                </div>
                <div className="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-3 border border-gray-100/30">
                  <div className="text-xs text-gray-500 dark:text-slate-400 mb-1">{t('visit_type')}</div>
                  <div className="font-bold text-sm text-gray-800 dark:text-slate-100">{selectedResponse.patientInfo.visitType}</div>
                </div>
              </div>

              <div className="bg-gray-50 dark:bg-slate-800/40 rounded-xl p-3 border border-gray-100/30">
                <div className="text-xs text-gray-500 dark:text-slate-400 mb-1">{t('responses_submission_date')}</div>
                <div className="font-bold text-sm text-gray-800 dark:text-slate-100">
                  {new Date(selectedResponse.submittedAt).toLocaleString(i18n.language === 'ar' ? 'ar-SA' : 'en-US')}
                </div>
              </div>

              <div className="space-y-3 mt-4">
                <h4 className="font-bold text-gray-700 dark:text-slate-200">{t('responses_detailed_answers')}</h4>
                {Object.entries(selectedResponse.answers).map(([key, val]) => {
                  if (!val && val !== 0) return null;
                  return (
                    <div key={key} className="flex items-center justify-between py-2 border-b border-gray-50 dark:border-slate-800/50">
                      <span className="text-sm text-gray-600 dark:text-slate-300 max-w-[70%]">{getQuestionTitle(selectedResponse.surveyId, key, selectedResponse.answers)}</span>
                      <span className="text-sm font-bold text-gray-800 dark:text-slate-100 shrink-0">
                        {typeof val === 'number' ? (
                          <span className="flex items-center gap-1">
                            {val}
                            {key !== 'q13' && <Star className="w-3 h-3 text-amber-400 fill-amber-400" />}
                          </span>
                        ) : val === 'yes' ? t('responses_yes') : val === 'no' ? t('responses_no') : String(val)}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Export Modal */}
      <ExportModal
        isOpen={showExportModal}
        onClose={() => setShowExportModal(false)}
        initialFilters={{
          search: debouncedSearch,
          score: filterScore,
          dateFilter: filterDate,
          startDate: customStartDate,
          endDate: customEndDate,
          hasName: filterHasName ? 'true' : undefined,
          hasPhone: filterHasPhone ? 'true' : undefined,
          gender: filterGender !== 'all' ? filterGender : undefined
        }}
      />
    </div>
  );
}
