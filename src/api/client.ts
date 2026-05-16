import { SurveyTemplate, SurveyResponse, Ticket, DashboardStats } from '../types';
import { User, AuditLog } from '../store/useAuthStore';
import { SystemSettings } from '../store/useSettingsStore';

const API_BASE = '/api';

// Token management (stored safely in-memory only)
let authToken: string | null = null;
let refreshingPromise: Promise<string | null> | null = null;

export function setToken(token: string | null) {
  authToken = token;
}

export function getToken(): string | null {
  return authToken;
}

async function refreshAccessToken(): Promise<string | null> {
  if (refreshingPromise) return refreshingPromise;
  
  refreshingPromise = (async () => {
    try {
      const response = await fetch(`${API_BASE}/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
      });
      if (!response.ok) throw new Error('Refresh failed');
      const data = await response.json();
      setToken(data.token);
      return data.token;
    } catch (e) {
      setToken(null);
      return null;
    } finally {
      refreshingPromise = null;
    }
  })();
  
  return refreshingPromise;
}

// Ticket update payload
export interface TicketUpdatePayload {
  status?: string;
  resolutionNotes?: string;
  assignedTo?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  pagination: {
    total: number;
    page: number;
    limit: number;
    totalPages: number;
  };
}

// Generic fetch wrapper with automatic HTTP-only cookie support & Global Error Event dispatch
async function request<T>(
  endpoint: string,
  options: RequestInit = {},
  isRetry = false
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> || {}),
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ error: 'حدث خطأ غير متوقع في الاتصال بالخادم' }));
    let errorMessage = error.error || `HTTP Error ${response.status}`;
    if (error.details && Array.isArray(error.details)) {
      errorMessage += ': ' + error.details.map((d: any) => `${d.path?.join('.')}: ${d.message}`).join(', ');
    }

    // Handle Token Expiration or Missing Token (e.g. after page reload) with Auto-Refresh
    if (response.status === 401 && (error.code === 'TOKEN_EXPIRED' || error.code === 'TOKEN_MISSING') && !isRetry) {
      const newToken = await refreshAccessToken();
      if (newToken) {
        return request<T>(endpoint, options, true);
      }
    }

    // Dispatch custom API Error Event globally for Global Error Handling UI Response
    // Don't dispatch for 401 auth checks on startup (/auth/me), token refresh, or tokens being refreshed
    if (!(response.status === 401 && (error.code === 'TOKEN_EXPIRED' || error.code === 'TOKEN_MISSING' || endpoint === '/auth/me' || endpoint === '/auth/refresh'))) {
      window.dispatchEvent(
        new CustomEvent('medsurvey-api-error', {
          detail: {
            message: errorMessage,
            status: response.status,
          },
        })
      );
    }

    if (response.status === 401 && !isRetry) {
      setToken(null);
      // For non-refreshable 401s (like invalid user or missing token), redirect to login
      // Do not redirect if already checking auth on startup (/auth/me) or logging in (/auth/login)
      // Also avoid redirect loops when already on the login page in HashRouter (#/login)
      if (error.code !== 'TOKEN_EXPIRED' && endpoint !== '/auth/me' && endpoint !== '/auth/login' && !window.location.hash.includes('/login') && !window.location.pathname.includes('/login')) {
        window.location.href = '#/login';
      }
    }

    throw new Error(errorMessage);
  }

  return response.json();
}

// ============ AUTH API ============
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

// ============ USERS API ============
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

  delete: (id: string) =>
    request<{ message: string }>(`/users/${id}`, { method: 'DELETE' }),

  toggle: (id: string) =>
    request<User>(`/users/${id}/toggle`, { method: 'PATCH' }),
};

// ============ SURVEYS API ============
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

// ============ RESPONSES API ============
export const responsesAPI = {
  getAll: (
    filters?: {
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
  ) => {
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

  getStats: (filters?: { startDate?: string; endDate?: string }) => {
    const params = new URLSearchParams();
    if (filters?.startDate) params.set('startDate', filters.startDate);
    if (filters?.endDate) params.set('endDate', filters.endDate);
    const qs = params.toString();
    return request<DashboardStats>(`/responses/stats${qs ? `?${qs}` : ''}`);
  },
};

// ============ TICKETS API ============
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
};

// ============ SETTINGS API ============
export const settingsAPI = {
  get: () =>
    request<SystemSettings>('/settings'),

  update: (data: Partial<SystemSettings>) =>
    request<SystemSettings>('/settings', {
      method: 'PUT',
      body: JSON.stringify(data),
    }),
};

// ============ AUDIT API ============
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

// ============ MONITORING API ============
export const monitoringAPI = {
  getHealth: () =>
    request<any>('/monitoring/health'),
};
