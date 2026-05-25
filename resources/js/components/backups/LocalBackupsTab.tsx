import { Database, Filter, FileArchive, HardDrive, CheckCircle2, XCircle, Loader2, Download, FileSearch, Upload, Trash2 } from 'lucide-react';
import { BackupFile, BackupVerification, BackupListResponse } from '../../api/client';

interface LocalBackupsTabProps {
  data: BackupListResponse | null;
  filteredBackups: BackupFile[];
  verifications: Record<string, BackupVerification>;
  formatDate: (iso: string) => string;
  handleDownloadBackup: (filename: string) => void;
  downloadingFilename: string | null;
  handleVerify: (filename: string) => void;
  verifyingFilename: string | null;
  handleRestore: (filename: string) => void;
  restoringFilename: string | null;
  handleDeleteBackup: (filename: string) => void;
  clearFilters: () => void;
  totalSize: number;
}

export function LocalBackupsTab({
  data,
  filteredBackups,
  verifications,
  formatDate,
  handleDownloadBackup,
  downloadingFilename,
  handleVerify,
  verifyingFilename,
  handleRestore,
  restoringFilename,
  handleDeleteBackup,
  clearFilters,
  totalSize,
}: LocalBackupsTabProps) {
  return (
    <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h2 className="text-lg font-semibold text-slate-800 dark:text-white">قائمة النسخ الاحتياطية</h2>
        {data?.backups && data.backups.length > 0 && (
          <div className="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span className="flex items-center gap-1">
              <FileArchive className="w-3.5 h-3.5" />
              الإجمالي: {data.backups.length}
            </span>
            <span className="flex items-center gap-1">
              <HardDrive className="w-3.5 h-3.5" />
              {totalSize.toFixed(2)} MB
            </span>
          </div>
        )}
      </div>

      {data?.backups.length === 0 ? (
        <div className="p-12 text-center">
          <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
          <p className="text-slate-500 dark:text-slate-400">لا توجد نسخ احتياطية بعد</p>
          <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
            انقر على "إنشاء نسخة احتياطية" لبدء النسخ الأول
          </p>
        </div>
      ) : filteredBackups.length === 0 ? (
        <div className="p-12 text-center">
          <Filter className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
          <p className="text-slate-500 dark:text-slate-400">لا توجد نتائج تطابق معايير الفلترة</p>
          <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
            حاول تغيير معايير البحث أو
            <button onClick={clearFilters} className="text-teal-600 dark:text-teal-400 font-bold hover:underline mr-1 cursor-pointer">
              مسح الفلترة
            </button>
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
              {filteredBackups.map((file) => {
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
                  <td className="p-4 whitespace-nowrap text-left flex gap-1 justify-end">
                    <button
                      onClick={() => handleDownloadBackup(file.filename)}
                      disabled={downloadingFilename === file.filename}
                      className="p-2 text-teal-500 hover:text-teal-600 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
                      title="تنزيل ملف النسخة الاحتياطية"
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
                      className="p-2 text-amber-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors disabled:opacity-50 cursor-pointer"
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
                      className="p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors cursor-pointer"
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
  );
}
