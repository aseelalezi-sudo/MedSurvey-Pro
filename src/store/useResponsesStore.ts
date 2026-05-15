import { create } from 'zustand';
import { DashboardStats, SurveyResponse } from '../types';
import { responsesAPI } from '../api/client';
import { calculateDashboardStats } from '../data/statsUtils';
import { createLogger } from '../utils/logger';

const logger = createLogger('ResponsesStore');

interface ResponsesState {
  // Dashboard data
  responses: SurveyResponse[];
  stats: DashboardStats | null;
  loadingDashboard: boolean;

  // Actions
  loadDashboardData: (department?: string) => Promise<void>;
}

export const useResponsesStore = create<ResponsesState>((set) => ({
  responses: [],
  stats: null,
  loadingDashboard: false,

  loadDashboardData: async (department?: string) => {
    set({ loadingDashboard: true });
    try {
      const params: Record<string, string | boolean> = { exportAll: true };
      if (department) params.department = department;

      const res = await responsesAPI.getAll(params);
      const loadedResponses = res.data as SurveyResponse[];
      const computedStats = calculateDashboardStats(loadedResponses);

      set({
        responses: loadedResponses,
        stats: computedStats,
        loadingDashboard: false,
      });
    } catch (error) {
      logger.error('Failed to load dashboard data:', error);
      set({ loadingDashboard: false });
    }
  },
}));
