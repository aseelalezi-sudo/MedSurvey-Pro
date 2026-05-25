import { request } from '../core';
import type { PaginatedResponse } from '../core';
import type { AuditLog } from '../../types/auth';

export interface AuditFilters {
  userId?: string;
  action?: string;
  startDate?: string;
  endDate?: string;
  search?: string;
  page?: number;
  limit?: number;
}

export interface AuditStats {
  actionStats: { action: string; count: number }[];
  trendData: { date: string; count: number }[];
  topUsers: { name: string; username: string; count: number }[];
}

export const auditAPI = {
  getAll: (filters?: AuditFilters) => {
    const params = new URLSearchParams();
    if (filters?.userId) params.set('userId', filters.userId);
    if (filters?.action) params.set('action', filters.action);
    if (filters?.startDate) params.set('startDate', filters.startDate);
    if (filters?.endDate) params.set('endDate', filters.endDate);
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', filters.page.toString());
    if (filters?.limit) params.set('limit', filters.limit.toString());
    const qs = params.toString();
    return request<PaginatedResponse<AuditLog>>(`/audit${qs ? `?${qs}` : ''}`);
  },

  getStats: (days = 7) =>
    request<AuditStats>(`/audit/stats?days=${days}`),

  recordEvent: (data: { action: string; messageKey?: string; params?: Record<string, unknown> }) =>
    request<{ ok: boolean }>('/audit/events', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
};
