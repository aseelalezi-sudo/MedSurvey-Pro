import { request } from '../core';
import type { PaginatedResponse } from '../core';
import type { SurveyResponse, DashboardStats } from '../../types';

export interface ResponseFilters {
  department?: string;
  startDate?: string;
  endDate?: string;
  search?: string;
  score?: string;
  dateFilter?: string;
  hasName?: string;
  hasPhone?: string;
  gender?: string;
  sortBy?: string;
  order?: string;
  page?: number;
  limit?: number;
  exportAll?: boolean;
  auditAction?: 'export_responses' | 'print_report';
  exportFormat?: string;
  exportTitle?: string;
}

export const responsesAPI = {
  getAll: (filters?: ResponseFilters) => {
    const params = new URLSearchParams();
    if (filters?.department) params.set('department', filters.department);
    if (filters?.startDate) params.set('startDate', filters.startDate);
    if (filters?.endDate) params.set('endDate', filters.endDate);
    if (filters?.search) params.set('search', filters.search);
    if (filters?.score) params.set('score', filters.score);
    if (filters?.dateFilter) params.set('dateFilter', filters.dateFilter);
    if (filters?.hasName) params.set('hasName', filters.hasName);
    if (filters?.hasPhone) params.set('hasPhone', filters.hasPhone);
    if (filters?.gender) params.set('gender', filters.gender);
    if (filters?.sortBy) params.set('sortBy', filters.sortBy);
    if (filters?.order) params.set('order', filters.order);
    if (filters?.page) params.set('page', filters.page.toString());
    if (filters?.limit) params.set('limit', filters.limit.toString());
    if (filters?.exportAll) params.set('exportAll', 'true');
    if (filters?.auditAction) params.set('auditAction', filters.auditAction);
    if (filters?.exportFormat) params.set('exportFormat', filters.exportFormat);
    if (filters?.exportTitle) params.set('exportTitle', filters.exportTitle);
    const qs = params.toString();
    return request<PaginatedResponse<SurveyResponse>>(`/responses${qs ? `?${qs}` : ''}`);
  },

  create: (data: Omit<SurveyResponse, 'id'>) =>
    request<SurveyResponse>('/responses', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  getById: (id: string) =>
    request<SurveyResponse>(`/responses/${id}`),

  getPredictiveStats: () =>
    request<{
      alerts: import('../../types/predictive').PredictiveAlert[];
      stats: import('../../types/predictive').PredictiveStats;
    }>('/responses/predictive'),

  getStats: (filters?: { department?: string; startDate?: string; endDate?: string }) => {
    const params = new URLSearchParams();
    if (filters?.department) params.set('department', filters.department);
    if (filters?.startDate) params.set('startDate', filters.startDate);
    if (filters?.endDate) params.set('endDate', filters.endDate);
    const qs = params.toString();
    return request<DashboardStats>(`/responses/stats${qs ? `?${qs}` : ''}`);
  },
};
