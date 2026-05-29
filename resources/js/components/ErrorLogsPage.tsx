import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { errorLogsAPI, ErrorLogEntry, ErrorLogStats } from '../api/client';
import { useAuthStore } from '../store/useAuthStore';
import { createLogger } from '../utils/logger';

const logger = createLogger('ErrorLogsPage');
import {
  AlertTriangle,
  AlertCircle,
  Info,
  CheckCircle2,
  Search,
  RefreshCw,
  X,
  ExternalLink,
  Bug,
  ChevronLeft,
  ChevronRight,
  Trash2,
} from 'lucide-react';

const LEVEL_STYLES: Record<string, { bg: string; text: string; icon: typeof AlertTriangle }> = {
  error: { bg: 'bg-red-100 dark:bg-red-950/30', text: 'text-red-700 dark:text-red-400', icon: AlertCircle },
  warn: { bg: 'bg-amber-100 dark:bg-amber-950/30', text: 'text-amber-700 dark:text-amber-400', icon: AlertTriangle },
  info: { bg: 'bg-blue-100 dark:bg-blue-950/30', text: 'text-blue-700 dark:text-blue-400', icon: Info },
};

const STATUS_STYLES: Record<string, { bg: string; text: string }> = {
  new: { bg: 'bg-red-100 dark:bg-red-950/30', text: 'text-red-700 dark:text-red-400' },
  in_progress: { bg: 'bg-amber-100 dark:bg-amber-950/30', text: 'text-amber-700 dark:text-amber-400' },
  resolved: { bg: 'bg-green-100 dark:bg-green-950/30', text: 'text-green-700 dark:text-green-400' },
};

