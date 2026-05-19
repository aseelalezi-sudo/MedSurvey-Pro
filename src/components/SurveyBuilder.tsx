import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useForm, FormProvider, useFieldArray, useFormContext } from 'react-hook-form';
import { SurveyTemplate, QuestionType } from '../types';
import { useSettingsStore } from '../store/useSettingsStore';
import { useSurveyStore } from '../store/useSurveyStore';
import {
  Plus, Trash2, Save, X, ChevronDown, ChevronUp, GripVertical, Star, Smile,
  MessageSquare, CheckSquare, ToggleLeft, Hash, ClipboardList, DoorOpen,
  Stethoscope, Building2, Pill, ClipboardCheck, Users, Activity, Heart,
  FileText, AlertCircle, Check, Clock, User, Phone,
  type LucideIcon
} from 'lucide-react';

const sectionIcons = [
  { id: 'door-open', icon: DoorOpen, labelKey: 'survey_section_reception' },
  { id: 'stethoscope', icon: Stethoscope, labelKey: 'survey_section_doctor' },
  { id: 'building', icon: Building2, labelKey: 'survey_section_building' },
  { id: 'pill', icon: Pill, labelKey: 'survey_section_pharmacy' },
  { id: 'clipboard-check', icon: ClipboardCheck, labelKey: 'survey_section_review' },
  { id: 'users', icon: Users, labelKey: 'survey_section_staff' },
  { id: 'activity', icon: Activity, labelKey: 'survey_section_activity' },
  { id: 'heart', icon: Heart, labelKey: 'survey_section_care' },
  { id: 'file-text', icon: FileText, labelKey: 'survey_section_documents' },
];

const questionTypes: { type: QuestionType; labelKey: string; icon: LucideIcon }[] = [
  { type: 'stars', labelKey: 'survey_qtype_stars', icon: Star },
  { type: 'emoji', labelKey: 'survey_qtype_emoji', icon: Smile },
  { type: 'nps', labelKey: 'survey_qtype_nps', icon: Hash },
  { type: 'yes_no', labelKey: 'survey_qtype_yes_no', icon: ToggleLeft },
  { type: 'multiple_choice', labelKey: 'survey_qtype_multiple', icon: CheckSquare },
  { type: 'text', labelKey: 'survey_qtype_text', icon: MessageSquare },
];

// --- Sub-components ---

const OptionList = ({ sectionIndex, questionIndex }: { sectionIndex: number; questionIndex: number }) => {
  const { t } = useTranslation();
  const { register, control, setValue } = useFormContext<SurveyTemplate>();
  const { fields, append, remove } = useFieldArray({
    control,
    name: `sections.${sectionIndex}.questions.${questionIndex}.options` as const
  });

  return (
    <div className="space-y-2 pl-6 text-start">
      <label className="text-xs font-bold text-gray-500 dark:text-slate-400">{t('survey_options_label')}</label>
      {fields.map((field, index) => (
        <div key={field.id} className="flex items-center gap-2">
          <input
            {...register(`sections.${sectionIndex}.questions.${questionIndex}.options.${index}.label` as const)}
            onChange={(e) => {
              const val = e.target.value;
              setValue(`sections.${sectionIndex}.questions.${questionIndex}.options.${index}.label` as const, val);
              setValue(`sections.${sectionIndex}.questions.${questionIndex}.options.${index}.value` as const, val);
            }}
            placeholder={t('survey_option_placeholder')}
            className="flex-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-900 dark:text-white focus:border-teal-500 outline-none text-xs font-medium"
          />
          <button type="button" onClick={() => remove(index)} className="text-gray-400 hover:text-red-500 cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>
      ))}
      <button
        type="button"
        onClick={() => append({ id: `opt-${Date.now()}`, label: '', value: `option_${Date.now()}` })}
        className="text-xs text-teal-600 dark:text-teal-400 hover:text-teal-750 font-bold flex items-center gap-1 cursor-pointer"
      >
        <Plus className="w-3 h-3" />
        {t('survey_add_option')}
      </button>
    </div>
  );
};

