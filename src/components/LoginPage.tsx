import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore, useAuthZustandStore } from '../store/useAuthStore';
import { useSurveyStore } from '../store/useSurveyStore';
import { useTranslation } from 'react-i18next';
import {
  Stethoscope,
  User,
  Lock,
  Eye,
  EyeOff,
  LogIn,
  AlertCircle,
} from 'lucide-react';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, loginError, setLoginError } = useAuthStore();
  const { loadSurveys } = useSurveyStore();
  const { t } = useTranslation();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setLoginError('');

    const success = await login(username, password);
    if (success) {
      loadSurveys();
      const updatedUser = useAuthZustandStore.getState().currentUser;
      navigate(updatedUser?.role === 'staff' ? '/dashboard/responses' : '/dashboard');
    }
    setIsLoading(false);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900 flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background decorations */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -left-40 w-80 h-80 bg-teal-500 rounded-full opacity-10 blur-3xl" />
        <div className="absolute -bottom-40 -right-40 w-80 h-80 bg-emerald-500 rounded-full opacity-10 blur-3xl" />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-600 rounded-full opacity-5 blur-3xl" />
      </div>

      <div className="w-full max-w-md relative z-10">
        {/* Logo */}
        <div className="text-center mb-8 animate-slide-up">
          <div className="w-20 h-20 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-2xl shadow-teal-500/30 mb-4">
            <Stethoscope className="w-10 h-10 text-white" />
          </div>
          <h1 className="text-3xl font-black text-white mb-2">MedSurvey Pro</h1>
          <p className="text-slate-400">{t('system_description')}</p>
        </div>

        {/* Login Card */}
        <div className="bg-white/10 backdrop-blur-xl rounded-3xl p-8 border border-white/10 shadow-2xl animate-scale-in">
          <div className="text-center mb-6">
            <h2 data-testid="login-title" className="text-xl font-bold text-white mb-1">{t('login_title')}</h2>
            <p className="text-slate-400 text-sm">{t('login_subtitle')}</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Error Message */}
            {loginError && (
              <div data-testid="login-error" className="flex items-center gap-2 bg-red-500/20 border border-red-500/30 rounded-xl px-4 py-3 text-red-300 text-sm animate-slide-up">
                <AlertCircle className="w-5 h-5 flex-shrink-0" />
                {loginError}
              </div>
            )}

            {/* Username */}
            <div className="space-y-2">
              <label className="flex items-center gap-2 text-sm font-medium text-slate-300">
                <User className="w-4 h-4 text-teal-400" />
                {t('username')}
              </label>
              <input
                id="username"
                data-testid="login-username"
                type="text"
                value={username}
                onChange={e => setUsername(e.target.value)}
                placeholder={t('username_placeholder')}
                className="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none transition-all"
                dir="ltr"
                autoFocus
              />
            </div>

            {/* Password */}
            <div className="space-y-2">
              <label className="flex items-center gap-2 text-sm font-medium text-slate-300">
                <Lock className="w-4 h-4 text-teal-400" />
                {t('password')}
              </label>
              <div className="relative">
                <input
                  id="password"
                  data-testid="login-password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  placeholder={t('password_placeholder')}
                  className="w-full px-4 py-3 pl-12 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none transition-all"
                  dir="ltr"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
                >
                  {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                </button>
              </div>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              data-testid="login-submit"
              disabled={!username || !password || isLoading}
              className={`w-full flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-white transition-all duration-300 ${
                username && password && !isLoading
                  ? 'bg-gradient-to-l from-teal-600 to-emerald-600 shadow-lg shadow-teal-500/30 hover:shadow-xl hover:-translate-y-0.5'
                  : 'bg-slate-600 cursor-not-allowed'
              }`}
            >
              {isLoading ? (
                <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
              ) : (
                <>
                  <LogIn className="w-5 h-5" />
                  {t('login_button')}
                </>
              )}
            </button>
          </form>

          {/* Back to main site */}
          <div className="mt-6 text-center">
            <button
              onClick={() => navigate('/')}
              className="text-slate-400 hover:text-white text-sm transition-colors"
            >
              {t('back_to_site')}
            </button>
          </div>
        </div>


      </div>
    </div>
  );
}
