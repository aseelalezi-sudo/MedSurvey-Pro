import './dashboard/ajax-helpers';
import Alpine from 'alpinejs';
// @ts-expect-error: missing types for alpinejs collapse
import collapse from '@alpinejs/collapse';
import type ApexCharts from 'apexcharts';

if (import.meta.env.VITE_ENABLE_BROADCASTING === 'true') {
  void import('./echo');
}

const pageModules = import.meta.glob('./pages/*.ts');

type ApexChartsConstructor = typeof ApexCharts;
type LucideModule = typeof import('./icons');

const globalWindow = window as Window &
  typeof globalThis & {
    Alpine: typeof Alpine;
    ApexCharts?: ApexChartsConstructor;
    loadApexCharts: () => Promise<ApexChartsConstructor>;
    lucide: {
      createIcons: () => void;
    };
  };

let apexChartsPromise: Promise<ApexChartsConstructor> | null = null;
let lucidePromise: Promise<LucideModule> | null = null;

Alpine.plugin(collapse);
globalWindow.Alpine = Alpine;
globalWindow.loadApexCharts = () => {
  if (globalWindow.ApexCharts) {
    return Promise.resolve(globalWindow.ApexCharts);
  }

  apexChartsPromise ??= import('apexcharts').then((module) => {
    globalWindow.ApexCharts = module.default;
    return module.default;
  });

  return apexChartsPromise;
};
globalWindow.lucide = {
  createIcons: () => {
    lucidePromise ??= import('./icons');
    void lucidePromise.then(({ renderIcons }) => renderIcons());
  },
};

async function startPage() {
  const page = document.documentElement.dataset.dashboardPage;
  if (page) {
    await pageModules[`./pages/${page}.ts`]?.();
  }
  Alpine.start();
  globalWindow.lucide.createIcons();
}

void startPage();

type PredictiveActionPlan = {
  department?: string;
  drop?: string | number;
  keyDriver?: string;
};

function fillPredictiveActionPlan(plan: PredictiveActionPlan): void {
  const modal = document.getElementById('predictive-action-modal');
  if (!modal) {
    return;
  }

  const department = String(plan.department ?? '');
  const drop = String(plan.drop ?? '');
  const keyDriver = String(plan.keyDriver ?? '');

  modal.querySelectorAll<HTMLElement>('[data-predictive-plan-field="department"]').forEach((element) => {
    element.textContent = department;
  });

  modal.querySelectorAll<HTMLElement>('[data-predictive-plan-field="drop"]').forEach((element) => {
    element.textContent = drop;
  });

  modal.querySelectorAll<HTMLElement>('[data-predictive-plan-field="keyDriverWrapped"]').forEach((element) => {
    element.textContent = keyDriver ? `(${keyDriver})` : '';
  });

  modal.querySelectorAll<HTMLInputElement>('[data-predictive-plan-input="department"]').forEach((input) => {
    input.value = department;
  });
}

function openPredictiveActionModal(plan: PredictiveActionPlan): void {
  const modal = document.getElementById('predictive-action-modal');
  const panel = modal?.querySelector<HTMLElement>('[data-predictive-action-panel]');
  if (!modal) {
    return;
  }

  fillPredictiveActionPlan(plan);
  modal.removeAttribute('x-cloak');
  modal.setAttribute('aria-hidden', 'false');
  modal.style.display = 'flex';

  if (panel) {
    panel.style.display = 'block';
  }
}

function closePredictiveActionModal(): void {
  const modal = document.getElementById('predictive-action-modal');
  if (!modal) {
    return;
  }

  modal.setAttribute('aria-hidden', 'true');
  modal.style.display = 'none';
}

type PredictiveSharePayload = {
  title?: string;
  text?: string;
};

function copyTextFallback(text: string): boolean {
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', 'readonly');
  textarea.style.position = 'fixed';
  textarea.style.top = '-9999px';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();

  try {
    return document.execCommand('copy');
  } finally {
    textarea.remove();
  }
}

async function copyShareText(text: string): Promise<void> {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return;
  }

  if (!copyTextFallback(text)) {
    throw new Error('Unable to copy predictive alert text.');
  }
}

async function sharePredictiveAlert(button: HTMLElement): Promise<void> {
  const payloadJson = button.dataset.sharePayload;
  if (!payloadJson) {
    return;
  }

  const payload = JSON.parse(payloadJson) as PredictiveSharePayload;
  const title = String(payload.title ?? '');
  const text = String(payload.text ?? '');
  const copiedMessage = button.dataset.shareCopiedMessage ?? 'Alert copied';

  if (!text) {
    return;
  }

  try {
    if (navigator.share) {
      await navigator.share({ title, text });
      return;
    }
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      return;
    }
  }

  await copyShareText(text);
  window.alert(copiedMessage);
}

document.addEventListener(
  'click',
  (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const shareButton = target.closest<HTMLElement>('[data-predictive-share-button]');
    if (!shareButton) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    void sharePredictiveAlert(shareButton);
  },
  true,
);

document.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const actionButton = target.closest<HTMLElement>('[data-predictive-action-button]');
  if (actionButton) {
    const encodedPlan = actionButton.dataset.actionPlan;
    if (!encodedPlan) {
      return;
    }

    try {
      openPredictiveActionModal(JSON.parse(encodedPlan) as PredictiveActionPlan);
    } catch {
      return;
    }
  }

  if (target.closest('[data-predictive-action-close]') || target.id === 'predictive-action-modal') {
    closePredictiveActionModal();
  }
});

// Keep the service worker at the site root so the browser can offer app install.
// Browsers require a secure context (HTTPS/localhost) to register service workers.
if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
    void navigator.serviceWorker
      .register('/sw.js', { scope: '/', updateViaCache: 'none' })
      .then((registration) => {
        function offerUpdate(worker: ServiceWorker | null) {
          if (!worker || !navigator.serviceWorker.controller) return;
          if (confirm('A new update is available. Do you want to refresh the page to apply changes?')) {
            navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), {
              once: true,
            });
            worker.postMessage({ type: 'SKIP_WAITING' });
          }
        }
        offerUpdate(registration.waiting);
        registration.addEventListener('updatefound', () => {
          const worker = registration.installing;
          worker?.addEventListener('statechange', () => {
            if (worker.state === 'installed') offerUpdate(worker);
          });
        });
      })
      .catch(() => {
        // Browser policies may disable service workers.
      });
  });
}
