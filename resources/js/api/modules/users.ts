import { request } from '../core';
import type { User } from '../../types/auth';

export const usersAPI = {
  getAll: () =>
    request<User[]>('/users'),

  create: (data: Omit<User, 'id' | 'createdAt' | 'lastLogin' | 'isActive'>) =>
    request<User>('/users', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  update: (id: string, data: Partial<User>) =>
    request<User>(`/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    }),

  changePassword: (id: string, password: string, currentPassword?: string) =>
    request<User>(`/users/${id}/password`, {
      method: 'PATCH',
      body: JSON.stringify({ password, currentPassword }),
    }),

  delete: (id: string) =>
    request<{ message: string }>(`/users/${id}`, { method: 'DELETE' }),

  toggle: (id: string) =>
    request<User>(`/users/${id}/toggle`, { method: 'PATCH' }),
};