function formatErrorDateTime(value: string, language: string) {
  return new Date(value).toLocaleString(language === 'ar' ? 'ar-SA' : 'en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function ErrorLogsPage() {
  const { currentUser } = useAuthStore();
  const { t, i18n } = useTranslation();
  const isSuperAdmin = currentUser?.role === 'super_admin';
  const [logs, setLogs] = useState<ErrorLogEntry[]>([]);
  const [stats, setStats] = useState<ErrorLogStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({ page: 1, limit: 50, total: 0, totalPages: 0 });

  const [filterLevel, setFilterLevel] = useState('all');
  const [filterStatus, setFilterStatus] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  const [selectedLog, setSelectedLog] = useState<ErrorLogEntry | null>(null);
  const [actionStatus, setActionStatus] = useState('new');
  const [actionNotes, setActionNotes] = useState('');
  const [isClearing, setIsClearing] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const levelLabel = (level: string) => {
    if (level === 'error') return t('error_logs_level_error');
    if (level === 'warn') return t('error_logs_level_warn');
    return t('error_logs_level_info');
  };

  const statusLabel = (status: string) => {
    if (status === 'in_progress') return t('error_logs_status_in_progress');
    if (status === 'resolved') return t('error_logs_status_resolved');
    return t('error_logs_status_new');
  };

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [logsRes, statsRes] = await Promise.all([
        errorLogsAPI.getAll({
          level: filterLevel !== 'all' ? filterLevel : undefined,
          status: filterStatus !== 'all' ? filterStatus : undefined,
          search: searchQuery || undefined,
          page: pagination.page,
          limit: pagination.limit,
        }),
        errorLogsAPI.getStats(),
      ]);
      setLogs(logsRes.data);
      setPagination(logsRes.pagination);
      setStats(statsRes);
    } catch {
      logger.error('Failed to load error logs');
    }
    setLoading(false);
  }, [filterLevel, filterStatus, searchQuery, pagination.page, pagination.limit]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleUpdateStatus = async () => {
    if (!selectedLog) return;
    try {
      await errorLogsAPI.update(selectedLog.id, { status: actionStatus, resolutionNotes: actionNotes || undefined });
      setSelectedLog(null);
      setActionNotes('');
      loadData();
    } catch {
      logger.error('Failed to update log status');
    }
  };

  const handleClearLogs = async () => {
    if (!isSuperAdmin || isClearing) return;
    const confirmed = window.confirm(t('error_logs_clear_confirm'));
    if (!confirmed) return;

    setIsClearing(true);
    try {
      await errorLogsAPI.clearAll();
      setLogs([]);
      setStats({ byLevel: [], byStatus: [], topSources: [] });
      setSelectedLog(null);
      setPagination((p) => ({ ...p, page: 1, total: 0, totalPages: 0 }));
      await loadData();
    } catch {
      logger.error('Failed to clear logs');
    }
    setIsClearing(false);
  };

  const handleDeleteSelectedLog = async () => {
    if (!isSuperAdmin || !selectedLog || isDeleting) return;
    const confirmed = window.confirm(t('error_logs_delete_confirm'));
    if (!confirmed) return;

    setIsDeleting(true);
    try {
      await errorLogsAPI.deleteOne(selectedLog.id);
      setSelectedLog(null);
      setLogs((current) => current.filter((log) => log.id !== selectedLog.id));
      await loadData();
    } catch {
      logger.error('Failed to delete log');
    }
    setIsDeleting(false);
  };

  const openActionModal = (log: ErrorLogEntry) => {
    setSelectedLog(log);
    setActionStatus(log.status);
    setActionNotes(log.resolutionNotes || '');
  };

  const totalNew = stats?.byStatus.find((s) => s.status === 'new')?.count || 0;
  const totalInProgress = stats?.byStatus.find((s) => s.status === 'in_progress')?.count || 0;
  const totalError = stats?.byLevel.find((l) => l.level === 'error')?.count || 0;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
            <Bug className="w-6 h-6 text-red-500" />
            {t('error_logs_title')}
          </h1>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{t('error_logs_subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          {isSuperAdmin && (
            <button
              onClick={handleClearLogs}
              disabled={isClearing || loading || pagination.total === 0}
              className="flex items-center gap-2 px-4 py-2 bg-red-600 border border-red-600 rounded-xl text-sm font-bold text-white hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
            >
              <Trash2 className="w-4 h-4" />
              {isClearing ? t('error_logs_clearing') : t('error_logs_clear')}
            </button>
          )}
          <button
            onClick={loadData}
            className="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-all cursor-pointer"
          >
            <RefreshCw className="w-4 h-4" />
            {t('refresh')}
          </button>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-2">
            <div className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/30 flex items-center justify-center">
              <AlertCircle className="w-4 h-4 text-red-600 dark:text-red-400" />
            </div>
            <span className="text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_errors_7_days')}</span>
          </div>
          <span className="text-2xl font-black text-gray-900 dark:text-white">{totalError}</span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-2">
            <div className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/30 flex items-center justify-center">
              <X className="w-4 h-4 text-red-600 dark:text-red-400" />
            </div>
            <span className="text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_status_new')}</span>
          </div>
          <span className="text-2xl font-black text-gray-900 dark:text-white">{totalNew}</span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-2">
            <div className="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950/30 flex items-center justify-center">
              <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400" />
            </div>
            <span className="text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_status_in_progress')}</span>
          </div>
          <span className="text-2xl font-black text-gray-900 dark:text-white">{totalInProgress}</span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
          <div className="flex items-center gap-2 mb-2">
            <div className="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-950/30 flex items-center justify-center">
              <CheckCircle2 className="w-4 h-4 text-green-600 dark:text-green-400" />
            </div>
            <span className="text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_sources')}</span>
          </div>
          <span className="text-2xl font-black text-gray-900 dark:text-white">{stats?.topSources.length || 0}</span>
        </div>
      </div>

      <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setPagination((p) => ({ ...p, page: 1 }));
              }}
              placeholder={t('error_logs_search_placeholder')}
              className="w-full pr-10 pl-4 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/30 focus:border-teal-500 dark:text-slate-200 transition-all"
            />
          </div>
          <select
            value={filterLevel}
            onChange={(e) => {
              setFilterLevel(e.target.value);
              setPagination((p) => ({ ...p, page: 1 }));
            }}
            className="px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/30 dark:text-slate-200 cursor-pointer"
          >
            <option value="all">{t('error_logs_all_levels')}</option>
            <option value="error">{t('error_logs_level_error')}</option>
            <option value="warn">{t('error_logs_level_warn')}</option>
            <option value="info">{t('error_logs_level_info')}</option>
          </select>
          <select
            value={filterStatus}
            onChange={(e) => {
              setFilterStatus(e.target.value);
              setPagination((p) => ({ ...p, page: 1 }));
            }}
            className="px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/30 dark:text-slate-200 cursor-pointer"
          >
            <option value="all">{t('error_logs_all_statuses')}</option>
            <option value="new">{t('error_logs_status_new')}</option>
            <option value="in_progress">{t('error_logs_status_in_progress')}</option>
            <option value="resolved">{t('error_logs_status_resolved')}</option>
          </select>
        </div>
      </div>

      {stats && stats.topSources.length > 0 && (
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-4 rounded-2xl shadow-sm">
          <h3 className="text-xs font-bold text-gray-500 dark:text-slate-400 mb-3">{t('error_logs_top_sources')}</h3>
          <div className="flex flex-wrap gap-2">
            {stats.topSources.map((s) => (
              <span
                key={s.source}
                className="px-3 py-1.5 bg-gray-100 dark:bg-slate-800 rounded-lg text-xs font-bold text-gray-600 dark:text-slate-300"
              >
                {s.source || t('unknown')} ({s.count})
              </span>
            ))}
          </div>
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/50">
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_level')}</th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400">{t('error_logs_message')}</th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400 hidden sm:table-cell">
                  {t('error_logs_source')}
                </th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400 hidden md:table-cell">
                  {t('error_logs_status')}
                </th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400 hidden md:table-cell">
                  {t('error_logs_count')}
                </th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400">
                  {t('date_time')}
                </th>
                <th className="text-start px-4 py-3 text-xs font-bold text-gray-500 dark:text-slate-400"></th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="text-center py-12">
                    <div className="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mx-auto" />
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-center py-12 text-gray-400 dark:text-slate-500 text-sm font-bold">
                    {searchQuery || filterLevel !== 'all' || filterStatus !== 'all'
                      ? t('error_logs_no_matching_results')
                      : t('error_logs_no_logs')}
                  </td>
                </tr>
              ) : (
                logs.map((log) => {
                  const levelStyle = LEVEL_STYLES[log.level] || LEVEL_STYLES.error;
                  const LevelIcon = levelStyle.icon;
                  const statusStyle = STATUS_STYLES[log.status] || STATUS_STYLES.new;
                  return (
                    <tr
                      key={log.id}
                      className="border-b border-gray-50 dark:border-slate-800/60 hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors"
                    >
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-bold ${levelStyle.bg} ${levelStyle.text}`}
                        >
                          <LevelIcon className="w-3 h-3" />
                          {levelLabel(log.level)}
                        </span>
                      </td>
                      <td className="px-4 py-3 max-w-[200px] sm:max-w-xs">
                        <span className="text-gray-800 dark:text-slate-200 font-semibold block truncate" title={log.message}>
                          {log.message}
                        </span>
                      </td>
                      <td className="px-4 py-3 hidden sm:table-cell">
                        <span
                          className="text-gray-500 dark:text-slate-400 text-xs font-medium truncate block max-w-[120px]"
                          title={log.source || ''}
                        >
                          {log.source || '-'}
                        </span>
                      </td>
                      <td className="px-4 py-3 hidden md:table-cell">
                        <span className={`px-2 py-1 rounded-lg text-[11px] font-bold ${statusStyle.bg} ${statusStyle.text}`}>
                          {statusLabel(log.status)}
                        </span>
                      </td>
                      <td className="px-4 py-3 hidden md:table-cell">
                        {log.count > 1 ? (
                          <span className="inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 bg-gray-100 dark:bg-slate-800 rounded-full text-xs font-bold text-gray-600 dark:text-slate-300">
                            {log.count}
                          </span>
                        ) : (
                          <span className="text-gray-400 dark:text-slate-500 text-xs">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-500 dark:text-slate-400 font-medium whitespace-nowrap">
                        {formatErrorDateTime(log.createdAt, i18n.language)}
                      </td>
                      <td className="px-4 py-3">
                        <button
                          onClick={() => openActionModal(log)}
                          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-all cursor-pointer"
                          title={t('error_logs_details_action')}
                        >
                          <ExternalLink className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {pagination.totalPages > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-slate-800">
            <span className="text-xs text-gray-500 dark:text-slate-400 font-medium">
              {t('pagination_summary', { total: pagination.total, page: pagination.page, totalPages: pagination.totalPages })}
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={pagination.page <= 1}
                onClick={() => setPagination((p) => ({ ...p, page: p.page - 1 }))}
                className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 disabled:opacity-30 text-gray-500 dark:text-slate-400 cursor-pointer disabled:cursor-not-allowed"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
              <button
                disabled={pagination.page >= pagination.totalPages}
                onClick={() => setPagination((p) => ({ ...p, page: p.page + 1 }))}
                className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 disabled:opacity-30 text-gray-500 dark:text-slate-400 cursor-pointer disabled:cursor-not-allowed"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {selectedLog && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm animate-fade-in"
          onClick={() => setSelectedLog(null)}
        >
          <div
            className="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-gray-200 dark:border-slate-800 w-full max-w-lg animate-scale-in overflow-hidden"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-lg font-black text-gray-900 dark:text-white">{t('error_logs_details_title')}</h2>
                <button onClick={() => setSelectedLog(null)} className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 text-gray-400 cursor-pointer">
                  <X className="w-5 h-5" />
                </button>
              </div>

              <div className="space-y-4 mb-6">
                <div>
                  <span className="text-xs font-bold text-gray-500 dark:text-slate-400 block mb-1">{t('error_logs_message')}</span>
                  <p className="text-sm font-semibold text-gray-800 dark:text-slate-200 bg-gray-50 dark:bg-slate-800 p-3 rounded-xl">
                    {selectedLog.message}
                  </p>
                </div>
                {selectedLog.source && (
                  <div>
                    <span className="text-xs font-bold text-gray-500 dark:text-slate-400 block mb-1">{t('error_logs_source')}</span>
                    <p className="text-sm font-medium text-gray-600 dark:text-slate-300">{selectedLog.source}</p>
                  </div>
                )}
                {selectedLog.stack && (
                  <div>
                    <span className="text-xs font-bold text-gray-500 dark:text-slate-400 block mb-1">Stack Trace</span>
                    <pre className="text-[11px] text-gray-500 dark:text-slate-400 bg-gray-50 dark:bg-slate-800 p-3 rounded-xl overflow-x-auto max-h-32 leading-relaxed font-mono">
                      {selectedLog.stack}
                    </pre>
                  </div>
                )}
                {selectedLog.count > 1 && (
                  <div className="flex items-center gap-2">
                    <span className="px-2.5 py-1 bg-amber-100 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 rounded-lg text-xs font-bold">
                      {t('error_logs_repeated_count', { count: selectedLog.count })}
                    </span>
                  </div>
                )}
                <div>
                  <span className="text-xs font-bold text-gray-500 dark:text-slate-400 block mb-1">{t('error_logs_update_status')}</span>
                  <select
                    value={actionStatus}
                    onChange={(e) => setActionStatus(e.target.value)}
                    className="w-full px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/30 dark:text-slate-200 cursor-pointer"
                  >
                    <option value="new">{t('error_logs_status_new')}</option>
                    <option value="in_progress">{t('error_logs_status_in_progress')}</option>
                    <option value="resolved">{t('error_logs_status_resolved')}</option>
                  </select>
                </div>
                <div>
                  <span className="text-xs font-bold text-gray-500 dark:text-slate-400 block mb-1">{t('error_logs_resolution_notes')}</span>
                  <textarea
                    value={actionNotes}
                    onChange={(e) => setActionNotes(e.target.value)}
                    placeholder={t('error_logs_resolution_notes_placeholder')}
                    className="w-full px-3 py-2.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm outline-none focus:ring-2 focus:ring-teal-500/30 dark:text-slate-200 resize-none min-h-[80px]"
                  />
                </div>
              </div>

              <div className="flex gap-3">
                {isSuperAdmin && (
                  <button
                    onClick={handleDeleteSelectedLog}
                    disabled={isDeleting}
                    className="flex-1 py-2.5 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 rounded-xl text-sm font-bold hover:bg-red-50 dark:hover:bg-red-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                  >
                    {isDeleting ? t('error_logs_deleting') : t('error_logs_delete')}
                  </button>
                )}
                <button
                  onClick={() => setSelectedLog(null)}
                  className="flex-1 py-2.5 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-slate-800 transition-all cursor-pointer"
                >
                  {t('cancel')}
                </button>
                <button
                  onClick={handleUpdateStatus}
                  className="flex-1 py-2.5 bg-linear-to-r from-teal-600 to-emerald-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl transition-all cursor-pointer"
                >
                  {t('save_changes')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
