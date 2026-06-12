/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface SettingsOptions {
  texts: any;
  hospital: any;
  surveySettings: any;
  appearance: any;
  backupSettings: any;
  archiveSettings: any;
  departments: any[];
  ageGroups: any[];
  visitTypes: any[];
  usageCheckUrl: string;
}

document.addEventListener('alpine:init', () => {
  Alpine.data('settingsManager', (options: SettingsOptions): any => ({
    activeTab: 'hospital',
    texts: options.texts,
    hospitalForm: {} as any,
    surveySettings: {} as any,
    appearance: {} as any,
    backupSettings: {} as any,
    archiveSettings: {} as any,
    departments: [] as any[],
    ageGroups: [] as any[],
    visitTypes: [] as any[],
    toast: { show: false, message: '', type: 'success' },
    isSaving: false,
    editingItem: null as any,
    newItemValue: '',
    deleteConfirm: null as any,
    colorOptions: [
      '#0d9488',
      '#10b981',
      '#3b82f6',
      '#6366f1',
      '#8b5cf6',
      '#ec4899',
      '#ef4444',
      '#f97316',
      '#f59e0b',
      '#14b8a6',
      '#06b6d4',
      '#7c3aed',
      '#dc2626',
      '#059669',
      '#2563eb',
    ],

    init() {
      this.hospitalForm = options.hospital || {};
      this.surveySettings = options.surveySettings || {};
      this.appearance = options.appearance || {};
      this.appearance.showLanguageToggle = this.isLanguageToggleEnabled();
      this.backupSettings = options.backupSettings || {};
      this.archiveSettings = options.archiveSettings || {};
      this.archiveSettings.enabled = this.isArchiveEnabled();
      this.archiveSettings.schedule = this.archiveSettings.schedule || '02:30';
      this.archiveSettings.retentionYears = Number(this.archiveSettings.retentionYears || 3);
      this.departments = this.normalizeList(options.departments || []);
      this.ageGroups = this.normalizeList(options.ageGroups || []);
      this.visitTypes = this.normalizeList(options.visitTypes || []);
    },

    normalizeList(items: any[]) {
      return (items || []).map((item) => ({
        ...item,
        isActive: item.isActive === true || item.isActive === 1 || item.isActive === '1',
      }));
    },

    isLanguageToggleEnabled() {
      return (
        this.appearance.showLanguageToggle === undefined ||
        this.appearance.showLanguageToggle === true ||
        this.appearance.showLanguageToggle === 1 ||
        this.appearance.showLanguageToggle === '1'
      );
    },

    isArchiveEnabled() {
      return (
        this.archiveSettings.enabled === undefined ||
        this.archiveSettings.enabled === true ||
        this.archiveSettings.enabled === 1 ||
        this.archiveSettings.enabled === '1'
      );
    },

    showToast(message: string, type = 'success') {
      this.toast = { show: true, message, type };
      setTimeout(() => {
        this.toast.show = false;
      }, 3000);
    },

    async submitSettings(form: HTMLFormElement) {
      this.isSaving = true;

      try {
        const csrfTokenEl = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
        const response = await fetch(form.action, {
          method: form.method || 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfTokenEl ? csrfTokenEl.content : '',
          },
          body: new FormData(form),
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.success === false) {
          const errors = result.errors ? Object.values(result.errors).flat() : [];
          throw new Error((errors[0] as string) || result.message || this.texts.saveFailed);
        }

        this.showToast(this.texts.saved);
      } catch (error: any) {
        this.showToast(error.message || this.texts.saveFailed, 'error');
      } finally {
        this.isSaving = false;
        this.$nextTick(() => {
          if ((window as any).lucide) (window as any).lucide.createIcons();
        });
      }
    },

    handleLogoFile(event: Event) {
      const target = event.target as HTMLInputElement;
      const file = target.files?.[0];
      if (!file) return;
      const maxSize = 500 * 1024;
      if (file.size > maxSize) {
        this.showToast(this.texts.logoTooLarge, 'error');
        target.value = '';
        return;
      }
      if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        this.showToast(this.texts.logoUnsupported, 'error');
        target.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        if (typeof e.target?.result === 'string') {
          this.hospitalForm.logo = e.target.result;
          (this.$refs as any).logoBase64.value = e.target.result;
        }
      };
      reader.readAsDataURL(file);
    },

    openAddModal(type: string) {
      this.editingItem = { type, id: null, value: '', color: '#0d9488' };
      this.newItemValue = '';
    },

    openEditModal(type: string, index: number) {
      const list = type === 'department' ? this.departments : type === 'ageGroup' ? this.ageGroups : this.visitTypes;
      const item = list[index];
      this.editingItem = { type, id: item.id, value: item.label || item.name, color: item.color || '#0d9488' };
      this.newItemValue = item.label || item.name;
    },

    getEditTitle() {
      return this.texts.editTitles[this.editingItem.type] || this.texts.editFallback;
    },

    getAddTitle() {
      return this.texts.addTitles[this.editingItem.type] || this.texts.addFallback;
    },

    getLabelText() {
      return this.texts.labels[this.editingItem.type] || this.texts.nameFallback;
    },

    saveEditItem() {
      if (!this.newItemValue.trim()) return;
      const { type, id, color } = this.editingItem;
      let listKey: 'departments' | 'ageGroups' | 'visitTypes', itemKey: string;
      if (type === 'department') {
        listKey = 'departments';
        itemKey = 'name';
      } else if (type === 'ageGroup') {
        listKey = 'ageGroups';
        itemKey = 'label';
      } else {
        listKey = 'visitTypes';
        itemKey = 'label';
      }

      if (id) {
        this[listKey] = this[listKey].map((item: any) =>
          item.id === id ? { ...item, [itemKey]: this.newItemValue, ...(color ? { color } : {}) } : item,
        );
      } else {
        const prefix = type === 'department' ? 'dept' : type === 'ageGroup' ? 'age' : 'vt';
        const newItem: any = { id: prefix + '-' + Date.now(), [itemKey]: this.newItemValue, isActive: true };
        if (type === 'department') newItem.color = color || '#0d9488';
        this[listKey] = [...this[listKey], newItem];
      }
      this.editingItem = null;
      this.$nextTick(() => {
        if ((window as any).lucide) (window as any).lucide.createIcons();
      });
    },

    toggleItemActive(listKey: 'departments' | 'ageGroups' | 'visitTypes', index: number) {
      this[listKey] = this[listKey].map((item: any, itemIndex: number) =>
        itemIndex === index ? { ...item, isActive: !item.isActive } : item,
      );
      this.$nextTick(() => {
        if ((window as any).lucide) (window as any).lucide.createIcons();
      });
    },

    confirmDeleteItem(type: string, id: string, name: string) {
      const csrfTokenEl = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      fetch(options.usageCheckUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfTokenEl ? csrfTokenEl.content : '',
          Accept: 'application/json',
        },
        body: JSON.stringify({ type, value: name }),
      })
        .then((res) => res.json())
        .then((data) => {
          this.deleteConfirm = { type, id, name, count: data.count || 0 };
        })
        .catch(() => {
          this.deleteConfirm = { type, id, name, count: 0 };
        });
    },

    executeDelete() {
      if (!this.deleteConfirm) return;
      const { type, id } = this.deleteConfirm;
      const listKey = type === 'department' ? 'departments' : type === 'ageGroup' ? 'ageGroups' : 'visitTypes';
      this[listKey] = this[listKey].filter((item: any) => item.id !== id);
      this.deleteConfirm = null;
      this.$nextTick(() => {
        if ((window as any).lucide) (window as any).lucide.createIcons();
      });
    },
  }));
});
