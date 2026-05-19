import { request } from '../core';
import type { User } from '../../types/auth';

export const authAPI = {
  login: (username: string, password: string) =>
    request<{ token: string; user: User }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    }),

  logout: () =>
    request<{ message: string }>('/auth/logout', { method: 'POST' }),

  me: () =>
    request<User>('/auth/me'),
};
