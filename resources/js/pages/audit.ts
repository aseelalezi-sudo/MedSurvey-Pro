/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface AuditTexts {
  totalOperations: string;
  failedLogins: string;
  operations: string;
  legendAlign: 'left' | 'right';
  loadError: string;
  noLogs: string;
  noLogsHint: string;
  unknownUser: string;
  unknownDevice: string;
  unknownIp: string;
  changesCount: string;
  viewChangeDetails: string;
  showingPage: string;
  of: string;
  total: string;
  logs: string;
  noChangeDetails: string;
  changedFields: string;
  field: string;
  before: string;
  after: string;
}

interface AuditOptions {
  showFilters: boolean;
  route: string;
  isRtl: boolean;
  texts: AuditTexts;
  trendData: any[];
  actionStats: any[];
  actionLabels: Record<string, string>;
  roleLabels: Record<string, string>;
  messageLabels: Record<string, string>;
  roleBadgeColors: Record<string, string>;
  actionBadges: Record<string, { bg: string; text: string }>;
  settingLabels: Record<string, string>;
  pagination: { page: number; totalPages: number; total: number };
}

const globalWindow = window as any;
let cfg: AuditOptions;
let currentAuditPage = 1;

const byId = (id: string) => document.getElementById(id);

const formatAuditNumber = (value: unknown) => new Intl.NumberFormat('en-US').format(Number(value || 0));

const compactAuditNumber = (value: unknown) => {
  const number = Number(value || 0);
  const abs = Math.abs(number);

  if (abs >= 1000000) {
    return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
  }

  if (abs >= 1000) {
    return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
  }

  return formatAuditNumber(number);
};

