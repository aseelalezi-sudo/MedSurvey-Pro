import { useCallback, useEffect } from 'react';
import { create } from 'zustand';
import { settingsAPI } from '../api/client';
import type { HospitalInfo, Department, AgeGroup, VisitType, SystemSettings } from '../types/settings';
export type { HospitalInfo, Department, AgeGroup, VisitType, SystemSettings } from '../types/settings';
export type { UsageCheckResult } from '../api/modules/settings';

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
    { id: 'dept-1', name: 'Emergency', isActive: true, color: '#EF4444' },
    { id: 'dept-2', name: 'Outpatient Clinics', isActive: true, color: '#3B82F6' },
    { id: 'dept-3', name: 'Internal Medicine', isActive: true, color: '#10B981' },
    { id: 'dept-4', name: 'Surgery', isActive: true, color: '#8B5CF6' },
    { id: 'dept-5', name: 'Pediatrics', isActive: true, color: '#F59E0B' },
    { id: 'dept-6', name: 'Obstetrics & Gynecology', isActive: true, color: '#EC4899' },
    { id: 'dept-7', name: 'Orthopedics', isActive: true, color: '#6366F1' },
    { id: 'dept-8', name: 'Ophthalmology', isActive: true, color: '#14B8A6' },
    { id: 'dept-9', name: 'ENT', isActive: true, color: '#F97316' },
    { id: 'dept-10', name: 'Dentistry', isActive: true, color: '#06B6D4' },
    { id: 'dept-11', name: 'Cardiology', isActive: true, color: '#DC2626' },
    { id: 'dept-12', name: 'Laboratory & Radiology', isActive: true, color: '#7C3AED' },
  ],
  ageGroups: [
    { id: 'age-1', label: 'Under 18 years', isActive: true },
    { id: 'age-2', label: '18 - 30 years', isActive: true },
    { id: 'age-3', label: '31 - 45 years', isActive: true },
    { id: 'age-4', label: '46 - 60 years', isActive: true },
    { id: 'age-5', label: 'Over 60 years', isActive: true },
  ],
  visitTypes: [
    { id: 'vt-1', label: 'Emergency Visit', isActive: true },
    { id: 'vt-2', label: 'Scheduled Appointment', isActive: true },
    { id: 'vt-3', label: 'Inpatient Admission', isActive: true },
    { id: 'vt-4', label: 'Follow-up', isActive: true },
    { id: 'vt-5', label: 'Surgical Operation', isActive: true },
  ],
  surveySettings: {
    allowAnonymous: true,
    requireAllQuestions: false,
    requireName: false,
    requirePhone: false,
    showProgressBar: true,
    enableThankYouPage: true,
    thankYouMessage: 'Thank you for your participation! Your opinion helps us improve our services.',
  },
  appearance: {
    primaryColor: '#0d9488',
    secondaryColor: '#10b981',
    fontFamily: 'Cairo',
    showLanguageToggle: true,
  },
  activatedPredictivePlans: [],
  backupSettings: {
    schedule: '03:00',
    retentionDays: 30,
    compressGzip: true,
    backupDir: 'storage/app/backups',
  },
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
    let data: Partial<SystemSettings>;
    try {
      // Try authenticated endpoint first (returns full settings including backupSettings)
      data = await settingsAPI.get();
    } catch {
      try {
        // Fall back to public endpoint (unauthenticated — returns only public-safe fields)
        data = await settingsAPI.getPublic() as Partial<SystemSettings>;
      } catch {
        return get().settings;
      }
    }

    if (!data) return get().settings;

    // Deep merge with defaults so no keys are missing
    const merged = {
      ...defaultSettings,
      ...data,
      hospital: { ...defaultSettings.hospital, ...(data?.hospital || {}) },
      surveySettings: { ...defaultSettings.surveySettings, ...(data?.surveySettings || {}) },
      appearance: { ...defaultSettings.appearance, ...(data?.appearance || {}) },
      backupSettings: { ...defaultSettings.backupSettings, ...(data?.backupSettings || {}) },
      departments: (data?.departments || defaultSettings.departments).map(d => ({ ...d, isActive: d.isActive ?? true })),
      ageGroups: (data?.ageGroups || defaultSettings.ageGroups).map(a => ({ ...a, isActive: a.isActive ?? true })),
      visitTypes: (data?.visitTypes || defaultSettings.visitTypes).map(v => ({ ...v, isActive: v.isActive ?? true })),
      activatedPredictivePlans: data?.activatedPredictivePlans || [],
    };
    set({ settings: merged });
    return merged;
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
        backupSettings: { ...defaultSettings.backupSettings, ...(saved?.backupSettings || {}) },
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
      const message = error instanceof Error ? error.message : 'Failed to save settings';
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
    if (store.settings.departments.some(d => d.name === dept.name)) {
      throw new Error(`Department "${dept.name}" already exists.`);
    }
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
    if (updates.name && store.settings.departments.some(d => d.id !== id && d.name === updates.name)) {
      throw new Error(`Department "${updates.name}" already exists.`);
    }
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

  const updateBackupSettings = useCallback(async (updates: Partial<SystemSettings['backupSettings']>) => {
    const newSettings: SystemSettings = {
      ...store.settings,
      backupSettings: { ...store.settings.backupSettings, ...updates } as SystemSettings['backupSettings'],
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
    updateBackupSettings,
    resetToDefaults,
  };
}


