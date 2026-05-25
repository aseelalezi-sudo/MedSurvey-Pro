import { request } from '../core';
import type { SystemSettings } from '../../types/settings';

export interface UsageCheckResult {
  inUse: boolean;
  count: number;
}

export const settingsAPI = {
  get: () =>
    request<SystemSettings>('/settings'),

  update: (data: Partial<SystemSettings>) =>
    request<SystemSettings>('/settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    }),

  checkUsage: (type: 'department' | 'ageGroup' | 'visitType', value: string) =>
    request<UsageCheckResult>(`/settings/usage-check?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`),
};
