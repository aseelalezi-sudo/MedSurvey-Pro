/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface TicketsInitial {
  q: string;
  status: string;
  priority: string;
  department: string;
  dateFilter: string;
  startDate: string;
  endDate: string;
  perPage: number;
  restrictedDept: boolean;
  isAr: boolean;
}

const byId = (id: string): any => document.getElementById(id);

document.addEventListener('alpine:init', () => {
  Alpine.data('ticketsComponent', (initial: TicketsInitial): any => ({
    q: initial.q,
    status: initial.status,
    priority: initial.priority,
    department: initial.department,
    dateFilter: initial.dateFilter,
    startDate: initial.startDate,
    endDate: initial.endDate,
    perPage: initial.perPage,
    restrictedDept: initial.restrictedDept,

    resolvingTicketId: null,
    resolutionNotes: '',
    deletingTicketId: null,

    loadingTickets: false,
    loadingResponse: false,
    selectedResponse: null,
    selectedTicketStatus: null,
    survey: null,
    isAr: initial.isAr,

    init() {
      this.bindTicketPaginationControls();
    },

    buildFilterParams(page = 1) {
      const params = new URLSearchParams();

      if (this.q) params.set('q', this.q);
      if (this.status) params.set('status', this.status);
      if (this.priority) params.set('priority', this.priority);
      if (this.department !== 'all') params.set('department', this.department);
      params.set('dateFilter', this.dateFilter);
      params.set('per_page', String(this.perPage));
      if (page > 1) params.set('page', String(page));

      if (this.dateFilter === 'custom') {
        if (this.startDate) params.set('startDate', this.startDate);
        if (this.endDate) params.set('endDate', this.endDate);
      }

      return params;
    },

    applyFilters() {
      const params = this.buildFilterParams();
      window.location.search = params.toString();
    },

    async searchTickets(page = 1) {
      const params = this.buildFilterParams(page);
      const qs = params.toString();
      (window as any).MedSurveyAjax.updateUrl(`/dashboard/tickets${qs ? `?${qs}` : ''}`);
      try {
        this.loadingTickets = true;
        const data = await (window as any).MedSurveyAjax.fetchJson(`/dashboard/tickets/filter?${qs}`);
        (window as any).MedSurveyAjax.replaceHtml('tickets-grid', data.html);
        (window as any).MedSurveyAjax.replaceHtml('tickets-pagination', data.pagination);
        (window as any).MedSurveyAjax.refreshIcons();
        this.bindTicketPaginationControls();
      } catch (e) {
        console.error('AJAX search failed, reloading page', e);
        window.location.search = qs;
      } finally {
        this.loadingTickets = false;
      }
    },

    bindTicketPaginationControls() {
      this.$nextTick(() => {
        const container = byId('tickets-pagination');
        if (!container) return;

        container.querySelectorAll('[data-ticket-page]').forEach((button: any) => {
          button.addEventListener('click', () => {
            if (button.disabled) return;
            this.searchTickets(Number(button.dataset.ticketPage || 1) || 1);
          });
        });

        const perPageSelect = container.querySelector('[data-ticket-per-page]');
        if (perPageSelect) {
          perPageSelect.addEventListener('change', () => {
            this.perPage = Number(perPageSelect.value || 20) || 20;
            this.searchTickets(1);
          });
        }

        const jumpInput = container.querySelector('[data-ticket-page-jump]');
        const jumpButton = container.querySelector('[data-ticket-jump]');
        const jump = () => {
          if (!jumpInput) return;
          const maxPage = Number(jumpInput.max || 1) || 1;
          const page = Math.min(Math.max(1, Number(jumpInput.value || 1) || 1), maxPage);
          jumpInput.value = '';
          this.searchTickets(page);
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

    setDateFilter(val: any) {
      this.dateFilter = val;
      if (val !== 'custom') {
        this.searchTickets();
      }
    },

    resetFilters() {
      this.q = '';
      this.status = '';
      this.priority = '';
      this.department = 'all';
      this.dateFilter = 'all';
      this.startDate = '';
      this.endDate = '';
      this.searchTickets();
    },

    isFiltered() {
      return (
        this.q !== '' ||
        this.status !== '' ||
        this.priority !== '' ||
        this.department !== 'all' ||
        this.dateFilter !== 'all' ||
        this.startDate !== '' ||
        this.endDate !== ''
      );
    },

    openResolutionNotes(ticketId: any, initialNotes: any) {
      this.resolvingTicketId = ticketId;
      this.resolutionNotes = initialNotes || '';
    },

    async viewSurveyDetails(responseId: any, ticketStatus = null) {
      try {
        this.loadingResponse = true;
        this.selectedTicketStatus = ticketStatus;
        const res = await fetch(`/dashboard/responses/${responseId}/json`);
        if (!res.ok) {
          throw new Error('Failed to fetch response details');
        }
        const data = await res.json();
        this.selectedResponse = data.response;
        this.survey = data.survey;

        // Re-trigger Lucide icons inside loaded content after Alpine tick
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
      if (score >= 85) return 'from-green-500 to-teal-600';
      if (score >= 70) return 'from-blue-500 to-indigo-600';
      if (score >= 50) return 'from-amber-500 to-orange-650';
      return 'from-red-500 to-rose-600';
    },

    getTicketStatusGradientClass(status: any) {
      if (status === 'open') return 'from-rose-500 to-red-600';
      if (status === 'in_progress') return 'from-amber-500 to-orange-500';
      if (status === 'resolved') return 'from-emerald-500 to-teal-600';
      return 'from-slate-500 to-slate-600';
    },

    formatDate(isoString: any) {
      if (!isoString) return '';
      const date = new Date(isoString);
      return date.toLocaleDateString(this.isAr ? 'ar-SA' : 'en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
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

      // Find question type
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

      const translatedList = values
        .map((v: any) => this.escapeHtml(this.translateValueLabel(v)))
        .join(this.isAr ? '، ' : ', ');
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
