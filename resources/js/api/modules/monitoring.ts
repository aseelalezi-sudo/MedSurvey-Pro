import { request } from '../core';

export interface HealthData {
  status: string;
  timestamp: string;
  totalLatencyMs: number;
  services: {
    database: { status: string; latencyMs: number | null; error?: string };
    cache: { status: string; type: string; error?: string };
  };
  system: {
    uptime: number | null;
    memory: { heapUsedMb: number; heapTotalMb: number | null; rssMb: number };
    os: { platform: string; freeMemMb: number | null };
  };
}

export const monitoringAPI = {
  getHealth: () =>
    request<HealthData>('/monitoring/health'),
};
