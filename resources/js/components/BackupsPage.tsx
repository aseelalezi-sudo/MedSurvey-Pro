import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Database,
  Download,
  Trash2,
  RefreshCcw,
  Clock,
  HardDrive,
  Calendar,
  Shield,
  AlertCircle,
  CheckCircle2,
  FileArchive,
  FileSearch,
  XCircle,
  Loader2,
  Upload,
} from 'lucide-react';
import { backupsAPI, BackupListResponse, BackupVerification, getToken, BackupFile } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('BackupsPage');

export default function BackupsPage() {
  const { t } = useTranslation();
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const [verifications, setVerifications] = useState<Record<string, BackupVerification>>({});
  const [verifyingFilename, setVerifyingFilename] = useState<string | null>(null);
  const [restoringFilename, setRestoringFilename] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState('');
  const [confirmModal, setConfirmModal] = useState<{
    isOpen: boolean;
    type: 'restore' | 'delete' | 'upload_restore' | 'external_restore' | null;
    filename: string;
    extraData?: string;
  }>({
    isOpen: false,
    type: null,
    filename: '',
  });

  const [activeTab, setActiveTab] = useState<'local' | 'upload' | 'external'>('local');
  const [downloadingFilename, setDownloadingFilename] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [externalDir, setExternalDir] = useState('');
  const [scanning, setScanning] = useState(false);
  const [externalFiles, setExternalFiles] = useState<(BackupFile & { fullPath: string })[]>([]);
  const [scanAttempted, setScanAttempted] = useState(false);

  const fetchBackups = useCallback(async () => {
    try {
      setError('');
      const res = await backupsAPI.list();
      setData(res);
    } catch (err) {
      logger.error('Failed to fetch backups:', err);
      setError(t('backups_error_load_list', 'فشل في تحميل قائمة النسخ الاحتياطية'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchBackups();
  }, [fetchBackups]);

  const handleCreateBackup = async () => {
    setCreating(true);
    setError('');
    try {
      const res = await backupsAPI.create();
      const verifyResult = res?.verification;
      if (verifyResult) {
        setVerifications(prev => ({ ...prev, [verifyResult.filename]: verifyResult }));
      }
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_create', 'فشل في إنشاء النسخة الاحتياطية');
      setError(msg);
      logger.error('Backup creation failed:', err);
    } finally {
      setCreating(false);
    }
  };

  const handleVerify = async (filename: string) => {
    setVerifyingFilename(filename);
    try {
      const result = await backupsAPI.verify(filename);
      setVerifications(prev => ({ ...prev, [filename]: result }));
    } catch (err) {
      logger.error('Verification failed:', err);
      setError(t('backups_error_verify', 'فشل في التحقق من الملف'));
    } finally {
      setVerifyingFilename(null);
    }
  };

  const handleRestore = (filename: string) => {
    setConfirmModal({
      isOpen: true,
      type: 'restore',
      filename,
    });
  };

  const handleDeleteBackup = (filename: string) => {
    setConfirmModal({
      isOpen: true,
      type: 'delete',
      filename,
    });
  };

  const handleDownloadBackup = async (filename: string) => {
    setDownloadingFilename(filename);
    setError('');
    try {
      const token = getToken();
      const headers: Record<string, string> = {};
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
      const response = await fetch(`/api/backups/${encodeURIComponent(filename)}/download`, {
        headers,
      });
      if (!response.ok) {
        throw new Error(t('backups_error_download_from_server', 'فشل في تحميل ملف النسخة الاحتياطية من الخادم'));
      }
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      logger.error('Failed to download backup:', err);
      setError(t('backups_error_download', 'فشل في تنزيل ملف النسخة الاحتياطية'));
    } finally {
      setDownloadingFilename(null);
    }
  };

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.name.endsWith('.sql.gz')) {
      setError(t('backups_error_invalid_file', 'نوع ملف غير صالح. الرجاء تحديد ملف ينتهي بامتداد .sql.gz'));
      return;
    }

    setUploading(true);
    setError('');
    setSuccessMessage('');

    try {
      const reader = new FileReader();
      reader.onload = async (e) => {
        try {
          const result = e.target?.result as string;
          const base64Content = result.split(',')[1] || result;

          setConfirmModal({
            isOpen: true,
            type: 'upload_restore',
            filename: file.name,
            extraData: base64Content,
          });
        } catch (err) {
          logger.error('Error processing uploaded file:', err);
          setError(t('backups_error_process_uploaded', 'فشل في معالجة ملف النسخة الاحتياطية المرفوع'));
        } finally {
          setUploading(false);
        }
      };
      reader.readAsDataURL(file);
    } catch (err) {
      logger.error('Upload failed:', err);
      setError(t('backups_error_read_file', 'فشل في قراءة الملف'));
      setUploading(false);
    }
  };

  const handleScanExternal = async () => {
    if (!externalDir.trim()) {
      setError(t('backups_error_no_path', 'الرجاء إدخال مسار المجلد أولاً'));
      return;
    }
    setScanning(true);
    setError('');
    setSuccessMessage('');
    setScanAttempted(true);
    try {
      const res = await backupsAPI.scanExternal(externalDir);
      setExternalFiles(res.backups);
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_read_dir', 'فشل في قراءة المجلد');
      setError(msg);
      setExternalFiles([]);
    } finally {
      setScanning(false);
    }
  };

  const handleRestoreExternal = (fullPath: string, filename: string) => {
    setConfirmModal({
      isOpen: true,
      type: 'external_restore',
      filename,
      extraData: fullPath,
    });
  };

  const executeRestore = async () => {
    const filename = confirmModal.filename;
    setConfirmModal({ isOpen: false, type: null, filename: '' });
    setError('');
    setSuccessMessage('');
    setRestoringFilename(filename);
    try {
      await backupsAPI.restore(filename);
      setSuccessMessage(t('backups_success_restore_local', `✅ تم استعادة قاعدة البيانات بنجاح من "${filename}"`, { filename }));
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_restore', 'فشل في استعادة قاعدة البيانات');
      setError(msg);
      logger.error('Restore failed:', err);
    } finally {
      setRestoringFilename(null);
    }
  };

  const executeUploadRestore = async () => {
    const filename = confirmModal.filename;
    const content = confirmModal.extraData || '';
    setConfirmModal({ isOpen: false, type: null, filename: '' });
    setError('');
    setSuccessMessage('');
    setRestoringFilename(filename);
    try {
      await backupsAPI.uploadRestore(filename, content);
      setSuccessMessage(t('backups_success_restore_uploaded', `✅ تم استعادة قاعدة البيانات بنجاح من الملف المرفوع "${filename}"`, { filename }));
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_restore_uploaded', 'فشل في استعادة قاعدة البيانات من الملف المرفوع');
      setError(msg);
      logger.error('Upload restore failed:', err);
    } finally {
      setRestoringFilename(null);
    }
  };

  const executeExternalRestore = async () => {
    const filename = confirmModal.filename;
    const filepath = confirmModal.extraData || '';
    setConfirmModal({ isOpen: false, type: null, filename: '' });
    setError('');
    setSuccessMessage('');
    setRestoringFilename(filename);
    try {
      await backupsAPI.restoreExternal(filepath);
      setSuccessMessage(t('backups_success_restore_external', `✅ تم استعادة قاعدة البيانات بنجاح من الملف الخارجي "${filename}"`, { filename }));
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_restore_dir', 'فشل في استعادة قاعدة البيانات من المجلد المحدد');
      setError(msg);
      logger.error('External restore failed:', err);
    } finally {
      setRestoringFilename(null);
    }
  };

  const executeDelete = async () => {
    const filename = confirmModal.filename;
    setConfirmModal({ isOpen: false, type: null, filename: '' });
    try {
      setError('');
      await backupsAPI.delete(filename);
      setSuccessMessage(t('backups_success_delete', `✅ تم حذف ملف النسخة الاحتياطية "${filename}" بنجاح`, { filename }));
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : t('backups_error_delete', 'فشل في حذف الملف');
      setError(msg);
      logger.error('Backup deletion failed:', err);
    }
  };

  const formatDate = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleDateString('ar-SA', {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  };

  const totalSize = data?.backups.reduce((sum, f) => sum + f.sizeMb, 0) || 0;
  const latestBackup = data?.backups[0] || null;
  const config = data?.config;

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 w-48 bg-slate-200 dark:bg-slate-700 rounded" />
          <div className="h-32 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="h-64 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <Database className="w-6 h-6 text-teal-500" />
            {t('backups_title', 'النسخ الاحتياطي لقاعدة البيانات')}
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {t('backups_subtitle', 'إدارة النسخ الاحتياطية التلقائية واليدوية لقاعدة البيانات')}
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={fetchBackups}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            <RefreshCcw className="w-4 h-4" />
            {t('backups_btn_refresh', 'تحديث')}
          </button>
          <button
            onClick={handleCreateBackup}
            disabled={creating}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-500/20"
          >
            <Download className="w-4 h-4" />
            {creating ? t('backups_creating', 'جاري الإنشاء...') : t('backups_create_btn', 'إنشاء نسخة احتياطية')}
          </button>
        </div>
      </div>

      {error && (
        <div className="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-300 text-sm">
          <AlertCircle className="w-5 h-5 shrink-0" />
          {error}
        </div>
      )}

      {successMessage && (
        <div className="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-300 text-sm">
          <CheckCircle2 className="w-5 h-5 shrink-0" />
          {successMessage}
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-teal-100 dark:bg-teal-900/30 rounded-xl">
              <FileArchive className="w-5 h-5 text-teal-600 dark:text-teal-400" />
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">{t('backups_total_count', 'إجمالي النسخ')}</p>
              <p className="text-xl font-bold text-slate-800 dark:text-white">{data?.backups.length || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
              <HardDrive className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">{t('backups_total_size', 'الحجم الإجمالي')}</p>
              <p className="text-xl font-bold text-slate-800 dark:text-white">{totalSize.toFixed(2)} MB</p>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-xl">
              <Calendar className="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">{t('backups_retention_period', 'مدة الاحتفاظ')}</p>
              <p className="text-xl font-bold text-slate-800 dark:text-white">{config?.retentionDays || 30} {t('backups_days', 'يوم')}</p>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
          <div className="flex items-center gap-3">
            <div className={`p-2.5 ${config?.enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-slate-100 dark:bg-slate-700'} rounded-xl`}>
              <Shield className={`w-5 h-5 ${config?.enabled ? 'text-green-600 dark:text-green-400' : 'text-slate-400'}`} />
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">{t('backups_status_label', 'الحالة')}</p>
              <p className={`text-xl font-bold ${config?.enabled ? 'text-green-600 dark:text-green-400' : 'text-slate-500'}`}>
                {config?.enabled ? t('backups_status_active', 'نشط') : t('backups_status_inactive', 'متوقف')}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Latest backup info */}
      {latestBackup && (
        <div className={`rounded-2xl p-4 flex items-center gap-3 ${
          verifications[latestBackup.filename]?.valid === false
            ? 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800'
            : 'bg-teal-50 dark:bg-teal-900/10 border border-teal-200 dark:border-teal-800'
        }`}>
          {verifications[latestBackup.filename]?.valid === false ? (
            <XCircle className="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" />
          ) : (
            <CheckCircle2 className="w-5 h-5 text-teal-600 dark:text-teal-400 shrink-0" />
          )}
          <div className={`text-sm ${verifications[latestBackup.filename]?.valid === false ? 'text-red-700 dark:text-red-300' : 'text-teal-700 dark:text-teal-300'}`}>
            <span className="font-semibold">{t('backups_last_backup', 'آخر نسخة احتياطية:')}</span> {formatDate(latestBackup.createdAt)}
            <span className="mx-2">·</span>
            <span className="font-semibold">{latestBackup.sizeMb} MB</span>
            <span className="mx-2">·</span>
            {latestBackup.filename}
            {verifications[latestBackup.filename] && (
              <>
                <span className="mx-2">·</span>
                <span className={verifications[latestBackup.filename].valid ? 'text-teal-600' : 'text-red-600'}>
                  {verifications[latestBackup.filename].valid
                    ? t('backups_verification_success_detail', `${verifications[latestBackup.filename].tableCount} جدول, ${verifications[latestBackup.filename].estimatedRows} صف`, { tables: verifications[latestBackup.filename].tableCount, rows: verifications[latestBackup.filename].estimatedRows })
                    : t('backups_verification_fail_detail', `غير صالحة: ${verifications[latestBackup.filename].error}`, { error: verifications[latestBackup.filename].error })}
                </span>
              </>
            )}
          </div>
        </div>
      )}

      {!config?.enabled && (
        <div className="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-700 dark:text-amber-300 text-sm">
          <AlertCircle className="w-5 h-5 shrink-0" />
          {t('backups_auto_disabled_msg', 'النسخ الاحتياطي التلقائي معطل حالياً. يمكنك تفعيله عبر متغير البيئة')} <code className="mx-1 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 rounded text-xs font-mono">DB_BACKUP_ENABLED=true</code>
        </div>
      )}

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-800 gap-1 mt-4">
        <button
          onClick={() => setActiveTab('local')}
          className={`flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer ${
            activeTab === 'local'
              ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
          }`}
        >
          <Database className="w-4 h-4" />
          {t('backups_tab_system', 'النسخ الاحتياطية للنظام')}
        </button>
        <button
          onClick={() => setActiveTab('upload')}
          className={`flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer ${
            activeTab === 'upload'
              ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
          }`}
        >
          <Upload className="w-4 h-4" />
          {t('backups_tab_local', 'استعادة من ملف محلي (.sql.gz)')}
        </button>
        <button
          onClick={() => setActiveTab('external')}
          className={`flex items-center gap-2 px-6 py-3 border-b-2 font-medium text-sm transition-all cursor-pointer ${
            activeTab === 'external'
              ? 'border-teal-500 text-teal-600 dark:text-teal-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
          }`}
        >
          <HardDrive className="w-4 h-4" />
          {t('backups_tab_external', 'استعادة من مجلد خادم آخر')}
        </button>
      </div>

      {activeTab === 'local' && (
        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
          <div className="p-5 border-b border-slate-100 dark:border-slate-800">
            <h2 className="text-lg font-semibold text-slate-800 dark:text-white">{t('backups_list_title', 'قائمة النسخ الاحتياطية')}</h2>
          </div>

          {data?.backups.length === 0 ? (
            <div className="p-12 text-center">
              <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
              <p className="text-slate-500 dark:text-slate-400">{t('backups_list_empty', 'لا توجد نسخ احتياطية بعد')}</p>
              <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                {t('backups_list_empty_hint', 'انقر على "إنشاء نسخة احتياطية" لبدء النسخ الأول')}
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-100 dark:border-slate-800">
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_filename', 'اسم الملف')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_size', 'الحجم')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_created_at', 'تاريخ الإنشاء')}</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_status', 'الحالة')}</th>
                    <th className="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_actions', 'إجراءات')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {data?.backups.map((file) => {
                    const v = verifications[file.filename];
                    return (
                    <tr key={file.filename} className="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                      <td className="p-4">
                        <div className="flex items-center gap-2">
                          <FileArchive className="w-4 h-4 text-teal-500 shrink-0" />
                          <span className="text-slate-700 dark:text-slate-300 font-medium break-all">{file.filename}</span>
                        </div>
                      </td>
                      <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap dir=ltr text-left">{file.sizeMb} MB</td>
                      <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{formatDate(file.createdAt)}</td>
                      <td className="p-4 whitespace-nowrap">
                        {v ? (
                          <span className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full ${
                            v.valid
                              ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                              : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                          }`}>
                            {v.valid ? (
                              <><CheckCircle2 className="w-3 h-3" /> {t('backups_status_valid', 'صالحة')}</>
                            ) : (
                              <><XCircle className="w-3 h-3" /> {t('backups_status_invalid', 'غير صالحة')}</>
                            )}
                          </span>
                        ) : (
                          <span className="text-xs text-slate-400 dark:text-slate-500">{t('backups_status_unverified', 'لم يتم التحقق')}</span>
                        )}
                        {v && v.valid && (
                          <span className="text-xs text-slate-400 dark:text-slate-500 mr-2">
                            ({t('backups_verify_tables', '{{count}} جدول', { count: v.tableCount })}{v.hasData ? t('backups_verify_rows', `, {{count}} صف`, { count: v.estimatedRows }) : ''})
                          </span>
                        )}
                      </td>
                      <td className="p-4 whitespace-nowrap text-left flex gap-1 justify-end">
                        <button
                          onClick={() => handleDownloadBackup(file.filename)}
                          disabled={downloadingFilename === file.filename}
                          className="p-2 text-teal-500 hover:text-teal-600 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                          title={t('backups_btn_download', 'تنزيل ملف النسخة الاحتياطية')}
                        >
                          {downloadingFilename === file.filename ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                          ) : (
                            <Download className="w-4 h-4" />
                          )}
                        </button>
                        <button
                          onClick={() => handleVerify(file.filename)}
                          disabled={verifyingFilename === file.filename}
                          className="p-2 text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                          title={t('backups_btn_verify', 'التحقق من الملف')}
                        >
                          {verifyingFilename === file.filename ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                          ) : (
                            <FileSearch className="w-4 h-4" />
                          )}
                        </button>
                        <button
                          onClick={() => handleRestore(file.filename)}
                          disabled={restoringFilename === file.filename}
                          className="p-2 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                          title={t('backups_btn_restore', 'استعادة قاعدة البيانات من هذه النسخة')}
                        >
                          {restoringFilename === file.filename ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                          ) : (
                            <Upload className="w-4 h-4" />
                          )}
                        </button>
                        <button
                          onClick={() => handleDeleteBackup(file.filename)}
                          className="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors cursor-pointer"
                          title={t('backups_btn_delete', 'حذف')}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
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

      {activeTab === 'upload' && (
        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-8 text-center space-y-6">
          <div className="max-w-md mx-auto space-y-4">
            <div className="w-16 h-16 bg-teal-50 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mx-auto text-teal-600 dark:text-teal-400 shadow-md">
              <Upload className="w-8 h-8" />
            </div>
            <div className="space-y-2">
              <h2 className="text-xl font-bold text-slate-800 dark:text-white">{t('backups_upload_title', 'رفع واستعادة نسخة احتياطية')}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                {t('backups_upload_desc_part1', 'قم باختيار ملف نسخة احتياطية ينتهي بامتداد')} <code className="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-teal-600 dark:text-teal-400 text-xs font-mono">.sql.gz</code> {t('backups_upload_desc_part2', 'من أي مجلد على جهازك وسيتكفل النظام برفعها وفحصها واستعادتها بأمان.')}
              </p>
            </div>

            <div className="pt-4">
              <label className="cursor-pointer inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 transition-all shadow-lg shadow-teal-500/20">
                <Upload className="w-5 h-5 animate-pulse" />
                {uploading ? t('backups_uploading_file', 'جاري قراءة الملف...') : t('backups_choose_file', 'اختر ملف النسخة الاحتياطية')}
                <input
                  type="file"
                  accept=".sql.gz"
                  onChange={handleFileUpload}
                  className="hidden"
                  disabled={uploading}
                />
              </label>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'external' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-6 space-y-4">
            <div className="space-y-2">
              <h2 className="text-lg font-bold text-slate-800 dark:text-white">{t('backups_external_dir_title', 'مسار مجلد النسخ الاحتياطية على الخادم')}</h2>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                {t('backups_external_dir_desc', 'أدخل المسار الكامل للمجلد على الخادم ليقوم النظام بفحص الملفات الموجودة بداخله.')}
              </p>
            </div>

            <div className="flex flex-col sm:flex-row gap-3">
              <input
                type="text"
                value={externalDir}
                onChange={(e) => setExternalDir(e.target.value)}
                placeholder={t('backups_external_dir_placeholder', 'مثال: C:\\backups أو /var/backups')}
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
                    {t('backups_external_scanning', 'جاري الفحص...')}
                  </>
                ) : (
                  <>
                    <FileSearch className="w-4 h-4" />
                    {t('backups_external_scan_btn', 'فحص المجلد')}
                  </>
                )}
              </button>
            </div>
          </div>

          {scanAttempted && (
            <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
              <div className="p-5 border-b border-slate-100 dark:border-slate-800">
                <h3 className="text-md font-bold text-slate-800 dark:text-white">{t('backups_external_files_found', 'الملفات المكتشفة في المجلد')}</h3>
              </div>

              {externalFiles.length === 0 ? (
                <div className="p-12 text-center text-slate-500 dark:text-slate-400">
                  <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                  {t('backups_external_no_files_part1', 'لم يتم العثور على أي ملفات نسخة احتياطية ينتهي اسمها بـ')} <code className="text-teal-500">.sql.gz</code> في هذا المجلد.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-slate-100 dark:border-slate-800">
                        <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_filename', 'اسم الملف')}</th>
                        <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_size', 'الحجم')}</th>
                        <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_updated_at', 'تاريخ التعديل')}</th>
                        <th className="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('backups_th_restore', 'الاستعادة')}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {externalFiles.map((file) => (
                        <tr key={file.filename} className="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                          <td className="p-4">
                            <div className="flex items-center gap-2">
                              <FileArchive className="w-4 h-4 text-teal-500 shrink-0" />
                              <span className="text-slate-700 dark:text-slate-300 font-medium break-all">{file.filename}</span>
                            </div>
                          </td>
                          <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{file.sizeMb} MB</td>
                          <td className="p-4 text-slate-600 dark:text-slate-400 whitespace-nowrap">{formatDate(file.createdAt)}</td>
                          <td className="p-4 whitespace-nowrap text-left">
                            <button
                              onClick={() => handleRestoreExternal(file.fullPath, file.filename)}
                              disabled={restoringFilename === file.filename}
                              className="px-4 py-1.5 text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:bg-amber-400 rounded-lg transition-all flex items-center gap-1.5 justify-center cursor-pointer shadow-sm"
                            >
                              {restoringFilename === file.filename ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                              ) : (
                                <Upload className="w-3.5 h-3.5" />
                              )}
                              {t('backups_btn_restore_short', 'استعادة')}
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Info note */}
      <div className="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-500 dark:text-slate-400">
        <Clock className="w-4 h-4 mt-0.5 shrink-0" />
        <div>
          <p className="font-medium text-slate-700 dark:text-slate-300 mb-1">{t('backups_info_title', 'معلومات')}</p>
          <ul className="list-disc list-inside space-y-1">
            <li>{t('backups_info_auto_time', 'يتم تشغيل النسخ الاحتياطي التلقائي يومياً في الساعة')} <span dir="ltr">{config?.schedule || '03:00'}</span></li>
            <li>يتم الاحتفاظ بالنسخ لمدة {config?.retentionDays || 30} {t('backups_days', 'يوم')}اً قبل الحذف التلقائي</li>
            <li>{config?.compressGzip !== false ? t('backups_info_gzip_on', 'يتم ضغط النسخ بصيغة gzip لتوفير المساحة') : t('backups_info_gzip_off', 'حفظ النسخ الاحتياطية كملفات SQL عادية (بدون ضغط)')}</li>
            {config?.enabled && <li>{t('backups_info_dir_set', 'تم تحديد مجلد الحفظ إلى:')} <code className="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-xs font-mono">{config?.backupDir}</code></li>}
          </ul>
        </div>
      </div>

      {/* Confirmation Modal */}
      {confirmModal.isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            onClick={() => setConfirmModal({ isOpen: false, type: null, filename: '' })}
          />
          
          {/* Content */}
          <div className="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 dark:border-slate-700/50 relative z-10 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-start gap-4">
              <div className={`p-3 rounded-xl shrink-0 ${
                confirmModal.type === 'delete' 
                  ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' 
                  : 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400'
              }`}>
                {confirmModal.type === 'delete' ? (
                  <Trash2 className="w-6 h-6" />
                ) : (
                  <AlertCircle className="w-6 h-6 animate-pulse" />
                )}
              </div>
              
              <div className="space-y-2">
                <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                  {confirmModal.type === 'delete' ? t('backups_confirm_delete_title', 'تأكيد حذف النسخة الاحتياطية') : t('backups_confirm_restore_title', 'تأكيد استعادة قاعدة البيانات')}
                </h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                  {confirmModal.type === 'delete' ? (
                    <>
                      {t('backups_confirm_delete_msg1', 'هل أنت متأكد من حذف الملف')} <code className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-red-600 dark:text-red-400 break-all">{confirmModal.filename}</code>{t('backups_confirm_delete_msg2', '؟ لا يمكن التراجع عن هذا الإجراء بعد إتمامه.')}
                    </>
                  ) : confirmModal.type === 'upload_restore' ? (
                    <>
                      {t('backups_confirm_restore_upload_msg1', 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف المرفوع')} <code className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all">{confirmModal.filename}</code>؟
                      <strong className="block mt-2 text-red-600 dark:text-red-400">{t('backups_confirm_restore_upload_msg2', 'سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المرفوعة المحددة!')}</strong>
                    </>
                  ) : confirmModal.type === 'external_restore' ? (
                    <>
                      {t('backups_confirm_restore_ext_msg1', 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من الملف الخارجي')} <code className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-teal-600 dark:text-teal-400 break-all">{confirmModal.filename}</code>؟
                      <strong className="block mt-2 text-red-600 dark:text-red-400">{t('backups_confirm_restore_ext_msg2', 'سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المحددة!')}</strong>
                    </>
                  ) : (
                    <>
                      {t('backups_confirm_restore_local_msg1', 'تحذير: هل أنت متأكد من استعادة قاعدة البيانات من النسخة')} <code className="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-xs text-amber-600 dark:text-amber-400 break-all">{confirmModal.filename}</code>؟ 
                      <strong className="block mt-2 text-red-600 dark:text-red-400">{t('backups_confirm_restore_ext_msg2', 'سيؤدي هذا إلى استبدال كافة البيانات الحالية تماماً ببيانات النسخة المحددة!')}</strong>
                    </>
                  )}
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              <button
                type="button"
                onClick={() => setConfirmModal({ isOpen: false, type: null, filename: '' })}
                className="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors cursor-pointer"
              >
                {t('backups_cancel_btn', 'إلغاء')}
              </button>
              <button
                type="button"
                onClick={
                  confirmModal.type === 'delete'
                    ? executeDelete
                    : confirmModal.type === 'upload_restore'
                    ? executeUploadRestore
                    : confirmModal.type === 'external_restore'
                    ? executeExternalRestore
                    : executeRestore
                }
                className={`px-4 py-2 text-sm font-medium text-white rounded-xl shadow-lg transition-all cursor-pointer ${
                  confirmModal.type === 'delete'
                    ? 'bg-red-500 hover:bg-red-600 shadow-red-500/20'
                    : 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20'
                }`}
              >
                {confirmModal.type === 'delete' ? t('backups_confirm_delete_btn', 'تأكيد الحذف') : t('backups_confirm_restore_btn', 'تأكيد الاستعادة')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
