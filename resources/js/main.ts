import './index.css';
import './echo';
import './dashboard/ajax-helpers';
import '@fontsource/cairo/400.css';
import '@fontsource/cairo/500.css';
import '@fontsource/cairo/600.css';
import '@fontsource/cairo/700.css';
import '@fontsource/cairo/800.css';
import '@fontsource/cairo/900.css';
import Alpine from 'alpinejs';
import type ApexCharts from 'apexcharts';
import { registerSW } from 'virtual:pwa-register';

type ApexChartsConstructor = typeof ApexCharts;
type LucideModule = typeof import('lucide');

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
    lucidePromise ??= import('lucide');
    void lucidePromise.then(({ createIcons, icons }) => createIcons({ icons }));
  },
};

Alpine.start();
globalWindow.lucide.createIcons();

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

  modal
    .querySelectorAll<HTMLElement>('[data-predictive-plan-field="department"]')
    .forEach((element) => {
      element.textContent = department;
    });

  modal.querySelectorAll<HTMLElement>('[data-predictive-plan-field="drop"]').forEach((element) => {
    element.textContent = drop;
  });

  modal
    .querySelectorAll<HTMLElement>('[data-predictive-plan-field="keyDriverWrapped"]')
    .forEach((element) => {
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

// Register PWA service worker
const updateSW = registerSW({
  onNeedRefresh() {
    if (confirm('A new update is available. Do you want to refresh the page to apply changes?')) {
      updateSW(true);
    }
  },
  onOfflineReady() {
    // App is ready to work offline
  },
});
