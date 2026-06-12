/* eslint-disable @typescript-eslint/no-explicit-any */
import Alpine from 'alpinejs';

interface BackupsOptions {
  backups: any[];
  config: any;
  texts: any;
  routes: {
    base: string;
    create: string;
    uploadRestore: string;
    scanExternal: string;
    verifyExternal: string;
    restoreExternal: string;
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
    restoringFilename: null as string | null,
    uploading: false,
    externalDir: '',
    scanning: false,
    scanAttempted: false,
    externalFiles: [] as any[],
    externalPage: 1,
    externalPageJump: '',
    externalPageSize: 25,
    externalVerifications: {} as Record<string, any>,
    verifyingExternalPath: null as string | null,
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

    openRestoreModal(filename: string) {
      this.confirmModal = { isOpen: true, type: 'restore', filename: filename, extraData: '' };
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
      } else if (type === 'restore') {
        this.executeRestore(filename);
      } else if (type === 'upload_restore') {
        this.executeUploadRestore(filename, this.confirmModal.extraData);
      } else if (type === 'external_restore') {
        this.executeExternalRestore(this.confirmModal.extraData, filename);
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

    executeRestore(filename: string) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch(`${options.routes.base}/${encodeURIComponent(filename)}/restore`, {
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
            this.refreshBackups();
          } else {
            this.error = data.message;
          }
        })
        .catch(() => {
          this.error = this.texts.restoreFailed;
        })
        .finally(() => {
          this.restoringFilename = null;
        });
    },

    handleUpload(event: Event) {
      const file = (event.target as HTMLInputElement).files?.[0];
      if (!file) return;
      if (!file.name.endsWith('.sql.gz')) {
        this.error = this.texts.invalidFileType;
        return;
      }
      this.uploading = true;
      this.error = '';
      this.successMessage = '';

      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const result = e.target?.result as string;
          const base64Content = result.includes(',') ? result.split(',')[1] : result;
          this.uploading = false;
          this.confirmModal = {
            isOpen: true,
            type: 'upload_restore',
            filename: file.name,
            extraData: base64Content,
          };
        } catch {
          this.error = this.texts.uploadProcessFailed;
          this.uploading = false;
        }
      };
      reader.readAsDataURL(file);
    },

    executeUploadRestore(filename: string, content: string) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch(options.routes.uploadRestore, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
        },
        body: JSON.stringify({ filename: filename, content: content }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            this.successMessage = this.texts.uploadRestoreSuccessPrefix + ' "' + filename + '"';
            setTimeout(() => window.location.reload(), 1500);
          } else {
            this.error = data.message || this.texts.uploadRestoreFailed;
          }
        })
        .catch(() => {
          this.error = this.texts.uploadRestoreFailed;
        })
        .finally(() => {
          this.restoringFilename = null;
        });
    },

    handleScanExternal() {
      if (!this.externalDir.trim()) {
        this.error = this.texts.pathRequired;
        return;
      }
      this.scanning = true;
      this.error = '';
      this.successMessage = '';
      this.scanAttempted = true;

      fetch(options.routes.scanExternal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
        },
        body: JSON.stringify({ path: this.externalDir }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.backups) {
            this.externalFiles = data.backups;
            this.externalVerifications = {};
            this.externalPage = 1;
          } else {
            this.error = data.message || this.texts.readFolderFailed;
            this.externalFiles = [];
          }
        })
        .catch(() => {
          this.error = this.texts.readFolderFailed;
          this.externalFiles = [];
        })
        .finally(() => {
          this.scanning = false;
        });
    },

    get externalTotalPages() {
      return Math.max(1, Math.ceil(this.externalFiles.length / this.externalPageSize));
    },

    get externalCurrentPage() {
      return Math.min(Math.max(1, this.externalPage), this.externalTotalPages);
    },

    get externalRangeStart() {
      if (this.externalFiles.length === 0) return 0;
      return (this.externalCurrentPage - 1) * this.externalPageSize + 1;
    },

    get externalRangeEnd() {
      return Math.min(this.externalCurrentPage * this.externalPageSize, this.externalFiles.length);
    },

    get paginatedExternalFiles() {
      const start = (this.externalCurrentPage - 1) * this.externalPageSize;
      return this.externalFiles.slice(start, start + this.externalPageSize);
    },

    setExternalPage(page: number | string) {
      this.externalPage = Math.min(Math.max(1, Number(page) || 1), this.externalTotalPages);
      this.externalPageJump = '';
      this.$nextTick(() => {
        if (typeof (window as any).lucide !== 'undefined') (window as any).lucide.createIcons();
      });
    },

    setExternalPageSize(size: number | string) {
      const nextSize = Number(size) || 25;
      this.externalPageSize = this.pageSizeOptions.includes(nextSize) ? nextSize : 25;
      this.externalPage = 1;
      this.externalPageJump = '';
      this.$nextTick(() => {
        if (typeof (window as any).lucide !== 'undefined') (window as any).lucide.createIcons();
      });
    },

    jumpExternalPage() {
      this.setExternalPage(this.externalPageJump);
    },

    handleVerifyExternal(fullPath: string) {
      this.verifyingExternalPath = fullPath;
      this.error = '';
      fetch(options.routes.verifyExternal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
        },
        body: JSON.stringify({ path: fullPath }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.valid !== undefined) {
            this.externalVerifications[fullPath] = data;
          } else {
            this.error = data.message || this.texts.verifyFailed;
          }
        })
        .catch((_err: any) => {
          this.error = this.texts.verifyFailed;
        })
        .finally(() => {
          this.verifyingExternalPath = null;
        });
    },

    handleRestoreExternal(fullPath: string, filename: string) {
      const verification = this.externalVerifications[fullPath];
      if (!verification?.valid) {
        this.error = this.texts.restoreNeedsValid;
        return;
      }
      this.confirmModal = {
        isOpen: true,
        type: 'external_restore',
        filename: filename,
        extraData: fullPath,
      };
    },

    executeExternalRestore(filepath: string, filename: string) {
      this.restoringFilename = filename;
      this.error = '';
      this.successMessage = '';
      fetch(options.routes.restoreExternal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCsrfToken(),
        },
        body: JSON.stringify({ path: filepath }),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            this.successMessage = this.texts.externalRestoreSuccessPrefix + ' "' + filename + '"';
            setTimeout(() => window.location.reload(), 1500);
          } else {
            this.error = data.message || this.texts.externalRestoreFailed;
          }
        })
        .catch((_err: any) => {
          this.error = this.texts.externalRestoreFailed;
        })
        .finally(() => {
          this.restoringFilename = null;
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
