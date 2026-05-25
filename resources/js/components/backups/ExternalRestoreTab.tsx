import { Loader2, FileSearch, Database, FileArchive, Upload } from 'lucide-react';
import { BackupFile } from '../../api/client';

interface ExternalRestoreTabProps {
  externalDir: string;
  setExternalDir: (dir: string) => void;
  handleScanExternal: () => void;
  scanning: boolean;
  scanAttempted: boolean;
  externalFiles: (BackupFile & { fullPath: string })[];
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
  handleRestoreExternal,
  restoringFilename,
  formatDate,
}: ExternalRestoreTabProps) {
  return (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-6 space-y-4">
        <div className="space-y-2">
          <h2 className="text-lg font-bold text-slate-800 dark:text-white">مسار مجلد النسخ الاحتياطية على الخادم</h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            أدخل المسار الكامل للمجلد على الخادم ليقوم النظام بفحص الملفات الموجودة بداخله.
          </p>
        </div>

        <div className="flex flex-col sm:flex-row gap-3">
          <input
            type="text"
            value={externalDir}
            onChange={(e) => setExternalDir(e.target.value)}
            placeholder="مثال: C:\backups أو /var/backups"
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
                جاري الفحص...
              </>
            ) : (
              <>
                <FileSearch className="w-4 h-4" />
                فحص المجلد
              </>
            )}
          </button>
        </div>
      </div>

      {scanAttempted && (
        <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden">
          <div className="p-5 border-b border-slate-100 dark:border-slate-800">
            <h3 className="text-md font-bold text-slate-800 dark:text-white">الملفات المكتشفة في المجلد</h3>
          </div>

          {externalFiles.length === 0 ? (
            <div className="p-12 text-center text-slate-500 dark:text-slate-400">
              <Database className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
              لم يتم العثور على أي ملفات نسخة احتياطية ينتهي اسمها بـ <code className="text-teal-500">.sql.gz</code> في هذا المجلد.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-100 dark:border-slate-800">
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">اسم الملف</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الحجم</th>
                    <th className="text-right p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">تاريخ التعديل</th>
                    <th className="text-left p-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">الاستعادة</th>
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
                          استعادة
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
  );
}
