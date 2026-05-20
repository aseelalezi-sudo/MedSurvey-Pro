import { useCallback, useEffect } from 'react';
import { create } from 'zustand';
import { settingsAPI } from '../api/client';
import type { HospitalInfo } from '../types/settings';
export type { HospitalInfo } from '../types/settings';
export type { UsageCheckResult } from '../api/modules/settings';

export interface Department {
  id: string;
  name: string;
  isActive: boolean;
  color: string;
}

export interface AgeGroup {
  id: string;
  label: string;
  isActive: boolean;
}

export interface VisitType {
  id: string;
  label: string;
  isActive: boolean;
}

export interface SystemSettings {
  hospital: HospitalInfo;
  departments: Department[];
  ageGroups: AgeGroup[];
  visitTypes: VisitType[];
  surveySettings: {
    allowAnonymous: boolean;
    requireAllQuestions: boolean;
    requireName: boolean;
    requirePhone: boolean;
    showProgressBar: boolean;
    enableThankYouPage: boolean;
    thankYouMessage: string;
  };
  appearance: {
    primaryColor: string;
    secondaryColor: string;
    fontFamily: string;
    showLanguageToggle?: boolean;
  };
  activatedPredictivePlans: string[];
}

const defaultSettings: SystemSettings = {
  hospital: {
    name: '',
    shortName: '',
    logo: '',
    address: '',
    phone: '',
    email: '',
    website: '',
    description: '',
    workingHours: '',
    operatingTitle: '',
    welcomeMessage: '',
  },
  departments: [
    { id: 'dept-1', name: 'الطوارئ', isActive: true, color: '#EF4444' },
    { id: 'dept-2', name: 'العيادات الخارجية', isActive: true, color: '#3B82F6' },
    { id: 'dept-3', name: 'الباطنية', isActive: true, color: '#10B981' },
    { id: 'dept-4', name: 'الجراحة', isActive: true, color: '#8B5CF6' },
    { id: 'dept-5', name: 'الأطفال', isActive: true, color: '#F59E0B' },
    { id: 'dept-6', name: 'النساء والولادة', isActive: true, color: '#EC4899' },
    { id: 'dept-7', name: 'العظام', isActive: true, color: '#6366F1' },
    { id: 'dept-8', name: 'العيون', isActive: true, color: '#14B8A6' },
    { id: 'dept-9', name: 'الأنف والأذن والحنجرة', isActive: true, color: '#F97316' },
    { id: 'dept-10', name: 'الأسنان', isActive: true, color: '#06B6D4' },
    { id: 'dept-11', name: 'القلب', isActive: true, color: '#DC2626' },
    { id: 'dept-12', name: 'المختبر والأشعة', isActive: true, color: '#7C3AED' },
  ],
  ageGroups: [
    { id: 'age-1', label: 'أقل من 18 سنة', isActive: true },
    { id: 'age-2', label: '18 - 30 سنة', isActive: true },
    { id: 'age-3', label: '31 - 45 سنة', isActive: true },
    { id: 'age-4', label: '46 - 60 سنة', isActive: true },
    { id: 'age-5', label: 'أكثر من 60 سنة', isActive: true },
  ],
  visitTypes: [
    { id: 'vt-1', label: 'زيارة طارئة', isActive: true },
    { id: 'vt-2', label: 'موعد مسبق', isActive: true },
    { id: 'vt-3', label: 'تنويم', isActive: true },
    { id: 'vt-4', label: 'مراجعة', isActive: true },
    { id: 'vt-5', label: 'عملية جراحية', isActive: true },
  ],
  surveySettings: {
    allowAnonymous: true,
    requireAllQuestions: false,
    requireName: false,
    requirePhone: false,
    showProgressBar: true,
    enableThankYouPage: true,
    thankYouMessage: 'شكراً لمشاركتكم! رأيكم يساعدنا في تحسين خدماتنا.',
  },
  appearance: {
    primaryColor: '#0d9488',
    secondaryColor: '#10b981',
    fontFamily: 'Cairo',
    showLanguageToggle: true,
  },
  activatedPredictivePlans: [],
};

interface SettingsState {
  settings: SystemSettings;
  loaded: boolean;
  loading: boolean;

  setSettings: (settings: SystemSettings) => void;
  setLoaded: (loaded: boolean) => void;
  setLoading: (loading: boolean) => void;

  refreshFromAPI: () => Promise<SystemSettings>;
  saveToAPI: (newSettings: SystemSettings) => Promise<boolean>;
}

/**
 * Dual-layer Store Pattern (mirrors useAuthStore approach):
 * - useSettingsZustandStore: Raw Zustand store (pure state + actions)
 * - useSettingsStore:        React hook wrapper with auto-load, derived state, optimistic updates
 */
