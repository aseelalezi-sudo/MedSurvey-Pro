import { SurveyTemplate, SurveyResponse, Ticket, DashboardStats } from '../types';
import { User, AuditLog } from '../store/useAuthStore';
import { SystemSettings } from '../store/useSettingsStore';

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api';
const API_CONNECTION_RETRY_DELAYS_MS = [300, 900, 1800];
const NETWORK_ERROR_MESSAGE = 'تعذر الوصول إلى الخادم. تأكد من تشغيل خدمة الـ API ثم أعد المحاولة.';
const PROXY_ERROR_MESSAGE = 'تعذر اتصال الواجهة بخدمة الـ API. تأكد من تشغيل الخادم الخلفي وإعادة تشغيل Vite بعد أي تغيير في المنفذ.';

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

function dispatchApiError(message: string, status?: number) {
  window.dispatchEvent(
    new CustomEvent('medsurvey-api-error', {
      detail: {
        message,
        status,
      },
    })
  );
}

function wait(ms: number) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function isProxyFailure(response: Response, rawText: string) {
  const normalizedText = rawText.toLowerCase();
  return (
    normalizedText.includes('http proxy error') ||
    normalizedText.includes('econnrefused') ||
    response.status === 500 ||
    response.status === 502 ||
    response.status === 503 ||
    response.status === 504
  );
}

// Generic fetch wrapper with automatic HTTP-only cookie support & Global Error Event dispatch
async function request<T>(
  endpoint: string,
  options: RequestInit = {},
  isRetry = false,
  connectionAttempt = 0
): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> || {}),
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  let response: Response;
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);
    try {
      response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers,
        credentials: 'include',
        signal: controller.signal,
      });
    } finally {
      clearTimeout(timeoutId);
    }
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      const retryDelay = API_CONNECTION_RETRY_DELAYS_MS[connectionAttempt];
      if (retryDelay !== undefined) {
        await wait(retryDelay);
        return request<T>(endpoint, options, isRetry, connectionAttempt + 1);
      }
      dispatchApiError('انتهت مهلة الاتصال بالخادم');
      throw new Error('انتهت مهلة الاتصال بالخادم');
    }
    const retryDelay = API_CONNECTION_RETRY_DELAYS_MS[connectionAttempt];
    if (retryDelay !== undefined) {
      await wait(retryDelay);
      return request<T>(endpoint, options, isRetry, connectionAttempt + 1);
    }
    dispatchApiError(NETWORK_ERROR_MESSAGE);
    throw new Error(NETWORK_ERROR_MESSAGE);
  }

  if (!response.ok) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const rawText = (await response.text().catch(() => '')).trim();
      const proxyFailure = isProxyFailure(response, rawText);
      const retryDelay = API_CONNECTION_RETRY_DELAYS_MS[connectionAttempt];
      if (proxyFailure && retryDelay !== undefined) {
        await wait(retryDelay);
        return request<T>(endpoint, options, isRetry, connectionAttempt + 1);
      }

      const errorMessage = proxyFailure ? PROXY_ERROR_MESSAGE : NETWORK_ERROR_MESSAGE;

      dispatchApiError(errorMessage, response.status);
      throw new Error(errorMessage);
    }
    const error = await response.json().catch(() => ({ error: 'حدث خطأ غير متوقع في الاتصال بالخادم' }));
    let errorMessage = error.error || `HTTP Error ${response.status}`;
    if (error.details && Array.isArray(error.details)) {
      errorMessage += ': ' + error.details.map((d: { path?: string[]; message?: string }) => `${d.path?.join('.')}: ${d.message}`).join(', ');
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
      dispatchApiError(errorMessage, response.status);
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

  changePassword: (id: string, password: string) =>
    request<User>(`/users/${id}/password`, {
      method: 'PATCH',
      body: JSON.stringify({ password }),
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

  getStats: (filters?: { department?: string; startDate?: string; endDate?: string }) => {
    const params = new URLSearchParams();
    if (filters?.department) params.set('department', filters.department);
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

export interface HealthData {
  status: string;
  timestamp: string;
  totalLatencyMs: number;
  services: {
    database: { status: string; latencyMs: number };
    cache: { status: string; type: string };
  };
  system: {
    uptime: number;
    memory: { heapUsedMb: number; heapTotalMb: number; rssMb: number };
    os: { platform: string; freeMemMb: number };
  };
}

// ============ MONITORING API ============
export const monitoringAPI = {
  getHealth: () =>
    request<HealthData>('/monitoring/health'),
};
