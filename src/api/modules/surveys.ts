import { request } from '../core';
import type { SurveyTemplate } from '../../types';

export const surveysAPI = {
  getAll: (activeOnly = false) =>
    request<SurveyTemplate[]>(`/surveys${activeOnly ? '?active=true' : ''}`),

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
