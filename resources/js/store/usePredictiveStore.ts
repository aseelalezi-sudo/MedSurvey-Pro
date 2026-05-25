import { create } from 'zustand';
import { responsesAPI } from '../api/client';
import type { PredictiveAlert, PredictiveStats } from '../types/predictive';
export type { PredictiveAlert, PredictiveStats } from '../types/predictive';

interface PredictiveState {
  /** Number of active (non-activated) early warning alerts */
  activeWarningCount: number;
  /** Full alert details for each department with a drop */
  alerts: PredictiveAlert[];
  /** Summary statistics */
  stats: PredictiveStats;
  /** Loading state */
  loading: boolean;
  /** Whether data has been fetched at least once */
  initialized: boolean;

  /**
   * Load predictive analysis from the server.
   * Accepts `activatedPlans` to exclude departments with approved plans from the warning count.
   */
  loadPredictiveData: (activatedPlans: string[]) => Promise<void>;
  /** Force a reload (e.g. after activating a plan) */
  reload: (activatedPlans: string[]) => Promise<void>;
}

// ============ Zustand Store ============

export const usePredictiveStore = create<PredictiveState>((set, get) => ({
  activeWarningCount: 0,
  alerts: [],
  stats: { totalDepts: 0, activeWarnings: 0, healthIndex: 100, totalResponsesAnalyzed: 0 },
  loading: false,
  initialized: false,

  loadPredictiveData: async (activatedPlans: string[]) => {
    // Skip if already loaded
    if (get().initialized) {
      // Just recompute the active warning count with updated activatedPlans
      const currentAlerts = get().alerts;
      const count = currentAlerts.filter(a => !activatedPlans.includes(a.department)).length;
      set({ activeWarningCount: count });
      return;
    }

    set({ loading: true });
    try {
      const { alerts, stats } = await responsesAPI.getPredictiveStats();
      const count = alerts.filter(a => !activatedPlans.includes(a.department)).length;
      set({
        alerts,
        stats,
        activeWarningCount: count,
        loading: false,
        initialized: true,
      });
    } catch {
      console.error('Failed to load predictive data');
      set({ loading: false, initialized: true });
    }
  },

  reload: async (activatedPlans: string[]) => {
    set({ initialized: false });
    await get().loadPredictiveData(activatedPlans);
  },
}));
