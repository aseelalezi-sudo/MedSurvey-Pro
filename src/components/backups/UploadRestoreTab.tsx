import { Upload } from 'lucide-react';

interface UploadRestoreTabProps {
  uploading: boolean;
  handleFileUpload: (event: React.ChangeEvent<HTMLInputElement>) => void;
}

export function UploadRestoreTab({ uploading, handleFileUpload }: UploadRestoreTabProps) {
  return (
    <div className="bg-white dark:bg-slate-800/50 backdrop-blur-sm border border-slate-100 dark:border-slate-800 rounded-2xl p-8 text-center space-y-6">
      <div className="max-w-md mx-auto space-y-4">
        <div className="w-16 h-16 bg-teal-50 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mx-auto text-teal-600 dark:text-teal-400 shadow-md">
          <Upload className="w-8 h-8" />
        </div>
        <div className="space-y-2">
          <h2 className="text-xl font-bold text-slate-800 dark:text-white">رفع واستعادة نسخة احتياطية</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            قم باختيار ملف نسخة احتياطية ينتهي بامتداد <code className="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-teal-600 dark:text-teal-400 text-xs font-mono">.sql.gz</code> من أي مجلد على جهازك وسيتكفل النظام برفعها وفحصها واستعادتها بأمان.
          </p>
        </div>

        <div className="pt-4">
          <label className="cursor-pointer inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white bg-linear-to-r from-teal-500 to-emerald-500 rounded-xl hover:from-teal-600 hover:to-emerald-600 transition-all shadow-lg shadow-teal-500/20">
            <Upload className="w-5 h-5 animate-pulse" />
            {uploading ? 'جاري قراءة الملف...' : 'اختر ملف النسخة الاحتياطية'}
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
  );
}
