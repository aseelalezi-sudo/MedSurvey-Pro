import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import LanguageSwitcher from './LanguageSwitcher';
import { CustomProjectIcon } from './CustomProjectIcon';
import { useSurveyStore } from '../store/useSurveyStore';
import { useSurveySessionTimer } from '../hooks/useSurveySessionTimer';
import {
  ClipboardList,
  ArrowRight,
  CheckCircle2,
  Clock,
  FileText,
  ChevronLeft,
  Stethoscope,
  DoorOpen,
  Building2,
  Pill,
  ClipboardCheck,
  Users,
  Activity,
  Heart,
} from 'lucide-react';

const sectionIcons: Record<string, React.ComponentType<{ className?: string }>> = {
  'door-open': DoorOpen,
  'stethoscope': Stethoscope,
  'building': Building2,
  'pill': Pill,
  'clipboard-check': ClipboardCheck,
  'users': Users,
  'activity': Activity,
  'heart': Heart,
};

export default function SurveySelection() {
  const navigate = useNavigate();
  const { surveys, selectSurvey } = useSurveyStore();
  const { t } = useTranslation();
  const { formattedTime } = useSurveySessionTimer();
  const activeSurveys = surveys.filter(s => s.isActive);

  const onSelect = (surveyId: string) => {
    selectSurvey(surveyId);
    navigate('/survey/info');
  };

  const onBack = () => navigate('/');

  return (
    <div className="min-h-screen bg-linear-to-r from-teal-50 via-white to-blue-50 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] text-gray-900 dark:text-slate-100 transition-colors duration-300">
      {/* Header */}
      <header className="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-100 dark:border-slate-800/80 sticky top-0 z-50 transition-colors duration-300">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <div className="flex items-center gap-3">
              <button
                onClick={onBack}
                type="button"
                className="flex items-center gap-2 text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors cursor-pointer"
              >
                <ArrowRight className="w-5 h-5 rtl:rotate-0 ltr:rotate-180" />
              </button>
              <div className="w-10 h-10 flex items-center justify-center rounded-xl overflow-hidden drop-shadow-md">
                <CustomProjectIcon className="w-full h-full object-contain" />
              </div>
              <div className="text-start flex flex-col gap-0.5">
                <h1 className="text-lg font-bold text-gray-900 dark:text-white leading-tight">{t('select_survey')}</h1>
                <p className="text-xs text-gray-500 dark:text-slate-450">{t('select_appropriate_survey', 'اختر الاستبيان المناسب لتقييم تجربتك')}</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1.5 rounded-xl bg-teal-50 dark:bg-teal-950/30 px-3 py-2 text-xs font-black text-teal-700 dark:text-teal-400 border border-teal-100 dark:border-teal-900/40" dir="ltr">
                <Clock className="w-3.5 h-3.5" />
                {formattedTime}
              </div>
              <LanguageSwitcher />
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Intro */}
        <div className="text-center mb-8 animate-slide-up">
          <h2 className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-3">
            {t('which_survey_title', 'ما الاستبيان الذي تريد تعبئته؟')}
          </h2>
          <p className="text-gray-500 dark:text-slate-400 max-w-xl mx-auto">
            {t('survey_selection_desc', 'اختر الاستبيان المناسب لتجربتك في المستشفى. رأيك يساعدنا في تحسين خدماتنا.')}
          </p>
        </div>

        {/* Survey Cards */}
        {activeSurveys.length === 0 ? (
          <div className="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800">
            <ClipboardList className="w-20 h-20 text-gray-200 dark:text-slate-700 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-600 dark:text-slate-350 mb-2">{t('no_available_surveys', 'لا توجد استبيانات متاحة حالياً')}</h3>
            <p className="text-gray-400 dark:text-slate-500">{t('try_again_later', 'يرجى المحاولة مرة أخرى لاحقاً')}</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {activeSurveys.map((survey, i) => {
              const totalQuestions = survey.sections.reduce((sum, s) => sum + s.questions.length, 0);
              const estimatedTime = Math.max(2, Math.ceil(totalQuestions * 0.3));

              return (
                <div
                  key={survey.id}
                  className="group h-full bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800/80 shadow-sm hover:shadow-xl hover:border-teal-500/20 dark:hover:border-teal-500/30 transition-all duration-300 overflow-hidden cursor-pointer animate-slide-up flex flex-col"
                  style={{ animationDelay: `${i * 100}ms` }}
                  onClick={() => onSelect(survey.id)}
                >
                  {/* Card Header */}
                  <div className="bg-linear-to-r from-teal-500 to-emerald-600 p-6 text-white relative overflow-hidden text-start min-h-[210px]">
                    <div className="absolute inset-0 opacity-10">
                      <div className="absolute -top-10 -left-10 w-40 h-40 bg-white rounded-full" />
                      <div className="absolute -bottom-10 -right-10 w-32 h-32 bg-white rounded-full" />
                    </div>
                    <div className="relative">
                      <div className="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <ClipboardList className="w-7 h-7" />
                      </div>
                      <h3 className="text-xl font-bold mb-2 leading-relaxed line-clamp-2">{survey.title}</h3>
                      <p className="text-teal-100 text-sm line-clamp-2">{survey.description}</p>
                    </div>
                  </div>

                  {/* Card Body */}
                  <div className="p-5 text-start flex flex-col flex-1">
                    {/* Stats */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mb-5 text-sm">
                      <div className="flex items-center gap-2 text-gray-600 dark:text-slate-300">
                        <FileText className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                        <span>{survey.sections.length} {t('sections_count', 'أقسام')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-gray-600 dark:text-slate-300">
                        <ClipboardCheck className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                        <span>{totalQuestions} {t('questions_count', 'أسئلة')}</span>
                      </div>
                      <div className="flex items-center gap-2 text-gray-600 dark:text-slate-300">
                        <Clock className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                        <span>~{estimatedTime} {t('minutes', 'دقائق')}</span>
                      </div>
                    </div>

                    {/* Sections Preview */}
                    <div className="flex flex-wrap content-start gap-2 mb-5 flex-1">
                      {survey.sections.slice(0, 4).map(section => {
                        const IconComp = sectionIcons[section.icon] || ClipboardCheck;
                        return (
                          <div
                            key={section.id}
                            className="flex items-center gap-1.5 bg-gray-50 dark:bg-slate-800/60 px-3 py-1.5 rounded-full text-xs text-gray-600 dark:text-slate-300 border border-transparent dark:border-slate-700/50"
                          >
                            <IconComp className="w-3.5 h-3.5 text-teal-600 dark:text-teal-400" />
                            <span>{section.title}</span>
                          </div>
                        );
                      })}
                      {survey.sections.length > 4 && (
                        <div className="flex items-center bg-gray-50 dark:bg-slate-800/60 px-3 py-1.5 rounded-full text-xs text-gray-500 dark:text-slate-400">
                          +{survey.sections.length - 4} {t('more', 'أخرى')}
                        </div>
                      )}
                    </div>

                    {/* Start Button */}
                    <button type="button" className="mt-auto min-h-12 w-full flex items-center justify-center gap-2 bg-linear-to-r from-teal-600 to-emerald-600 text-white px-4 py-3 rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 group-hover:shadow-xl group-hover:-translate-y-0.5 transition-all duration-300 cursor-pointer">
                      <CheckCircle2 className="w-5 h-5" />
                      {t('start_survey')}
                      <ChevronLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform rtl:rotate-0 ltr:rotate-180" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Back Button */}
        <div className="mt-8 text-center">
          <button
            onClick={onBack}
            type="button"
            className="text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors inline-flex items-center gap-2 cursor-pointer"
          >
            <ArrowRight className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
            {t('home')}
          </button>
        </div>
      </main>
    </div>
  );
}