function escapeAuditHtml(text: unknown): string {
  if (!text) return '';
  const map: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return String(text).replace(/[&<>"']/g, (m) => map[m]);
}

function getAuditFilters() {
  const value = (id: string) => (byId(id) as HTMLInputElement | null)?.value || '';
  return {
    search: value('audit-search-input'),
    action: value('audit-action-filter'),
    start_date: value('audit-start-date'),
    end_date: value('audit-end-date'),
  };
}

function setDateLabels(start: string, end: string) {
  const startLabel = byId('audit-start-date-label');
  const endLabel = byId('audit-end-date-label');
  if (startLabel) startLabel.textContent = start || 'YYYY-MM-DD';
  if (endLabel) endLabel.textContent = end || 'YYYY-MM-DD';
}

function handleAuditSearch() {
  currentAuditPage = 1;
  fetchAuditLogs();
}

function handleAuditFilterChange() {
  const filters = getAuditFilters();
  setDateLabels(filters.start_date, filters.end_date);
  currentAuditPage = 1;
  fetchAuditLogs();
}

function handleAuditPageChange(page: number | string) {
  const target = Number(page);
  if (!target || target < 1) return;
  currentAuditPage = target;
  fetchAuditLogs();
}

function resetAuditFilters() {
  ['audit-search-input', 'audit-action-filter', 'audit-start-date', 'audit-end-date'].forEach((id) => {
    const el = byId(id) as HTMLInputElement | null;
    if (el) el.value = '';
  });
  setDateLabels('', '');
  currentAuditPage = 1;
  fetchAuditLogs();
}

function refreshAuditLogs() {
  currentAuditPage = 1;
  fetchAuditLogs();
}

function fetchAuditLogs() {
  const tbody = byId('audit-logs-tbody');
  if (!tbody) return;

  tbody.innerHTML = `
    <tr>
      <td colspan="5" class="text-center py-12">
        <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mx-auto"></div>
      </td>
    </tr>
  `;

  const filters = getAuditFilters();
  const params = new URLSearchParams({
    ajax: 'true',
    page: String(currentAuditPage),
    ...(filters.search ? { search: filters.search } : {}),
    ...(filters.action ? { action: filters.action } : {}),
    ...(filters.start_date ? { start_date: filters.start_date } : {}),
    ...(filters.end_date ? { end_date: filters.end_date } : {}),
  });

  fetch(`${cfg.route}?${params.toString()}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
  })
    .then((res) => {
      if (!res.ok) throw new Error('Connection failed');
      return res.json();
    })
    .then((data) => {
      renderAuditTableRows(data.logs);
      renderAuditPagination(data.pagination);
    })
    .catch((err) => {
      console.error('Failed to load audit logs:', err);
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center py-12 text-rose-500 font-bold">
            ${escapeAuditHtml(cfg.texts.loadError)}
          </td>
        </tr>
      `;
    });
}

function buildDetails(log: any): { detailsText: string; auditChanges: any[] } {
  const t = cfg.texts;
  let detailsText = log.details || '—';
  let auditChanges: any[] = [];

  try {
    const decoded = JSON.parse(log.details);
    if (decoded && decoded.messageKey) {
      auditChanges = Array.isArray(decoded.params?.changes)
        ? decoded.params.changes
        : Array.isArray(decoded.params?.settingsChanges)
          ? decoded.params.settingsChanges
          : [];

      let template = cfg.messageLabels[decoded.messageKey] || decoded.messageKey;

      if (template !== decoded.messageKey && decoded.params) {
        for (const [k, v] of Object.entries(decoded.params)) {
          const regexBraces = new RegExp('\\{\\{\\s*' + k + '\\s*\\}\\}', 'g');
          const regexColon = new RegExp(':' + k + '\\b', 'g');
          template = template.replace(regexBraces, String(v)).replace(regexColon, String(v));
        }
        detailsText = template;
      } else if (template === decoded.messageKey && decoded.params) {
        const paramStr = Object.entries(decoded.params)
          .filter(
            ([key, value]) =>
              !['changes', 'settingsChanges', 'method', 'path', 'status'].includes(key) &&
              !Array.isArray(value) &&
              value !== null &&
              typeof value !== 'object',
          )
          .map(([, value]) => value)
          .join(', ');
        detailsText = paramStr ? decoded.messageKey + ': ' + paramStr : decoded.messageKey;
      } else {
        detailsText = decoded.messageKey;
      }

      if (auditChanges.length > 0) {
        detailsText += ` (${t.changesCount}: ${compactAuditNumber(auditChanges.length)})`;
      }
    }
  } catch {
    // Use raw details string
  }

  return { detailsText, auditChanges };
}

function renderAuditTableRows(logs: any[]) {
  const tbody = byId('audit-logs-tbody');
  if (!tbody) return;
  const t = cfg.texts;

  if (logs.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="py-20 text-center">
          <div class="max-w-md mx-auto flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-gray-50 dark:bg-slate-800/80 border border-gray-100 dark:border-slate-850 rounded-full flex items-center justify-center text-gray-300 dark:text-slate-650 mb-4 shadow-inner">
              <i data-lucide="shield" class="w-8 h-8"></i>
            </div>
            <h3 class="text-base font-bold text-gray-800 dark:text-white mb-1">${escapeAuditHtml(t.noLogs)}</h3>
            <p class="text-xs text-gray-400 dark:text-slate-450">${escapeAuditHtml(t.noLogsHint)}</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = logs
    .map((log) => {
      const badge = cfg.actionBadges[log.action] || {
        bg: 'border-gray-200 dark:border-slate-700',
        text: 'text-gray-700 dark:text-slate-300',
      };
      const user = log.user || null;
      const userName = user ? user.name || user.username || t.unknownUser : t.unknownUser;
      const username = user ? user.username : 'system';
      const initial = user ? (user.name || user.username || 'S').charAt(0) : 'S';
      const role = user && user.role ? user.role : null;
      const roleBadgeColor = role ? cfg.roleBadgeColors[role] || '' : '';
      const roleLabel = role ? cfg.roleLabels[role] || role : '';

      const actionLabel = cfg.actionLabels[log.action] || log.action;
      const deviceName = log.deviceName || t.unknownDevice;
      const ipAddress = log.ipAddress || t.unknownIp;

      let timestamp = '—';
      if (log.timestamp) {
        const date = new Date(log.timestamp);
        timestamp =
          date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' }) +
          ', ' +
          date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
      }

      const { detailsText, auditChanges } = buildDetails(log);

      const settingsDetailsButton =
        auditChanges.length > 0
          ? `<button type="button" data-changes="${encodeURIComponent(JSON.stringify(auditChanges))}" onclick="openAuditSettingsDetailsFromButton(this)" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-teal-100 bg-teal-50 px-2.5 py-1 text-[10px] font-black text-teal-700 transition hover:border-teal-200 hover:bg-teal-100 dark:border-teal-900/40 dark:bg-teal-950/20 dark:text-teal-300 dark:hover:bg-teal-950/35"><i data-lucide="list-tree" class="h-3.5 w-3.5"></i><span>${escapeAuditHtml(t.viewChangeDetails)}</span><span class="stat-badge rounded-full bg-white px-1.5 py-0.5 text-[9px] text-teal-700 dark:bg-slate-900 dark:text-teal-300" title="${formatAuditNumber(auditChanges.length)}">${compactAuditNumber(auditChanges.length)}</span></button>`
          : '';

      return `
        <tr class="border-b border-gray-50 dark:border-slate-800/80 hover:bg-gray-50/50 dark:hover:bg-slate-850/40 transition-colors">
          <td class="py-3.5 px-5 text-sm whitespace-nowrap">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-linear-to-r from-teal-500 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">
                ${user ? escapeAuditHtml(initial) : '<i data-lucide="user" class="w-4 h-4"></i>'}
              </div>
              <div class="flex flex-col text-start">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-gray-900 dark:text-white">${escapeAuditHtml(userName)}</span>
                  ${role && roleBadgeColor ? `<span class="text-[9px] font-extrabold px-2 py-0.5 rounded border ${roleBadgeColor}">${escapeAuditHtml(roleLabel)}</span>` : ''}
                </div>
                <span class="text-[10px] text-gray-400 dark:text-slate-500 mt-0.5 font-bold" dir="ltr">@${escapeAuditHtml(username)}</span>
              </div>
            </div>
          </td>
          <td class="py-3.5 px-5 text-sm text-center whitespace-nowrap">
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border whitespace-nowrap ${badge.bg} ${badge.text}">
              ${escapeAuditHtml(actionLabel)}
            </span>
          </td>
          <td class="py-3.5 px-5 text-sm max-w-2xl text-start">
            <div class="max-w-[200px] sm:max-w-[300px] md:max-w-md lg:max-w-xl" style="overflow-wrap: anywhere; word-break: break-word; white-space: normal;">
              <p class="text-gray-700 dark:text-slate-300 leading-relaxed font-medium text-xs text-start">
                ${escapeAuditHtml(detailsText)}
              </p>
            </div>
            ${settingsDetailsButton}
          </td>
          <td class="py-3.5 px-5 text-xs text-gray-500 dark:text-slate-400 min-w-40 text-start whitespace-nowrap">
            <div class="flex items-start gap-2">
              <i data-lucide="monitor-smartphone" class="w-4 h-4 text-teal-600 dark:text-teal-400 mt-0.5 shrink-0"></i>
              <div class="space-y-1 text-start">
                <div class="font-bold text-gray-700 dark:text-slate-300">${escapeAuditHtml(deviceName)}</div>
                <div class="font-mono text-[10px]" dir="ltr">${escapeAuditHtml(ipAddress)}</div>
              </div>
            </div>
          </td>
          <td class="py-3.5 px-5 text-xs text-gray-400 dark:text-slate-500 font-bold whitespace-nowrap text-left" dir="ltr">
            ${escapeAuditHtml(timestamp)}
          </td>
        </tr>
      `;
    })
    .join('');

  globalWindow.lucide?.createIcons?.();
}

function renderAuditPagination(pagination: { page: number; totalPages: number; total: number }) {
  const bar = byId('audit-pagination-bar');
  if (!bar) return;
  const t = cfg.texts;

  const infoEl = byId('audit-pagination-info');
  if (infoEl) {
    const strong = (n: number) =>
      `<span class="text-gray-700 dark:text-slate-300 font-extrabold" title="${formatAuditNumber(n)}">${compactAuditNumber(n)}</span>`;
    infoEl.innerHTML = `${escapeAuditHtml(t.showingPage)} ${strong(pagination.page)} ${escapeAuditHtml(t.of)} ${strong(pagination.totalPages)} (${escapeAuditHtml(t.total)} <span title="${formatAuditNumber(pagination.total)}">${compactAuditNumber(pagination.total)}</span> ${escapeAuditHtml(t.logs)})`;
  }

  const setDisabled = (el: HTMLElement | null, disabled: boolean) => {
    if (!el) return;
    el.classList.toggle('opacity-40', disabled);
    el.classList.toggle('cursor-not-allowed', disabled);
    el.classList.toggle('pointer-events-none', disabled);
  };

  const prevBtn = byId('audit-prev-page');
  const nextBtn = byId('audit-next-page');
  if (prevBtn) prevBtn.onclick = () => handleAuditPageChange(pagination.page - 1);
  if (nextBtn) nextBtn.onclick = () => handleAuditPageChange(pagination.page + 1);
  setDisabled(prevBtn, pagination.page <= 1);
  setDisabled(nextBtn, pagination.page >= pagination.totalPages);

  const numbersContainer = byId('audit-pagination-numbers');
  if (numbersContainer) {
    let html = '';
    const currentPage = pagination.page;
    const lastPage = pagination.totalPages;

    const addPage = (p: number) => {
      if (p === currentPage) {
        html += `<span aria-current="page" class="shrink-0"><span class="flex items-center justify-center w-9 h-9 rounded-xl bg-linear-to-r from-teal-500 to-emerald-600 text-white text-sm font-bold shadow-md shadow-teal-200 dark:shadow-none">${p}</span></span>`;
      } else {
        html += `<button type="button" onclick="handleAuditPageChange(${p})" class="flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 hover:text-teal-600 dark:hover:text-teal-400 hover:border-teal-300 dark:hover:border-teal-700 transition-all shadow-sm text-sm font-bold shrink-0 cursor-pointer">${p}</button>`;
      }
    };

    const addDots = () => {
      html += `<span class="shrink-0"><span class="flex items-center justify-center w-9 h-9 text-sm font-bold text-gray-400 dark:text-slate-500">...</span></span>`;
    };

    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) addPage(i);
    } else if (currentPage <= 4) {
      for (let i = 1; i <= 5; i++) addPage(i);
      addDots();
      addPage(lastPage);
    } else if (currentPage >= lastPage - 3) {
      addPage(1);
      addDots();
      for (let i = lastPage - 4; i <= lastPage; i++) addPage(i);
    } else {
      addPage(1);
      addDots();
      addPage(currentPage - 1);
      addPage(currentPage);
      addPage(currentPage + 1);
      addDots();
      addPage(lastPage);
    }
    numbersContainer.innerHTML = html;
  }

  const hidePagination = pagination.totalPages <= 1;
  bar.classList.toggle('hidden', hidePagination);
  bar.classList.toggle('flex', !hidePagination);

  const jumpInput = byId('audit-page-jump-input') as HTMLInputElement | null;
  if (jumpInput) {
    jumpInput.max = String(pagination.totalPages);
    jumpInput.value = '';
  }
}

