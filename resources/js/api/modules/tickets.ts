import { request } from '../core';
import type { TicketUpdatePayload } from '../core';
import type { Ticket } from '../../types';

export const ticketsAPI = {
  getAll: (filters?: { status?: string; department?: string }) => {
    const params = new URLSearchParams();
    if (filters?.status) params.set('status', filters.status);
    if (filters?.department) params.set('department', filters.department);
    const qs = params.toString();
    return request<Ticket[]>(`/tickets${qs ? `?${qs}` : ''}`);
  },

  update: (id: string, data: TicketUpdatePayload) =>
    request<Ticket>(`/tickets/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(data),
    }),

  delete: (id: string) =>
    request<{ message: string }>(`/tickets/${id}`, {
      method: 'DELETE',
    }),
};
