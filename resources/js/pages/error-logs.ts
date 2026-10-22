/* eslint-disable @typescript-eslint/no-explicit-any */
// Error logs dashboard page. Configuration is rendered by Blade into
// <script type="application/json" id="error-logs-config">.
const win = window as any;
const byId = (id: string): any => document.getElementById(id);

function initErrorLogs(cfg: any) {
    const isRtl = document.documentElement.dir === 'rtl' || cfg.isAr;
    const canDeleteLogs = cfg.canDeleteLogs;
    
    let currentPage = cfg.currentPage;
    let currentPerPage = cfg.perPage;
    let selectedLog: any = null;
    const formatNumber = (value: unknown) => new Intl.NumberFormat('en-US').format(Number(value || 0));
    const compactNumber = (value: unknown) => {
        const number = Number(value || 0);
        const abs = Math.abs(number);

        if (abs >= 1000000) {
            return `${(number / 1000000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000000 ? 0 : 1 })}M`;
        }

        if (abs >= 1000) {
            return `${(number / 1000).toLocaleString('en-US', { maximumFractionDigits: abs >= 10000 ? 0 : 1 })}K`;
        }

        return formatNumber(number);
    };
    
    const t: any = cfg.texts;

    // Card Counter updates
    function updateStatsHeader(stats: any) {
        if (!stats) return;
        
        const errors = stats.byLevel.find((l: any) => l.level === 'error')?.count || 0;
        const totalNew = stats.byStatus.find((s: any) => s.status === 'new')?.count || 0;
        const totalInProgress = stats.byStatus.find((s: any) => s.status === 'investigating')?.count || 0;
        const totalSources = stats.topSources.length || 0;
        
        byId("stat-errors").textContent = compactNumber(errors);
        byId("stat-errors").title = formatNumber(errors);
        byId("stat-new").textContent = compactNumber(totalNew);
        byId("stat-new").title = formatNumber(totalNew);
        byId("stat-inprogress").textContent = compactNumber(totalInProgress);
        byId("stat-inprogress").title = formatNumber(totalInProgress);
        byId("stat-sources").textContent = compactNumber(totalSources);
        byId("stat-sources").title = formatNumber(totalSources);
        
        // Update top sources badges
        const sourcesPanel = byId("top-sources-panel");
        const sourcesList = byId("top-sources-list");
        if (totalSources === 0) {
            sourcesPanel.classList.add("hidden");
        } else {
            sourcesPanel.classList.remove("hidden");
            sourcesList.innerHTML = stats.topSources.map((s: any) => `
                <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800/80 rounded-xl text-xs font-bold text-slate-650 dark:text-slate-350 border border-slate-100 dark:border-slate-800">
                    ${escapeHtml(s.source || t.unknown)} (${compactNumber(s.count)})
                </span>
            `).join('');
        }
    }

    function escapeHtml(text: string) {
        if (!text) return '';
        const map: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Modal Window functions
    win.openErrorLogDetails = function (log: any) {
        selectedLog = log;

        const displayMessage = log.translatedMessage || log.message || '-';
        
        byId("modal-message").textContent = displayMessage;
        byId("modal-source").textContent = log.source || '-';

        const originalMessageContainer = byId("modal-original-message-container");
        const originalMessage = byId("modal-original-message");
        if (log.message && displayMessage !== log.message) {
            originalMessage.textContent = log.message;
            originalMessageContainer.classList.remove("hidden");
        } else {
            originalMessageContainer.classList.add("hidden");
            originalMessage.textContent = '-';
        }
        
        const stackElement = byId("modal-stack");
        const stackContainer = byId("modal-stack-container");
        if (log.stack) {
            stackElement.textContent = log.stack;
            stackContainer.classList.remove("hidden");
        } else {
            stackContainer.classList.add("hidden");
        }
        
        const countContainer = byId("modal-count-container");
        const countPill = byId("modal-count-pill");
        if (log.count > 1) {
            countPill.textContent = t.repeated_tpl.replace('\x7b\x7bcount\x7d\x7d', compactNumber(log.count));
            countContainer.classList.remove("hidden");
        } else {
            countContainer.classList.add("hidden");
        }
        
        byId("modal-status-select").value = log.status;
        byId("modal-notes-textarea").value = log.resolutionNotes || '';
        
        // Show modal with animation
        const modal = byId("error-details-modal");
        const card = byId("modal-card");
        
        modal.classList.remove("hidden");
        // Reflow
        void modal.offsetHeight;
        
        modal.classList.remove("bg-black/0");
        modal.classList.add("bg-black/50");
        card.classList.remove("scale-95", "opacity-0");
        card.classList.add("scale-100", "opacity-100");
    };

    win.closeErrorLogDetails = function () {
        const modal = byId("error-details-modal");
        const card = byId("modal-card");
        
        card.classList.remove("scale-100", "opacity-100");
        card.classList.add("scale-95", "opacity-0");
        modal.classList.remove("bg-black/50");
        modal.classList.add("bg-black/0");
        
        setTimeout(() => {
            modal.classList.add("hidden");
            selectedLog = null;
        }, 300);
    };

    // Fetch filtered data
    function hasAdvancedErrorFilters() {
        const level = byId("level-filter")?.value || 'all';
        const status = byId("status-filter")?.value || 'all';
        const startDate = byId("error-start-date")?.value || '';
        const endDate = byId("error-end-date")?.value || '';

        return level !== 'all' || status !== 'all' || startDate !== '' || endDate !== '';
    }

    function updateErrorFilterChrome() {
        const hasAdvanced = hasAdvancedErrorFilters();
        const hasSearch = (byId("search-input")?.value || '').trim() !== '';
        const toggle = byId("error-filters-toggle");
        const dot = byId("error-filters-dot");
        const clearBtn = byId("error-clear-filters-btn");

        if (dot) {
            dot.classList.toggle('hidden', !hasAdvanced);
        }

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', !hasAdvanced && !hasSearch);
        }

        if (toggle) {
            toggle.classList.toggle('border-teal-200', hasAdvanced);
            toggle.classList.toggle('bg-teal-50', hasAdvanced);
            toggle.classList.toggle('text-teal-700', hasAdvanced);
            toggle.classList.toggle('dark:border-teal-900/50', hasAdvanced);
            toggle.classList.toggle('dark:bg-teal-950/30', hasAdvanced);
            toggle.classList.toggle('dark:text-teal-300', hasAdvanced);
        }
    }

    win.toggleErrorLogFilters = function () {
        const panel = byId("error-advanced-filters");
        if (!panel) return;

        panel.classList.toggle("hidden");
    };

    win.resetErrorLogFilters = function () {
        byId("search-input").value = '';
        byId("level-filter").value = 'all';
        byId("status-filter").value = 'all';
        byId("error-start-date").value = '';
        byId("error-end-date").value = '';
        byId("error-start-date-label").textContent = 'YYYY-MM-DD';
        byId("error-end-date-label").textContent = 'YYYY-MM-DD';
        updateErrorFilterChrome();
        win.fetchLogsData(1);
    };

    win.fetchLogsData = function (page = 1) {
        currentPage = page;
        
        const level = byId("level-filter").value;
        const status = byId("status-filter").value;
        const search = byId("search-input").value;
        const startDate = byId("error-start-date")?.value || '';
        const endDate = byId("error-end-date")?.value || '';
        const perPage = byId("per-page-select")?.value || currentPerPage;
        currentPerPage = Number(perPage) || 25;
        
        const params = new URLSearchParams({
            ajax: 'true',
            page: String(page),
            per_page: String(currentPerPage),
            level: level,
            status: status,
            search: search,
            start_date: startDate,
            end_date: endDate
        });
        
        // Set loading state
        const tbody = byId("logs-table-body");
        tbody.innerHTML = `
            <tr>
              <td colspan="7" class="text-center py-12">
                <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mx-auto"></div>
              </td>
            </tr>
        `;
        
        return fetch(`${cfg.routes.index}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error("Connection failed");
            return res.json();
        })
        .then(data => {
            renderTableRows(data.logs);
            renderPagination(data.pagination);
            currentPage = data.pagination.page;
            currentPerPage = data.pagination.limit;
            updateStatsHeader(data.stats);
            updateErrorFilterChrome();
        })
        .catch(err => {
            console.error("Failed to load logs:", err);
            tbody.innerHTML = `
                <tr>
                  <td colspan="7" class="text-center py-12 text-rose-500 font-bold">
                    ${t.load_failed}
                  </td>
                </tr>
            `;
        });
    };

    win.handleFilterChange = function () {
        const startDate = byId("error-start-date");
        const endDate = byId("error-end-date");
        const startLabel = byId("error-start-date-label");
        const endLabel = byId("error-end-date-label");

        if (startDate && startLabel) {
            startLabel.textContent = startDate.value || 'YYYY-MM-DD';
        }

        if (endDate && endLabel) {
            endLabel.textContent = endDate.value || 'YYYY-MM-DD';
        }

        updateErrorFilterChrome();
        win.fetchLogsData(1);
    };

    win.handlePageChange = function (page: number | string) {
        win.fetchLogsData(page);
    };

    win.handlePerPageChange = function () {
        win.fetchLogsData(1);
    };

    win.jumpToPage = function () {
        const input = byId("page-jump-input");
        const totalPages = Number(input?.max || 1) || 1;
        const page = Math.min(Math.max(1, Number(input?.value || 1) || 1), totalPages);

        if (input) input.value = '';
        win.fetchLogsData(page);
    };

    function renderTableRows(logs: any[]) {
        const tbody = byId("logs-table-body");
        if (logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                  <td colspan="7" class="text-center py-12 text-slate-450 dark:text-slate-500 text-sm font-bold">
                    ${t.no_matching}
                  </td>
                </tr>
            `;
            return;
        }
        
        const levelClasses: Record<string, string> = {
            error: 'bg-red-500/10 text-red-600 dark:text-red-400',
            warn: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
        };
        
        const statusClasses: Record<string, string> = {
            new: 'bg-red-500/10 text-red-600 dark:text-red-400',
            investigating: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            resolved: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            ignored: 'bg-slate-500/10 text-slate-600 dark:text-slate-400'
        };

        tbody.innerHTML = logs.map((log: any) => {
            const lvlBg = levelClasses[log.level] || levelClasses.error;
            const lvlLabel = t[log.level] || t.error;
            const stBg = statusClasses[log.status] || statusClasses.new;
            const stLabel = t[log.status] || t.new;
            const displayMessage = log.translatedMessage || log.message || '-';
            
            // Safe JSON rendering inside Javascript click handler
            const logSafeJson = JSON.stringify(log)
                .replace(/&/g, '&amp;')
                .replace(/'/g, '&#39;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const logDate = new Date(log.createdAt).toLocaleString(isRtl ? 'ar-SA' : 'en-US', {
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false
            });
            
            const countPill = log.count > 1 
                ? `<span class="stat-badge inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-slate-100 px-1.5 text-xs font-bold text-slate-600 dark:bg-slate-850 dark:text-slate-350" title="${formatNumber(log.count)}">${compactNumber(log.count)}</span>`
                : `<span class="text-slate-400 dark:text-slate-550 text-xs">-</span>`;
                
            return `
                <tr id="log-row-${log.id}" class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50/50 dark:hover:bg-slate-850/20 transition-all duration-300">
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black ${lvlBg}">
                      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                      <span>${escapeHtml(lvlLabel)}</span>
                    </span>
                  </td>
                  <td class="px-4 py-3 max-w-[200px] sm:max-w-xs text-start">
                    <span class="text-slate-800 dark:text-slate-200 font-bold block truncate" title="${escapeHtml(displayMessage)}">
                      ${escapeHtml(displayMessage)}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden sm:table-cell text-start">
                    <span class="text-slate-500 dark:text-slate-450 text-xs font-semibold truncate block max-w-[120px]" title="${escapeHtml(log.source || '')}">
                      ${escapeHtml(log.source || '-')}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden md:table-cell">
                    <span class="px-2.5 py-1 rounded-xl text-xs font-black ${stBg}">
                      ${escapeHtml(stLabel)}
                    </span>
                  </td>
                  <td class="px-4 py-3 hidden md:table-cell">
                    ${countPill}
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 font-semibold whitespace-nowrap">
                    ${escapeHtml(logDate)}
                  </td>
                  <td class="px-4 py-3">
                    <button
                      onclick="openErrorLogDetails(JSON.parse('${logSafeJson}'))"
                      class="p-1.5 rounded-lg hover:bg-slate-150 dark:hover:bg-slate-800 text-slate-400 hover:text-teal-500 dark:hover:text-teal-400 transition-all cursor-pointer"
                      title="${t.details_action}"
                    >
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>
                    </button>
                  </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination(pageInfo: any) {
        const rangeStart = pageInfo.total === 0 ? 0 : ((pageInfo.page - 1) * pageInfo.limit) + 1;
        const rangeEnd = Math.min(pageInfo.page * pageInfo.limit, pageInfo.total);

        byId("pagination-summary").textContent = isRtl
            ? `${t.page_label} ${compactNumber(pageInfo.page)} ${t.of_label} ${compactNumber(pageInfo.totalPages)} | ${compactNumber(rangeStart)}-${compactNumber(rangeEnd)} ${t.shown_from_total} ${compactNumber(pageInfo.total)}`
            : `${t.page_label} ${compactNumber(pageInfo.page)} ${t.of_label} ${compactNumber(pageInfo.totalPages)} | ${compactNumber(rangeStart)}-${compactNumber(rangeEnd)} ${t.shown_from_total} ${compactNumber(pageInfo.total)}`;
            
        const prevBtn = byId("prev-page-btn");
        const nextBtn = byId("next-page-btn");
        const pageJumpInput = byId("page-jump-input");
        const perPageSelect = byId("per-page-select");

        if (pageJumpInput) {
            pageJumpInput.max = pageInfo.totalPages;
        }

        if (perPageSelect) {
            perPageSelect.value = pageInfo.limit;
        }
        
        if (pageInfo.page <= 1) {
            prevBtn.setAttribute("disabled", "disabled");
            prevBtn.onclick = null;
        } else {
            prevBtn.removeAttribute("disabled");
            prevBtn.onclick = () => win.handlePageChange(pageInfo.page - 1);
        }
        
        if (pageInfo.page >= pageInfo.totalPages) {
            nextBtn.setAttribute("disabled", "disabled");
            nextBtn.onclick = null;
        } else {
            nextBtn.removeAttribute("disabled");
            nextBtn.onclick = () => win.handlePageChange(pageInfo.page + 1);
        }
    }

    // Refresh data and load stats
    win.refreshLogsData = function () {
        const icon = byId("refresh-icon");
        icon.classList.add("animate-spin");

        Promise.resolve(win.fetchLogsData(currentPage))
            .finally(() => {
                setTimeout(() => icon.classList.remove("animate-spin"), 500);
            });
    };

    // Clear logs
    win.handleClearLogs = function () {
        if (!canDeleteLogs) return;
        win.openClearLogsConfirm();
    };

    win.openClearLogsConfirm = function () {
        const modal = byId("clear-logs-modal");
        const card = byId("clear-logs-modal-card");

        modal.classList.remove("hidden");
        void modal.offsetHeight;
        modal.classList.remove("bg-black/0");
        modal.classList.add("bg-black/50");
        card.classList.remove("scale-95", "opacity-0");
        card.classList.add("scale-100", "opacity-100");
    };

    win.closeClearLogsConfirm = function () {
        const modal = byId("clear-logs-modal");
        const card = byId("clear-logs-modal-card");

        card.classList.remove("scale-100", "opacity-100");
        card.classList.add("scale-95", "opacity-0");
        modal.classList.remove("bg-black/50");
        modal.classList.add("bg-black/0");

        setTimeout(() => {
            modal.classList.add("hidden");
        }, 300);
    };

    win.confirmClearLogs = function () {
        if (!canDeleteLogs) return;

        const btn = byId("clear-logs-btn");
        const text = byId("clear-logs-text");
        const confirmBtn = byId("confirm-clear-logs-btn");
        btn.setAttribute("disabled", "disabled");
        confirmBtn.setAttribute("disabled", "disabled");
        text.textContent = t.clearing;
        confirmBtn.textContent = t.clearing;
        
        fetch(cfg.routes.clear, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reset stats and view
                byId("stat-errors").textContent = "0";
                byId("stat-new").textContent = "0";
                byId("stat-inprogress").textContent = "0";
                byId("stat-sources").textContent = "0";
                byId("top-sources-panel").classList.add("hidden");
                
                win.fetchLogsData(1);
                win.closeClearLogsConfirm();
            }
        })
        .finally(() => {
            btn.removeAttribute("disabled");
            confirmBtn.removeAttribute("disabled");
            text.textContent = t.clear;
            confirmBtn.textContent = t.clear;
        });
    };

    // Delete single log
    win.handleDeleteSelectedLog = function () {
        if (!canDeleteLogs || !selectedLog) return;
        if (!confirm(t.delete_confirm)) return;
        
        const delBtn = byId("modal-delete-btn");
        delBtn.setAttribute("disabled", "disabled");
        delBtn.textContent = t.deleting;
        
        fetch(`${cfg.routes.base}/${selectedLog.id}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                win.closeErrorLogDetails();
                win.fetchLogsData(currentPage);
            }
        })
        .finally(() => {
            delBtn.removeAttribute("disabled");
            delBtn.textContent = t.delete;
        });
    };

    // Update status
    win.handleUpdateStatus = function () {
        if (!selectedLog) return;
        
        const status = byId("modal-status-select").value;
        const notes = byId("modal-notes-textarea").value;
        
        const saveBtn = byId("modal-save-btn");
        saveBtn.setAttribute("disabled", "disabled");
        saveBtn.textContent = t.saving;
        
        fetch(`${cfg.routes.base}/${selectedLog.id}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                status: status,
                resolutionNotes: notes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                win.closeErrorLogDetails();
                win.fetchLogsData(currentPage);
            }
        })
        .finally(() => {
            saveBtn.removeAttribute("disabled");
            saveBtn.textContent = t.save_changes;
        });
    };
}

function boot() {
  const el = byId('error-logs-config');
  if (!el) return;
  initErrorLogs(JSON.parse(el.textContent || '{}'));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