function getAuditSettingLabel(item: any): string {
  const labels = cfg.settingLabels;
  const path = String(item?.path || '');
  if (path && labels[path]) return labels[path];

  const parts = path.split('.').filter(Boolean);
  if (parts[0] === 'survey') {
    const field = parts[parts.length - 1];
    if (parts.includes('questions') && labels[`survey.questions.${field}`]) {
      return labels[`survey.questions.${field}`];
    }
    if (parts.includes('sections') && labels[`survey.sections.${field}`]) {
      return labels[`survey.sections.${field}`];
    }
  }

  if (parts.length >= 2) {
    const collectionField = `${parts[0]}.${parts[parts.length - 1]}`;
    if (labels[collectionField]) return labels[collectionField];

    const parentPath = parts.slice(0, 2).join('.');
    if (labels[parentPath]) return labels[parentPath];
  }

  if (parts.length >= 1 && labels[parts[0]]) return labels[parts[0]];

  return item?.label || path || '-';
}

function formatAuditChangeValue(value: unknown): string {
  const text = String(value ?? '—');
  const rtl = cfg.isRtl;

  switch (text.toLowerCase()) {
    case 'true':
      return rtl ? 'مفعل / نعم' : 'Enabled / Yes';
    case 'false':
      return rtl ? 'معطل / لا' : 'Disabled / No';
  }

  switch (text) {
    case '[protected]':
      return rtl ? 'قيمة محمية' : 'Protected value';
    case '[changed]':
      return rtl ? 'تم التغيير' : 'Changed';
    case '[embedded image]':
    case '[stored image]':
      return rtl ? 'صورة محفوظة' : 'Stored image';
  }

  return text;
}

