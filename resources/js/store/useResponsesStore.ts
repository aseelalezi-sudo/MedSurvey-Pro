import { create } from 'zustand';
import { DashboardStats, SurveyResponse } from '../types';
import { responsesAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('ResponsesStore');

interface ResponsesPagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

interface ResponsesState {
  // Dashboard data
  responses: SurveyResponse[];
  stats: DashboardStats | null;
  loadingDashboard: boolean;

  // Paginated Responses list (for ResponsesPage)
  responsesList: SurveyResponse[];
  loadingResponses: boolean;
  responsesMeta: { averageScore: number; filteredTotal: number } | null;
  responsesPagination: ResponsesPagination;

  // Actions
  loadDashboardData: (department?: string) => Promise<void>;
  loadResponses: (params?: Record<string, unknown>) => Promise<void>;
}

const defaultPagination: ResponsesPagination = {
  page: 1,
  limit: 50,
  total: 0,
  totalPages: 0
};

export const useResponsesStore = create<ResponsesState>((set) => ({
  // Dashboard states
  responses: [],
  stats: null,
  loadingDashboard: false,

  // Paginated list states
  responsesList: [],
  loadingResponses: false,
  responsesMeta: null,
  responsesPagination: { ...defaultPagination },

  loadDashboardData: async (department?: string) => {
    set({ loadingDashboard: true });
    try {
      const params: Record<string, string | boolean> = {};
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

  loadResponses: async (params) => {
    set({ loadingResponses: true });
    try {
      const res = await responsesAPI.getAll(params);
      set({
        responsesList: res.data as SurveyResponse[],
        responsesPagination: res.pagination as ResponsesPagination,
        responsesMeta: res.meta || null,
        loadingResponses: false
      });
    } catch (error) {
      logger.error('Failed to load paginated responses:', error);
      set({ loadingResponses: false });
    }
  }
}));
