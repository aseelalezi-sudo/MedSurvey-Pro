import { create } from 'zustand';
import { DashboardStats, SurveyResponse } from '../types';
import { responsesAPI } from '../api/client';
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

      const [res, stats] = await Promise.all([
        responsesAPI.getAll(params),
        responsesAPI.getStats(department ? { department } : undefined),
      ]);
      const loadedResponses = res.data as SurveyResponse[];

      set({
        responses: loadedResponses,
        stats,
        loadingDashboard: false,
      });
    } catch (error) {
      logger.error('Failed to load dashboard data:', error);
      set({ loadingDashboard: false });
    }
  },
}));
