import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { SurveyQuestion, AnswerValue } from '../types';
import StarRating from './questions/StarRating';
import EmojiRating from './questions/EmojiRating';
import NPSRating from './questions/NPSRating';
import YesNoQuestion from './questions/YesNoQuestion';
import MultipleChoice from './questions/MultipleChoice';
import TextQuestion from './questions/TextQuestion';
import { useTranslation } from 'react-i18next';
import LanguageSwitcher from './LanguageSwitcher';
import { useSettingsStore } from '../store/useSettingsStore';
import { useSurveyStore } from '../store/useSurveyStore';
import { useSurveySessionTimer } from '../hooks/useSurveySessionTimer';
import {
  ChevronLeft,
  ArrowRight,
  CheckCircle2,
  Clock,
  Stethoscope,
  DoorOpen,
  Building2,
  Pill,
  ClipboardCheck,
  MessageSquare,
  Heart,
} from 'lucide-react';

const sectionIcons: Record<string, any> = {
  'door-open': DoorOpen,
  'stethoscope': Stethoscope,
  'building': Building2,
  'pill': Pill,
  'clipboard-check': ClipboardCheck,
};

function renderQuestion(question: SurveyQuestion, value: AnswerValue, onChange: (v: AnswerValue) => void) {
  switch (question.type) {
    case 'stars':
    case 'rating':
      return <StarRating value={(value as number) || 0} onChange={onChange} />;
    case 'emoji':
      return <EmojiRating value={(value as number) || 0} onChange={onChange} />;
    case 'nps':
      return <NPSRating value={(value as number) ?? -1} onChange={onChange} />;
    case 'yes_no':
      return <YesNoQuestion value={(value as string) || ''} onChange={onChange} />;
    case 'multiple_choice':
      return <MultipleChoice options={question.options || []} value={(value as string) || ''} onChange={onChange} />;
    case 'text':
      return <TextQuestion value={(value as string) || ''} onChange={onChange} />;
    default:
      return null;
  }
}

