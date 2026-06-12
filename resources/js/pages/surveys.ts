import Alpine from 'alpinejs';

interface Option {
  label: string;
  value?: string;
  [key: string]: unknown;
}

interface Question {
  id: string;
  type: string;
  title: string;
  description: string;
  required: boolean;
  options: Option[];
}

interface Section {
  id: string;
  title: string;
  description: string;
  icon: string;
  questions: Question[];
}

interface Survey {
  id: number | string | null;
  title: string;
  description: string;
  isActive: boolean;
  requireName: boolean;
  requirePhone: boolean;
  tips: string[];
  assignedDepartments: string[];
  sections: Section[];
}

interface CustomWindow {
  Alpine?: {
    initTree: (el: HTMLElement | null) => void;
  };
  lucide?: {
    createIcons: () => void;
  };
}

interface SurveyComponent {
  $nextTick: (cb: () => void) => void;
  showToastMsg: (msg: string, type?: string) => void;
  refreshSurveysContent: () => Promise<void>;
  syncSurveysFromDocument: (doc?: Document) => void;
}

document.addEventListener('alpine:init', () => {
  Alpine.data('surveyComponent', (props: { isAr: boolean }) => ({
    surveys: [] as Survey[],
    availableDepartments: [] as string[],
    showModal: false,
    isEditing: false,
    isSaving: false,
    isRefreshing: false,
    deleteModal: { show: false, id: null as string | number | null, title: '', action: '', responseCount: 0 },
    toast: { show: false, message: '', type: 'success' },
    expandedSections: {} as Record<number, boolean>,
    showPreview: false,
    dragSectionIndex: null as number | null,
    dragQuestionIndex: null as number | null,
    dragSourceSection: null as number | null,
    dragOverSectionIndex: null as number | null,
    dragOverQuestionIndex: null as number | null,
    dragOverTargetSection: null as number | null,
    sectionIcons: [
      { id: 'door-open', icon: 'DoorOpen' },
      { id: 'stethoscope', icon: 'Stethoscope' },
      { id: 'building', icon: 'Building2' },
      { id: 'pill', icon: 'Pill' },
      { id: 'clipboard-check', icon: 'ClipboardCheck' },
      { id: 'users', icon: 'Users' },
      { id: 'activity', icon: 'Activity' },
      { id: 'heart', icon: 'Heart' },
      { id: 'file-text', icon: 'FileText' },
    ],

    init() {
      this.syncSurveysFromDocument();
    },

    formatNumber(value: number) {
      return new Intl.NumberFormat('en-US').format(Number(value || 0));
    },

    compactNumber(value: number) {
      const number = Number(value || 0);
      const abs = Math.abs(number);

      if (abs >= 1000000) {
        return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
      }

      if (abs >= 1000) {
        return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
      }

      return this.formatNumber(number);
    },

    get questionTypes() {
      return [
        { id: 'stars', label: props.isAr ? 'نجوم' : 'Stars', icon: 'Star' },
        { id: 'emoji', label: props.isAr ? 'وجوه تعبيرية' : 'Emoji', icon: 'Smile' },
        { id: 'nps', label: props.isAr ? 'NPS' : 'NPS', icon: 'Hash' },
        { id: 'yes_no', label: props.isAr ? 'نعم/لا' : 'Yes/No', icon: 'ToggleLeft' },
        { id: 'multiple_choice', label: props.isAr ? 'خيارات' : 'Multiple Choice', icon: 'CheckSquare' },
        { id: 'text', label: props.isAr ? 'نص حر' : 'Text', icon: 'MessageSquare' },
      ];
    },

    form: {
      id: null as number | string | null,
      title: '',
      description: '',
      isActive: false,
      requireName: false,
      requirePhone: false,
      tips: [] as string[],
      assignedDepartments: [] as string[],
      sections: [] as Section[],
    },

    getIconHtml(iconName: string) {
      const icons: Record<string, string> = {
        DoorOpen:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>',
        Stethoscope:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.3.3 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>',
        Building2:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>',
        Pill: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
        ClipboardCheck:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
        Users:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        Activity:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        Heart:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
        FileText:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
      };
      return icons[iconName] || icons.ClipboardCheck;
    },

    getQuestionTypeIcon(typeId: string) {
      const icons: Record<string, string> = {
        stars:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        emoji:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>',
        nps: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/></svg>',
        yes_no:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><rect x="2" y="6" width="20" height="12" rx="6"/><circle cx="8" cy="12" r="2"/></svg>',
        multiple_choice:
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        text: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
      };
      return icons[typeId] || icons.stars;
    },

    getSectionIconHtml(iconId: string) {
      return this.getIconHtml((this.sectionIcons.find((i) => i.id === iconId) || this.sectionIcons[4]).icon);
    },

    showToastMsg(msg: string, type = 'success') {
      this.toast = { show: true, message: msg, type: type };
      setTimeout(() => {
        this.toast.show = false;
      }, 3000);
    },

    _refreshIcons() {
      (this as unknown as SurveyComponent).$nextTick(() => {
        const win = window as unknown as CustomWindow;
        if (win.lucide) win.lucide.createIcons();
      });
    },

    syncSurveysFromDocument(doc = document) {
      const source = doc.getElementById('surveys-json');
      if (source) {
        try {
          this.surveys = JSON.parse(source.textContent || '[]');
        } catch (error) {
          console.error(error);
        }
      }

      const depts = doc.getElementById('departments-json');
      if (depts) {
        try {
          this.availableDepartments = JSON.parse(depts.textContent || '[]');
        } catch (error) {
          console.error(error);
        }
      }
    },

    async refreshSurveysContent() {
      this.isRefreshing = true;

      try {
        const response = await fetch(window.location.href, {
          headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) throw new Error('Failed to refresh surveys');

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextContent = doc.getElementById('surveys-content');
        const currentContent = document.getElementById('surveys-content');
        const nextJson = doc.getElementById('surveys-json');
        const currentJson = document.getElementById('surveys-json');

        if (nextContent && currentContent) {
          currentContent.innerHTML = nextContent.innerHTML;
          const win = window as unknown as CustomWindow;
          if (win.Alpine) win.Alpine.initTree(currentContent);
        }

        if (nextJson && currentJson) {
          currentJson.textContent = nextJson.textContent;
        }

        const self = this as unknown as SurveyComponent;
        self.syncSurveysFromDocument();
        this._refreshIcons();
      } catch (error) {
        console.error(error);
        (this as unknown as SurveyComponent).showToastMsg(
          props.isAr ? 'تعذر تحديث قائمة الاستبيانات' : 'Could not refresh surveys list',
          'error',
        );
      } finally {
        this.isRefreshing = false;
      }
    },

    async submitSurveyAction(form: HTMLFormElement, successMessage: string, afterSuccess: (() => void) | null = null) {
      this.isRefreshing = true;

      try {
        const response = await fetch(form.action, {
          method: form.method || 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement).content,
          },
          body: new FormData(form),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.error || result.message || 'Action failed');
        }

        if (typeof afterSuccess === 'function') afterSuccess();
        const self = this as unknown as SurveyComponent;
        self.showToastMsg(successMessage);
        await self.refreshSurveysContent();
      } catch (error: unknown) {
        (this as unknown as SurveyComponent).showToastMsg(
          error instanceof Error ? error.message : 'Network Error',
          'error',
        );
      } finally {
        this.isRefreshing = false;
      }
    },

    openCreate() {
      this.isEditing = false;
      this.form = {
        id: null,
        title: '',
        description: '',
        isActive: false,
        requireName: false,
        requirePhone: false,
        tips: [],
        assignedDepartments: [],
        sections: [],
      };
      this.expandedSections = {};
      this.showModal = true;
      this._refreshIcons();
    },

    openEdit(id: string | number) {
      const survey = this.surveys.find((s: Survey) => String(s.id) === String(id));
      if (survey) {
        this.isEditing = true;
        this.form = JSON.parse(JSON.stringify(survey)); // Deep copy
        this.form.tips = this.form.tips || [];
        this.form.assignedDepartments = this.form.assignedDepartments || [];
        this.form.sections = this.form.sections || [];

        // Fix options stringification issue & ensure description field
        this.form.sections.forEach((s: Section) => {
          s.questions.forEach((q: Question) => {
            if (typeof q.options === 'string') {
              try {
                q.options = JSON.parse(q.options as unknown as string);
              } catch {
                q.options = [];
              }
            }
            if (!q.options) q.options = [];
            q.description = q.description || '';
          });
          // Ensure section has icon
          s.icon = s.icon || 'clipboard-check';
        });

        // Expand all sections when editing
        this.expandedSections = {};
        this.form.sections.forEach((_: unknown, i: number) => {
          this.expandedSections[i] = true;
        });

        this.showModal = true;
        this._refreshIcons();
      }
    },

    closeModal() {
      this.showModal = false;
    },

    openDelete(id: string | number, title: string, action: string, responseCount = 0) {
      this.deleteModal = {
        show: true,
        id: id,
        title: title,
        action: action,
        responseCount: Number(responseCount) || 0,
      };
      this._refreshIcons();
    },

    closeDelete() {
      this.deleteModal.show = false;
    },

    toggleDepartment(dept: string) {
      if (this.form.assignedDepartments.includes(dept)) {
        this.form.assignedDepartments = this.form.assignedDepartments.filter((d: string) => d !== dept);
      } else {
        this.form.assignedDepartments.push(dept);
      }
    },

    toggleSection(sIndex: number) {
      this.expandedSections[sIndex] = !this.expandedSections[sIndex];
      this._refreshIcons();
    },

    moveSection(sIndex: number, direction: number) {
      const newIndex = sIndex + direction;
      if (newIndex < 0 || newIndex >= this.form.sections.length) return;

      const temp = this.form.sections[sIndex];
      this.form.sections[sIndex] = this.form.sections[newIndex];
      this.form.sections[newIndex] = temp;

      const expandedTemp = this.expandedSections[sIndex];
      this.expandedSections[sIndex] = this.expandedSections[newIndex];
      this.expandedSections[newIndex] = expandedTemp;

      this._refreshIcons();
    },

    moveQuestion(sIndex: number, qIndex: number, direction: number) {
      const newIndex = qIndex + direction;
      if (newIndex < 0 || newIndex >= this.form.sections[sIndex].questions.length) return;

      const questions = this.form.sections[sIndex].questions;
      const temp = questions[qIndex];
      questions[qIndex] = questions[newIndex];
      questions[newIndex] = temp;

      this._refreshIcons();
    },

    handleSectionDragStart(sIndex: number, event: DragEvent) {
      this.dragSectionIndex = sIndex;
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', sIndex.toString());
      }
      if (event.target) (event.target as HTMLElement).classList.add('opacity-50');
    },
    handleSectionDragEnd(event: DragEvent) {
      this.dragSectionIndex = null;
      this.dragOverSectionIndex = null;
      if (event.target) (event.target as HTMLElement).classList.remove('opacity-50', 'border-teal-500');
    },
    handleSectionDragOver(sIndex: number, event: DragEvent) {
      event.preventDefault();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      this.dragOverSectionIndex = sIndex;
    },
    handleSectionDrop(dropIndex: number, event: DragEvent) {
      event.preventDefault();
      const dragIndex = this.dragSectionIndex;
      if (dragIndex === null || dragIndex === dropIndex) return;

      const section = this.form.sections.splice(dragIndex, 1)[0];
      this.form.sections.splice(dropIndex, 0, section);

      const newExpanded: Record<number, boolean> = {};
      this.form.sections.forEach((_: unknown, i: number) => {
        newExpanded[i] = false;
      });
      newExpanded[dropIndex] = true;
      this.expandedSections = newExpanded;

      this.dragSectionIndex = null;
      this.dragOverSectionIndex = null;
      this._refreshIcons();
    },

    handleQuestionDragStart(sIndex: number, qIndex: number, event: DragEvent) {
      this.dragQuestionIndex = qIndex;
      this.dragSourceSection = sIndex;
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', qIndex.toString());
      }
      if (event.target) (event.target as HTMLElement).closest('[data-question-card]')?.classList.add('opacity-50');
    },
    handleQuestionDragEnd(event: DragEvent) {
      this.dragQuestionIndex = null;
      this.dragSourceSection = null;
      this.dragOverQuestionIndex = null;
      this.dragOverTargetSection = null;
      if (event.target)
        (event.target as HTMLElement)
          .closest('[data-question-card]')
          ?.classList.remove('opacity-50', 'ring-2', 'ring-teal-500');
    },
    handleQuestionDragOver(sIndex: number, qIndex: number, event: DragEvent) {
      event.preventDefault();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      this.dragOverQuestionIndex = qIndex;
      this.dragOverTargetSection = sIndex;
    },
    handleQuestionDrop(sIndex: number, qIndex: number, event: DragEvent) {
      event.preventDefault();
      const fromSection = this.dragSourceSection;
      const fromQIndex = this.dragQuestionIndex;

      if (fromSection === null || fromQIndex === null) return;

      const questions = this.form.sections[fromSection].questions;

      if (fromSection === sIndex) {
        if (fromQIndex === qIndex) return;
        const q = questions.splice(fromQIndex, 1)[0];
        questions.splice(qIndex, 0, q);
      } else {
        const q = questions.splice(fromQIndex, 1)[0];
        this.form.sections[sIndex].questions.splice(qIndex, 0, q);
      }

      this.dragQuestionIndex = null;
      this.dragSourceSection = null;
      this.dragOverQuestionIndex = null;
      this.dragOverTargetSection = null;
      this._refreshIcons();
    },

    loadTemplate(templateName: string) {
      const templates: Record<string, { sections: Section[] }> = {
        reception: {
          sections: [
            {
              id: 'sec-' + Date.now(),
              title: props.isAr ? 'تقييم خدمة الاستقبال' : 'Reception Service',
              description: props.isAr ? 'تقييم تجربتك مع موظفي الاستقبال' : 'Rate your reception experience',
              icon: 'door-open',
              questions: [
                {
                  id: 'q-' + Date.now() + '-1',
                  type: 'stars',
                  title: props.isAr ? 'مدى ترحيب موظف الاستقبال' : 'Reception staff welcome',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-2',
                  type: 'emoji',
                  title: props.isAr ? 'سرعة إنهاء إجراءات الدخول' : 'Check-in speed',
                  description: '',
                  required: true,
                  options: [],
                },
              ],
            },
          ],
        },
        nursing: {
          sections: [
            {
              id: 'sec-' + Date.now(),
              title: props.isAr ? 'تقييم الخدمة التمريضية' : 'Nursing Care',
              description: props.isAr ? 'قيم مستوى الرعاية التمريضية' : 'Rate the nursing care level',
              icon: 'heart',
              questions: [
                {
                  id: 'q-' + Date.now() + '-1',
                  type: 'stars',
                  title: props.isAr ? 'تعامل الممرضين مع المرضى' : 'Nurses attitude towards patients',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-2',
                  type: 'nps',
                  title: props.isAr ? 'مدى رضاك عن الرعاية التمريضية' : 'Nursing care satisfaction',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-3',
                  type: 'yes_no',
                  title: props.isAr ? 'هل تم الاستجابة لطلبك بسرعة؟' : 'Was your request responded to quickly?',
                  description: '',
                  required: false,
                  options: [],
                },
              ],
            },
          ],
        },
        full: {
          sections: [
            {
              id: 'sec-' + Date.now() + '-1',
              title: props.isAr ? 'خدمة الاستقبال' : 'Reception',
              description: props.isAr ? 'قيم تجربتك مع خدمة الاستقبال' : 'Rate your reception experience',
              icon: 'door-open',
              questions: [
                {
                  id: 'q-' + Date.now() + '-1',
                  type: 'stars',
                  title: props.isAr ? 'مدى ترحيب وتودد موظفي الاستقبال' : 'Reception staff friendliness',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-2',
                  type: 'yes_no',
                  title: props.isAr ? 'هل كانت عملية التسجيل سريعة وسهلة؟' : 'Was registration quick and easy?',
                  description: '',
                  required: true,
                  options: [],
                },
              ],
            },
            {
              id: 'sec-' + Date.now() + '-2',
              title: props.isAr ? 'خدمة الطبيب' : 'Doctor Service',
              description: props.isAr ? 'قيم تجربتك مع الطبيب المعالج' : 'Rate your doctor experience',
              icon: 'stethoscope',
              questions: [
                {
                  id: 'q-' + Date.now() + '-3',
                  type: 'stars',
                  title: props.isAr ? 'مستوى الشرح والتوضيح من الطبيب' : 'Doctor explanation clarity',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-4',
                  type: 'emoji',
                  title: props.isAr ? 'شعورك بالراحة مع الطبيب' : 'Comfort level with doctor',
                  description: '',
                  required: true,
                  options: [],
                },
              ],
            },
            {
              id: 'sec-' + Date.now() + '-3',
              title: props.isAr ? 'الخدمة التمريضية' : 'Nursing',
              description: props.isAr ? 'قيم مستوى الرعاية التمريضية' : 'Rate nursing care',
              icon: 'heart',
              questions: [
                {
                  id: 'q-' + Date.now() + '-5',
                  type: 'nps',
                  title: props.isAr ? 'مدى رضاك عن الرعاية التي تلقيتها' : 'Satisfaction with care received',
                  description: '',
                  required: true,
                  options: [],
                },
              ],
            },
          ],
        },
        quick: {
          sections: [
            {
              id: 'sec-' + Date.now(),
              title: props.isAr ? 'تقييم سريع' : 'Quick Feedback',
              description: props.isAr ? 'سؤالين سريعين فقط' : 'Just 2 quick questions',
              icon: 'clipboard-check',
              questions: [
                {
                  id: 'q-' + Date.now() + '-1',
                  type: 'stars',
                  title: props.isAr ? 'التقييم العام للخدمة' : 'Overall service rating',
                  description: '',
                  required: true,
                  options: [],
                },
                {
                  id: 'q-' + Date.now() + '-2',
                  type: 'yes_no',
                  title: props.isAr ? 'هل تنصح بزيارة المستشفى للآخرين؟' : 'Would you recommend us?',
                  description: '',
                  required: true,
                  options: [],
                },
              ],
            },
          ],
        },
      };

      this.form.sections = templates[templateName]?.sections || [];
      this.expandedSections = {};
      if (this.form.sections.length > 0) {
        this.form.sections.forEach((_: unknown, i: number) => {
          this.expandedSections[i] = true;
        });
      }
      this._refreshIcons();
    },

    togglePreview() {
      this.showPreview = !this.showPreview;
      this._refreshIcons();
    },

    getPreviewStars(_question?: unknown) {
      let stars = '';
      for (let i = 1; i <= 5; i++) {
        stars += `<svg class="w-8 h-8 inline-block ${i <= 3 ? 'text-amber-400 fill-amber-400' : 'text-gray-300'}" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;
      }
      return stars;
    },

    addSection() {
      const newId = 'section-' + Date.now();
      this.form.sections.push({
        id: newId,
        title: '',
        description: '',
        icon: 'clipboard-check',
        questions: [],
      });
      this.expandedSections[this.form.sections.length - 1] = true;
      this._refreshIcons();
    },

    addQuestion(sectionIndex: number) {
      if (!this.form.sections[sectionIndex].questions) {
        this.form.sections[sectionIndex].questions = [];
      }
      this.form.sections[sectionIndex].questions.push({
        id: 'question-' + Date.now(),
        type: 'stars',
        title: '',
        description: '',
        required: false,
        options: [],
      });
      this._refreshIcons();
    },

    async saveSurvey() {
      this.isSaving = true;
      try {
        const url = this.isEditing ? `/dashboard/surveys/${this.form.id}` : '/dashboard/surveys';
        const method = this.isEditing ? 'PUT' : 'POST';

        this.form.sections.forEach((sec: Section) => {
          sec.questions.forEach((q: Question) => {
            if (q.type === 'multiple_choice' && q.options) {
              q.options.forEach((opt: Option) => {
                opt.value = opt.label;
              });
            } else {
              q.options = [];
            }
          });
        });

        const response = await fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement).content,
            Accept: 'application/json',
          },
          body: JSON.stringify(this.form),
        });

        const result = await response.json();

        if (response.ok && result.success) {
          const self = this as unknown as SurveyComponent;
          self.showToastMsg(props.isAr ? 'تم حفظ الاستبيان بنجاح' : 'Survey saved successfully');
          this.closeModal();
          this.isSaving = false;
          await self.refreshSurveysContent();
        } else {
          (this as unknown as SurveyComponent).showToastMsg(
            result.error || result.message || 'Error occurred',
            'error',
          );
          this.isSaving = false;
        }
      } catch {
        (this as unknown as SurveyComponent).showToastMsg('Network Error', 'error');
        this.isSaving = false;
      }
    },
  }));
});
