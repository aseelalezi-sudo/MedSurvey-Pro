import { describe, it, expect, beforeEach, vi, Mock } from 'vitest';
import { request, setToken, getToken } from '../api/core';
import { useErrorStore } from '../store/useErrorStore';

global.fetch = vi.fn();

describe('apiCore', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    setToken(null);
    useErrorStore.getState().clearAllErrors();
  });

  it('should set and get token', () => {
    setToken('test-token');
    expect(getToken()).toBe('test-token');
  });

  it('should make a successful GET request', async () => {
    const mockData = { success: true };
    (global.fetch as Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => mockData,
      headers: new Headers({ 'content-type': 'application/json' }),
    });

    const result = await request('/test-endpoint');
    
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/test-endpoint'),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Content-Type': 'application/json'
        })
      })
    );
    expect(result).toEqual(mockData);
  });

  it('should attach auth token if available', async () => {
    setToken('my-secret-token');
    (global.fetch as Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => ({}),
      headers: new Headers({ 'content-type': 'application/json' }),
    });

    await request('/auth-endpoint');
    
    expect(global.fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Authorization': 'Bearer my-secret-token'
        })
      })
    );
  });

  it('should handle API errors and dispatch to error store', async () => {
    const errorMessage = 'Resource not found';
    (global.fetch as Mock).mockResolvedValueOnce({
      ok: false,
      status: 404,
      json: async () => ({ error: errorMessage }),
      headers: new Headers({ 'content-type': 'application/json' }),
    });

    await expect(request('/not-found')).rejects.toThrow(errorMessage);
    
    const errors = useErrorStore.getState().apiErrors;
    expect(errors.length).toBe(1);
    expect(errors[0].message).toBe(errorMessage);
    expect(errors[0].status).toBe(404);
  });

  it('should surface Laravel validation messages from 422 responses', async () => {
    const validationMessage = 'Current password is incorrect';
    (global.fetch as Mock).mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({
        message: 'The given data was invalid.',
        errors: {
          currentPassword: [validationMessage],
        },
      }),
      headers: new Headers({ 'content-type': 'application/json' }),
    });

    await expect(request('/users/user-1/password', { method: 'PATCH' })).rejects.toThrow(validationMessage);

    const errors = useErrorStore.getState().apiErrors;
    expect(errors.length).toBe(1);
    expect(errors[0].message).toBe(validationMessage);
    expect(errors[0].status).toBe(422);
  });
});
