import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PatientInfo } from '../types';
import { useSettingsStore } from '../store/useSettingsStore';
import { useSurveyStore } from '../store/useSurveyStore';
import { useTranslation } from 'react-i18next';

import { useThemeStore } from '../store/useThemeStore';
import { useSurveySessionTimer } from '../hooks/useSurveySessionTimer';
import { User, Calendar, Building2, Activity, ChevronLeft, ArrowRight, Phone, AlertCircle, Heart, Clock, Globe, Sun, Moon } from 'lucide-react';

export default function PatientInfoForm() {
  const navigate = useNavigate();
  const { patientInfo, selectedSurvey, updatePatientInfo } = useSurveyStore();
  const onUpdate = (field: keyof PatientInfo, value: string) => updatePatientInfo(field, value);
  const onNext = () => navigate('/survey/take');
  const onBack = () => navigate('/survey-selection');
  const { t, i18n } = useTranslation();
  const { formattedTime } = useSurveySessionTimer();
  const { settings } = useSettingsStore();
  const { theme, toggleTheme } = useThemeStore();
  const hospitalMobileName = settings.hospital.shortName || settings.hospital.name;
  const departments = settings.departments.filter(d => d.isActive).map(d => d.name);
  const ageGroups = settings.ageGroups.filter(a => a.isActive).map(a => a.label);
  const visitTypes = settings.visitTypes.filter(v => v.isActive).map(v => v.label);
  const [phoneError, setPhoneError] = useState('');

  // Read require settings from selected survey only
  const requireName = selectedSurvey?.requireName ?? false;
  const requirePhone = selectedSurvey?.requirePhone ?? false;

  // Name validation: Only allow letters (Arabic and English) and spaces
  const handleNameChange = (value: string) => {
    const lettersOnly = value.replace(/[^\u0621-\u064A\u0671-\u06D3a-zA-Z\s]/g, '');
    onUpdate('name', lettersOnly);
  };

  // Phone validation: 9 digits, must start with 7
  const validatePhone = (value: string): boolean => {
    if (!value) return false;
    if (value.length !== 9) return false;
    if (!value.startsWith('7')) return false;
    return /^\d{9}$/.test(value);
  };

  const handlePhoneChange = (value: string) => {
    const digitsOnly = value.replace(/\D/g, '');
    const limited = digitsOnly.slice(0, 9);
    onUpdate('phone', limited);

    if (limited.length > 0 && !limited.startsWith('7')) {
      setPhoneError(t('phone_start_with_7', 'رقم الهاتف يجب أن يبدأ بالرقم 7'));
    } else if (limited.length > 0 && limited.length < 9) {
      setPhoneError(t('phone_enter_more', { count: 9 - limited.length }));
    } else {
      setPhoneError('');
    }
  };

  const isNameValid = requireName ? patientInfo.name.trim().length > 0 : true;
  const isPhoneValid = requirePhone ? validatePhone(patientInfo.phone) : (!patientInfo.phone || validatePhone(patientInfo.phone) || patientInfo.phone.length === 0);

  const isValid = 
    isNameValid && 
    isPhoneValid && 
    patientInfo.department && 
    patientInfo.gender && 
    patientInfo.ageGroup && 
    patientInfo.visitType;

  return (
    <div className="min-h-screen bg-linear-to-br from-teal-50 via-white to-blue-50 dark:from-[#09101d] dark:via-[#080c14] dark:to-[#0a1424] flex items-center justify-center p-4 text-gray-900 dark:text-slate-100 transition-colors duration-300">
      <div className="w-full max-w-2xl animate-scale-in">
        <div className="bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-gray-100 dark:border-slate-800/80 overflow-hidden">
          {/* Header */}
          <div className="bg-linear-to-l from-teal-600 to-emerald-600 px-6 sm:px-8 py-6 text-white text-start">
            <div className="flex items-center justify-between gap-3 mb-4 min-w-0">
              <div className="flex items-center gap-2 min-w-0">
                {settings.hospital.logo ? (
                  <div className="relative group bg-white p-0.5 rounded-lg border border-gray-200 dark:border-slate-600 shadow-md flex items-center justify-center shrink-0">
                    <img src={settings.hospital.logo} alt={settings.hospital.name} className="h-7 sm:h-8 w-auto max-w-[64px] sm:max-w-[88px] object-contain rounded-md transform group-hover:scale-105 transition-transform duration-300" />
                  </div>
                ) : (
                  <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <Heart className="w-4 h-4 text-white" />
                  </div>
                )}
                <div className="text-start min-w-0">
                  <span className="text-sm font-bold tracking-wide block leading-none whitespace-nowrap">
                    <span className="sm:hidden">{hospitalMobileName}</span>
                    <span className="hidden sm:inline">{settings.hospital.name}</span>
                  </span>
                  <span className="text-[10px] text-teal-100 block mt-1 leading-none">{settings.hospital.operatingTitle || t('operating_hospital', 'المستشفى المشغل')}</span>
                </div>
              </div>
              <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
                {/* Timer */}
                <div className="flex items-center gap-1.5 rounded-xl bg-white/15 hover:bg-white/20 px-3 py-2 text-xs font-black text-white border border-white/10 shadow-sm transition-all select-none" dir="ltr">
                  <Clock className="w-3.5 h-3.5" />
                  {formattedTime}
                </div>

                {/* Language Switcher */}
                <button
                  onClick={() => {
                    const newLng = i18n.language === 'ar' ? 'en' : 'ar';
                    i18n.changeLanguage(newLng);
                    document.documentElement.dir = newLng === 'ar' ? 'rtl' : 'ltr';
                    document.documentElement.lang = newLng;
                  }}
                  type="button"
                  title={i18n.language === 'ar' ? 'English' : t('arabic_language')}
                  className="flex items-center gap-1.5 rounded-xl bg-white/15 hover:bg-white/20 px-3 py-2 text-xs font-black text-white border border-white/10 shadow-sm transition-all cursor-pointer select-none"
                >
                  <Globe className="w-3.5 h-3.5" />
                  <span className="hidden sm:inline">{i18n.language === 'ar' ? 'English' : t('arabic_language')}</span>
                </button>

                {/* Theme Toggle */}
                <button
                  onClick={toggleTheme}
                  type="button"
                  title={theme === 'light' ? t('enable_dark_mode') : t('enable_light_mode')}
                  className="flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/20 p-2.5 border border-white/10 shadow-sm transition-all cursor-pointer select-none"
                >
                  {theme === 'light' ? (
                    <Moon className="w-4 h-4 text-white animate-scale-in" />
                  ) : (
                    <Sun className="w-4 h-4 text-amber-300 animate-scale-in" />
                  )}
                </button>
              </div>
            </div>
            
            <div className="border-t border-white/10 pt-4 text-start">
              <h2 className="text-xl sm:text-2xl font-bold mb-1">{t('patient_info')}</h2>
              <p className="text-teal-100 text-sm">{t('please_fill_info', 'يرجى تعبئة البيانات التالية قبل البدء بالاستبيان')}</p>
            </div>
          </div>

          <div className="p-6 sm:p-8 space-y-6">
            {/* Name */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <User className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('full_name')}
                {requireName ? (
                  <span className="text-red-500">*</span>
                ) : (
                  <span className="text-gray-400 text-xs font-normal">{t('optional')}</span>
                )}
              </label>
              <input
                type="text"
                value={patientInfo.name}
                onChange={e => handleNameChange(e.target.value)}
                placeholder={t('full_name_placeholder', 'أدخل اسمك الكامل')}
                className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-4 focus:ring-teal-100 outline-none transition-all text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 bg-white dark:bg-slate-850"
              />
            </div>

            {/* Phone */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <Phone className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('phone_number')}
                {requirePhone ? (
                  <span className="text-red-500">*</span>
                ) : (
                  <span className="text-gray-400 text-xs font-normal">{t('optional')}</span>
                )}
              </label>
              <div className="relative">
                <input
                  type="tel"
                  inputMode="numeric"
                  value={patientInfo.phone}
                  onChange={e => handlePhoneChange(e.target.value)}
                  placeholder="7XXXXXXXX"
                  maxLength={9}
                  className={`w-full px-4 py-3 rounded-xl border-2 outline-none transition-all text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 bg-white dark:bg-slate-850 text-left tracking-wider ${
                    phoneError
                      ? 'border-red-300 focus:border-red-500 focus:ring-4 focus:ring-red-100'
                      : patientInfo.phone.length === 9 && validatePhone(patientInfo.phone)
                        ? 'border-green-300 focus:border-green-500 focus:ring-4 focus:ring-green-100'
                        : 'border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-4 focus:ring-teal-100'
                  }`}
                  dir="ltr"
                />
                <div className="flex items-center justify-between mt-1.5">
                  <div>
                    {phoneError && (
                      <p className="flex items-center gap-1 text-xs text-red-500 animate-slide-up">
                        <AlertCircle className="w-3 h-3" />
                        {phoneError}
                      </p>
                    )}
                    {!phoneError && patientInfo.phone.length === 9 && (
                      <p className="text-xs text-green-500">✓ {t('phone_correct', 'رقم الهاتف صحيح')}</p>
                    )}
                  </div>
                  <span className={`text-xs font-medium ${
                    patientInfo.phone.length === 9 ? 'text-green-500' : 'text-gray-400 dark:text-slate-500'
                  }`}>
                    {patientInfo.phone.length}/9
                  </span>
                </div>
              </div>
            </div>

            {/* Gender */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <User className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('gender')}
                <span className="text-red-500">*</span>
              </label>
              <div className="grid grid-cols-1 min-[380px]:grid-cols-2 gap-3">
                {['male', 'female'].map(g => (
                  <button
                    key={g}
                    onClick={() => onUpdate('gender', g === 'male' ? 'ذكر' : 'أنثى')}
                    type="button"
                    className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all duration-200 cursor-pointer ${
                      patientInfo.gender === (g === 'male' ? 'ذكر' : 'أنثى')
                        ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 shadow-md shadow-teal-100 dark:shadow-none'
                        : 'border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:border-teal-300 hover:bg-teal-50/50'
                    }`}
                  >
                    {g === 'male' ? '👨 ' : '👩 '}{t(g)}
                  </button>
                ))}
              </div>
            </div>

            {/* Age Group */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <Calendar className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('age_group')}
                <span className="text-red-500">*</span>
              </label>
              <div className="grid grid-cols-1 min-[380px]:grid-cols-2 sm:grid-cols-3 gap-3">
                {ageGroups.map(age => (
                  <button
                    key={age}
                    onClick={() => onUpdate('ageGroup', age)}
                    type="button"
                    className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all duration-200 cursor-pointer ${
                      patientInfo.ageGroup === age
                        ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 shadow-md shadow-teal-100 dark:shadow-none'
                        : 'border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:border-teal-300 hover:bg-teal-50/50'
                    }`}
                  >
                    {age}
                  </button>
                ))}
              </div>
            </div>

            {/* Department */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <Building2 className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('department')}
                <span className="text-red-500">*</span>
              </label>
              <div className="grid grid-cols-1 min-[380px]:grid-cols-2 sm:grid-cols-3 gap-3">
                {departments.map(dept => (
                  <button
                    key={dept}
                    onClick={() => onUpdate('department', dept)}
                    type="button"
                    className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all duration-200 cursor-pointer ${
                      patientInfo.department === dept
                        ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 shadow-md shadow-teal-100 dark:shadow-none'
                        : 'border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:border-teal-300 hover:bg-teal-50/50'
                    }`}
                  >
                    {dept}
                  </button>
                ))}
              </div>
            </div>

            {/* Visit Type */}
            <div className="space-y-3 text-start">
              <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-slate-350">
                <Activity className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                {t('visit_type')}
                <span className="text-red-500">*</span>
              </label>
              <div className="grid grid-cols-1 min-[380px]:grid-cols-2 sm:grid-cols-3 gap-3">
                {visitTypes.map(vt => (
                  <button
                    key={vt}
                    onClick={() => onUpdate('visitType', vt)}
                    type="button"
                    className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all duration-200 cursor-pointer ${
                      patientInfo.visitType === vt
                        ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 shadow-md shadow-teal-100 dark:shadow-none'
                        : 'border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:border-teal-300 hover:bg-teal-50/50'
                    }`}
                  >
                    {vt}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="px-6 sm:px-8 pb-6 sm:pb-8 flex flex-col-reverse min-[380px]:flex-row items-stretch min-[380px]:items-center justify-between gap-3">
            <button
              onClick={onBack}
              type="button"
              className="flex items-center justify-center gap-2 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors px-4 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 cursor-pointer"
            >
              <ArrowRight className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
              {t('back')}
            </button>
            <button
              onClick={onNext}
              disabled={!isValid}
              type="button"
              className={`flex items-center justify-center gap-2 px-8 py-3 rounded-xl font-bold text-white transition-all duration-300 cursor-pointer ${
                isValid
                  ? 'bg-linear-to-l from-teal-600 to-emerald-600 shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5'
                  : 'bg-gray-300 dark:bg-slate-800 text-gray-500 dark:text-slate-500 cursor-not-allowed shadow-none'
              }`}
            >
              {t('next')}
              <ChevronLeft className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
