import { request } from '../core';
import type { SystemSettings } from '../../types/settings';

export const settingsAPI = {
  get: () =>
    request<SystemSettings>('/settings'),

  update: (data: Partial<SystemSettings>) =>
    request<SystemSettings>('/settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    }),
};