function openAuditSettingsDetails(changes: unknown) {
  const modal = byId('audit-settings-details-modal');
  const body = byId('audit-settings-details-body');
  if (!modal || !body) return;
  const t = cfg.texts;

  const list: any[] = Array.isArray(changes) ? changes : [];
  if (list.length === 0) {
    body.innerHTML = `
      <div class="rounded-xl border border-gray-100 bg-gray-50 p-5 text-center text-sm font-bold text-gray-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
        ${escapeAuditHtml(t.noChangeDetails)}
      </div>
    `;
  } else {
    body.innerHTML = `
      <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-teal-100 bg-teal-50 px-4 py-3 text-start dark:border-teal-900/40 dark:bg-teal-950/20">
        <span class="text-xs font-black text-teal-700 dark:text-teal-300">${escapeAuditHtml(t.changedFields)}</span>
        <span class="stat-badge rounded-full bg-white px-2 py-1 text-xs font-black text-teal-700 dark:bg-slate-900 dark:text-teal-300" title="${formatAuditNumber(list.length)}">${compactAuditNumber(list.length)}</span>
      </div>
      <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-slate-800">
        <table class="w-full min-w-[760px] border-collapse text-xs">
          <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:bg-slate-950 dark:text-slate-500">
            <tr>
              <th class="w-[30%] px-3 py-2 text-start">${escapeAuditHtml(t.field)}</th>
              <th class="w-[35%] px-3 py-2 text-start">${escapeAuditHtml(t.before)}</th>
              <th class="w-[35%] px-3 py-2 text-start">${escapeAuditHtml(t.after)}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
            ${list
              .map(
                (item) => `
              <tr class="align-top">
                <td class="bg-gray-50/60 px-3 py-3 font-black text-gray-700 dark:bg-slate-950/40 dark:text-slate-200">
                  <div class="break-words">${escapeAuditHtml(getAuditSettingLabel(item))}</div>
                  <div class="mt-1 break-all font-mono text-[10px] font-bold text-gray-400 dark:text-slate-500" dir="ltr">${escapeAuditHtml(item.path || '')}</div>
                </td>
                <td class="px-3 py-3 text-gray-600 dark:text-slate-300">
                  <div class="max-h-32 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-red-50/60 p-2 leading-5 dark:bg-red-950/10">${escapeAuditHtml(formatAuditChangeValue(item.before))}</div>
                </td>
                <td class="px-3 py-3 text-gray-600 dark:text-slate-300">
                  <div class="max-h-32 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-emerald-50/70 p-2 leading-5 dark:bg-emerald-950/10">${escapeAuditHtml(formatAuditChangeValue(item.after))}</div>
                </td>
              </tr>
            `,
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.classList.add('overflow-hidden');
  globalWindow.lucide?.createIcons?.();
}

function openAuditSettingsDetailsFromButton(button: HTMLElement) {
  try {
    const encoded = button?.dataset?.changes || '%5B%5D';
    openAuditSettingsDetails(JSON.parse(decodeURIComponent(encoded)));
  } catch (error) {
    console.error('Failed to parse audit settings changes', error);
    openAuditSettingsDetails([]);
  }
}

function closeAuditSettingsDetails() {
  const modal = byId('audit-settings-details-modal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.classList.remove('overflow-hidden');
}

async function renderAuditCharts() {
  const hasTrend = byId('trendChart') && cfg.trendData.length > 0;
  const hasActions = byId('actionDistributionChart') && cfg.actionStats.length > 0;
  if (!hasTrend && !hasActions) return;

  const ApexCharts = await globalWindow.loadApexCharts();
  const t = cfg.texts;
  const isDark = document.documentElement.classList.contains('dark');
  const textColor = isDark ? '#94a3b8' : '#6b7280';
  const gridColor = isDark ? '#1e293b' : '#f3f4f6';
  const axisLabelStyle = (fontSize: string) => ({ colors: textColor, fontSize, fontFamily: 'Cairo' });
  const grid = {
    borderColor: gridColor,
    strokeDashArray: 3,
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } },
  };
  const tooltip = {
    theme: isDark ? 'dark' : 'light',
    y: { formatter: (val: number) => `${val} ${t.operations}` },
  };

  if (hasTrend) {
    const trend = cfg.trendData;
    new ApexCharts(byId('trendChart'), {
      chart: {
        type: 'area',
        height: 250,
        fontFamily: 'Cairo, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
      },
      series: [
        { name: t.totalOperations, data: trend.map((d) => d.total) },
        { name: t.failedLogins, data: trend.map((d) => d.failed) },
      ],
      xaxis: {
        categories: trend.map((d) => d.formattedDate || d.date),
        labels: {
          style: axisLabelStyle('11px'),
          formatter: (value: string, _timestamp: unknown, opts: any) => {
            if (typeof opts === 'undefined' || !opts.w) return value;
            const idx = opts.index;
            const total = opts.w.globals.labels.length;
            // Always show first/last labels, month starts, and every 5th label
            if (idx === 0 || idx === total - 1 || value.startsWith('01/') || value.startsWith('1/') || idx % 5 === 0) {
              return value;
            }
            return '';
          },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: { labels: { style: axisLabelStyle('11px') } },
      grid,
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 4] },
      colors: ['#0d9488', '#e11d48'], // Teal for general, rose for failed security logins
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: [0.35, 0.2], opacityTo: [0.02, 0.01], stops: [0, 100] },
      },
      legend: {
        show: true,
        position: 'top',
        horizontalAlign: t.legendAlign,
        fontFamily: 'Cairo',
        fontSize: '12px',
        labels: { colors: textColor },
        markers: { radius: 12 },
      },
      tooltip,
    }).render();
  }

  if (hasActions) {
    const stats = cfg.actionStats;
    new ApexCharts(byId('actionDistributionChart'), {
      chart: {
        type: 'bar',
        height: 220,
        fontFamily: 'Cairo, sans-serif',
        toolbar: { show: false },
        background: 'transparent',
      },
      series: [{ name: t.operations, data: stats.map((d) => d.count) }],
      xaxis: {
        categories: stats.map((d) => cfg.actionLabels[d.action] || d.action),
        labels: { style: axisLabelStyle('10px') },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: { labels: { style: axisLabelStyle('11px') } },
      grid,
      plotOptions: { bar: { borderRadius: 4, columnWidth: '40%', distributed: true } },
      colors: ['#0d9488', '#6366f1', '#e11d48', '#d97706', '#9333ea'],
      dataLabels: { enabled: false },
      legend: { show: false },
      tooltip,
    }).render();
  }
}

// The audit template uses inline handlers, so these stay reachable globally.
Object.assign(globalWindow, {
  handleAuditSearch,
  handleAuditFilterChange,
  handleAuditPageChange,
  resetAuditFilters,
  refreshAuditLogs,
  openAuditSettingsDetails,
  openAuditSettingsDetailsFromButton,
  closeAuditSettingsDetails,
});

document.addEventListener('alpine:init', () => {
  Alpine.data('auditManagement', (options: AuditOptions): any => ({
    showFilters: options.showFilters,

    init() {
      cfg = options;
      currentAuditPage = options.pagination.page;
      renderAuditPagination(options.pagination);
      void renderAuditCharts();
    },
  }));
});
