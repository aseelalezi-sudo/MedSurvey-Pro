import { request } from '../core';

export interface BackupFile {
  filename: string;
  sizeBytes: number;
  sizeMb: number;
  createdAt: string;
  modifiedAt: string;
}

export interface BackupConfig {
  enabled: boolean;
  retentionDays: number;
  backupDir: string;
}

export interface BackupListResponse {
  backups: BackupFile[];
  config: BackupConfig;
}

export interface BackupVerification {
  valid: boolean;
  filename: string;
  sizeBytes: number;
  sizeMb: number;
  hasDatabaseSelection: boolean;
  databaseName: string | null;
  tableCount: number;
  hasData: boolean;
  estimatedRows: number;
  error: string | null;
  checkedAt: string;
}

export interface BackupCreateResponse {
  message: string;
  file: string;
  timestamp: string;
  verification?: BackupVerification;
}

export const backupsAPI = {
  list: () =>
    request<BackupListResponse>('/backups'),

  create: () =>
    request<BackupCreateResponse>('/backups', {
      method: 'POST',
    }),

  delete: (filename: string) =>
    request<{ message: string; filename: string }>(`/backups/${encodeURIComponent(filename)}`, {
      method: 'DELETE',
    }),

  verify: (filename: string) =>
    request<BackupVerification>(`/backups/${encodeURIComponent(filename)}/verify`),

  restore: (filename: string) =>
    request<{ message: string; filename: string }>(`/backups/${encodeURIComponent(filename)}/restore`, {
      method: 'POST',
    }),
};