const QuestionList = ({ sectionIndex }: { sectionIndex: number }) => {
  const { t } = useTranslation();
  const { register, control, watch, setValue } = useFormContext<SurveyTemplate>();
  const { fields, append, remove } = useFieldArray({
    control,
    name: `sections.${sectionIndex}.questions` as const
  });

  return (
    <div className="space-y-3 text-start">
      <div className="flex items-center justify-between">
        <h4 className="font-bold text-gray-600 dark:text-slate-350 text-sm">{t('survey_questions_count')} ({fields.length})</h4>
        <button
          type="button"
          onClick={() => append({
            id: `question-${Date.now()}`,
            type: 'stars',
            title: '',
            description: '',
            required: false,
            category: '',
          })}
          className="flex items-center gap-1 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors cursor-pointer"
        >
          <Plus className="w-3 h-3" />
          {t('survey_add_question')}
        </button>
      </div>

      {fields.map((question, qi) => {
        const currentType = watch(`sections.${sectionIndex}.questions.${qi}.type`);
        const isRequired = watch(`sections.${sectionIndex}.questions.${qi}.required`);

        return (
          <div key={question.id} className="bg-gray-50 dark:bg-slate-900/60 border border-transparent dark:border-slate-800 rounded-xl p-4 space-y-3">
            <div className="flex items-start gap-3">
              <span className="w-6 h-6 bg-teal-100 dark:bg-teal-950/30 text-teal-700 dark:text-teal-400 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0 mt-1">
                {qi + 1}
              </span>
              <div className="flex-1 space-y-3">
                {/* Question Type */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {questionTypes.map(qt => {
                    const QTIcon = qt.icon;
                    return (
                      <button
                        type="button"
                        key={qt.type}
                        onClick={() => setValue(`sections.${sectionIndex}.questions.${qi}.type`, qt.type)}
                        className={`p-2 rounded-lg border-2 text-xs font-medium transition-all flex items-center gap-1.5 cursor-pointer ${
                          currentType === qt.type
                            ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400'
                            : 'border-gray-200 dark:border-slate-750 text-gray-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-600'
                        }`}
                      >
                        <QTIcon className="w-3.5 h-3.5" />
                        {t(qt.labelKey)}
                      </button>
                    );
                  })}
                </div>

                <input
                  {...register(`sections.${sectionIndex}.questions.${qi}.title` as const)}
                  placeholder={t('survey_question_title_placeholder')}
                  className="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-medium"
                />

                <input
                  {...register(`sections.${sectionIndex}.questions.${qi}.description` as const)}
                  placeholder={t('survey_question_desc_placeholder')}
                  className="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-750 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/20 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-medium"
                />

                {currentType === 'multiple_choice' && (
                  <OptionList sectionIndex={sectionIndex} questionIndex={qi} />
                )}

                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => setValue(`sections.${sectionIndex}.questions.${qi}.required`, !isRequired)}
                    className={`w-10 h-5 rounded-full transition-all relative cursor-pointer ${
                      isRequired ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'
                    }`}
                  >
                    <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white shadow-sm transition-all ${
                      isRequired ? 'right-5' : 'right-0.5'
                    }`} />
                  </button>
                  <span className="text-xs text-gray-500 dark:text-slate-400">{t('survey_required_answer')}</span>
                </div>
              </div>

              <button
                type="button"
                onClick={() => remove(qi)}
                className="text-gray-400 hover:text-red-500 p-1 flex-shrink-0 cursor-pointer"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>
        );
      })}
    </div>
  );
};

const SectionList = () => {
  const { t } = useTranslation();
  const { register, control, watch, setValue } = useFormContext<SurveyTemplate>();
  const { fields, append, remove } = useFieldArray({
    control,
    name: 'sections'
  });
  const [expandedSection, setExpandedSection] = useState<string | null>(null);

  const sectionsWatch = watch('sections');

  return (
    <div className="space-y-4 text-start">
      <div className="flex items-center justify-between">
        <h3 className="font-bold text-gray-700 dark:text-white flex items-center gap-2">
          <FileText className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('survey_sections_count')} ({fields.length})
        </h3>
        <button
          type="button"
          onClick={() => {
            const newId = `section-${Date.now()}`;
            append({
              id: newId,
              title: '',
              description: '',
              icon: 'clipboard-check',
              questions: [],
            });
            setExpandedSection(newId);
          }}
          className="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          {t('survey_add_section')}
        </button>
      </div>

      {fields.length === 0 && (
        <div className="text-center py-12 bg-gray-50 dark:bg-slate-800/40 rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-750">
          <AlertCircle className="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-3" />
          <p className="text-gray-500 dark:text-slate-400">{t('survey_no_sections')}</p>
        </div>
      )}

      {fields.map((section, si) => {
        const isExpanded = expandedSection === section.id;
        const currentIcon = sectionsWatch?.[si]?.icon || section.icon;
        const IconComp = sectionIcons.find(i => i.id === currentIcon)?.icon || ClipboardCheck;
        const currentQuestionsCount = sectionsWatch?.[si]?.questions?.length || 0;

        return (
          <div key={section.id} className="border border-gray-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <div
              className="bg-gray-50 dark:bg-slate-800/60 p-4 flex items-center gap-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors"
              onClick={() => setExpandedSection(isExpanded ? null : section.id)}
            >
              <GripVertical className="w-5 h-5 text-gray-300 dark:text-slate-600" />
              <IconComp className="w-5 h-5 text-teal-600 dark:text-teal-400" />
              <div className="flex-1">
                <span className="font-bold text-gray-700 dark:text-white">
                  {sectionsWatch?.[si]?.title || `${t('survey_section_label')} ${si + 1}`}
                </span>
                <span className="text-sm text-gray-400 dark:text-slate-450 mr-2">
                  ({currentQuestionsCount} {t('survey_questions_label')})
                </span>
              </div>
              <button
                type="button"
                onClick={e => { e.stopPropagation(); remove(si); }}
                className="text-gray-400 hover:text-red-500 p-1 cursor-pointer"
              >
                <Trash2 className="w-4 h-4" />
              </button>
              {isExpanded ? <ChevronUp className="w-5 h-5 text-gray-400" /> : <ChevronDown className="w-5 h-5 text-gray-400" />}
            </div>

            {isExpanded && (
              <div className="p-4 space-y-4 bg-white dark:bg-slate-900 border-t border-gray-150 dark:border-slate-800 animate-slide-up">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('survey_section_title_label')}</label>
                    <input
                      {...register(`sections.${si}.title` as const)}
                      placeholder={t('survey_section_label')}
                      className="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-medium"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('survey_section_desc_label')}</label>
                    <input
                      {...register(`sections.${si}.description` as const)}
                      placeholder={t('survey_section_desc_placeholder')}
                      className="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-medium"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('survey_section_icon_label')}</label>
                  <div className="flex flex-wrap gap-2">
                    {sectionIcons.map(siOption => {
                      const SIIcon = siOption.icon;
                      return (
                        <button
                          type="button"
                          key={siOption.id}
                          onClick={() => setValue(`sections.${si}.icon`, siOption.id)}
                          className={`p-3 rounded-xl border-2 transition-all cursor-pointer ${
                            currentIcon === siOption.id
                              ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/30'
                              : 'border-gray-200 dark:border-slate-750 hover:border-gray-300 dark:hover:border-slate-650'
                          }`}
                          title={t(siOption.labelKey)}
                        >
                          <SIIcon className={`w-5 h-5 ${currentIcon === siOption.id ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-slate-400'}`} />
                        </button>
                      );
                    })}
                  </div>
                </div>

                <QuestionList sectionIndex={si} />
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

const SurveyEditorModal = ({ onSave, onClose }: { onSave: (data: SurveyTemplate) => void; onClose: () => void }) => {
  const { t } = useTranslation();
  const { settings } = useSettingsStore();
  const departments = settings.departments.filter(d => d.isActive).map(d => d.name);

  const methods = useFormContext<SurveyTemplate>();
  const { register, handleSubmit, watch, setValue } = methods;

  const isActive = watch('isActive');
  const requireName = watch('requireName');
  const requirePhone = watch('requirePhone');
  const assignedDepartments = watch('assignedDepartments') || [];
  const currentTitle = watch('title');

  // Custom tips handling since it's an array of strings, not objects
  const tips = watch('tips') || [];

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-start justify-center z-50 p-4 overflow-y-auto">
      <div className="bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl max-w-4xl w-full my-8 animate-scale-in text-start shadow-2xl">
        <form onSubmit={handleSubmit(onSave)}>
          <div className="p-6 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 rounded-t-2xl z-10">
            <h2 className="text-xl font-bold text-gray-800 dark:text-white">
              {currentTitle ? `${t('survey_edit_title_prefix')}: ${currentTitle}`: t('survey_new_title')}
            </h2>
            <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-650 dark:hover:text-slate-300 cursor-pointer">
              <X className="w-6 h-6" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            <div className="space-y-4">
              <h3 className="font-bold text-gray-700 dark:text-white flex items-center gap-2">
                <ClipboardList className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                {t('survey_basic_info')}
              </h3>
              <div className="grid grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
                    {t('survey_title_label')} <span className="text-red-500">*</span>
                  </label>
                  <input
                    {...register('title')}
                    placeholder={t('survey_title_placeholder')}
                    className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
                  />
                </div>
                <div>
                  <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('survey_description_label')}</label>
                  <textarea
                    {...register('description')}
                    placeholder={t('survey_description_placeholder')}
                    rows={2}
                    className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none resize-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
                  />
                </div>
              </div>

              <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl">
                <div>
                  <p className="font-bold text-gray-700 dark:text-slate-200">{t('survey_status_label')}</p>
                  <p className="text-sm text-gray-500 dark:text-slate-400">{t('survey_status_desc')}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setValue('isActive', !isActive)}
                  className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${isActive ? 'bg-teal-500' : 'bg-gray-300 dark:bg-slate-700'}`}
                >
                  <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${isActive ? 'right-7' : 'right-0.5'}`} />
                </button>
              </div>

              <div className={`flex items-center justify-between p-4 rounded-xl border-2 transition-all ${requireName ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'}`}>
                <div className="flex-1 min-w-0 text-start">
                  <p className="font-bold text-gray-700 dark:text-slate-200">{t('survey_name_field')}</p>
                  <p className="text-sm text-gray-500 dark:text-slate-400">{requireName ? t('survey_name_required_desc') : t('survey_name_optional_desc')}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setValue('requireName', !requireName)}
                  className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${requireName ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'}`}
                >
                  <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${requireName ? 'right-7' : 'right-0.5'}`} />
                </button>
              </div>

              <div className={`flex items-center justify-between p-4 rounded-xl border-2 transition-all ${requirePhone ? 'bg-orange-50 border-orange-200 dark:bg-orange-950/20 dark:border-orange-900/40' : 'bg-gray-50 dark:bg-slate-950 border-transparent dark:border-slate-800'}`}>
                <div className="flex-1 min-w-0 text-start">
                  <p className="font-bold text-gray-700 dark:text-slate-200">{t('survey_phone_field')}</p>
                  <p className="text-sm text-gray-500 dark:text-slate-400">{requirePhone ? t('survey_phone_required_desc') : t('survey_name_optional_desc')}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setValue('requirePhone', !requirePhone)}
                  className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${requirePhone ? 'bg-orange-500' : 'bg-gray-300 dark:bg-slate-700'}`}
                >
                  <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${requirePhone ? 'right-7' : 'right-0.5'}`} />
                </button>
              </div>

              <div className="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-start">
                <div className="flex items-center justify-between">
                  <h3 className="font-bold text-gray-700 dark:text-white flex items-center gap-2">
                    <Heart className="w-5 h-5 text-red-500" />
                    {t('survey_medical_tips_title')}
                  </h3>
                  <button type="button" onClick={() => setValue('tips', [...tips, ''])} className="text-xs font-bold text-teal-600 dark:text-teal-400 cursor-pointer">
                    {t('survey_add_tip')}
                  </button>
                </div>
                <div className="space-y-2">
                  {tips.map((tip, index) => (
                    <div key={index} className="flex items-center gap-2">
                      <input
                        type="text"
                        value={tip}
                        onChange={(e) => {
                          const newTips = [...tips];
                          newTips[index] = e.target.value;
                          setValue('tips', newTips);
                        }}
                        className="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-750 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-sm font-medium"
                      />
                      <button type="button" onClick={() => setValue('tips', tips.filter((_, i) => i !== index))} className="p-2.5 text-red-400 hover:text-red-500 cursor-pointer">
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-4 text-start">
              <h3 className="font-bold text-gray-700 dark:text-white flex items-center gap-2">
                <Building2 className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                {t('survey_assigned_depts')}
              </h3>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                {departments.map(dept => {
                  const isSelected = assignedDepartments.includes(dept);
                  return (
                    <button
                      type="button"
                      key={dept}
                      onClick={() => {
                        const updated = isSelected ? assignedDepartments.filter(d => d !== dept) : [...assignedDepartments, dept];
                        setValue('assignedDepartments', updated);
                      }}
                      className={`p-3 rounded-xl border-2 text-sm font-medium transition-all cursor-pointer ${
                        isSelected 
                          ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400' 
                          : 'border-gray-200 dark:border-slate-750 bg-white dark:bg-slate-950 text-gray-700 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800 hover:border-gray-350'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        {isSelected && <Check className="w-4 h-4 text-teal-600 dark:text-teal-400" />}
                        {dept}
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>

            <SectionList />
          </div>

          <div className="p-6 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between sticky bottom-0 bg-white dark:bg-slate-900 rounded-b-2xl z-10">
            <button type="button" onClick={onClose} className="px-6 py-3 rounded-xl text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 font-bold transition-all cursor-pointer">
              {t('survey_cancel')}
            </button>
            <button type="submit" disabled={!currentTitle} className="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white bg-teal-600 hover:bg-teal-700 disabled:bg-gray-350 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:disabled:text-slate-500 disabled:cursor-not-allowed transition-all cursor-pointer">
              <Save className="w-5 h-5" />
              {t('survey_save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default function SurveyBuilder() {
  const { surveys, saveSurvey, deleteSurvey } = useSurveyStore();
  const onSave = (survey: SurveyTemplate) => saveSurvey(survey);
  const onDelete = (id: string) => deleteSurvey(id);
  const { t } = useTranslation();
  const [showModal, setShowModal] = useState(false);
  const [showConfirmDelete, setShowConfirmDelete] = useState<string | null>(null);
  
  const methods = useForm<SurveyTemplate>();

  const createNewSurvey = (): SurveyTemplate => ({
    id: `survey-${Date.now()}`,
    title: '',
    description: '',
    sections: [],
    createdAt: new Date().toISOString(),
    isActive: true,
    assignedDepartments: [],
    requireName: false,
    requirePhone: false,
    tips: []
  });

  const handleCreateNew = () => {
    methods.reset(createNewSurvey());
    setShowModal(true);
  };

  const handleEdit = (survey: SurveyTemplate) => {
    methods.reset(survey);
    setShowModal(true);
  };

  const handleDuplicate = (survey: SurveyTemplate) => {
    const duplicated: SurveyTemplate = {
      ...survey,
      id: `survey-${Date.now()}`,
      title: `${survey.title} (${t('survey_duplicate_suffix', 'نسخة')})`,
      createdAt: new Date().toISOString(),
    };
    onSave(duplicated);
  };

  return (
    <div className="animate-fade-in text-start">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 border-b border-gray-100 dark:border-slate-800/80 pb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gradient-to-br from-teal-500 to-teal-600 dark:from-teal-600 dark:to-teal-800 rounded-xl flex items-center justify-center shadow-lg shadow-teal-100 dark:shadow-none">
              <ClipboardList className="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white leading-tight">{t('survey_builder_title', 'إدارة وتصميم الاستبيانات')}</h2>
              <p className="text-xs text-gray-500 dark:text-slate-400 mt-1.5">{t('survey_builder_subtitle', 'قم بإنشاء وتعديل استبيانات رضا المرضى وتخصيصها للأقسام الطبية')}</p>
            </div>
          </div>
          <button
            onClick={handleCreateNew}
            className="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-250 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
          >
            <Plus className="w-5 h-5" />
            {t('survey_create_new', 'إضافة استبيان جديد')}
          </button>
        </div>

        {/* Empty State */}
        {surveys.length === 0 && (
          <div className="text-center py-20 bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm">
            <div className="w-20 h-20 bg-teal-55 dark:bg-teal-950/20 text-teal-600 dark:text-teal-400 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
              <ClipboardList className="w-10 h-10" />
            </div>
            <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-2">{t('survey_no_surveys_title', 'لا توجد استبيانات مضافة حالياً')}</h3>
            <p className="text-gray-500 dark:text-slate-400 max-w-sm mx-auto mb-6 text-sm">{t('survey_no_surveys_desc', 'ابدأ بإنشاء استبيانك الأول لتتمكن من جمع وتقييم آراء المرضى وتطوير أداء عيادات المستشفى.')}</p>
            <button
              onClick={handleCreateNew}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-sm transition-colors cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              {t('survey_create_new', 'إضافة استبيان جديد')}
            </button>
          </div>
        )}

        {/* Grid List */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {surveys.map(survey => {
            const totalQuestions = survey.sections.reduce((acc, s) => acc + s.questions.length, 0);
            return (
              <div 
                key={survey.id} 
                className="group bg-white dark:bg-slate-900 rounded-3xl border border-gray-150/70 dark:border-slate-800/80 shadow-md hover:shadow-2xl hover:border-teal-500/20 dark:hover:border-teal-500/30 transition-all duration-300 overflow-hidden flex flex-col text-start animate-fade-in"
              >
                {/* Card Header */}
                <div className={`p-6 text-white relative overflow-hidden text-start ${
                  survey.isActive 
                    ? 'bg-gradient-to-br from-teal-500 to-emerald-600' 
                    : 'bg-gradient-to-br from-slate-400 to-slate-500 dark:from-slate-700 dark:to-slate-800'
                }`}>
                  <div className="absolute inset-0 opacity-10">
                    <div className="absolute -top-10 -left-10 w-40 h-40 bg-white rounded-full" />
                    <div className="absolute -bottom-10 -right-10 w-32 h-32 bg-white rounded-full" />
                  </div>
                  
                  <div className="relative">
                    {/* Top row with Icon and Status badge */}
                    <div className="flex items-center justify-between mb-4">
                      <div className="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <ClipboardList className="w-6 h-6 text-white" />
                      </div>
                      <span className={`px-2.5 py-1 rounded-full text-[10px] font-black ${
                        survey.isActive 
                          ? 'bg-emerald-500/20 border border-emerald-400/30 text-emerald-100' 
                          : 'bg-white/20 border border-white/25 text-white/90'
                      }`}>
                        {survey.isActive ? t('survey_status_active', 'نشط') : t('survey_status_inactive', 'غير نشط')}
                      </span>
                    </div>

                    <h3 className="text-lg font-black mb-1.5 leading-relaxed text-white line-clamp-1">{survey.title}</h3>
                    <p className={`text-xs line-clamp-2 min-h-[2rem] ${survey.isActive ? 'text-teal-100' : 'text-slate-100/90'}`}>
                      {survey.description || t('survey_no_description', 'لا يوجد وصف لهذا الاستبيان.')}
                    </p>
                  </div>
                </div>

                {/* Card Body */}
                <div className="p-5 flex-1 flex flex-col justify-between text-start">
                  {/* Stats */}
                  <div className="flex items-center gap-4 mb-4 text-xs font-medium text-gray-500 dark:text-slate-400">
                    <div className="flex items-center gap-1.5">
                      <FileText className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                      <span>{survey.sections.length} {t('survey_sections_count', 'أقسام')}</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <ClipboardCheck className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                      <span>{totalQuestions} {t('survey_questions_label', 'أسئلة')}</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Clock className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                      <span className="font-mono">{new Date(survey.createdAt).toLocaleDateString('ar-EG')}</span>
                    </div>
                  </div>

                  {/* Assigned Departments tags */}
                  <div className="flex flex-wrap gap-1.5 mb-5 min-h-[1.75rem]">
                    {/* Require Name Badge */}
                    {survey.requireName && (
                      <span className="bg-amber-50 dark:bg-amber-950/20 border border-amber-100/30 dark:border-amber-900/40 text-[10px] font-bold text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <User className="w-3 h-3" />
                        <span>{t('survey_require_name_badge', 'الاسم مطلوب')}</span>
                      </span>
                    )}

                    {/* Require Phone Badge */}
                    {survey.requirePhone && (
                      <span className="bg-orange-50 dark:bg-orange-950/20 border border-orange-100/30 dark:border-orange-900/40 text-[10px] font-bold text-orange-700 dark:text-orange-400 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <Phone className="w-3 h-3" />
                        <span>{t('survey_require_phone_badge', 'الهاتف مطلوب')}</span>
                      </span>
                    )}

                    {survey.assignedDepartments && survey.assignedDepartments.length > 0 ? (
                      survey.assignedDepartments.slice(0, 2).map((dept, index) => (
                        <span 
                          key={index} 
                          className="bg-teal-50 dark:bg-teal-950/20 border border-teal-100/30 dark:border-teal-900/40 text-[10px] font-bold text-teal-700 dark:text-teal-400 px-2 py-0.5 rounded-md"
                        >
                          {dept}
                        </span>
                      ))
                    ) : (
                      <span className="bg-slate-50 dark:bg-slate-800/30 border border-slate-150/40 dark:border-slate-800 text-[10px] font-bold text-slate-400 dark:text-slate-500 px-2.5 py-0.5 rounded-md flex items-center gap-1">
                        <Building2 className="w-3 h-3 text-slate-400 dark:text-slate-500" />
                        <span>{t('not_assigned_to_any_dept', 'غير مخصص لأي قسم')}</span>
                      </span>
                    )}
                    {survey.assignedDepartments && survey.assignedDepartments.length > 2 && (
                      <span className="bg-gray-100 dark:bg-slate-800 text-[10px] font-bold text-gray-500 dark:text-slate-400 px-2 py-0.5 rounded-md">
                        +{survey.assignedDepartments.length - 2}
                      </span>
                    )}
                  </div>

                  {/* Action Buttons inside custom drawer style */}
                  <div className="flex items-center gap-2 pt-4 border-t border-gray-100 dark:border-slate-800/80 mt-auto">
                    <button 
                      onClick={() => handleDuplicate(survey)} 
                      className="flex-1 py-2 px-3 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/50 dark:hover:bg-slate-800 border border-gray-150/70 dark:border-slate-750 text-xs font-bold text-gray-600 dark:text-slate-300 transition-all cursor-pointer flex items-center justify-center gap-1"
                    >
                      <span>{t('survey_duplicate', 'تكرار')}</span>
                    </button>
                    <button 
                      onClick={() => handleEdit(survey)} 
                      className="flex-1 py-2 px-3 rounded-xl bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/30 dark:hover:bg-teal-950/50 border border-teal-100/30 dark:border-teal-900/40 text-xs font-bold text-teal-600 dark:text-teal-400 transition-all cursor-pointer flex items-center justify-center gap-1"
                    >
                      <span>{t('survey_edit', 'تعديل')}</span>
                    </button>
                    <button 
                      onClick={() => setShowConfirmDelete(survey.id)} 
                      className="py-2 px-2.5 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/30 border border-red-100/30 dark:border-red-900/40 text-xs font-bold text-red-500 dark:text-red-400 transition-all cursor-pointer flex items-center justify-center"
                      title={t('survey_delete', 'حذف')}
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Editor Modal Overlay */}
      {showModal && (
        <FormProvider {...methods}>
          <SurveyEditorModal
            onClose={() => setShowModal(false)}
            onSave={(data) => {
              onSave(data);
              setShowModal(false);
            }}
          />
        </FormProvider>
      )}

      {/* Delete Confirmation Modal */}
      {showConfirmDelete && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 animate-scale-in text-center shadow-2xl">
            <div className="w-16 h-16 bg-red-100 dark:bg-red-950/20 rounded-full flex items-center justify-center mx-auto mb-4">
              <AlertCircle className="w-8 h-8 text-red-500 dark:text-red-400" />
            </div>
            <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-2">{t('survey_delete_confirm_title', 'حذف الاستبيان نهائياً؟')}</h3>
            <p className="text-gray-500 dark:text-slate-400 text-sm mb-6">{t('survey_delete_confirm_desc', 'هل أنت متأكد من حذف هذا الاستبيان؟ سيتم مسح كافة البيانات والإحصائيات المرتبطة به ولا يمكن التراجع.')}</p>
            <div className="flex items-center gap-3">
              <button
                onClick={() => setShowConfirmDelete(null)}
                className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                {t('survey_cancel')}
              </button>
              <button
                onClick={() => {
                  onDelete(showConfirmDelete);
                  setShowConfirmDelete(null);
                }}
                className="flex-1 px-4 py-3 rounded-xl bg-red-500 hover:bg-red-650 text-white font-bold transition-colors cursor-pointer"
              >
                {t('survey_delete')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
