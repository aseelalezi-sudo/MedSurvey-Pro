import { request } from '../core';
import type { PaginatedResponse } from '../core';

export interface ErrorLogEntry {
  id: string;
  level: string;
  message: string;
  stack: string | null;
  source: string | null;
  metadata: unknown;
  status: string;
  resolutionNotes: string | null;
  count: number;
  createdAt: string;
  resolvedAt: string | null;
  userId: string | null;
}

export interface ErrorLogStats {
  byLevel: { level: string; count: number }[];
  byStatus: { status: string; count: number }[];
  topSources: { source: string | null; count: number }[];
}

export const errorLogsAPI = {
  getAll: (filters?: {
    level?: string;
    status?: string;
    source?: string;
    search?: string;
    startDate?: string;
    endDate?: string;
    page?: number;
    limit?: number;
  }) => {
    const params = new URLSearchParams();
    if (filters?.level) params.set('level', filters.level);
    if (filters?.status) params.set('status', filters.status);
    if (filters?.source) params.set('source', filters.source);
    if (filters?.search) params.set('search', filters.search);
    if (filters?.startDate) params.set('startDate', filters.startDate);
    if (filters?.endDate) params.set('endDate', filters.endDate);
    if (filters?.page) params.set('page', filters.page.toString());
    if (filters?.limit) params.set('limit', filters.limit.toString());
    const qs = params.toString();
    return request<PaginatedResponse<ErrorLogEntry>>(`/error-logs${qs ? `?${qs}` : ''}`);
  },

  getStats: (days = 7) =>
    request<ErrorLogStats>(`/error-logs/stats?days=${days}`),

  update: (id: string, data: { status: string; resolutionNotes?: string }) =>
    request<ErrorLogEntry>(`/error-logs/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    }),

  clearAll: () =>
    request<{ ok: boolean; deleted: number }>('/error-logs', {
      method: 'DELETE',
    }),

  deleteOne: (id: string) =>
    request<{ ok: boolean }>(`/error-logs/${id}`, {
      method: 'DELETE',
    }),

  logClientError: (data: { message: string; stack?: string; source?: string; metadata?: Record<string, unknown> }) =>
    request<{ ok: boolean }>('/error-logs/client', {
      method: 'POST',
      body: JSON.stringify({ ...data, level: 'error' }),
    }),
};
