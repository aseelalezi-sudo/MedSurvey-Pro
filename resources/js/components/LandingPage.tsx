import {
  ClipboardList,
  Shield,
  Clock,
  Heart,
  ChevronLeft,
  Settings,
  Stethoscope,
  Star,
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import LanguageSwitcher from './LanguageSwitcher';
import { useSettingsStore } from '../store/useSettingsStore';
import { useAuthStore } from '../store/useAuthStore';
import { useSurveyStore } from '../store/useSurveyStore';

export default function LandingPage() {
  const navigate = useNavigate();
  const { currentUser } = useAuthStore();
  const { surveys, resetSurveySession, startSurveySessionTimer } = useSurveyStore();

  const onStartSurvey = () => {
    resetSurveySession();
    const activeSurveys = surveys.filter(s => s.isActive);
    if (activeSurveys.length >= 1) {
      startSurveySessionTimer();
      navigate('/survey-selection');
    } else if (surveys.length > 0) {
      startSurveySessionTimer();
      navigate('/survey/info');
    }
  };

  const onAdminClick = () => {
    if (currentUser) navigate('/dashboard');
    else navigate('/login');
  };
  const { t } = useTranslation();
  const { settings } = useSettingsStore();
  const hospitalMobileName = settings.hospital.shortName || settings.hospital.name;

  return (
    <div className="min-h-screen bg-linear-to-r from-teal-50 via-white to-blue-50 dark:from-[#080d1a] dark:via-[#0c1224] dark:to-[#0a1020] text-gray-900 dark:text-slate-100 transition-colors duration-300 animate-fade-in">
      {/* Header */}
      <header className="bg-white/80 dark:bg-[#0c1224]/80 backdrop-blur-md border-b border-gray-100 dark:border-slate-800/60 sticky top-0 z-50 transition-colors">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16 gap-2 min-w-0">
            <div className="flex items-center gap-2 sm:gap-3 min-w-0">
              {/* System Branding */}
                <div className="flex items-center gap-2 shrink-0">
                  <div className="w-9 h-9 sm:w-10 sm:h-10 bg-linear-to-r from-teal-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-200 dark:shadow-teal-900/30">
                    <Stethoscope className="w-4.5 h-4.5 sm:w-5 sm:h-5 text-white" />
                  </div>
                  <div className="text-start hidden sm:block">
                    <h1 className="text-sm sm:text-lg font-black text-gray-900 dark:text-white leading-none">MedSurvey Pro</h1>
                    <span className="text-[9px] sm:text-[10px] text-gray-400 dark:text-slate-400 block mt-0.5">{t('system_description', 'نظام رضا المرضى')}</span>
                  </div>
                </div>

                {/* Elegant Divider */}
                <div className="hidden sm:block h-8 w-px bg-gray-200 dark:bg-slate-800 mx-1 sm:mx-2 shrink-0" />

              {/* Hospital Branding */}
              <div className="flex items-center gap-1.5 sm:gap-2 min-w-0">
                {settings.hospital.logo ? (
                  <div className="relative group bg-white p-0.5 rounded-lg border border-gray-200 dark:border-slate-600 shadow-md flex items-center justify-center shrink-0">
                    <img
                      src={settings.hospital.logo}
                      alt={settings.hospital.name}
                      className="h-7 sm:h-9 w-auto max-w-[56px] sm:max-w-[80px] object-contain rounded-md transform group-hover:scale-105 transition-transform duration-300"
                    />
                  </div>
                ) : (
                  <div className="w-8 h-8 bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800/40 rounded-lg flex items-center justify-center text-teal-600 dark:text-teal-400">
                    <Heart className="w-4 h-4" />
                  </div>
                )}
                <div className="text-start hidden min-[360px]:block">
                  <span className="text-xs sm:hidden font-bold text-teal-700 dark:text-teal-400 block whitespace-nowrap">{hospitalMobileName}</span>
                  <span className="hidden sm:block text-sm font-bold text-teal-700 dark:text-teal-400 whitespace-nowrap">{settings.hospital.name}</span>
                  <span className="text-[9px] sm:text-[10px] text-gray-400 dark:text-slate-500 block leading-none mt-0.5 whitespace-nowrap">{settings.hospital.operatingTitle || t('operating_hospital', 'المستشفى المشغل')}</span>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
              <LanguageSwitcher />
              <button
                onClick={onAdminClick}
                type="button"
                className="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors px-2.5 sm:px-3 py-2 rounded-lg hover:bg-teal-50 dark:hover:bg-slate-800 cursor-pointer"
              >
                <Settings className="w-4 h-4" />
                <span className="hidden sm:inline">{t('admin_panel', 'لوحة التحكم')}</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section className="relative min-h-[calc(100vh-4rem)] flex items-center overflow-hidden">
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -top-40 -left-40 w-80 h-80 bg-teal-200 dark:bg-teal-950/20 rounded-full opacity-20 blur-3xl" />
          <div className="absolute -bottom-40 -right-40 w-80 h-80 bg-blue-200 dark:bg-blue-950/20 rounded-full opacity-20 blur-3xl" />
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-100 dark:bg-emerald-950/10 rounded-full opacity-10 blur-3xl" />
        </div>

        <div className="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
          <div className="text-center max-w-3xl mx-auto">

            <div className="inline-flex max-w-full items-center gap-2 bg-linear-to-r from-teal-500/10 via-emerald-500/10 to-blue-500/10 border border-teal-500/20 dark:border-teal-500/30 backdrop-blur-md rounded-full px-5 py-2.5 mb-8 animate-slide-up shadow-sm">
              <Heart className="w-4 h-4 text-teal-600 dark:text-teal-400 animate-pulse" />
              <span className="text-xs sm:text-sm text-teal-800 dark:text-teal-300 font-bold">{settings.hospital.welcomeMessage}</span>
            </div>

            <h3 className="text-3xl sm:text-5xl font-black text-gray-900 dark:text-white leading-tight mb-6 animate-slide-up">
              {t('hero_title_part1', 'رأيكم يصنع')}
              <span className="text-transparent bg-clip-text bg-linear-to-r from-teal-600 to-emerald-600 dark:from-teal-400 dark:to-emerald-400"> {t('hero_title_highlight', 'الفرق')} </span>
              {t('hero_title_part2', 'في تطوير خدماتنا')}
            </h3>

            <p className="text-lg sm:text-xl text-gray-600 dark:text-slate-300 mb-10 leading-relaxed animate-slide-up">
              {t('hero_desc', 'شاركونا تجربتكم في المستشفى لنتمكن من تحسين وتطوير الخدمات الصحية المقدمة لكم. استبيان سري وآمن لا يتجاوز 3 دقائق.')}
            </p>

            <div className="flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up">
              <button
                onClick={onStartSurvey}
                type="button"
                data-testid="start-survey"
                className="group w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-linear-to-r from-teal-600 to-emerald-600 text-white px-8 py-4 rounded-2xl text-lg font-bold shadow-xl shadow-teal-200 dark:shadow-teal-900/30 hover:shadow-2xl hover:shadow-teal-300 dark:hover:shadow-teal-900/45 transform hover:-translate-y-1 transition-all duration-300 cursor-pointer"
              >
                <ClipboardList className="w-5 h-5" />
                {t('start_survey')}
                <ChevronLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform rtl:rotate-0 ltr:rotate-180" />
              </button>
            </div>

            <div className="flex flex-wrap items-center justify-center gap-3 sm:gap-6 mt-8 text-sm text-gray-500 dark:text-slate-400">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-teal-500 dark:text-teal-400" />
                <span>{t('landing_3_mins', '3 دقائق فقط')}</span>
              </div>
              <div className="flex items-center gap-2">
                <Shield className="w-4 h-4 text-teal-500 dark:text-teal-400" />
                <span>{t('landing_secure_100', 'مشفر وآمن 100%')}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-16 bg-white/50 dark:bg-slate-900/20 border-t border-gray-100/50 dark:border-slate-800/20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {[
              {
                icon: <ClipboardList className="w-7 h-7" />,
                title: t('landing_feat_survey_title', 'استبيان شامل'),
                desc: t('landing_feat_survey_desc', 'يغطي جميع جوانب الخدمة من الاستقبال حتى المغادرة'),
                color: 'from-teal-500 to-emerald-500',
                shadow: 'shadow-teal-200 dark:shadow-teal-900/20',
              },
              {
                icon: <Shield className="w-7 h-7" />,
                title: t('landing_feat_privacy_title', 'خصوصية تامة'),
                desc: t('landing_feat_privacy_desc', 'بياناتكم محمية ومشفرة ولا يتم مشاركتها مع أي طرف'),
                color: 'from-blue-500 to-indigo-500',
                shadow: 'shadow-blue-200 dark:shadow-blue-900/20',
              },
              {
                icon: <Star className="w-7 h-7" />,
                title: t('landing_feat_improve_title', 'تحسين مستمر'),
                desc: t('landing_feat_improve_desc', 'نستخدم آراءكم لتطوير وتحسين جودة الخدمات باستمرار'),
                color: 'from-amber-500 to-orange-500',
                shadow: 'shadow-amber-200 dark:shadow-amber-900/20',
              },
            ].map((feature, i) => (
              <div
                key={i}
                className="group bg-white dark:bg-[#0f172a] rounded-2xl p-8 shadow-sm dark:shadow-slate-900/10 hover:shadow-xl hover:border-teal-500/10 transition-all duration-300 border border-gray-100 dark:border-slate-800/40 animate-slide-up text-start"
                style={{ animationDelay: `${i * 100}ms` }}
              >
                <div className={`w-14 h-14 bg-linear-to-r ${feature.color} rounded-2xl flex items-center justify-center text-white shadow-lg ${feature.shadow} mb-5 group-hover:scale-110 transition-transform`}>
                  {feature.icon}
                </div>
                <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-3">{feature.title}</h3>
                <p className="text-gray-600 dark:text-slate-300 leading-relaxed">{feature.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-900 dark:bg-slate-950 text-white py-10 transition-colors">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-6 border-b border-gray-800 dark:border-slate-800/60 pb-6 mb-6">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 bg-linear-to-r from-teal-500 to-emerald-600 rounded-lg flex items-center justify-center">
                <Stethoscope className="w-4 h-4 text-white" />
              </div>
              <span className="font-bold text-lg">MedSurvey Pro</span>
            </div>

            <div className="flex items-center gap-2">
              {settings.hospital.logo ? (
                <img src={settings.hospital.logo} alt={settings.hospital.name} className="h-6 max-w-[100px] object-contain rounded opacity-80" />
              ) : (
                <div className="w-6 h-6 bg-white/10 rounded flex items-center justify-center text-teal-400">
                  <Heart className="w-3.5 h-3.5" />
                </div>
              )}
              <span className="text-sm text-gray-300 font-semibold">
                <span className="sm:hidden">{hospitalMobileName}</span>
                <span className="hidden sm:inline">{settings.hospital.name}</span>
              </span>
            </div>
          </div>
          <p className="text-gray-500 text-xs">
            {t('footer_copyright', 'تم التطوير والتشغيل لصالح {{hospital}} عبر نظام قياس وتحسين رضا المرضى MedSurvey Pro', { hospital: settings.hospital.name })} © {new Date().getFullYear()}
          </p>
        </div>
      </footer>
    </div>
  );
}
