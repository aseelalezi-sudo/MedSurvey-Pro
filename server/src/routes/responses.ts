import { Router, Request, Response } from 'express';
import rateLimit from 'express-rate-limit';
import { prisma } from '../lib/prisma.js';
import { redis } from '../lib/redis.js';
import { authMiddleware } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { submitResponseSchema } from '../lib/validations.js';
import { responseService } from '../services/responseService.js';
import { statsService } from '../services/statsService.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('ResponsesRoute');
const router = Router();

const submitResponseLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 30,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'تم تجاوز الحد المسموح لإرسال الاستبيانات. يرجى المحاولة لاحقاً.' },
});

// POST /api/responses — Public (for patients)
router.post('/', submitResponseLimiter, validateRequest(submitResponseSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const response = await responseService.createResponse(req.body);
    res.status(201).json(response);
  } catch (error) {
    logger.error('Create response error:', error);
    const statusCode = typeof error === 'object' && error !== null && 'statusCode' in error
      ? (error as { statusCode?: number }).statusCode
      : undefined;
    if (error instanceof Error && statusCode === 400) {
      res.status(400).json({ error: error.message });
      return;
    }
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/responses — Requires auth (for admin dashboard)
router.get('/', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    if (req.query.exportAll === 'true' && !['super_admin', 'admin', 'unit_manager', 'head_of_department'].includes(req.user!.role)) {
      res.status(403).json({ error: 'ليس لديك صلاحية لتصدير البيانات' });
      return;
    }

    const result = await responseService.getResponses(req.query, req.user);
    if (req.query.exportAll === 'true' && req.query.auditAction) {
      const auditAction = req.query.auditAction === 'print_report' ? 'print_report' : 'export_responses';
      await writeAuditLog(req.user!.id, auditAction, {
        messageKey: `audit.details.${auditAction}`,
        params: {
          format: typeof req.query.exportFormat === 'string' ? req.query.exportFormat : 'unknown',
          reportType: typeof req.query.exportTitle === 'string' ? req.query.exportTitle : 'responses',
          records: result.pagination.total,
          department: typeof req.query.department === 'string' ? req.query.department : 'all',
          dateRange: typeof req.query.dateFilter === 'string' ? req.query.dateFilter : 'all',
        },
      });
    }
    res.json(result);
  } catch (error) {
    logger.error('Get responses error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/responses/stats — Dashboard statistics (database-optimized + Redis cached)
router.get('/stats', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    const statsCacheVersion = await redis.get('dashboard_stats_version') || 'v1';
    const tenantScope = req.user!.tenantId || 'global';
    const cacheKey = `dashboard_stats:${statsCacheVersion}:${tenantScope}:${req.user!.role}:${req.query.department || 'all'}:${req.query.startDate || 'none'}:${req.query.endDate || 'none'}`;
    
    // Cache check
    try {
      const cachedData = await redis.get(cacheKey);
      if (cachedData) {
        res.json(JSON.parse(cachedData));
        return;
      }
    } catch (cacheErr) {
      logger.error('Cache read error (non-fatal):', cacheErr);
    }

    const statsResult = await statsService.getDashboardStats(req.user!.role, req.user!.department, req.query, req.user!.tenantId);

    // Save to cache (5-minute TTL)
    try {
      await redis.set(cacheKey, JSON.stringify(statsResult), 'EX', 300);
    } catch (cacheErr) {
      logger.error('Cache write error (non-fatal):', cacheErr);
    }

    res.json(statsResult);
  } catch (error) {
    logger.error('Get stats error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/responses/:id — Requires auth
router.get('/:id', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    const response = await prisma.surveyResponse.findUnique({
      where: { id: req.params.id as string },
    });

    if (!response || (req.user!.tenantId && response.tenantId !== req.user!.tenantId)) {
      res.status(404).json({ error: 'الاستجابة غير موجودة' });
      return;
    }

    res.json(responseService.transformResponse(response));
  } catch (error) {
    logger.error('Get response by ID error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
