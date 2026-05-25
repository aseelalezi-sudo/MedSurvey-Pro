import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { useErrorStore } from '../store/useErrorStore';
import { useThemeStore } from '../store/useThemeStore';

describe('useErrorStore', () => {
  beforeEach(() => {
    useErrorStore.getState().clearAllErrors();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should have initial state', () => {
    const state = useErrorStore.getState();
    expect(state.apiErrors).toEqual([]);
    expect(state.fatalError).toBeNull();
    expect(state.hasFatalError).toBe(false);
  });

  it('should add an api error', () => {
    useErrorStore.getState().addApiError('Network error', 500);
    const state = useErrorStore.getState();
    expect(state.apiErrors.length).toBe(1);
    expect(state.apiErrors[0].message).toBe('Network error');
    expect(state.apiErrors[0].status).toBe(500);
  });

  it('should not add duplicate errors', () => {
    useErrorStore.getState().addApiError('Network error', 500);
    useErrorStore.getState().addApiError('Network error', 500);
    const state = useErrorStore.getState();
    expect(state.apiErrors.length).toBe(1);
  });

  it('should dismiss error', () => {
    useErrorStore.getState().addApiError('Network error', 500);
    const id = useErrorStore.getState().apiErrors[0].id;
    useErrorStore.getState().dismissApiError(id);
    expect(useErrorStore.getState().apiErrors.length).toBe(0);
  });

  it('should auto-dismiss error after 6 seconds', () => {
    useErrorStore.getState().addApiError('Auto dismiss', 400);
    expect(useErrorStore.getState().apiErrors.length).toBe(1);
    
    vi.advanceTimersByTime(6000);
    
    expect(useErrorStore.getState().apiErrors.length).toBe(0);
  });

  it('should set fatal error', () => {
    const err = new Error('Fatal crash');
    useErrorStore.getState().setFatalError(err);
    expect(useErrorStore.getState().fatalError).toBe(err);
    expect(useErrorStore.getState().hasFatalError).toBe(true);
  });
});

describe('useThemeStore', () => {
  beforeEach(() => {
    let store: Record<string, string> = {};
    vi.stubGlobal('localStorage', {
      getItem: (key: string) => store[key] || null,
      setItem: (key: string, value: string) => { store[key] = value.toString() },
      clear: () => { store = {} },
    });
    localStorage.clear();
    if (typeof document !== 'undefined') {
      document.documentElement.classList.remove('dark');
    }
  });

  it('should toggle theme from light to dark', () => {
    useThemeStore.setState({ theme: 'light' });
    
    useThemeStore.getState().toggleTheme();
    
    expect(useThemeStore.getState().theme).toBe('dark');
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('should toggle theme from dark to light', () => {
    useThemeStore.setState({ theme: 'dark' });
    
    useThemeStore.getState().toggleTheme();
    
    expect(useThemeStore.getState().theme).toBe('light');
    expect(localStorage.getItem('theme')).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });
});