export const useSettingsZustandStore = create<SettingsState>((set, get) => ({
  settings: { ...defaultSettings },
  loaded: false,
  loading: false,

  setSettings: (settings) => set({ settings }),
  setLoaded: (loaded) => set({ loaded }),
  setLoading: (loading) => set({ loading }),

  refreshFromAPI: async () => {
    try {
      const data = await settingsAPI.get();
      // Deep merge with defaults so no keys are missing
      const merged = {
        ...defaultSettings,
        ...data,
        hospital: { ...defaultSettings.hospital, ...(data?.hospital || {}) },
        surveySettings: { ...defaultSettings.surveySettings, ...(data?.surveySettings || {}) },
        appearance: { ...defaultSettings.appearance, ...(data?.appearance || {}) },
        departments: (data?.departments || defaultSettings.departments).map(d => ({ ...d, isActive: d.isActive ?? true })),
        ageGroups: (data?.ageGroups || defaultSettings.ageGroups).map(a => ({ ...a, isActive: a.isActive ?? true })),
        visitTypes: (data?.visitTypes || defaultSettings.visitTypes).map(v => ({ ...v, isActive: v.isActive ?? true })),
        activatedPredictivePlans: data?.activatedPredictivePlans || [],
      };
      set({ settings: merged });
      return merged;
    } catch {
      return get().settings;
    }
  },

  saveToAPI: async (newSettings) => {
    const previousSettings = { ...get().settings };
    // Optimistic UI update
    set({ settings: newSettings });

    try {
      const saved = await settingsAPI.update(newSettings);
      const merged = {
        ...defaultSettings,
        ...saved,
        hospital: { ...defaultSettings.hospital, ...(saved?.hospital || {}) },
        surveySettings: { ...defaultSettings.surveySettings, ...(saved?.surveySettings || {}) },
        appearance: { ...defaultSettings.appearance, ...(saved?.appearance || {}) },
        departments: (saved?.departments || newSettings.departments).map(d => ({ ...d, isActive: d.isActive ?? true })),
        ageGroups: (saved?.ageGroups || newSettings.ageGroups).map(a => ({ ...a, isActive: a.isActive ?? true })),
        visitTypes: (saved?.visitTypes || newSettings.visitTypes).map(v => ({ ...v, isActive: v.isActive ?? true })),
        activatedPredictivePlans: saved?.activatedPredictivePlans || newSettings.activatedPredictivePlans || [],
      };
      set({ settings: merged });
      return true;
    } catch (error: unknown) {
      // Rollback to previous settings on error
      set({ settings: previousSettings });
      const message = error instanceof Error ? error.message : 'فشل في حفظ الإعدادات';
      throw new Error(message);
    }
  },
}));

// Component-Facing Hook Wrapper (Maintains 100% Backward Compatibility)
export function useSettingsStore() {
  const store = useSettingsZustandStore();

  useEffect(() => {
    if (!store.loaded && !store.loading) {
      store.setLoaded(true);
      store.setLoading(true);
      store.refreshFromAPI().finally(() => {
        store.setLoading(false);
      });
    }
  }, [store]);

  const updateHospital = useCallback(async (hospital: Partial<HospitalInfo>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      hospital: { ...store.settings.hospital, ...hospital },
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const addDepartment = useCallback(async (dept: Omit<Department, 'id'>) => {
    const newDept: Department = {
      ...dept,
      id: `dept-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`,
    };
    const newSettings: SystemSettings = {
      ...store.settings,
      departments: [...store.settings.departments, newDept],
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const updateDepartment = useCallback(async (id: string, updates: Partial<Department>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      departments: store.settings.departments.map(d => d.id === id ? { ...d, ...updates } : d),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const deleteDepartment = useCallback(async (id: string) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      departments: store.settings.departments.filter(d => d.id !== id),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const addAgeGroup = useCallback(async (label: string) => {
    const newAgeGroup: AgeGroup = { id: `age-${Date.now()}`, label, isActive: true };
    const newSettings: SystemSettings = {
      ...store.settings,
      ageGroups: [...store.settings.ageGroups, newAgeGroup],
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const updateAgeGroup = useCallback(async (id: string, updates: Partial<AgeGroup>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      ageGroups: store.settings.ageGroups.map(a => a.id === id ? { ...a, ...updates } : a),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const deleteAgeGroup = useCallback(async (id: string) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      ageGroups: store.settings.ageGroups.filter(a => a.id !== id),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const addVisitType = useCallback(async (label: string) => {
    const newVisitType: VisitType = { id: `vt-${Date.now()}`, label, isActive: true };
    const newSettings: SystemSettings = {
      ...store.settings,
      visitTypes: [...store.settings.visitTypes, newVisitType],
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const updateVisitType = useCallback(async (id: string, updates: Partial<VisitType>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      visitTypes: store.settings.visitTypes.map(v => v.id === id ? { ...v, ...updates } : v),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const deleteVisitType = useCallback(async (id: string) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      visitTypes: store.settings.visitTypes.filter(v => v.id !== id),
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const updateSurveySettings = useCallback(async (updates: Partial<SystemSettings['surveySettings']>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      surveySettings: { ...store.settings.surveySettings, ...updates },
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const updateAppearance = useCallback(async (updates: Partial<SystemSettings['appearance']>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      appearance: { ...store.settings.appearance, ...updates },
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  const resetToDefaults = useCallback(async () => {
    return store.saveToAPI(defaultSettings);
  }, [store]);

  const saveSettings = useCallback(async (newSettings: SystemSettings) => {
    return store.saveToAPI(newSettings);
  }, [store]);

  const togglePredictivePlan = useCallback(async (dept: string) => {
    const current = store.settings.activatedPredictivePlans || [];
    const isActivated = current.includes(dept);
    const updated = isActivated
      ? current.filter(d => d !== dept)
      : [...current, dept];
    
    const newSettings: SystemSettings = {
      ...store.settings,
      activatedPredictivePlans: updated,
    };
    return store.saveToAPI(newSettings);
  }, [store]);

  return {
    settings: store.settings,
    saveSettings,
    togglePredictivePlan,
    updateHospital,
    addDepartment,
    updateDepartment,
    deleteDepartment,
    addAgeGroup,
    updateAgeGroup,
    deleteAgeGroup,
    addVisitType,
    updateVisitType,
    deleteVisitType,
    updateSurveySettings,
    updateAppearance,
    resetToDefaults,
  };
}
