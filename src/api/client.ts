// Core API utilities
export { setToken, getToken, request } from './core';
export type { TicketUpdatePayload, PaginatedResponse } from './core';

// API modules (separated by domain for maintainability)
export { authAPI } from './modules/auth';
export { usersAPI } from './modules/users';
export { surveysAPI } from './modules/surveys';
export { responsesAPI } from './modules/responses';
export { ticketsAPI } from './modules/tickets';
export { settingsAPI } from './modules/settings';
export { auditAPI } from './modules/audit';
export type { AuditFilters, AuditStats } from './modules/audit';
export { errorLogsAPI } from './modules/errorLogs';
export type { ErrorLogEntry, ErrorLogStats } from './modules/errorLogs';
export { monitoringAPI } from './modules/monitoring';
export type { HealthData } from './modules/monitoring';
export { backupsAPI } from './modules/backups';
export type { BackupFile, BackupConfig, BackupListResponse, BackupCreateResponse, BackupVerification } from './modules/backups';
