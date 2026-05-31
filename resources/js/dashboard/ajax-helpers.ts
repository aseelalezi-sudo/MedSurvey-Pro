declare global {
  interface Window {
    MedSurveyAjax: typeof MedSurveyAjax;
    lucide?: { createIcons: () => void };
  }
}

interface PaginationOptions {
  containerId: string;
  gridId: string;
  paginationId: string;
  onLoadingChange: (loading: boolean) => void;
  onFallback?: (url: string) => void;
  onSuccess?: (href: string) => void;
}

const MedSurveyAjax = {
  fetchJson(url: string): Promise<Record<string, unknown>> {
    return fetch(url, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      return res.json();
    });
  },

  replaceHtml(targetId: string, html: string): void {
    const el = document.getElementById(targetId);
    if (el) {
      el.innerHTML = html;
    }
  },

  refreshIcons(): void {
    if (window.lucide) {
      window.lucide.createIcons();
    }
  },

  updateUrl(url: string): void {
    window.history.replaceState({}, '', url);
  },

  queryStringFromForm(form: HTMLFormElement): string {
    const fd = new FormData(form);
    const params = new URLSearchParams();
    fd.forEach((value, key) => {
      if (typeof value === 'string') {
        params.append(key, value);
      }
    });
    return params.toString();
  },

  bindAjaxPagination(options: PaginationOptions): void {
    const { containerId, gridId, paginationId, onLoadingChange, onFallback, onSuccess } = options;
    const container = document.getElementById(containerId);
    if (!container) return;

    container.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', async (event) => {
        event.preventDefault();
        const href = link.getAttribute('href') || link.href;

        try {
          onLoadingChange(true);

          const data = await MedSurveyAjax.fetchJson(href);

          MedSurveyAjax.replaceHtml(gridId, data.html as string);
          MedSurveyAjax.replaceHtml(paginationId, data.pagination as string);
          MedSurveyAjax.updateUrl(href);
          MedSurveyAjax.refreshIcons();

          // Re-bind pagination on the new pagination element
          MedSurveyAjax.bindAjaxPagination(options);

          if (onSuccess) {
            onSuccess(href);
          }
        } catch (e) {
          console.error('AJAX pagination failed, falling back to normal navigation', e);
          if (onFallback) {
            onFallback(href);
          } else {
            window.location.href = href;
          }
        } finally {
          onLoadingChange(false);
        }
      });
    });
  },
};

window.MedSurveyAjax = MedSurveyAjax;

export {};
