import { SurveyResponse, DashboardStats } from '../types';
import { analyticsService } from '../services/analyticsService';

/**
 * Legacy stats utility wrapper. Delegating calculation logic directly to analyticsService.
 */
export const calculateDashboardStats = (responses: SurveyResponse[]): DashboardStats => {
  return analyticsService.calculateDashboardStats(responses);
};
