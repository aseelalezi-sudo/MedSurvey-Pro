import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useSettingsStore, HospitalInfo } from '../store/useSettingsStore';
import {
  Settings,
  Building2,
  Users,
  Calendar,
  ClipboardList,
  Palette,
  Save,
  X,
  Plus,
  Trash2,
  Check,
  Phone,
  Mail,
  Globe,
  MapPin,
  Clock,
  CheckCircle2,
  LucideIcon,
  Eye,
  EyeOff,
} from 'lucide-react';

type SettingsTab = 'hospital' | 'departments' | 'age-groups' | 'visit-types' | 'survey' | 'appearance';

export default function SettingsPage() {
  const {
    settings,
    updateHospital,
    addDepartment,
    updateDepartment,
    addAgeGroup,
    deleteAgeGroup,
    unhideAgeGroup,
    addVisitType,
    deleteVisitType,
    unhideVisitType,
    updateSurveySettings,
    updateAppearance,
  } = useSettingsStore();

  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<SettingsTab>('hospital');
  const [editingItem, setEditingItem] = useState<{ type: string; id?: string; value: string; color?: string } | null>(null);
  const [newItemValue, setNewItemValue] = useState('');
  const [toast, setToast] = useState<{ show: boolean; message: string; type: 'success' | 'error' }>({ show: false, message: '', type: 'success' });

  const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    setToast({ show: true, message, type });
    setTimeout(() => setToast({ show: false, message: '', type }), 3000);
  };

  const handleStoreAction = async (action: () => Promise<unknown>, successMsg?: string) => {
    try {
      await action();
      if (successMsg) showToast(successMsg, 'success');
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'حدث خطأ';
      showToast(message, 'error');
    }
  };

  // Hospital form state
  const [hospitalForm, setHospitalForm] = useState<HospitalInfo>(settings.hospital);
  const [thankYouMessage, setThankYouMessage] = useState(settings.surveySettings.thankYouMessage);

  // Sync form state when settings are loaded from API
  useEffect(() => {
    setHospitalForm(settings.hospital);
  }, [settings.hospital]);

  useEffect(() => {
    setThankYouMessage(settings.surveySettings.thankYouMessage);
  }, [settings.surveySettings.thankYouMessage]);

  const handleSaveHospital = () => {
    const requiredFields: { key: keyof HospitalInfo; label: string }[] = [
      { key: 'name', label: t('settings_hospital_name') },
      { key: 'shortName', label: t('settings_short_name') },
      { key: 'operatingTitle', label: t('settings_operating_title', 'نص وصف الجهة') },
      { key: 'address', label: t('settings_address') },
      { key: 'phone', label: t('settings_phone') },
      { key: 'email', label: t('settings_email') },
      { key: 'website', label: t('settings_website') },
      { key: 'workingHours', label: t('settings_working_hours') },
      { key: 'welcomeMessage', label: t('settings_welcome_message', 'الرسالة الترحيبية') },
    ];
    const missing = requiredFields.find(f => !hospitalForm[f.key] || String(hospitalForm[f.key]).trim() === '');
    if (missing) {
      showToast(`الحقل "${missing.label}" مطلوب`, 'error');
      return;
    }
    handleStoreAction(() => updateHospital(hospitalForm), t('settings_save_success'));
  };

  const tabs: { id: SettingsTab; label: string; icon: LucideIcon }[] = [
    { id: 'hospital', label: t('settings_tab_hospital'), icon: Building2 },
    { id: 'departments', label: t('settings_tab_departments'), icon: Users },
    { id: 'age-groups', label: t('settings_tab_age_groups'), icon: Calendar },
    { id: 'visit-types', label: t('settings_tab_visit_types'), icon: ClipboardList },
    { id: 'survey', label: t('settings_tab_survey'), icon: Settings },
    { id: 'appearance', label: t('settings_tab_appearance'), icon: Palette },
  ];

  const colorOptions = [
    '#0d9488', '#10b981', '#3b82f6', '#6366f1', '#8b5cf6',
    '#ec4899', '#ef4444', '#f97316', '#f59e0b', '#14b8a6',
    '#06b6d4', '#7c3aed', '#dc2626', '#059669', '#2563eb',
  ];

  const renderHospitalSettings = () => (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <Building2 className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_basic_info')}
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Hospital Logo Selection */}
          <div className="md:col-span-2 border-2 border-dashed border-gray-200 dark:border-slate-700 rounded-2xl p-6 bg-gray-50/50 dark:bg-slate-800/40 flex flex-col sm:flex-row items-center gap-6 mb-2">
            <div className="relative group w-24 h-24 bg-white dark:bg-slate-900 border border-gray-150 dark:border-slate-800 rounded-2xl flex items-center justify-center p-2 shadow-sm">
              {hospitalForm.logo ? (
                <img src={hospitalForm.logo} alt="Hospital Logo" className="max-w-full max-h-full object-contain rounded" />
              ) : (
                <Building2 className="w-10 h-10 text-gray-300 dark:text-slate-650" />
              )}
              {hospitalForm.logo && (
                <button
                  type="button"
                  onClick={() => setHospitalForm({ ...hospitalForm, logo: '' })}
                  className="absolute -top-2 -left-2 bg-red-100 dark:bg-red-950/50 hover:bg-red-200 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 p-1.5 rounded-lg shadow transition-colors cursor-pointer"
                  title={t('remove_logo', 'إزالة الشعار')}
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>
            <div className="flex-1 space-y-3 text-start w-full">
              <label className="block text-sm font-bold text-gray-700 dark:text-slate-300">{t('hospital_logo_upload', 'شعار المستشفى المشغل')}</label>
              <div className="flex flex-col sm:flex-row gap-3">
                <label className="cursor-pointer bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 border-2 border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-600 font-bold px-4 py-2.5 rounded-xl text-center text-sm transition-all flex items-center justify-center gap-2 shadow-sm shrink-0">
                  <Plus className="w-4 h-4 text-teal-600 dark:text-teal-400" />
                  {t('upload_logo_file', 'رفع ملف الشعار')}
                  <input
                    type="file"
                    accept="image/*"
                    onChange={e => {
                      const file = e.target.files?.[0];
                      if (file) {
                        const reader = new FileReader();
                        reader.onload = () => {
                          if (typeof reader.result === 'string') {
                            setHospitalForm({ ...hospitalForm, logo: reader.result });
                          }
                        };
                        reader.readAsDataURL(file);
                      }
                    }}
                    className="hidden"
                  />
                </label>
                <div className="flex-1">
                  <input
                    type="text"
                    value={hospitalForm.logo}
                    onChange={e => setHospitalForm({ ...hospitalForm, logo: e.target.value })}
                    placeholder={t('logo_url_placeholder', 'أو أدخل رابط الشعار الإلكتروني المباشر...')}
                    className="w-full px-4 py-2.5 text-sm rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 outline-none bg-white dark:bg-slate-950 placeholder-gray-400 dark:placeholder-slate-600 text-gray-900 dark:text-white text-start"
                  />
                </div>
              </div>
              <p className="text-[10px] text-gray-400 dark:text-slate-500">
                {t('logo_tip', 'يمكنك رفع صورة مباشرة (JPEG, PNG, SVG) أو كتابة رابط الشعار مباشرة. المقاس الموصى به: 250×80 بكسل.')}
              </p>
            </div>
          </div>

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_hospital_name')}<span className="text-red-500 mr-1">*</span></label>
            <input
              type="text"
              required
              value={hospitalForm.name}
              onChange={e => setHospitalForm({ ...hospitalForm, name: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
            />
          </div>
          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_short_name')}<span className="text-red-500 mr-1">*</span></label>
            <input
              type="text"
              required
              value={hospitalForm.shortName}
              onChange={e => setHospitalForm({ ...hospitalForm, shortName: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_operating_title', 'نص وصف الجهة')}<span className="text-red-500 mr-1">*</span></label>
            <input
              type="text"
              required
              value={hospitalForm.operatingTitle || ''}
              onChange={e => setHospitalForm({ ...hospitalForm, operatingTitle: e.target.value })}
              placeholder="المستشفى المشغل"
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none text-start bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_welcome_message', 'الرسالة الترحيبية')}<span className="text-red-500 mr-1">*</span></label>
            <textarea
              required
              value={hospitalForm.welcomeMessage}
              onChange={e => setHospitalForm({ ...hospitalForm, welcomeMessage: e.target.value })}
              rows={2}
              placeholder="أهلاً بك في مستشفى ...، نسعد بمشاركتك رأيك..."
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none text-start bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_hospital_description')}</label>
            <textarea
              value={hospitalForm.description}
              onChange={e => setHospitalForm({ ...hospitalForm, description: e.target.value })}
              rows={3}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none text-start bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
            />
          </div>
        </div>
      </div>

      <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <Phone className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_contact_info')}
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
              <MapPin className="w-4 h-4 text-gray-400 dark:text-slate-500" />
              {t('settings_address')}<span className="text-red-500 mr-1">*</span>
            </label>
            <input
              type="text"
              required
              value={hospitalForm.address}
              onChange={e => setHospitalForm({ ...hospitalForm, address: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
            />
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
              <Phone className="w-4 h-4 text-gray-400 dark:text-slate-500" />
              {t('settings_phone')}<span className="text-red-500 mr-1">*</span>
            </label>
            <input
              type="tel"
              required
              value={hospitalForm.phone}
              onChange={e => setHospitalForm({ ...hospitalForm, phone: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
              dir="ltr"
            />
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
              <Mail className="w-4 h-4 text-gray-400 dark:text-slate-500" />
              {t('settings_email')}<span className="text-red-500 mr-1">*</span>
            </label>
            <input
              type="email"
              required
              value={hospitalForm.email}
              onChange={e => setHospitalForm({ ...hospitalForm, email: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
              dir="ltr"
            />
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
              <Globe className="w-4 h-4 text-gray-400 dark:text-slate-500" />
              {t('settings_website')}<span className="text-red-500 mr-1">*</span>
            </label>
            <input
              type="url"
              required
              value={hospitalForm.website}
              onChange={e => setHospitalForm({ ...hospitalForm, website: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
              dir="ltr"
            />
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">
              <Clock className="w-4 h-4 text-gray-400 dark:text-slate-500" />
              {t('settings_working_hours')}<span className="text-red-500 mr-1">*</span>
            </label>
            <input
              type="text"
              required
              value={hospitalForm.workingHours}
              onChange={e => setHospitalForm({ ...hospitalForm, workingHours: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
            />
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <button
          onClick={handleSaveHospital}
          className="flex items-center gap-2 px-6 py-3 bg-linear-to-l from-teal-600 to-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-teal-200 dark:shadow-teal-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all cursor-pointer"
        >
          <Save className="w-5 h-5" />
          {t('settings_save_changes')}
        </button>
      </div>
    </div>
  );

  const renderDepartmentsSettings = () => (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <Users className="w-5 h-5 text-teal-600 dark:text-teal-400" />
            {t('settings_manage_departments')} ({settings.departments.length})
          </h3>
          <button
            onClick={() => {
              setEditingItem({ type: 'department', value: '' });
              setNewItemValue('');
            }}
            className="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            {t('settings_add_department')}
          </button>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {settings.departments.map(dept => (
            <div key={dept.id} className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
              <div className="w-4 h-4 rounded-full shrink-0" style={{ backgroundColor: dept.color }} />
              <span className={`flex-1 font-medium truncate ${dept.isActive ? 'text-gray-800 dark:text-slate-200' : 'text-gray-400 dark:text-slate-500 line-through'}`}>
                {dept.name}
              </span>
              <button
                onClick={() => handleStoreAction(() => updateDepartment(dept.id, { isActive: !dept.isActive }))}
                className={`w-8 h-8 rounded-lg flex items-center justify-center transition-colors cursor-pointer ${
                  dept.isActive ? 'bg-green-100 dark:bg-green-950/30 text-green-600 dark:text-green-400' : 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500'
                }`}
                title={dept.isActive ? t('settings_deactivate', 'تعطيل') : t('settings_activate', 'تفعيل')}
              >
                {dept.isActive ? <Check className="w-4 h-4" /> : <X className="w-4 h-4" />}
              </button>
            </div>
          ))}
        </div>
      </div>

      {/* Add Department Modal */}
      {editingItem?.type === 'department' && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 animate-scale-in text-start">
            <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4">إضافة قسم جديد</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">اسم القسم</label>
                <input
                  type="text"
                  value={newItemValue}
                  onChange={e => setNewItemValue(e.target.value)}
                  placeholder={t('settings_enter_department_name')}
                  className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
                  autoFocus
                />
              </div>
              <div>
                <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_department_color')}</label>
                <div className="flex flex-wrap gap-2">
                  {colorOptions.map(color => (
                    <button
                      key={color}
                      onClick={() => setEditingItem({ ...editingItem, color })}
                      className={`w-8 h-8 rounded-full transition-all cursor-pointer ${
                        editingItem.color === color ? 'ring-4 ring-offset-2 ring-teal-500 scale-110' : ''
                      }`}
                      style={{ backgroundColor: color }}
                    />
                  ))}
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3 mt-6">
              <button
                onClick={() => setEditingItem(null)}
                className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                {t('settings_cancel')}
              </button>
              <button
                onClick={() => {
                  if (newItemValue.trim()) {
                     handleStoreAction(() => addDepartment({ name: newItemValue, isActive: true, color: editingItem.color || '#0d9488' }));
                     setEditingItem(null);
                  }
                }}
                disabled={!newItemValue.trim()}
                className="flex-1 px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-colors disabled:bg-gray-350 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:disabled:text-slate-550 disabled:cursor-not-allowed cursor-pointer"
              >
                {t('settings_add')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderAgeGroupsSettings = () => (
    <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start animate-fade-in">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <Calendar className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_tab_age_groups')} ({settings.ageGroups.length})
        </h3>
        <button
          onClick={() => {
            setEditingItem({ type: 'age-group', value: '' });
            setNewItemValue('');
          }}
          className="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          {t('settings_add_age_group')}
        </button>
      </div>

      <div className="space-y-2">
        {settings.ageGroups.map(age => (
          <div key={age.id} className={`flex items-center gap-3 p-3 border rounded-xl transition-all ${
            age.isHidden 
              ? 'bg-amber-50/50 dark:bg-amber-950/10 border-amber-200 dark:border-amber-900/30 opacity-70' 
              : 'bg-gray-50 dark:bg-slate-800/50 border-transparent dark:border-slate-800'
          }`}>
            {age.isHidden ? (
              <EyeOff className="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" />
            ) : (
              <Calendar className="w-4 h-4 text-gray-400 dark:text-slate-500 shrink-0" />
            )}
            <span className={`flex-1 font-medium ${
              age.isHidden ? 'text-gray-400 dark:text-slate-500 line-through' : 'text-gray-800 dark:text-slate-200'
            }`}>{age.label}</span>
            {age.isHidden ? (
              <button
                onClick={() => handleStoreAction(() => unhideAgeGroup(age.id), t('settings_unhide_success', 'تم إعادة إظهار العنصر'))}
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-900/40 transition-colors cursor-pointer text-xs font-medium"
                title={t('settings_unhide', 'إعادة الإظهار')}
              >
                <Eye className="w-3.5 h-3.5" />
                {t('settings_unhide', 'إظهار')}
              </button>
            ) : (
              <button
                onClick={() => handleStoreAction(() => deleteAgeGroup(age.id))}
                className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-200 dark:hover:bg-red-900/40 transition-colors cursor-pointer"
                title={t('settings_delete', 'حذف')}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            )}
          </div>
        ))}
      </div>

      {/* Add Age Group Modal */}
      {editingItem?.type === 'age-group' && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 animate-scale-in text-start">
            <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4">{t('settings_add_age_group_title')}</h3>
            <div>
              <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_age_group_name')}</label>
              <input
                type="text"
                value={newItemValue}
                onChange={e => setNewItemValue(e.target.value)}
                placeholder={t('settings_age_group_placeholder')}
                className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
                autoFocus
              />
            </div>
            <div className="flex items-center gap-3 mt-6">
              <button
                onClick={() => setEditingItem(null)}
                className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                {t('settings_cancel')}
              </button>
              <button
                onClick={() => {
                  if (newItemValue.trim()) {
                    handleStoreAction(() => addAgeGroup(newItemValue));
                    setEditingItem(null);
                  }
                }}
                disabled={!newItemValue.trim()}
                className="flex-1 px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-colors disabled:bg-gray-350 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:disabled:text-slate-550 disabled:cursor-not-allowed cursor-pointer"
              >
                {t('settings_add')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderVisitTypesSettings = () => (
    <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start animate-fade-in">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <ClipboardList className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_tab_visit_types')} ({settings.visitTypes.length})
        </h3>
        <button
          onClick={() => {
            setEditingItem({ type: 'visit-type', value: '' });
            setNewItemValue('');
          }}
          className="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-medium hover:bg-teal-700 transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          {t('settings_add_visit_type')}
        </button>
      </div>

      <div className="space-y-2">
        {settings.visitTypes.map(vt => (
          <div key={vt.id} className={`flex items-center gap-3 p-3 border rounded-xl transition-all ${
            vt.isHidden 
              ? 'bg-amber-50/50 dark:bg-amber-950/10 border-amber-200 dark:border-amber-900/30 opacity-70' 
              : 'bg-gray-50 dark:bg-slate-800/50 border-transparent dark:border-slate-800'
          }`}>
            {vt.isHidden ? (
              <EyeOff className="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" />
            ) : (
              <ClipboardList className="w-4 h-4 text-gray-400 dark:text-slate-500 shrink-0" />
            )}
            <span className={`flex-1 font-medium ${
              vt.isHidden ? 'text-gray-400 dark:text-slate-500 line-through' : 'text-gray-800 dark:text-slate-200'
            }`}>{vt.label}</span>
            {vt.isHidden ? (
              <button
                onClick={() => handleStoreAction(() => unhideVisitType(vt.id), t('settings_unhide_success', 'تم إعادة إظهار العنصر'))}
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-900/40 transition-colors cursor-pointer text-xs font-medium"
                title={t('settings_unhide', 'إعادة الإظهار')}
              >
                <Eye className="w-3.5 h-3.5" />
                {t('settings_unhide', 'إظهار')}
              </button>
            ) : (
              <button
                onClick={() => handleStoreAction(() => deleteVisitType(vt.id))}
                className="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-200 dark:hover:bg-red-900/40 transition-colors cursor-pointer"
                title={t('settings_delete', 'حذف')}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            )}
          </div>
        ))}
      </div>

      {/* Add Visit Type Modal */}
      {editingItem?.type === 'visit-type' && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 animate-scale-in text-start">
            <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4">{t('settings_add_visit_type_title')}</h3>
            <div>
              <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_visit_type_name')}</label>
              <input
                type="text"
                value={newItemValue}
                onChange={e => setNewItemValue(e.target.value)}
                placeholder={t('settings_visit_type_placeholder')}
                className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-950 text-gray-900 dark:text-white text-start font-medium"
                autoFocus
              />
            </div>
            <div className="flex items-center gap-3 mt-6">
              <button
                onClick={() => setEditingItem(null)}
                className="flex-1 px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 font-medium hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                {t('settings_cancel')}
              </button>
              <button
                onClick={() => {
                  if (newItemValue.trim()) {
                    handleStoreAction(() => addVisitType(newItemValue));
                    setEditingItem(null);
                  }
                }}
                disabled={!newItemValue.trim()}
                className="flex-1 px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-colors disabled:bg-gray-350 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:disabled:text-slate-550 disabled:cursor-not-allowed cursor-pointer"
              >
                {t('settings_add')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderSurveySettings = () => (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start animate-fade-in">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <Settings className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_tab_survey')}
        </h3>
        <div className="space-y-4">
          <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <div>
              <p className="font-bold text-gray-700 dark:text-slate-200">{t('settings_allow_anonymous')}</p>
              <p className="text-sm text-gray-500 dark:text-slate-400">{t('settings_allow_anonymous_desc')}</p>
            </div>
            <button
              onClick={() => handleStoreAction(() => updateSurveySettings({ allowAnonymous: !settings.surveySettings.allowAnonymous }))}
              className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${
                settings.surveySettings.allowAnonymous ? 'bg-teal-500' : 'bg-gray-350 dark:bg-slate-700'
              }`}
            >
              <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${
                settings.surveySettings.allowAnonymous ? 'right-7' : 'right-0.5'
              }`} />
            </button>
          </div>

          <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <div>
              <p className="font-bold text-gray-700 dark:text-slate-200">{t('settings_require_all')}</p>
              <p className="text-sm text-gray-500 dark:text-slate-400">{t('settings_require_all_desc')}</p>
            </div>
            <button
              onClick={() => handleStoreAction(() => updateSurveySettings({ requireAllQuestions: !settings.surveySettings.requireAllQuestions }))}
              className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${
                settings.surveySettings.requireAllQuestions ? 'bg-teal-500' : 'bg-gray-350 dark:bg-slate-700'
              }`}
            >
              <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${
                settings.surveySettings.requireAllQuestions ? 'right-7' : 'right-0.5'
              }`} />
            </button>
          </div>

          <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <div>
              <p className="font-bold text-gray-700 dark:text-slate-200">{t('settings_show_progress')}</p>
              <p className="text-sm text-gray-500 dark:text-slate-400">{t('settings_show_progress_desc')}</p>
            </div>
            <button
              onClick={() => handleStoreAction(() => updateSurveySettings({ showProgressBar: !settings.surveySettings.showProgressBar }))}
              className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${
                settings.surveySettings.showProgressBar ? 'bg-teal-500' : 'bg-gray-350 dark:bg-slate-700'
              }`}
            >
              <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${
                settings.surveySettings.showProgressBar ? 'right-7' : 'right-0.5'
              }`} />
            </button>
          </div>

          <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <div>
              <p className="font-bold text-gray-700 dark:text-slate-200">{t('settings_enable_thank_you')}</p>
              <p className="text-sm text-gray-500 dark:text-slate-400">{t('settings_enable_thank_you_desc')}</p>
            </div>
            <button
              onClick={() => handleStoreAction(() => updateSurveySettings({ enableThankYouPage: !settings.surveySettings.enableThankYouPage }))}
              className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${
                settings.surveySettings.enableThankYouPage ? 'bg-teal-500' : 'bg-gray-350 dark:bg-slate-700'
              }`}
            >
              <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${
                settings.surveySettings.enableThankYouPage ? 'right-7' : 'right-0.5'
              }`} />
            </button>
          </div>

          <div className="p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <label className="block font-bold text-gray-700 dark:text-slate-200 mb-2">{t('settings_thank_you_message')}</label>
            <textarea
              value={thankYouMessage}
              onChange={e => setThankYouMessage(e.target.value)}
              onBlur={() => handleStoreAction(() => updateSurveySettings({ thankYouMessage }))}
              rows={3}
              className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none resize-none text-start bg-white dark:bg-slate-950 text-gray-900 dark:text-white font-medium"
              placeholder={t('settings_thank_you_message_placeholder')}
            />
          </div>
        </div>
      </div>
    </div>
  );

  const renderAppearanceSettings = () => (
    <div className="space-y-6">
      <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start animate-fade-in">
        <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <Palette className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          {t('settings_customize_appearance')}
        </h3>
        <div className="space-y-6">
          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-3">{t('settings_primary_color')}</label>
            <div className="flex flex-wrap gap-3">
              {colorOptions.map(color => (
                <button
                  key={color}
                  onClick={() => handleStoreAction(() => updateAppearance({ primaryColor: color }))}
                  className={`w-12 h-12 rounded-xl transition-all cursor-pointer ${
                    settings.appearance.primaryColor === color
                      ? 'ring-4 ring-offset-2 ring-teal-500 scale-110'
                      : 'hover:scale-105'
                  }`}
                  style={{ backgroundColor: color }}
                />
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-3">{t('settings_secondary_color')}</label>
            <div className="flex flex-wrap gap-3">
              {colorOptions.map(color => (
                <button
                  key={color}
                  onClick={() => handleStoreAction(() => updateAppearance({ secondaryColor: color }))}
                  className={`w-12 h-12 rounded-xl transition-all cursor-pointer ${
                    settings.appearance.secondaryColor === color
                      ? 'ring-4 ring-offset-2 ring-teal-500 scale-110'
                      : 'hover:scale-105'
                  }`}
                  style={{ backgroundColor: color }}
                />
              ))}
            </div>
          </div>

          <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 border border-transparent dark:border-slate-800 rounded-xl">
            <div>
              <p className="font-bold text-gray-700 dark:text-slate-200">{t('settings_show_language_toggle', 'تفعيل أيقونة تغيير اللغة')}</p>
              <p className="text-sm text-gray-500 dark:text-slate-400">{t('settings_show_language_toggle_desc', 'إظهار أو إخفاء زر تبديل اللغة (العربية / English) في جميع شاشات النظام')}</p>
            </div>
            <button
              onClick={() => handleStoreAction(() => updateAppearance({ showLanguageToggle: settings.appearance.showLanguageToggle !== false ? false : true }))}
              className={`w-14 h-7 rounded-full transition-all relative cursor-pointer ${
                settings.appearance.showLanguageToggle !== false ? 'bg-teal-500' : 'bg-gray-350 dark:bg-slate-700'
              }`}
            >
              <div className={`absolute top-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-all ${
                settings.appearance.showLanguageToggle !== false ? 'right-7' : 'right-0.5'
              }`} />
            </button>
          </div>

          <div>
            <label className="block text-sm font-bold text-gray-600 dark:text-slate-350 mb-2">{t('settings_color_preview')}</label>
            <div className="p-6 rounded-2xl bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800/85">
              <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                <div className="flex gap-2">
                  <div className="w-16 h-16 rounded-xl shadow-lg shrink-0" style={{ backgroundColor: settings.appearance.primaryColor }} />
                  <div className="w-16 h-16 rounded-xl shadow-lg shrink-0" style={{ backgroundColor: settings.appearance.secondaryColor }} />
                </div>
                <div className="flex-1 p-4 rounded-xl text-white text-start" style={{ backgroundColor: settings.appearance.primaryColor }}>
                  <p className="font-bold text-white">{t('settings_demo_text')}</p>
                  <p className="text-sm opacity-80">{t('settings_demo_desc')}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  const renderContent = () => {
    switch (activeTab) {
      case 'hospital': return renderHospitalSettings();
      case 'departments': return renderDepartmentsSettings();
      case 'age-groups': return renderAgeGroupsSettings();
      case 'visit-types': return renderVisitTypesSettings();
      case 'survey': return renderSurveySettings();
      case 'appearance': return renderAppearanceSettings();
      default: return null;
    }
  };

  return (
    <div className="animate-fade-in text-start">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-slate-800/80 pb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-linear-to-br from-teal-500 to-teal-600 dark:from-teal-600 dark:to-teal-800 rounded-xl flex items-center justify-center shadow-lg shadow-teal-100 dark:shadow-none">
              <Settings className="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">{t('settings_title')}</h2>
              <p className="text-[10px] text-gray-500 dark:text-slate-400 -mt-1">{t('settings_subtitle')}</p>
            </div>
          </div>
        </div>
        <div className="flex flex-col lg:flex-row gap-6">
          {/* Sidebar Tabs */}
          <div className="lg:w-64 shrink-0">
            <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden sticky top-24">
              {tabs.map(tab => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`w-full flex items-center gap-3 px-4 py-3 text-right transition-all cursor-pointer ${
                      activeTab === tab.id
                        ? 'bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-r-4 border-teal-500'
                        : 'text-gray-600 dark:text-slate-350 hover:bg-gray-50 dark:hover:bg-slate-800'
                    }`}
                  >
                    <Icon className={`w-5 h-5 ${activeTab === tab.id ? 'text-teal-600 dark:text-teal-400' : 'text-gray-400 dark:text-slate-500'}`} />
                    <span className="font-bold text-sm">{tab.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0">
            {renderContent()}
          </div>
        </div>
      </div>


      {/* Toast Notification */}
      {toast.show && (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 animate-slide-up">
          <div className={`flex items-center gap-3 px-6 py-3 rounded-xl shadow-xl ${
            toast.type === 'success' 
              ? 'bg-green-500 text-white shadow-green-200' 
              : 'bg-red-500 text-white shadow-red-200'
          }`}>
            <CheckCircle2 className="w-5 h-5" />
            <span className="font-bold text-sm">{toast.message}</span>
          </div>
        </div>
      )}
    </div>
  );
}
