import { useErrorStore } from '../store/useErrorStore';

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api';
const API_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS) || 30000;
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
    } catch {
      setToken(null);
      return null;
    } finally {
      refreshingPromise = null;
    }
  })();

  return refreshingPromise;
}

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
  meta?: {
    averageScore: number;
    filteredTotal: number;
  };
}

function dispatchApiError(message: string, status?: number) {
  useErrorStore.getState().addApiError(message, status);
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

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

export async function request<T>(
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

  const method = (options.method || 'GET').toUpperCase();
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    const csrfToken = getCookie('medsurvey_csrf');
    if (csrfToken) {
      headers['x-csrf-token'] = csrfToken;
    }
  }

  let response: Response;
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_TIMEOUT_MS);
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

    if (response.status === 401 && (error.code === 'TOKEN_EXPIRED' || error.code === 'TOKEN_MISSING') && !isRetry) {
      const newToken = await refreshAccessToken();
      if (newToken) {
        return request<T>(endpoint, options, true);
      }
    }

    if (!(response.status === 401 && (error.code === 'TOKEN_EXPIRED' || error.code === 'TOKEN_MISSING' || endpoint === '/auth/me' || endpoint === '/auth/refresh'))) {
      dispatchApiError(errorMessage, response.status);
    }

    if (response.status === 401 && !isRetry) {
      setToken(null);
      if (error.code !== 'TOKEN_EXPIRED' && endpoint !== '/auth/me' && endpoint !== '/auth/login' && !window.location.hash.includes('/login') && !window.location.pathname.includes('/login')) {
        window.location.href = '#/login';
      }
    }

    throw new Error(errorMessage);
  }

  return response.json();
}
