import { Loader2, FileSearch, Database, FileArchive, Upload, AlertCircle, CheckCircle2, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BackupFile, BackupVerification } from '../../api/client';

interface ExternalRestoreTabProps {
  externalDir: string;
  setExternalDir: (dir: string) => void;
  handleScanExternal: () => void;
  scanning: boolean;
  scanAttempted: boolean;
  externalFiles: (BackupFile & { fullPath: string })[];
  externalVerifications: Record<string, BackupVerification>;
  verifyingExternalPath: string | null;
  handleVerifyExternal: (fullPath: string) => void;
  handleRestoreExternal: (fullPath: string, filename: string) => void;
  restoringFilename: string | null;
  formatDate: (iso: string) => string;
}

export function ExternalRestoreTab({
  externalDir,
  setExternalDir,
  handleScanExternal,
  scanning,
  scanAttempted,
  externalFiles,
  externalVerifications,
  verifyingExternalPath,
  handleVerifyExternal,
  handleRestoreExternal,
  restoringFilename,
  formatDate,
}: ExternalRestoreTabProps) {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-6 space-y-4">
        <div className="space-y-2">
          <h2 className="text-lg font-bold text-slate-800 dark:text-white">{t('backup_external_path_title')}</h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            {t('backup_external_path_desc')}
          </p>
        </div>

        <div className="flex flex-col sm:flex-row gap-3">
          <input
            type="text"
            value={externalDir}
            onChange={(e) => setExternalDir(e.target.value)}
            placeholder={t('backup_external_path_placeholder')}
            className="flex-1 px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
          <button
            onClick={handleScanExternal}
            disabled={scanning}
            className="px-6 py-3 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 disabled:bg-teal-400 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0 cursor-pointer"
          >
            {scanning ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                {t('backup_scanning')}
              </>
            ) : (
              <>
                <FileSearch className="w-4 h-4" />
                {t('backup_scan_folder')}
              </>
            )}
          </button>
        </div>
      </div>

      {scanAttempted && (
        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
          <div className="p-5 border-b border-slate-100 dark:border-slate-800">
            <h3 className="text-md font-bold text-slate-800 dark:text-white">{t('backup_discovered_files')}</h3>
          </div>

          {externalFiles.length === 0 ? (
            <div className="p-12 text-center text-slate-500 dark:text-slate-400">
              <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
              {t('backup_no_external_files_prefix')}{' '}
              <code className="text-teal-500">.sql.gz</code>{' '}
              {t('backup_no_external_files_suffix')}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-100 dark:border-slate-800">
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backup_file_name')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backup_size')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backup_modified_at')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_status', 'الحالة')}</th>
                    <th className="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_actions', 'إجراءات')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {externalFiles.map((file) => {
                    const verification = externalVerifications[file.fullPath];
                    const isVerifying = verifyingExternalPath === file.fullPath;
                    const canRestore = verification?.valid === true;

                    return (
                      <tr key={file.fullPath} className="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td className="p-4">
                          <div className="flex items-center gap-2">
                            <FileArchive className="w-4 h-4 text-teal-500 shrink-0" />
                            <span className="text-slate-700 dark:text-slate-300 font-medium break-all">{file.filename}</span>
                          </div>
                        </td>
                        <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{file.sizeMb} MB</td>
                        <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{formatDate(file.createdAt)}</td>
                        <td className="p-4">
                          {!verification ? (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                              <AlertCircle className="w-3 h-3" />
                              {t('backups_status_unverified', 'لم يتم التحقق')}
                            </span>
                          ) : verification.valid ? (
                            <div className="space-y-1">
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                <CheckCircle2 className="w-3 h-3" />
                                {t('backups_status_valid', 'صالحة')}
                              </span>
                              <div className="text-xs text-slate-500 dark:text-slate-400">
                                {t('backups_verify_tables', '{{count}} جدول', { count: verification.tableCount })}
                                {t('backups_verify_rows', ', {{count}} صف', { count: verification.estimatedRows })}
                              </div>
                            </div>
                          ) : (
                            <div className="space-y-1">
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                <XCircle className="w-3 h-3" />
                                {t('backups_status_invalid', 'غير صالحة')}
                              </span>
                              {verification.error && (
                                <div className="text-xs text-red-600 dark:text-red-300 max-w-xs break-words">{verification.error}</div>
                              )}
                            </div>
                          )}
                        </td>
                        <td className="p-4 whitespace-nowrap text-left">
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => handleVerifyExternal(file.fullPath)}
                              disabled={isVerifying}
                              className="px-3 py-1.5 text-xs font-bold text-teal-700 bg-teal-50 hover:bg-teal-100 disabled:opacity-50 dark:text-teal-300 dark:bg-teal-900/20 dark:hover:bg-teal-900/30 rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                            >
                              {isVerifying ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                              ) : (
                                <FileSearch className="w-3.5 h-3.5" />
                              )}
                              {t('backups_btn_verify', 'التحقق من الملف')}
                            </button>
                            <button
                              onClick={() => handleRestoreExternal(file.fullPath, file.filename)}
                              disabled={restoringFilename === file.filename || !canRestore}
                              className="px-4 py-1.5 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 disabled:cursor-not-allowed rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                            >
                              {restoringFilename === file.filename ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                              ) : (
                                <Upload className="w-3.5 h-3.5" />
                              )}
                              {t('backup_restore')}
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
