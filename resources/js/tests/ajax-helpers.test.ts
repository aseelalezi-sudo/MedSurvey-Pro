import { describe, it, expect, vi, beforeEach } from 'vitest';

// The helper module assigns window.MedSurveyAjax as a side effect on import
import '../dashboard/ajax-helpers';

describe('window.MedSurveyAjax', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  // ── 1. Object registration ──────────────────────────────────────────────
  it('registers window.MedSurveyAjax after importing the helper module', () => {
    expect(window.MedSurveyAjax).toBeDefined();
    expect(typeof window.MedSurveyAjax.fetchJson).toBe('function');
    expect(typeof window.MedSurveyAjax.replaceHtml).toBe('function');
    expect(typeof window.MedSurveyAjax.refreshIcons).toBe('function');
    expect(typeof window.MedSurveyAjax.updateUrl).toBe('function');
    expect(typeof window.MedSurveyAjax.queryStringFromForm).toBe('function');
    expect(typeof window.MedSurveyAjax.bindAjaxPagination).toBe('function');
  });

  // ── 2. fetchJson() ──────────────────────────────────────────────────────
  describe('fetchJson()', () => {
    it('sends correct headers and returns parsed JSON on success', async () => {
      const mockData = { html: '<div>OK</div>', pagination: '<nav>...</nav>' };
      const mockResponse = {
        ok: true,
        json: vi.fn().mockResolvedValue(mockData),
      };
      const fetchSpy = vi.spyOn(window, 'fetch').mockResolvedValue(mockResponse as unknown as Response);

      const result = await window.MedSurveyAjax.fetchJson('/test-url');

      expect(fetchSpy).toHaveBeenCalledWith('/test-url', {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      expect(result).toEqual(mockData);
    });

    it('throws an Error when response.ok is false', async () => {
      vi.spyOn(window, 'fetch').mockResolvedValue({
        ok: false,
        status: 404,
      } as unknown as Response);

      await expect(window.MedSurveyAjax.fetchJson('/missing')).rejects.toThrow('HTTP 404');
    });
  });

  // ── 3. replaceHtml() ────────────────────────────────────────────────────
  describe('replaceHtml()', () => {
    it('replaces innerHTML of an existing element', () => {
      const el = document.createElement('div');
      el.id = 'target';
      document.body.appendChild(el);

      window.MedSurveyAjax.replaceHtml('target', '<p>Hello</p>');

      expect(el.innerHTML).toBe('<p>Hello</p>');
    });

    it('does not throw when the element id does not exist', () => {
      expect(() => {
        window.MedSurveyAjax.replaceHtml('nonexistent', '<p>Hi</p>');
      }).not.toThrow();
    });
  });

  // ── 4. refreshIcons() ───────────────────────────────────────────────────
  describe('refreshIcons()', () => {
    it('calls window.lucide.createIcons() when lucide is present', () => {
      const createIcons = vi.fn();
      window.lucide = { createIcons };

      window.MedSurveyAjax.refreshIcons();

      expect(createIcons).toHaveBeenCalledTimes(1);
    });

    it('does not throw when window.lucide is missing', () => {
      delete (window as unknown as Record<string, unknown>).lucide;

      expect(() => {
        window.MedSurveyAjax.refreshIcons();
      }).not.toThrow();
    });
  });

  // ── 5. updateUrl() ──────────────────────────────────────────────────────
  describe('updateUrl()', () => {
    it('calls window.history.replaceState with the given url', () => {
      const replaceStateSpy = vi.spyOn(window.history, 'replaceState');

      window.MedSurveyAjax.updateUrl('/dashboard/responses?q=test');

      expect(replaceStateSpy).toHaveBeenCalledWith({}, '', '/dashboard/responses?q=test');
    });
  });

  // ── 6. queryStringFromForm() ────────────────────────────────────────────
  describe('queryStringFromForm()', () => {
    it('builds query string from form inputs', () => {
      const form = document.createElement('form');
      form.innerHTML = `
        <input name="q" value="Ali">
        <input name="score" value="good">
        <input name="empty" value="">
      `;
      document.body.appendChild(form);

      const qs = window.MedSurveyAjax.queryStringFromForm(form);

      expect(qs).toContain('q=Ali');
      expect(qs).toContain('score=good');
      // empty values are included by URLSearchParams
      expect(qs).toContain('empty=');
    });
  });

  // ── 7. bindAjaxPagination() – success path ──────────────────────────────
  describe('bindAjaxPagination()', () => {
    function setupPaginationContainer(): {
      container: HTMLElement;
      link: HTMLAnchorElement;
    } {
      const container = document.createElement('div');
      container.id = 'pagination';
      container.innerHTML = '<a href="/dashboard/responses?page=2">2</a>';
      document.body.appendChild(container);

      const link = container.querySelector('a')!;
      return { container, link };
    }

    it('intercepts click, calls onLoadingChange and onSuccess', async () => {
      const { link } = setupPaginationContainer();
      const grid = document.createElement('div');
      grid.id = 'grid';
      document.body.appendChild(grid);
      const pagEl = document.createElement('div');
      pagEl.id = 'pag';
      document.body.appendChild(pagEl);

      const mockData = {
        html: '<div>page 2</div>',
        pagination: '<nav>...</nav>',
      };

      vi.spyOn(window, 'fetch').mockResolvedValue({
        ok: true,
        json: vi.fn().mockResolvedValue(mockData),
      } as unknown as Response);

      const onLoading = vi.fn();
      const onSuccess = vi.fn();

      window.MedSurveyAjax.bindAjaxPagination({
        containerId: 'pagination',
        gridId: 'grid',
        paginationId: 'pag',
        onLoadingChange: onLoading,
        onSuccess,
      });

      link.dispatchEvent(new MouseEvent('click', { bubbles: true }));

      // Wait for onSuccess to be called (implies finally block ran too)
      await vi.waitFor(() => {
        expect(onSuccess).toHaveBeenCalled();
      });

      expect(onLoading).toHaveBeenCalledWith(true);
      expect(onLoading).toHaveBeenCalledWith(false);

      // Check HTML replacement
      expect(grid.innerHTML).toBe('<div>page 2</div>');
      expect(pagEl.innerHTML).toBe('<nav>...</nav>');
    });

    it('calls onFallback when fetch fails', async () => {
      const { link } = setupPaginationContainer();

      vi.spyOn(window, 'fetch').mockRejectedValue(new Error('Network error'));

      const onLoading = vi.fn();
      const onFallback = vi.fn();
      const onSuccess = vi.fn();

      window.MedSurveyAjax.bindAjaxPagination({
        containerId: 'pagination',
        gridId: 'grid',
        paginationId: 'pag',
        onLoadingChange: onLoading,
        onFallback,
        onSuccess,
      });

      link.dispatchEvent(new MouseEvent('click', { bubbles: true }));

      // Wait for onFallback to be called
      await vi.waitFor(() => {
        expect(onFallback).toHaveBeenCalledWith(expect.stringContaining('page=2'));
      });

      expect(onLoading).toHaveBeenCalledWith(true);
      expect(onLoading).toHaveBeenCalledWith(false);
      expect(onSuccess).not.toHaveBeenCalled();
    });

    it('falls back to window.location.href when onFallback is not provided', async () => {
      const { link } = setupPaginationContainer();

      vi.spyOn(window, 'fetch').mockRejectedValue(new Error('Network error'));

      const onLoading = vi.fn();

      // Use Object.defineProperty to intercept window.location.href assignment
      // jsdom does not allow vi.spyOn on location.href, so we replace the setter
      const originalLocationDescriptor = Object.getOwnPropertyDescriptor(window, 'location');
      let assignedHref = '';

      const mockUrlRef = { href: '' };
      Object.defineProperty(mockUrlRef, 'href', {
        set(val: string) {
          assignedHref = val;
        },
        configurable: true,
      });

      Object.defineProperty(window, 'location', {
        value: mockUrlRef,
        writable: true,
        configurable: true,
      });

      window.MedSurveyAjax.bindAjaxPagination({
        containerId: 'pagination',
        gridId: 'grid',
        paginationId: 'pag',
        onLoadingChange: onLoading,
      });

      link.dispatchEvent(new MouseEvent('click', { bubbles: true }));

      // Wait for loading to toggle back to false
      await vi.waitFor(() => {
        expect(onLoading).toHaveBeenCalledWith(true);
      });
      await vi.waitFor(() => {
        expect(onLoading).toHaveBeenCalledWith(false);
      });

      expect(assignedHref).toContain('page=2');

      // Restore original location
      if (originalLocationDescriptor?.value) {
        Object.defineProperty(window, 'location', {
          value: originalLocationDescriptor.value,
          writable: true,
          configurable: true,
        });
      }
    });
  });
});
