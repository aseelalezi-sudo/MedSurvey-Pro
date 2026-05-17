import { Router } from 'express';
import { prisma } from '../lib/prisma.js';
import { redis } from '../lib/redis.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import os from 'os';

const router = Router();

/**
 * GET /api/monitoring/health
 * Returns comprehensive system health metrics.
 * Protected: Super Admin and Admin.
 */
router.get('/health', authMiddleware, requireRole('super_admin', 'admin'), async (req, res) => {
  const start = Date.now();
  
  // 1. Database Health
  let dbStatus = 'healthy';
  let dbLatency = 0;
  try {
    const dbStart = Date.now();
    await prisma.$queryRaw`SELECT 1`;
    dbLatency = Date.now() - dbStart;
  } catch (err) {
    dbStatus = 'unhealthy';
  }

  // 2. Cache Health
  let cacheStatus = 'healthy';
  try {
    const redisStatus = (redis as any).status;
    if (redisStatus !== 'ready' && !redisStatus?.includes('fallback')) {
        // If it's the in-memory fallback, we consider it healthy but flagged
        cacheStatus = 'fallback';
    }
  } catch (err) {
    cacheStatus = 'unhealthy';
  }

  // 3. System Metrics
  const memUsage = process.memoryUsage();
  const uptime = process.uptime();

  res.json({
    status: dbStatus === 'healthy' ? 'ok' : 'error',
    timestamp: new Date().toISOString(),
    totalLatencyMs: Date.now() - start,
    services: {
      database: {
        status: dbStatus,
        latencyMs: dbLatency,
      },
      cache: {
        status: cacheStatus,
        type: (redis as any).status === 'ready' ? 'redis' : 'in-memory',
      },
    },
    system: {
      uptime: Math.round(uptime),
      memory: {
        heapUsedMb: Math.round(memUsage.heapUsed / 1024 / 1024),
        heapTotalMb: Math.round(memUsage.heapTotal / 1024 / 1024),
        rssMb: Math.round(memUsage.rss / 1024 / 1024),
      },
      os: {
        platform: process.platform,
        loadAvg: os.loadavg(),
        freeMemMb: Math.round(os.freemem() / 1024 / 1024),
      }
    }
  });
});

export default router;
