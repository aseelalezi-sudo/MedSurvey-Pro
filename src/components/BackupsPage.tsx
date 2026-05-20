import { useState, useEffect, useCallback } from 'react';
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
import { backupsAPI, BackupListResponse, BackupVerification } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('BackupsPage');

export default function BackupsPage() {
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const [verifications, setVerifications] = useState<Record<string, BackupVerification>>({});
  const [verifyingFilename, setVerifyingFilename] = useState<string | null>(null);
  const [restoringFilename, setRestoringFilename] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState('');

  const fetchBackups = useCallback(async () => {
    try {
      setError('');
      const res = await backupsAPI.list();
      setData(res);
    } catch (err) {
      logger.error('Failed to fetch backups:', err);
      setError('فشل في تحميل قائمة النسخ الاحتياطية');
    } finally {
      setLoading(false);
    }
  }, []);

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
      const msg = err instanceof Error ? err.message : 'فشل في إنشاء النسخة الاحتياطية';
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
      setError('فشل في التحقق من الملف');
    } finally {
      setVerifyingFilename(null);
    }
  };

  const handleRestore = async (filename: string) => {
    setError('');
    setSuccessMessage('');
    setRestoringFilename(filename);
    try {
      await backupsAPI.restore(filename);
      setSuccessMessage(`✅ تم استعادة قاعدة البيانات بنجاح من "${filename}"`);
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'فشل في استعادة قاعدة البيانات';
      setError(msg);
      logger.error('Restore failed:', err);
    } finally {
      setRestoringFilename(null);
    }
  };

  const handleDeleteBackup = async (filename: string) => {
    if (!window.confirm(`هل أنت متأكد من حذف الملف "${filename}"؟`)) return;
    try {
      await backupsAPI.delete(filename);
      await fetchBackups();
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'فشل في حذف الملف';
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
    <div className="p-4 sm:p-6 space-y-6" dir="rtl">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <Database className="w-6 h-6 text-teal-500" />
            النسخ الاحتياطي لقاعدة البيانات
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            إدارة النسخ الاحتياطية التلقائية واليدوية لقاعدة البيانات
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={fetchBackups}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            <RefreshCcw className="w-4 h-4" />
            تحديث
          </button>
          <button
            onClick={handleCreateBackup}
            disabled={creating}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-500/20"
          >
            <Download className="w-4 h-4" />
            {creating ? 'جاري الإنشاء...' : 'إنشاء نسخة احتياطية'}
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
              <p className="text-xs text-slate-500 dark:text-slate-400">إجمالي النسخ</p>
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
              <p className="text-xs text-slate-500 dark:text-slate-400">الحجم الإجمالي</p>
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
              <p className="text-xs text-slate-500 dark:text-slate-400">مدة الاحتفاظ</p>
              <p className="text-xl font-bold text-slate-800 dark:text-white">{config?.retentionDays || 30} يوم</p>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-5">
          <div className="flex items-center gap-3">
            <div className={`p-2.5 ${config?.enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-slate-100 dark:bg-slate-700'} rounded-xl`}>
              <Shield className={`w-5 h-5 ${config?.enabled ? 'text-green-600 dark:text-green-400' : 'text-slate-400'}`} />
            </div>
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">الحالة</p>
              <p className={`text-xl font-bold ${config?.enabled ? 'text-green-600 dark:text-green-400' : 'text-slate-500'}`}>
                {config?.enabled ? 'نشط' : 'متوقف'}
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
            <span className="font-semibold">آخر نسخة احتياطية:</span> {formatDate(latestBackup.createdAt)}
            <span className="mx-2">·</span>
            <span className="font-semibold">{latestBackup.sizeMb} MB</span>
            <span className="mx-2">·</span>
            {latestBackup.filename}
            {verifications[latestBackup.filename] && (
              <>
                <span className="mx-2">·</span>
                <span className={verifications[latestBackup.filename].valid ? 'text-teal-600' : 'text-red-600'}>
                  {verifications[latestBackup.filename].valid
                    ? `${verifications[latestBackup.filename].tableCount} جدول, ${verifications[latestBackup.filename].estimatedRows} صف`
                    : `غير صالحة: ${verifications[latestBackup.filename].error}`}
                </span>
              </>
            )}
          </div>
        </div>
      )}

      {!config?.enabled && (
        <div className="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-700 dark:text-amber-300 text-sm">
          <AlertCircle className="w-5 h-5 shrink-0" />
          النسخ الاحتياطي التلقائي معطل حالياً. يمكنك تفعيله عبر متغير البيئة <code className="mx-1 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/40 rounded text-xs font-mono">DB_BACKUP_ENABLED=true</code>
        </div>
      )}

      {/* Backups Table */}
      <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div className="p-5 border-b border-slate-100 dark:border-slate-800">
          <h2 className="text-lg font-semibold text-slate-800 dark:text-white">قائمة النسخ الاحتياطية</h2>
        </div>

        {data?.backups.length === 0 ? (
          <div className="p-12 text-center">
            <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
            <p className="text-slate-500 dark:text-slate-400">لا توجد نسخ احتياطية بعد</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
              انقر على "إنشاء نسخة احتياطية" لبدء النسخ الأول
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-800">
                  <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">اسم الملف</th>
                  <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحجم</th>
                  <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">تاريخ الإنشاء</th>
                  <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحالة</th>
                  <th className="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">إجراءات</th>
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
                            <><CheckCircle2 className="w-3 h-3" /> صالحة</>
                          ) : (
                            <><XCircle className="w-3 h-3" /> غير صالحة</>
                          )}
                        </span>
                      ) : (
                        <span className="text-xs text-slate-400 dark:text-slate-500">لم يتم التحقق</span>
                      )}
                      {v && v.valid && (
                        <span className="text-xs text-slate-400 dark:text-slate-500 mr-2">
                          ({v.tableCount} جدول{v.hasData ? `, ${v.estimatedRows} صف` : ''})
                        </span>
                      )}
                    </td>
                    <td className="p-4 whitespace-nowrap text-left">
                      <button
                        onClick={() => handleVerify(file.filename)}
                        disabled={verifyingFilename === file.filename}
                        className="p-2 text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors disabled:opacity-50"
                        title="التحقق من الملف"
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
                        className="p-2 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors disabled:opacity-50"
                        title="استعادة قاعدة البيانات من هذه النسخة"
                      >
                        {restoringFilename === file.filename ? (
                          <Loader2 className="w-4 h-4 animate-spin" />
                        ) : (
                          <Upload className="w-4 h-4" />
                        )}
                      </button>
                      <button
                        onClick={() => handleDeleteBackup(file.filename)}
                        className="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        title="حذف"
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

      {/* Info note */}
      <div className="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-500 dark:text-slate-400">
        <Clock className="w-4 h-4 mt-0.5 shrink-0" />
        <div>
          <p className="font-medium text-slate-700 dark:text-slate-300 mb-1">معلومات</p>
          <ul className="list-disc list-inside space-y-1">
            <li>يتم تشغيل النسخ الاحتياطي التلقائي يومياً في الساعة 3:00 صباحاً</li>
            <li>يتم الاحتفاظ بالنسخ لمدة {config?.retentionDays || 30} يوماً قبل الحذف التلقائي</li>
            <li>يتم ضغط النسخ بصيغة gzip لتوفير المساحة</li>
            {config?.enabled && <li>تم تحديد مجلد الحفظ إلى: <code className="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-xs font-mono">{config?.backupDir}</code></li>}
          </ul>
        </div>
      </div>
    </div>
  );
}
