/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface ResponsesInitial {
  scoreFilter: string;
  dateFilter: string;
  startDate: string;
  endDate: string;
  genderFilter: string;
  hasName: boolean;
  hasPhone: boolean;
  perPage: number;
  isAr: boolean;
  forcedDateFilter: string | null;
  totalResponses: number;
  averageScore: number;
}

const byId = (id: string): any => document.getElementById(id);
const query = (selector: string): any => document.querySelector(selector);

document.addEventListener('alpine:init', () => {
  Alpine.data('responsesComponent', (initial: ResponsesInitial): any => ({
    showFilters: false,
    exportModalOpen: false,
    exportFormat: 'pdf',
    exportDateFilter: 'all',
    exportDepartment: 'all',
    scoreFilter: initial.scoreFilter,
    dateFilter: initial.dateFilter,
    startDate: initial.startDate,
    endDate: initial.endDate,
    genderFilter: initial.genderFilter,
    hasName: initial.hasName,
    hasPhone: initial.hasPhone,
    perPage: initial.perPage,
    loadingResponse: false,
    loadingFilters: false,
    selectedResponse: null,
    survey: null,
    isAr: initial.isAr,
    forcedDateFilter: initial.forcedDateFilter,
    totalResponses: initial.totalResponses,
    averageScore: initial.averageScore,
    estimatedRecords: initial.totalResponses,

    init() {
      this.bindResponsePaginationControls();
    },

    toggleFilters() {
      if (this.showFilters) {
        this.showFilters = false;
        this.clearAdvancedFilters();
        this.filterResponses();
        return;
      }

      this.showFilters = true;
    },

    clearAdvancedFilters() {
      this.scoreFilter = 'all';
      this.dateFilter = 'all';
      this.startDate = '';
      this.endDate = '';
      this.genderFilter = 'all';
      this.hasName = false;
      this.hasPhone = false;
    },

    fetchEstimatedCount() {
      // Build params from ALPINE STATE (all advanced filters)
      const params = new URLSearchParams();

      // Pass all current filters from Alpine state (the page filters)
      if (this.scoreFilter !== 'all') params.set('score', this.scoreFilter);
      if (this.genderFilter !== 'all') params.set('gender', this.genderFilter);
      if (this.hasName) params.set('hasName', '1');
      if (this.hasPhone) params.set('hasPhone', '1');
      if (this.startDate) params.set('startDate', this.startDate);
      if (this.endDate) params.set('endDate', this.endDate);

      // Date filter logic: if user changed period in export modal, use that; otherwise use page filter
      if (this.exportDateFilter !== 'all') {
        // User explicitly selected a period in the export modal - use it
        params.set('dateFilter', this.exportDateFilter);
      } else if (this.dateFilter !== 'all') {
        // User didn't change period in modal - respect the page's active filter
        params.set('dateFilter', this.dateFilter);
      }

      // Department: always from export modal
      if (this.exportDepartment !== 'all') {
        params.set('department', this.exportDepartment);
      }

      // Add search query from the form input
      const qInput = query('#filtersForm input[name="q"]');
      if (qInput && qInput.value.trim()) {
        params.set('q', qInput.value.trim());
      }

      params.set('count_only', '1');

      const form = byId('filtersForm');
      fetch(form.action + '?' + params.toString(), {
        headers: { Accept: 'application/json' },
      })
        .then((res) => (res.ok ? res.json() : Promise.reject(res)))
        .then((data) => {
          this.estimatedRecords = data.count;
        })
        .catch((e) => console.error('Count fetch error:', e));
    },

    submitForm() {
      this.filterResponses();
    },

    buildPageFilterParams() {
      const form = byId('filtersForm');
      const params = new URLSearchParams();

      const qInput = form.querySelector('input[name="q"]');
      const sortSelect = form.querySelector('select[name="sortBy"]');
      const q = qInput ? qInput.value.trim() : '';
      const sortBy = sortSelect ? sortSelect.value : '';
      const activeDateFilter = this.forcedDateFilter || this.dateFilter;

      if (q) params.set('q', q);
      if (sortBy && sortBy !== 'submittedAt-desc') params.set('sortBy', sortBy);
      if (this.scoreFilter !== 'all') params.set('score', this.scoreFilter);
      if (this.genderFilter !== 'all') params.set('gender', this.genderFilter);
      if (this.hasName) params.set('hasName', '1');
      if (this.hasPhone) params.set('hasPhone', '1');

      if (activeDateFilter !== 'all') {
        params.set('dateFilter', activeDateFilter);
      }

      if (activeDateFilter === 'custom') {
        if (this.startDate) params.set('startDate', this.startDate);
        if (this.endDate) params.set('endDate', this.endDate);
      }

      if (this.perPage && this.perPage !== 20) params.set('per_page', this.perPage);

      return params;
    },

    async filterResponses(page = 1) {
      const form = byId('filtersForm');
      const params = this.buildPageFilterParams();
      if (page > 1) params.set('page', page);
      const qs = params.toString();
      const action = form.action;

      (window as any).MedSurveyAjax.updateUrl(`${action}${qs ? `?${qs}` : ''}`);

      try {
        this.loadingFilters = true;
        const data = await (window as any).MedSurveyAjax.fetchJson(`/dashboard/responses/filter?${qs}`);

        (window as any).MedSurveyAjax.replaceHtml('responses-grid', data.html);
        (window as any).MedSurveyAjax.replaceHtml('responses-pagination', data.pagination);
        (window as any).MedSurveyAjax.refreshIcons();
        this.totalResponses = Number(data.total || 0);
        this.averageScore = Number(data.averageScore || 0);

        this.bindResponsePaginationControls();
      } catch (e) {
        console.error('AJAX response filter failed, falling back to full reload', e);
        window.location.search = qs;
      } finally {
        this.loadingFilters = false;
      }
    },

    bindResponsePaginationControls() {
      this.$nextTick(() => {
        const container = byId('responses-pagination');
        if (!container) return;

        container.querySelectorAll('[data-response-page]').forEach((button: any) => {
          button.addEventListener('click', () => {
            if (button.disabled) return;
            this.filterResponses(Number(button.dataset.responsePage || 1) || 1);
          });
        });

        const perPageSelect = container.querySelector('[data-response-per-page]');
        if (perPageSelect) {
          perPageSelect.addEventListener('change', () => {
            this.perPage = Number(perPageSelect.value || 20) || 20;
            this.filterResponses(1);
          });
        }

        const jumpInput = container.querySelector('[data-response-page-jump]');
        const jumpButton = container.querySelector('[data-response-jump]');
        const jump = () => {
          if (!jumpInput) return;
          const maxPage = Number(jumpInput.max || 1) || 1;
          const page = Math.min(Math.max(1, Number(jumpInput.value || 1) || 1), maxPage);
          jumpInput.value = '';
          this.filterResponses(page);
        };

        if (jumpInput) {
          jumpInput.addEventListener('keydown', (event: any) => {
            if (event.key === 'Enter') jump();
          });
        }

        if (jumpButton) {
          jumpButton.addEventListener('click', jump);
        }
      });
    },

    formatNumber(value: any, fractionDigits = 0) {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
      }).format(Number(value || 0));
    },

    compactNumber(value: any) {
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

    triggerExport() {
      // Build params from ALPINE STATE (all advanced filters)
      const params = new URLSearchParams();

      // Pass all current filters from Alpine state (the page filters)
      if (this.scoreFilter !== 'all') params.set('score', this.scoreFilter);
      if (this.genderFilter !== 'all') params.set('gender', this.genderFilter);
      if (this.hasName) params.set('hasName', '1');
      if (this.hasPhone) params.set('hasPhone', '1');
      if (this.startDate) params.set('startDate', this.startDate);
      if (this.endDate) params.set('endDate', this.endDate);

      // Date filter logic: if user changed period in export modal, use that; otherwise use page filter
      if (this.exportDateFilter !== 'all') {
        // User explicitly selected a period in the export modal - use it
        params.set('dateFilter', this.exportDateFilter);
      } else if (this.dateFilter !== 'all') {
        // User didn't change period in modal - respect the page's active filter
        params.set('dateFilter', this.dateFilter);
      }

      // Department: always from export modal
      if (this.exportDepartment !== 'all') {
        params.set('department', this.exportDepartment);
      }

      // Add search query from the form input
      const qInput = query('#filtersForm input[name="q"]');
      if (qInput && qInput.value.trim()) {
        params.set('q', qInput.value.trim());
      }

      const form = byId('filtersForm');
      if (this.exportFormat === 'excel') {
        params.set('export', 'csv');
        window.location.href = form.action + '?' + params.toString();
      } else {
        // PDF or Print opens the print view
        params.set('export', 'print');
        window.open(form.action + '?' + params.toString(), '_blank');
      }
      this.exportModalOpen = false;
    },

    async viewSurveyDetails(responseId: any) {
      try {
        this.loadingResponse = true;
        const res = await fetch(`/dashboard/responses/${responseId}/json`);
        if (!res.ok) {
          throw new Error('Failed to fetch response details');
        }
        const data = await res.json();
        this.selectedResponse = data.response;
        this.survey = data.survey;

        setTimeout(() => {
          if (window.lucide) {
            window.lucide.createIcons();
          }
        }, 50);
      } catch (err) {
        console.error('AJAX survey loading error:', err);
        alert(
          this.isAr ? 'فشل في تحميل تفاصيل استبيان المريض المعتمد.' : 'Failed to load patient survey response details.',
        );
      } finally {
        this.loadingResponse = false;
      }
    },

    getScoreGradientClass(score: any) {
      score = score || 0;
      if (score >= 85) return 'bg-green-500';
      if (score >= 70) return 'bg-blue-500';
      if (score >= 50) return 'bg-amber-500';
      return 'bg-red-500';
    },

    formatDate(isoString: any) {
      if (!isoString) return '';
      const date = new Date(isoString);
      const yyyy = date.getFullYear();
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      const dd = String(date.getDate()).padStart(2, '0');
      const hh = String(date.getHours()).padStart(2, '0');
      const min = String(date.getMinutes()).padStart(2, '0');
      return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
    },

    formatGender(gender: any) {
      if (!gender) return this.isAr ? 'النوع غير محدد' : 'Gender unspecified';
      const val = String(gender).trim().toLowerCase();
      if (val === 'male') return this.isAr ? 'ذكر' : 'Male';
      if (val === 'female') return this.isAr ? 'أنثى' : 'Female';
      return gender;
    },

    getQuestionTitle(key: any) {
      if (!this.survey) return key;
      const questions = this.survey.sections ? this.survey.sections.flatMap((s: any) => s.questions || []) : [];
      const directMatch = questions.find((q: any) => String(q.id) === String(key));
      if (directMatch) return directMatch.title;

      if (/^q\d+$/i.test(key)) {
        const index = parseInt(key.substring(1), 10) - 1;
        if (index >= 0 && index < questions.length) {
          return questions[index].title;
        }
      }

      return key;
    },

    renderAnswerValue(key: any, val: any) {
      if (val === null || val === undefined || val === '') return '';

      const questions =
        this.survey && this.survey.sections ? this.survey.sections.flatMap((s: any) => s.questions || []) : [];
      const question =
        questions.find((q: any) => String(q.id) === String(key)) ||
        (/^q\d+$/i.test(key) ? questions[parseInt(key.substring(1), 10) - 1] : null);
      const type = question ? question.type : '';

      // 1. Free-text questions must always be displayed as text, never as a rating
      if (type === 'text' || type === 'textarea') {
        return `<span class="bg-slate-50 dark:bg-slate-900 text-slate-750 dark:text-slate-300 px-3 py-1 rounded-full border border-slate-150 dark:border-slate-800 text-[11px] font-black break-words">${this.escapeHtml(val)}</span>`;
      }

      // 2. Yes / No questions or boolean values
      const strVal = typeof val === 'string' ? val.trim().toLowerCase() : String(val);
      if (
        type === 'yes_no' ||
        typeof val === 'boolean' ||
        strVal === 'yes' ||
        strVal === 'no' ||
        strVal === 'true' ||
        strVal === 'false'
      ) {
        if (
          val === true ||
          strVal === 'yes' ||
          strVal === 'true' ||
          (type === 'yes_no' && (val === 1 || strVal === '1'))
        ) {
          return `
          <span class="bg-green-50 dark:bg-green-950/20 text-green-700 dark:text-green-400 px-3 py-1 rounded-full border border-green-100 dark:border-green-900/35 text-[11px] font-black">
            ${this.isAr ? 'نعم' : 'Yes'}
          </span>
        `;
        }
        if (
          val === false ||
          strVal === 'no' ||
          strVal === 'false' ||
          (type === 'yes_no' && (val === 0 || strVal === '0'))
        ) {
          return `
          <span class="bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 px-3 py-1 rounded-full border border-rose-100 dark:border-rose-900/35 text-[11px] font-black">
            ${this.isAr ? 'لا' : 'No'}
          </span>
        `;
        }
      }

      // 3. Rating types (stars, rating, emoji, nps)
      const isRatingType = ['stars', 'rating', 'emoji', 'nps'].includes(type);
      const numericValue =
        typeof val === 'number'
          ? val
          : typeof val === 'string' && val.trim() !== '' && !Number.isNaN(Number(val))
            ? Number(val)
            : null;

      if (numericValue !== null && (isRatingType || (!type && numericValue >= 0 && numericValue <= 10))) {
        const isNps = type === 'nps';
        const scale = isNps ? 10 : 5;
        const starsHtml = !isNps ? '<span class="text-sm leading-none">&#9733;</span>' : '';
        return `
        <span class="${this.ratingBadgeClass(numericValue, scale)}">
          ${starsHtml}
          <span>${numericValue} / ${scale}</span>
        </span>
      `;
      }

      // 4. Multiple Choice / Array / Text fallback
      let values: any[];
      if (Array.isArray(val)) {
        values = val;
      } else if (typeof val === 'string' && val.startsWith('[') && val.endsWith(']')) {
        try {
          values = JSON.parse(val);
        } catch {
          values = [val];
        }
      } else if (typeof val === 'string' && val.includes(',')) {
        values = val.split(',').map((v) => v.trim());
      } else {
        values = [val];
      }

      const translatedList = values.map((v: any) => this.escapeHtml(this.translateValueLabel(v))).join('، ');
      return `<span class="bg-slate-50 dark:bg-slate-900 text-slate-750 dark:text-slate-300 px-3 py-1 rounded-full border border-slate-150 dark:border-slate-800 text-[11px] font-black">${translatedList}</span>`;
    },

    escapeHtml(value: any) {
      const div = document.createElement('div');
      div.textContent = value === null || value === undefined ? '' : String(value);
      return div.innerHTML;
    },

    ratingBadgeClass(value: any, scale: any) {
      const percentage = (Number(value) / Math.max(Number(scale), 1)) * 100;
      const base =
        'inline-flex h-8 min-w-[4.75rem] items-center justify-center gap-1.5 rounded-full border px-3 text-[11px] font-black leading-none shadow-sm';

      if (percentage >= 85) {
        return `${base} border-emerald-500/25 bg-emerald-500/10 text-emerald-500 shadow-emerald-950/10`;
      }

      if (percentage >= 70) {
        return `${base} border-blue-500/25 bg-blue-500/10 text-blue-500 shadow-blue-950/10`;
      }

      if (percentage >= 50) {
        return `${base} border-amber-500/25 bg-amber-500/10 text-amber-500 shadow-amber-950/10`;
      }

      return `${base} border-rose-500/25 bg-rose-500/10 text-rose-500 shadow-rose-950/10`;
    },

    translateValueLabel(val: any) {
      if (!val) return '';
      const normalized = String(val).trim().toLowerCase();
      const arTranslations: Record<string, string> = {
        inpatient: 'تنويم',
        outpatient: 'عيادات خارجية',
        emergency: 'طوارئ',
        male: 'ذكر',
        female: 'أنثى',
        yes: 'نعم',
        no: 'لا',
        staff: 'الكادر الطبي',
        waiting: 'الانتظار / وقت الانتظار',
        cleanliness: 'النظافة',
        treatment: 'المعاملة والتشخيص',
        speed: 'سرعة الخدمة',
        facility: 'المرافق والبيئة',
        parking: 'مواقف السيارات',
        signs: 'اللوحات الإرشادية',
        reception: 'الاستقبال',
        pharmacy: 'الصيدلية',
        appointments: 'المواعيد',
      };
      const enTranslations: Record<string, string> = {
        inpatient: 'Inpatient',
        outpatient: 'Outpatient',
        emergency: 'Emergency',
        male: 'Male',
        female: 'Female',
        yes: 'Yes',
        no: 'No',
        staff: 'Medical Staff',
        waiting: 'Waiting Time',
        cleanliness: 'Cleanliness',
        treatment: 'Treatment & Diagnostic',
        speed: 'Service Speed',
        facility: 'Facilities & Environment',
        parking: 'Parking',
        signs: 'Signage',
        reception: 'Reception',
        pharmacy: 'Pharmacy',
        appointments: 'Appointments',
      };

      if (this.isAr) {
        return arTranslations[normalized] || val;
      } else {
        return enTranslations[normalized] || val;
      }
    },
  }));
});
