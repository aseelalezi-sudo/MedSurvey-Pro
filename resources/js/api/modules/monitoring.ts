import { request } from '../core';

export interface HealthData {
  status: string;
  timestamp: string;
  totalLatencyMs: number;
  services: {
    database: { status: string; latencyMs: number };
    cache: { status: string; type: string };
  };
  system: {
    uptime: number;
    memory: { heapUsedMb: number; heapTotalMb: number; rssMb: number };
    os: { platform: string; freeMemMb: number };
  };
}

export const monitoringAPI = {
  getHealth: () =>
    request<HealthData>('/monitoring/health'),
};
