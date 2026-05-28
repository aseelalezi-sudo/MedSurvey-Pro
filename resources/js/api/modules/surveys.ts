import { request } from '../core';
import type { SurveyTemplate } from '../../types';

export const surveysAPI = {
  getAll: () =>
    request<SurveyTemplate[]>('/surveys'),

  getPublic: (tenantId?: string) =>
    request<SurveyTemplate[]>(`/surveys/public${tenantId ? `?tenantId=${tenantId}` : ''}`),

  create: (data: SurveyTemplate) =>
    request<SurveyTemplate>('/surveys', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  update: (id: string, data: Partial<SurveyTemplate>) =>
    request<SurveyTemplate>(`/surveys/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    }),

  delete: (id: string) =>
    request<{ message: string }>(`/surveys/${id}`, { method: 'DELETE' }),
};
