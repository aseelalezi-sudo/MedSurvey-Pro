import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { X, KeyRound, Eye, EyeOff, AlertCircle } from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';

interface ChangePasswordModalProps {
  isOpen: boolean;
  onClose: () => void;
  userId: string;
  username: string;
}

export default function ChangePasswordModal({ isOpen, onClose, userId, username }: ChangePasswordModalProps) {
  const { t } = useTranslation();
  const { changeUserPassword } = useAuthStore();
  
  const [passwordForm, setPasswordForm] = useState({ currentPassword: '', password: '', confirmPassword: '' });
  const [passwordError, setPasswordError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordError('');

    if (!passwordForm.currentPassword) {
      setPasswordError('يرجى إدخال كلمة المرور الحالية');
      return;
    }
    if (passwordForm.password.length < 6) {
      setPasswordError(t('user_password_err_min', 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'));
      return;
    }
    if (passwordForm.password !== passwordForm.confirmPassword) {
      setPasswordError(t('user_password_err_match', 'كلمتا المرور غير متطابقتين'));
      return;
    }

    setIsSubmitting(true);
    try {
      await changeUserPassword(userId, passwordForm.password, passwordForm.currentPassword);
      onClose();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : t('user_password_err_change', 'حدث خطأ أثناء تغيير كلمة المرور');
      setPasswordError(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/65 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in text-start">
      <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full animate-scale-in border border-gray-150 dark:border-slate-800">
        <div className="p-6 border-b border-gray-100 dark:border-slate-850 flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-gray-800 dark:text-white">{t('user_password_modal_title', 'تغيير كلمة المرور')}</h2>
            <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">@{username}</p>
          </div>
          <button onClick={onClose} type="button" className="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 cursor-pointer">
            <X className="w-6 h-6" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {passwordError && (
            <div className="flex items-center gap-2 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/40 rounded-xl px-4 py-3 text-red-600 dark:text-red-400 text-sm">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              {passwordError}
            </div>
          )}

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">كلمة المرور الحالية</label>
            <div className="relative">
              <input
                type={showCurrentPassword ? 'text' : 'password'}
                value={passwordForm.currentPassword}
                onChange={e => setPasswordForm({ ...passwordForm, currentPassword: e.target.value })}
                placeholder="أدخل كلمة المرور الحالية"
                className="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                dir="ltr"
              />
              <button
                type="button"
                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 cursor-pointer"
              >
                {showCurrentPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
              </button>
            </div>
          </div>

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_password_new_label', 'كلمة المرور الجديدة')}</label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={passwordForm.password}
                onChange={e => setPasswordForm({ ...passwordForm, password: e.target.value })}
                placeholder={t('user_password_new_placeholder', 'أدخل كلمة المرور الجديدة')}
                className="w-full px-4 py-3 pl-12 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
                dir="ltr"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 cursor-pointer"
              >
                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
              </button>
            </div>
          </div>

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-400 mb-2">{t('user_password_confirm_label', 'تأكيد كلمة المرور')}</label>
            <input
              type={showPassword ? 'text' : 'password'}
              value={passwordForm.confirmPassword}
              onChange={e => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
              placeholder={t('user_password_confirm_placeholder', 'أعد إدخال كلمة المرور')}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950/15 outline-none bg-white dark:bg-slate-850 text-gray-900 dark:text-white placeholder-gray-450"
              dir="ltr"
            />
          </div>

          <div className="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/35 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
            {t('user_password_session_note', 'ملاحظة: سيتم تسجيل خروجك من جميع الأجهزة الأخرى بعد تغيير كلمة المرور.')}
          </div>

          <div className="flex items-center gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer disabled:opacity-50"
            >
              {t('user_cancel', 'إلغاء')}
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !passwordForm.currentPassword || !passwordForm.password || !passwordForm.confirmPassword}
              className="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-all cursor-pointer disabled:opacity-50"
            >
              <KeyRound className="w-5 h-5" />
              {isSubmitting ? t('loading', 'جاري الحفظ...') : t('user_password_save_btn', 'تغيير كلمة المرور')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
