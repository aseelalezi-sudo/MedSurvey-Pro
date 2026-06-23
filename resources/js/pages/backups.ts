/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface BackupsOptions {
  backups: any[];
  config: any;
  texts: any;
  routes: {
    base: string;
    create: string;

  };
}

document.addEventListener('alpine:init', () => {
  Alpine.data('backupsManager', (options: BackupsOptions): any => ({
    activeTab: 'local',
    backupsData: options.backups || [],
    configData: options.config || {},
    error: '',
    successMessage: '',
    backupSearch: '',
    backupStatusFilter: 'all',
    backupTypeFilter: 'all',
    backupSort: 'newest',
    backupDateFrom: '',
    backupDateTo: '',
    filtersOpen: false,
    backupPage: 1,
    backupPageJump: '',
    backupPageSize: 25,
    pageSizeOptions: [10, 25, 50, 100],
    confirmModal: { isOpen: false, type: null as string | null, filename: '', extraData: '' },
    creating: false,
    verifying: null as string | null,
    downloadingFilename: null as string | null,
    texts: options.texts,

    formatNumber(value: number | string, fractionDigits = 0) {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
      }).format(Number(value || 0));
    },

    compactNumber(value: number | string) {
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

    formatFileSize(bytes: number | string, fullPrecision = false) {
      let size = Number(bytes || 0);
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      let unitIndex = 0;

      while (Math.abs(size) >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
      }

      const decimals = unitIndex === 0 ? 0 : 2;
      const value = fullPrecision
        ? this.formatNumber(size, decimals)
        : this.formatNumber(size, decimals).replace(/\.00$/, '');

      return `${value} ${units[unitIndex]}`;
    },

    get hasBackupFilters() {
      return this.backupSearch.trim() !== '' || this.hasAdvancedBackupFilters;
    },

    get hasAdvancedBackupFilters() {
      return (
        this.backupStatusFilter !== 'all' ||
        this.backupTypeFilter !== 'all' ||
        this.backupSort !== 'newest' ||
        this.backupDateFrom !== '' ||
        this.backupDateTo !== ''
      );
    },

    get filteredBackups() {
      const search = this.backupSearch.trim().toLowerCase();

      return [...(this.backupsData || [])]
        .filter((backup: any) => {
          const filename = String(backup.filename || '').toLowerCase();

          if (search && !filename.includes(search)) {
            return false;
          }

          if (this.backupTypeFilter === 'sql.gz' && !filename.endsWith('.sql.gz')) {
            return false;
          }

          if (this.backupTypeFilter === 'sql' && (!filename.endsWith('.sql') || filename.endsWith('.sql.gz'))) {
            return false;
          }

          if (this.backupStatusFilter === 'valid' && backup.verified !== true) {
            return false;
          }

          if (this.backupStatusFilter === 'invalid' && backup.verified !== false) {
            return false;
          }

          if (this.backupStatusFilter === 'unverified' && (backup.verified === true || backup.verified === false)) {
            return false;
          }

          const createdAt = this.backupDateValue(backup.createdAt);
          if (this.backupDateFrom && createdAt < this.backupDateValue(this.backupDateFrom)) {
            return false;
          }

          if (this.backupDateTo && createdAt > this.backupDateValue(this.backupDateTo, true)) {
            return false;
          }

          return true;
        })
        .sort((a: any, b: any) => {
          const filenameA = String(a.filename || '');
          const filenameB = String(b.filename || '');
          const dateA = new Date(a.createdAt || 0).getTime() || 0;
          const dateB = new Date(b.createdAt || 0).getTime() || 0;
          const sizeA = Number(a.sizeBytes || 0);
          const sizeB = Number(b.sizeBytes || 0);

          if (this.backupSort === 'oldest') return dateA - dateB;
          if (this.backupSort === 'largest') return sizeB - sizeA;
          if (this.backupSort === 'smallest') return sizeA - sizeB;
          if (this.backupSort === 'name-asc') return filenameA.localeCompare(filenameB);
          if (this.backupSort === 'name-desc') return filenameB.localeCompare(filenameA);

          return dateB - dateA;
        });
    },

    get backupTotalPages() {
      return Math.max(1, Math.ceil(this.filteredBackups.length / this.backupPageSize));
    },

    get backupCurrentPage() {
      return Math.min(Math.max(1, this.backupPage), this.backupTotalPages);
    },

    get backupRangeStart() {
      if (this.filteredBackups.length === 0) return 0;
      return (this.backupCurrentPage - 1) * this.backupPageSize + 1;
    },

    get backupRangeEnd() {
      return Math.min(this.backupCurrentPage * this.backupPageSize, this.filteredBackups.length);
    },

    get paginatedBackups() {
      const start = (this.backupCurrentPage - 1) * this.backupPageSize;
      return this.filteredBackups.slice(start, start + this.backupPageSize);
    },

    setBackupPage(page: number | string) {
      this.backupPage = Math.min(Math.max(1, Number(page) || 1), this.backupTotalPages);
      this.backupPageJump = '';
      this.$nextTick(() => {
        if (typeof (window as any).lucide !== 'undefined') (window as any).lucide.createIcons();
      });
    },

    setBackupPageSize(size: number | string) {
      const nextSize = Number(size) || 25;
      this.backupPageSize = this.pageSizeOptions.includes(nextSize) ? nextSize : 25;
      this.backupPage = 1;
      this.backupPageJump = '';
      this.$nextTick(() => {
        if (typeof (window as any).lucide !== 'undefined') (window as any).lucide.createIcons();
      });
    },

    jumpBackupPage() {
      this.setBackupPage(this.backupPageJump);
    },

    resetBackupFilters() {
      this.backupSearch = '';
      this.backupStatusFilter = 'all';
      this.backupTypeFilter = 'all';
      this.backupSort = 'newest';
      this.backupDateFrom = '';
      this.backupDateTo = '';
      this.backupPage = 1;
      this.$nextTick(() => {
        if (typeof (window as any).lucide !== 'undefined') (window as any).lucide.createIcons();
      });
    },

    backupDateValue(value: string | number, endOfDay = false) {
      if (!value) return endOfDay ? Number.MAX_SAFE_INTEGER : 0;
      const text = String(value);
      const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(text);
      const date = new Date(dateOnly ? `${text}T${endOfDay ? '23:59:59' : '00:00:00'}` : text);
      return Number.isNaN(date.getTime()) ? 0 : date.getTime();
    },

    getCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    },

    refreshBackups(skipClearSuccess = false) {
      if (!skipClearSuccess) this.successMessage = '';
      this.error = '';
      return fetch(`${options.routes.base}?ajax=true`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.backups) {
            this.backupsData = data.backups;
            this.configData = data.config;
            this.backupPage = 1;
          }
        })
        .catch(() => {
          this.error = this.texts.readFolderFailed || 'Failed to refresh data';
        });
    },

    handleCreate() {
      this.creating = true;
      this.error = '';
      this.successMessage = '';
      fetch(options.routes.create, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            this.successMessage = data.message;
            return this.refreshBackups(true);
          } else {
            this.error = data.message;
          }
        })
        .catch(() => {
          this.error = this.texts.createFailed;
        })
        .finally(() => {
          this.creating = false;
          setTimeout(() => this.autoVerifyLatest(), 100);
        });
    },

    autoVerifyLatest() {
      if (!this.backupsData || this.backupsData.length === 0) return;
      const sorted = [...this.backupsData].sort(
        (a: any, b: any) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
      );
      const latest = sorted[0];
      if (!latest) return;
      const idx = this.backupsData.indexOf(latest);
      const filename = latest.filename;

      fetch(`${options.routes.base}/${encodeURIComponent(filename)}/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          if (this.backupsData[idx]) {
            const updated = [...this.backupsData];
            updated[idx] = {
              ...updated[idx],
              verified: data.success ? true : false,
              verifyMessage: data.message || '',
            };
            this.backupsData = updated;
          }
          if (typeof (window as any).lucide !== 'undefined') setTimeout(() => (window as any).lucide.createIcons(), 50);
        })
        .catch(() => {
          // Silent fail
        });
    },

    handleVerify(filename: string) {
      this.verifying = filename;
      this.error = '';
      this.successMessage = '';
      fetch(`${options.routes.base}/${encodeURIComponent(filename)}/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          const idx = this.backupsData.findIndex((backup: any) => backup.filename === filename);
          if (this.backupsData[idx]) {
            const updated = [...this.backupsData];
            updated[idx] = {
              ...updated[idx],
              verified: data.success ? true : false,
              verifyMessage: data.message || '',
            };
            this.backupsData = updated;
          }
          if (typeof (window as any).lucide !== 'undefined') setTimeout(() => (window as any).lucide.createIcons(), 50);
        })
        .catch(() => {
          this.error = this.texts.verifyFailed;
        })
        .finally(() => {
          this.verifying = null;
        });
    },

    handleDownload(filename: string) {
      this.downloadingFilename = filename;
      this.error = '';
      fetch(`${options.routes.base}/${encodeURIComponent(filename)}/download`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((res) => {
          if (!res.ok) throw new Error(this.texts.downloadServerFailed);
          return res.blob();
        })
        .then((blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
        })
        .catch((err: any) => {
          this.error = err.message || this.texts.downloadFailed;
        })
        .finally(() => {
          this.downloadingFilename = null;
        });
    },


    openDeleteModal(filename: string) {
      this.confirmModal = { isOpen: true, type: 'delete', filename: filename, extraData: '' };
    },

    closeConfirmModal() {
      this.confirmModal = { isOpen: false, type: null, filename: '', extraData: '' };
    },

    executeConfirmAction() {
      const type = this.confirmModal.type;
      const filename = this.confirmModal.filename;
      this.closeConfirmModal();

      if (type === 'delete') {
        this.executeDelete(filename);
      }
    },

    executeDelete(filename: string) {
      this.error = '';
      this.successMessage = '';
      fetch(`${options.routes.base}/${encodeURIComponent(filename)}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            this.successMessage = data.message;
            this.refreshBackups();
          } else {
            this.error = data.message;
          }
        })
        .catch(() => {
          this.error = this.texts.deleteFailed;
        });
    },



    get latestBackup() {
      if (!this.backupsData || this.backupsData.length === 0) return null;
      return [...this.backupsData].sort(
        (a: any, b: any) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
      )[0];
    },

    calcTotalSizeBytes() {
      if (!this.backupsData || this.backupsData.length === 0) return 0;
      return this.backupsData.reduce((sum: number, b: any) => sum + (b.sizeBytes || 0), 0);
    },

    calcTotalSizeMb() {
      return (this.calcTotalSizeBytes() / 1048576).toFixed(2);
    },

    escapeHtml(str: string) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    },
  }));
});
