import { useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import LanguageSwitcher from './LanguageSwitcher';
import { useSurveyStore } from '../store/useSurveyStore';
import { CheckCircle2, Heart, Home, RotateCcw, Star } from 'lucide-react';

const AUTO_REDIRECT_DELAY_MS = 15000;

export default function ThankYouPage() {
  const navigate = useNavigate();
  const { selectedTip: medicalTip, resetSurveySession, surveys } = useSurveyStore();
  const redirectTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const goHome = useCallback(() => {
    if (redirectTimer.current) clearTimeout(redirectTimer.current);
    navigate('/');
  }, [navigate]);

  const onHome = () => goHome();
  const onNewSurvey = () => {
    if (redirectTimer.current) clearTimeout(redirectTimer.current);
    resetSurveySession();
    const activeSurveys = surveys.filter(s => s.isActive);
    if (activeSurveys.length >= 1) {
      navigate('/survey-selection');
    } else {
      navigate('/');
    }
  };
  const { t } = useTranslation();

  useEffect(() => {
    redirectTimer.current = setTimeout(goHome, AUTO_REDIRECT_DELAY_MS);
    return () => {
      if (redirectTimer.current) clearTimeout(redirectTimer.current);
    };
  }, [goHome]);

  return (
    <div className="min-h-screen bg-linear-to- from-green-50 via-white to-emerald-50 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] flex items-center justify-center p-4 relative text-gray-900 dark:text-slate-100 transition-colors duration-300">
      <div className="absolute top-4 right-4 z-10">
        <LanguageSwitcher />
      </div>
      <div className="text-center max-w-lg animate-scale-in">
        {/* Success Animation */}
        <div className="relative mb-8">
          <div className="w-28 h-28 mx-auto bg-linear-to- from-green-400 to-emerald-500 rounded-full flex items-center justify-center shadow-2xl shadow-green-200 dark:shadow-green-950/20 animate-pulse">
            <CheckCircle2 className="w-14 h-14 text-white" />
          </div>
          <div className="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-bounce">
            <span className="text-lg">⭐</span>
          </div>
          <div className="absolute -bottom-2 -left-2 w-8 h-8 bg-pink-400 rounded-full flex items-center justify-center shadow-lg animate-bounce" style={{ animationDelay: '0.2s' }}>
            <Heart className="w-4 h-4 text-white" />
          </div>
        </div>

        <h1 className="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">
          {t('thank_you')} 🎉
        </h1>
        
        <p className="text-lg text-gray-600 dark:text-slate-350 mb-3 leading-relaxed">
          {t('survey_submitted_success', 'تم إرسال استبيانكم بنجاح')}
        </p>
        
        <p className="text-gray-500 dark:text-slate-400 mb-10 leading-relaxed">
          {t('thank_you_desc', 'آراؤكم الكريمة تساعدنا في تقديم رعاية صحية أفضل. نسعى دائماً لتطوير خدماتنا لضمان حصولكم على أفضل تجربة ممكنة.')}
        </p>

        {medicalTip ? (
          <div className="bg-linear-to- from-teal-500 to-emerald-600 rounded-3xl p-8 mb-8 text-white shadow-xl shadow-teal-200 dark:shadow-teal-950/20 relative overflow-hidden group">
            <div className="absolute top-0 left-0 w-full h-full bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none" />
            <div className="relative z-10">
              <div className="flex items-center justify-center gap-2 mb-4">
                <div className="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                  <Star className="w-5 h-5 text-yellow-300 fill-yellow-300" />
                </div>
                <span className="font-black text-lg uppercase tracking-wider">نصيحة اليوم الصحية</span>
              </div>
              <p className="text-xl font-medium leading-relaxed italic">
                "{medicalTip}"
              </p>
            </div>
            <div className="absolute -bottom-6 -right-6 w-24 h-24 bg-white/10 rounded-full blur-2xl" />
          </div>
        ) : (
          <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-green-100 dark:border-slate-800 shadow-sm mb-8 text-start sm:text-center">
            <div className="flex items-center justify-center gap-2 mb-3">
              <Heart className="w-5 h-5 text-red-500" />
              <span className="font-bold text-gray-800 dark:text-slate-200">{t('health_priority', 'صحتكم أولويتنا')}</span>
            </div>
            <p className="text-sm text-gray-500 dark:text-slate-400">
              {t('health_wish', 'نتمنى لكم دوام الصحة والعافية ونتطلع لخدمتكم دائماً')}
            </p>
          </div>
        )}

        <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
          <button
            onClick={onHome}
            className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 bg-linear-to- from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
          >
            <Home className="w-5 h-5" />
            {t('home')}
          </button>
          <button
            onClick={onNewSurvey}
            className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-350 rounded-xl font-bold hover:bg-gray-50 dark:hover:bg-slate-800 hover:border-gray-300 dark:hover:border-slate-600 transition-all cursor-pointer"
          >
            <RotateCcw className="w-5 h-5" />
            {t('new_survey')}
          </button>
        </div>
      </div>
    </div>
  );
}
