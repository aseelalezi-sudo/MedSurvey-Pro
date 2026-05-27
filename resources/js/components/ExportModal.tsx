import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { analyticsService } from '../services/analyticsService';
import { exportToExcel, printPDF } from '../utils/exportUtils';
import { useSettingsStore } from '../store/useSettingsStore';
import { createLogger } from '../utils/logger';

const logger = createLogger('ExportModal');

import {
  X,
  Download,
  FileText,
  FileSpreadsheet,
  Check,
  Calendar,
  Building2,
  Printer,
  Info,
  AlertCircle,
} from 'lucide-react';

interface ExportModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: string;
  initialFilters?: {
    department?: string;
    dateFilter?: string;
    startDate?: string;
    endDate?: string;
    search?: string;
    score?: string;
    hasName?: string;
    hasPhone?: string;
    gender?: string;
  };
}

export default function ExportModal({ isOpen, onClose, title, initialFilters }: ExportModalProps) {
  const { t } = useTranslation();
  const { settings } = useSettingsStore();
  const exportTitle = title || t('export_default_title');
  const hospitalLogo = settings.hospital.logo;
  const hospitalName = settings.hospital.name || 'MedSurvey Pro';
  const [exportFormat, setExportFormat] = useState<'pdf' | 'excel' | 'print'>('pdf');
  const [dateRange, setDateRange] = useState<'all' | 'week' | 'month' | 'quarter' | 'custom'>(
    (initialFilters?.dateFilter as 'all' | 'week' | 'month' | 'quarter' | 'custom') || 'all'
  );
  const [selectedDepartment, setSelectedDepartment] = useState<string>(initialFilters?.department || 'all');
  const [isExporting, setIsExporting] = useState(false);
  const [exportSuccess, setExportSuccess] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);
  const [departments, setDepartments] = useState<string[]>([]);
  const [totalRecords, setTotalRecords] = useState(0);

  // Hidden advanced filters passed from ResponsesPage
  const advancedFilters = useMemo(() => ({
    search: initialFilters?.search,
    score: initialFilters?.score,
    hasName: initialFilters?.hasName,
    hasPhone: initialFilters?.hasPhone,
    gender: initialFilters?.gender,
    startDate: initialFilters?.startDate,
    endDate: initialFilters?.endDate
  }), [initialFilters?.search, initialFilters?.score, initialFilters?.hasName, initialFilters?.hasPhone, initialFilters?.gender, initialFilters?.startDate, initialFilters?.endDate]);

  useEffect(() => {
    if (isOpen) {
      setExportError(null);
      if (initialFilters) {
        if (initialFilters.dateFilter) setDateRange(initialFilters.dateFilter as 'all' | 'week' | 'month' | 'quarter' | 'custom');
        if (initialFilters.department) setSelectedDepartment(initialFilters.department);
      }
    }
  }, [isOpen, initialFilters]);

  useEffect(() => {
    if (isOpen) {
      import('../api/client').then(({ responsesAPI }) => {
        responsesAPI.getStats().then(stats => {
          setDepartments(stats.departmentScores.map((d: { name: string }) => d.name));
        });
      });
    }
  }, [isOpen]);

  useEffect(() => {
    if (isOpen) {
      import('../api/client').then(({ responsesAPI }) => {
        responsesAPI.getAll({
          limit: 1,
          department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
          dateFilter: dateRange !== 'all' ? dateRange : undefined,
          ...advancedFilters
        }).then(res => {
          setTotalRecords(res.pagination.total);
        });
      });
    }
  }, [isOpen, dateRange, selectedDepartment, initialFilters, advancedFilters]);

  const handleExport = async () => {
    setIsExporting(true);
    setExportSuccess(false);
    setExportError(null);
  
    try {
      const { responsesAPI } = await import('../api/client');
      const auditAction = exportFormat === 'print' ? 'print_report' : 'export_responses';
      const res = await responsesAPI.getAll({
        exportAll: true,
        auditAction,
        exportFormat,
        exportTitle,
        department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
        dateFilter: dateRange !== 'all' ? dateRange : undefined,
        ...advancedFilters
      });

      const filteredResponses = res.data;

      if (filteredResponses.length === 0) {
        setExportError(t('export_no_records_error'));
        setIsExporting(false);
        return;
      }

      const filteredStats = analyticsService.calculateDashboardStats(filteredResponses);

      let success = false;
      if (exportFormat === 'print' || exportFormat === 'pdf') {
        printPDF(filteredResponses, filteredStats, exportTitle, hospitalLogo, hospitalName);
        success = true;
      } else {
        success = await exportToExcel(filteredResponses, filteredStats, exportTitle);
      }

      if (success) {
        setExportSuccess(true);
        setTimeout(() => {
          setExportSuccess(false);
          onClose();
        }, 2000);
      } else if (!exportError) {
        setExportError(t('export_create_error'));
      }
    } catch (error) {
      const msg = error instanceof Error ? error.message : t('export_unexpected_error');
      logger.error('Export error:', error);
      if (error instanceof Error) {
        logger.error('Error stack:', error.stack || 'no stack');
      }
      setExportError(msg + (error instanceof Error ? ` (${error.name})` : ''));
    } finally {
      setIsExporting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-0 sm:p-4 animate-fade-in" onClick={onClose}>
      <div
        className="bg-white dark:bg-slate-900 w-full sm:max-w-lg sm:rounded-2xl rounded-t-3xl max-h-[95vh] sm:max-h-[90vh] overflow-hidden flex flex-col border border-gray-150 dark:border-slate-800 shadow-2xl animate-scale-in"
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-4 sm:p-6 border-b border-gray-100 dark:border-slate-800/80 flex items-center justify-between shrink-0 text-start">
          <div className="flex items-center gap-2 sm:gap-3">
            <div className="w-10 h-10 sm:w-12 sm:h-12 bg-linear-to-br from-teal-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-200 dark:shadow-teal-950/25">
              <Download className="w-5 h-5 sm:w-6 sm:h-6 text-white" />
            </div>
            <div>
              <h2 className="text-base sm:text-lg font-bold text-gray-800 dark:text-white">{t('export_title')}</h2>
              <p className="text-xs sm:text-sm text-gray-500 dark:text-slate-400">{t('export_subtitle')}</p>
            </div>
          </div>
          <button onClick={onClose} type="button" className="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 cursor-pointer">
            <X className="w-5 h-5 sm:w-6 sm:h-6" />
          </button>
        </div>

        {/* Scrollable Content */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 sm:space-y-6 text-start">
          {/* Export Format */}
          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-300 mb-2 sm:mb-3">{t('export_format')}</label>
            <div className="grid grid-cols-3 gap-2 sm:gap-3">
              {/* PDF */}
              <button
                onClick={() => setExportFormat('pdf')}
                type="button"
                className={`flex flex-col items-center gap-1.5 sm:gap-2 p-3 sm:p-4 rounded-xl border-2 transition-all cursor-pointer ${
                  exportFormat === 'pdf'
                    ? 'border-red-500 bg-red-50 dark:bg-red-950/20 shadow-md shadow-red-100 dark:shadow-none'
                    : 'border-gray-200 dark:border-slate-800 hover:border-gray-300 dark:hover:border-slate-700'
                }`}
              >
                <div className={`w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center ${
                  exportFormat === 'pdf' ? 'bg-red-500 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'
                }`}>
                  <FileText className="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <div className="text-center">
                  <p className={`font-bold text-xs sm:text-sm ${exportFormat === 'pdf' ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-slate-300'}`}>PDF</p>
                  <p className="text-[9px] sm:text-[10px] text-gray-500 dark:text-slate-400 hidden xs:block">{t('export_download_file')}</p>
                </div>
                {exportFormat === 'pdf' && <Check className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-red-500 dark:text-red-450" />}
              </button>

              {/* Excel */}
              <button
                onClick={() => setExportFormat('excel')}
                type="button"
                className={`flex flex-col items-center gap-1.5 sm:gap-2 p-3 sm:p-4 rounded-xl border-2 transition-all cursor-pointer ${
                  exportFormat === 'excel'
                    ? 'border-green-500 bg-green-50 dark:bg-green-950/20 shadow-md shadow-green-100 dark:shadow-none'
                    : 'border-gray-200 dark:border-slate-800 hover:border-gray-300 dark:hover:border-slate-700'
                }`}
              >
                <div className={`w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center ${
                  exportFormat === 'excel' ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'
                }`}>
                  <FileSpreadsheet className="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <div className="text-center">
                  <p className={`font-bold text-xs sm:text-sm ${exportFormat === 'excel' ? 'text-green-700 dark:text-green-400' : 'text-gray-700 dark:text-slate-300'}`}>Excel</p>
                  <p className="text-[9px] sm:text-[10px] text-gray-500 dark:text-slate-400 hidden xs:block">{t('export_spreadsheet')}</p>
                </div>
                {exportFormat === 'excel' && <Check className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-500 dark:text-green-450" />}
              </button>

              {/* Print */}
              <button
                onClick={() => setExportFormat('print')}
                type="button"
                className={`flex flex-col items-center gap-1.5 sm:gap-2 p-3 sm:p-4 rounded-xl border-2 transition-all cursor-pointer ${
                  exportFormat === 'print'
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/20 shadow-md shadow-blue-100 dark:shadow-none'
                    : 'border-gray-200 dark:border-slate-800 hover:border-gray-300 dark:hover:border-slate-700'
                }`}
              >
                <div className={`w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center ${
                  exportFormat === 'print' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'
                }`}>
                  <Printer className="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <div className="text-center">
                  <p className={`font-bold text-xs sm:text-sm ${exportFormat === 'print' ? 'text-blue-700 dark:text-blue-400' : 'text-gray-700 dark:text-slate-300'}`}>{t('export_print')}</p>
                  <p className="text-[9px] sm:text-[10px] text-gray-500 dark:text-slate-400 hidden xs:block">{t('export_direct_print')}</p>
                </div>
                {exportFormat === 'print' && <Check className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-500 dark:text-blue-450" />}
              </button>
            </div>
          </div>

          {/* Date Range Filter */}
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-300 mb-2 sm:mb-3">
              <Calendar className="w-4 h-4 text-teal-600 dark:text-teal-400" />
              {t('export_time_period')}
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {[
                { value: 'all', label: t('export_all') },
                { value: 'week', label: t('export_last_week') },
                { value: 'month', label: t('export_last_month') },
                { value: 'quarter', label: t('export_last_quarter') },
              ].map(option => (
                <button
                  key={option.value}
                  onClick={() => setDateRange(option.value as 'all' | 'week' | 'month' | 'quarter' | 'custom')}
                  type="button"
                  className={`px-2.5 sm:px-3 py-2 rounded-lg text-xs sm:text-sm font-medium transition-all cursor-pointer ${
                    dateRange === option.value
                      ? 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400 border border-teal-300 dark:border-teal-900/50'
                      : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-355 hover:bg-gray-200 dark:hover:bg-slate-700'
                  }`}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>

          {/* Department Filter */}
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-300 mb-2 sm:mb-3">
              <Building2 className="w-4 h-4 text-teal-600 dark:text-teal-400" />
              {t('export_department')}
            </label>
            <select
              value={selectedDepartment}
              onChange={e => setSelectedDepartment(e.target.value)}
              className="w-full px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm sm:text-base cursor-pointer"
            >
              <option value="all">{t('export_all_departments')}</option>
              {departments.map(dept => (
                <option key={dept} value={dept}>{dept}</option>
              ))}
            </select>
          </div>

          {/* Error message */}
          {exportError && (
            <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 rounded-xl p-3 sm:p-4 flex items-start gap-2 sm:gap-3 animate-fade-in">
              <AlertCircle className="w-4 h-4 sm:w-5 sm:h-5 text-red-500 shrink-0 mt-0.5" />
              <div className="text-xs sm:text-sm text-red-700 dark:text-red-300">
                <p className="font-bold mb-0.5 sm:mb-1">{t('export_error_title')}</p>
                <p className="text-red-600 dark:text-red-400">{exportError}</p>
              </div>
            </div>
          )}

          {/* Info message */}
          {!exportError && exportFormat !== 'print' && (
            <div className="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/30 rounded-xl p-3 sm:p-4 flex items-start gap-2 sm:gap-3">
              <Info className="w-4 h-4 sm:w-5 sm:h-5 text-blue-500 shrink-0 mt-0.5" />
              <div className="text-xs sm:text-sm text-blue-700 dark:text-blue-300">
                <p className="font-bold mb-0.5 sm:mb-1">{t('export_save_file')}</p>
                <p className="text-blue-600 dark:text-blue-400">
                  {t('export_save_file_desc')}
                </p>
              </div>
            </div>
          )}

          {exportFormat === 'print' && (
            <div className="bg-purple-50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/30 rounded-xl p-3 sm:p-4 flex items-start gap-2 sm:gap-3">
              <Printer className="w-4 h-4 sm:w-5 sm:h-5 text-purple-500 shrink-0 mt-0.5" />
              <div className="text-xs sm:text-sm text-purple-700 dark:text-purple-300">
                <p className="font-bold mb-0.5 sm:mb-1">{t('export_direct_print')}</p>
                <p className="text-purple-600 dark:text-purple-400">
                  {t('export_print_desc')}
                </p>
              </div>
            </div>
          )}

          <div className="bg-gray-50 dark:bg-slate-800/40 border border-gray-100/30 rounded-xl p-3 sm:p-4">
            <div className="flex items-center justify-between text-xs sm:text-sm">
              <span className="text-gray-500 dark:text-slate-400">{t('export_estimated_records')}</span>
              <span className="font-bold text-gray-800 dark:text-slate-100">{totalRecords} {t('export_record')}</span>
            </div>
          </div>
        </div>

        {/* Footer - Fixed at bottom */}
        <div className="p-4 sm:p-6 border-t border-gray-100 dark:border-slate-800 flex items-center gap-2 sm:gap-3 shrink-0 bg-white dark:bg-slate-900">
          <button
            onClick={onClose}
            type="button"
            className="flex-1 px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors text-sm sm:text-base cursor-pointer"
          >
            {t('export_cancel')}
          </button>
          <button
            onClick={handleExport}
            disabled={isExporting || totalRecords === 0}
            type="button"
            className={`flex-1 flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl font-bold text-white transition-all text-sm sm:text-base cursor-pointer ${
              isExporting || totalRecords === 0
                ? 'bg-gray-300 dark:bg-slate-700 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'
                : exportSuccess
                  ? 'bg-green-500'
                  : 'bg-linear-to-l from-teal-600 to-emerald-600 shadow-lg shadow-teal-200 dark:shadow-teal-950/25 hover:shadow-xl'
            }`}
          >
            {isExporting ? (
              <>
                <div className="w-4 h-4 sm:w-5 sm:h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                <span className="text-xs sm:text-sm">{exportFormat === 'print' ? t('export_preparing') : t('export_exporting')}</span>
              </>
            ) : exportSuccess ? (
              <>
                <Check className="w-4 h-4 sm:w-5 sm:h-5" />
                <span className="text-xs sm:text-sm">{exportFormat === 'print' ? t('export_done') : t('export_exported')}</span>
              </>
            ) : (
              <>
                {exportFormat === 'print' ? (
                  <Printer className="w-4 h-4 sm:w-5 sm:h-5" />
                ) : (
                  <Download className="w-4 h-4 sm:w-5 sm:h-5" />
                )}
                <span className="text-xs sm:text-sm">
                  {exportFormat === 'print' ? t('export_print') : exportFormat === 'pdf' ? t('export_download_pdf') : t('export_download_excel')}
                </span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