export default function SurveyPage() {
  const navigate = useNavigate();
  const {
    selectedSurvey: template,
    currentSection,
    answers,
    setAnswer: onAnswer,
    nextSection,
    prevSection,
    submitSurvey,
    clearSurveySessionTimer,
  } = useSurveyStore();
  const { formattedTime } = useSurveySessionTimer();

  const onNext = () => nextSection();
  const onPrev = () => {
    if (!prevSection()) navigate('/survey/info');
  };
  const onSubmit = async () => {
    const success = await submitSurvey();
    if (success) {
      clearSurveySessionTimer();
      navigate('/survey/thanks');
    }
  };

  const { t } = useTranslation();
  const { settings } = useSettingsStore();
  const hospitalMobileName = settings.hospital.shortName || settings.hospital.name;

  // Scroll to top when section changes
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior });
  }, [currentSection]);

  if (!template) return null;

  const sections = template.sections;
  const section = sections[currentSection];
  const isLastSection = currentSection === sections.length - 1;
  const totalSections = sections.length;
  const progress = ((currentSection + 1) / totalSections) * 100;

  const isSectionComplete = () => {
    return section.questions
      .filter(q => q.required)
      .every(q => {
        const val = answers[q.id];
        if (val === undefined || val === null || val === '' || val === 0 || val === -1) return false;
        return true;
      });
  };

  const IconComponent = sectionIcons[section.icon] || ClipboardCheck;

  // Branching Logic Helper
  const shouldShowFollowUp = (question: SurveyQuestion) => {
    const val = answers[question.id];
    // Trigger follow-up for low ratings (1 or 2 stars/emojis)
    if (typeof val === 'number' && (question.type === 'stars' || question.type === 'emoji' || question.type === 'rating') && val > 0 && val <= 2) {
      return true;
    }
    return false;
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-teal-50 via-white to-blue-50 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] text-gray-900 dark:text-slate-100 transition-colors duration-300">
      {/* Top Progress Bar */}
      <div className="fixed top-0 left-0 right-0 z-50 bg-white/90 dark:bg-slate-900/95 backdrop-blur-md border-b border-gray-100 dark:border-slate-800/80 transition-colors duration-300">
        <div className="h-1 bg-gray-100 dark:bg-slate-800">
          <div
            className="h-full bg-gradient-to-l from-teal-500 to-emerald-600 transition-all duration-700 ease-out rounded-full"
            style={{ width: `${progress}%` }}
          />
        </div>
        <div className="max-w-4xl mx-auto px-3 sm:px-4 py-3 flex items-center justify-between gap-2 min-w-0">
          <div className="flex items-center gap-2 sm:gap-4 min-w-0">
            {/* System Identity */}
            <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
              <div className="w-8 h-8 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-lg flex items-center justify-center shadow-md">
                <Stethoscope className="w-4 h-4 text-white" />
              </div>
              <span className="text-sm font-bold text-gray-700 dark:text-slate-200 hidden sm:block">MedSurvey Pro</span>
            </div>

            {/* Separator */}
            <div className="hidden sm:block h-6 w-px bg-gray-200 dark:bg-slate-850" />

            {/* Hospital Identity */}
            <div className="flex items-center gap-1.5 min-w-0">
              {settings.hospital.logo ? (
                <img src={settings.hospital.logo} alt={settings.hospital.name} className="h-6 max-w-[64px] sm:max-w-[80px] object-contain rounded shrink-0" />
              ) : (
                <div className="w-5 h-5 bg-teal-50 dark:bg-teal-950/20 border border-teal-200 dark:border-teal-900 rounded flex items-center justify-center text-teal-600 dark:text-teal-400">
                  <Heart className="w-3 h-3" />
                </div>
              )}
              <span className="text-xs font-semibold text-teal-700 dark:text-teal-400 block line-clamp-1 min-w-0">
                <span className="sm:hidden">{hospitalMobileName}</span>
                <span className="hidden sm:inline">{settings.hospital.name}</span>
              </span>
            </div>
          </div>
          <div className="flex items-center gap-2 sm:gap-4 shrink-0">
            <LanguageSwitcher />
            <div className="flex items-center gap-1.5 rounded-xl bg-teal-50 dark:bg-teal-950/30 px-3 py-2 text-xs font-black text-teal-700 dark:text-teal-400 border border-teal-100 dark:border-teal-900/40" dir="ltr">
              <Clock className="w-3.5 h-3.5" />
              {formattedTime}
            </div>
            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
              <span className="font-bold text-teal-600 dark:text-teal-400">{currentSection + 1}</span>
              <span>{t('of', 'من')}</span>
              <span>{totalSections}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Section Steps */}
      <div className="pt-20 pb-4 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="flex items-center justify-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
            {sections.map((s, i) => {
              const SIcon = sectionIcons[s.icon] || ClipboardCheck;
              return (
                <div
                  key={s.id}
                  className={`flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap transition-all ${
                    i === currentSection
                      ? 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400 shadow-sm'
                      : i < currentSection
                      ? 'bg-green-100 dark:bg-green-950/60 text-green-700 dark:text-green-400'
                      : 'bg-gray-100 dark:bg-slate-850 text-gray-400 dark:text-slate-500'
                  }`}
                >
                  {i < currentSection ? (
                    <CheckCircle2 className="w-3.5 h-3.5" />
                  ) : (
                    <SIcon className="w-3.5 h-3.5" />
                  )}
                  <span className="hidden sm:inline">{s.title}</span>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Section Content */}
      <div className="max-w-4xl mx-auto px-4 pb-24 text-center">
        <div className="animate-slide-up">
          {/* Section Header */}
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-2xl shadow-xl shadow-teal-200 dark:shadow-teal-950/30 mb-4">
              <IconComponent className="w-8 h-8 text-white" />
            </div>
            <h2 className="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-2">{section.title}</h2>
            <p className="text-gray-500 dark:text-slate-400">{section.description}</p>
          </div>

          {/* Questions */}
          <div className="space-y-6">
            {section.questions.map((question, qi) => (
              <div key={question.id} className="space-y-4">
                <div
                  className="min-w-0 bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all p-4 sm:p-8 animate-slide-up"
                  style={{ animationDelay: `${qi * 100}ms` }}
                >
                  <div className="mb-6">
                    <div className="flex items-start gap-3 mb-2">
                      <span className="flex-shrink-0 w-7 h-7 bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400 rounded-lg flex items-center justify-center text-xs font-bold">
                        {qi + 1}
                      </span>
                      <div className="flex-1 min-w-0">
                        <h3 className="text-base sm:text-lg font-bold text-gray-800 dark:text-white leading-relaxed text-start">
                          {question.title}
                          {question.required && <span className="text-red-500 mr-1">*</span>}
                        </h3>
                        {question.description && (
                          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1 text-start">{question.description}</p>
                        )}
                      </div>
                    </div>
                  </div>
                  {renderQuestion(question, answers[question.id], (v) => onAnswer(question.id, v))}
                </div>

                {/* Conditional Follow-up Question */}
                {shouldShowFollowUp(question) && (
                  <div className="bg-amber-50 dark:bg-amber-950/15 rounded-2xl border border-amber-100 dark:border-amber-900/30 p-6 sm:p-8 animate-slide-up ml-4 mr-4 shadow-inner">
                    <div className="flex items-start gap-3 mb-4 text-start">
                      <div className="w-6 h-6 rounded-full bg-amber-200 dark:bg-amber-955/45 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <MessageSquare className="w-3.5 h-3.5 text-amber-700 dark:text-amber-450" />
                      </div>
                      <h4 className="text-sm font-bold text-amber-900 dark:text-amber-300">
                        {t('follow_up_reason')}
                      </h4>
                    </div>
                    <TextQuestion 
                      value={(answers[`${question.id}_reason`] as string) || ''} 
                      onChange={(v) => onAnswer(`${question.id}_reason`, v)} 
                      placeholder={t('explain_reason', 'يرجى التوضيح هنا...')}
                    />
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Bottom Navigation */}
      <div className="fixed bottom-0 left-0 right-0 bg-white/90 dark:bg-slate-900/95 backdrop-blur-md border-t border-gray-100 dark:border-slate-800/80 z-40 transition-colors duration-300">
        <div className="max-w-4xl mx-auto px-3 sm:px-4 py-3 sm:py-4 grid grid-cols-[1fr_auto_1fr] items-center gap-2">
            <button
              onClick={onPrev}
              type="button"
            className="min-w-0 justify-self-start flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-medium transition-all text-gray-600 dark:text-slate-350 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-800 dark:hover:text-white cursor-pointer"
          >
            <ArrowRight className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
            <span className="truncate">{t('previous')}</span>
          </button>

          <div className="hidden min-[360px]:flex items-center gap-1.5">
            {sections.map((_, i) => (
              <div
                key={i}
                className={`h-2 rounded-full transition-all duration-300 ${
                  i === currentSection ? 'w-8 bg-teal-500' : i < currentSection ? 'w-2 bg-teal-300' : 'w-2 bg-gray-200 dark:bg-slate-800'
                }`}
              />
            ))}
          </div>

          {isLastSection ? (
            <button
              onClick={onSubmit}
              disabled={!isSectionComplete()}
              type="button"
              className={`min-w-0 justify-self-end flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-6 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-bold text-white transition-all duration-300 cursor-pointer ${
                isSectionComplete()
                  ? 'bg-gradient-to-l from-green-500 to-emerald-500 shadow-lg shadow-green-200 dark:shadow-green-950/20 hover:shadow-xl hover:-translate-y-0.5'
                  : 'bg-gray-300 dark:bg-slate-800 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'
              }`}
            >
              <CheckCircle2 className="w-5 h-5" />
              <span className="truncate">{t('submit')}</span>
            </button>
          ) : (
            <button
              onClick={onNext}
              disabled={!isSectionComplete()}
              type="button"
              className={`min-w-0 justify-self-end flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-6 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-bold text-white transition-all duration-300 cursor-pointer ${
                isSectionComplete()
                  ? 'bg-gradient-to-l from-teal-600 to-emerald-600 shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5'
                  : 'bg-gray-300 dark:bg-slate-800 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'
              }`}
            >
              <span className="truncate">{t('next')}</span>
              <ChevronLeft className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
