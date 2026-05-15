import { Router, Request, Response } from 'express';
import { prisma } from '../lib/prisma.js';
import { redis } from '../lib/redis.js';
import { authMiddleware } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { submitResponseSchema } from '../lib/validations.js';
import { responseService } from '../services/responseService.js';
import { statsService } from '../services/statsService.js';

const logger = createLogger('ResponsesRoute');
const router = Router();

// POST /api/responses — Public (for patients)
router.post('/', validateRequest(submitResponseSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const response = await responseService.createResponse(req.body);
    res.status(201).json(response);
  } catch (error) {
    logger.error('Create response error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/responses — Requires auth (for admin dashboard)
router.get('/', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    const result = await responseService.getResponses(req.query, req.user);
    res.json(result);
  } catch (error) {
    logger.error('Get responses error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/responses/stats — Dashboard statistics (database-optimized + Redis cached)
router.get('/stats', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    const cacheKey = `dashboard_stats:${req.user!.role}:${req.query.department || 'all'}:${req.query.startDate || 'none'}:${req.query.endDate || 'none'}`;
    
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

    const statsResult = await statsService.getDashboardStats(req.user!.role, req.user!.department, req.query);

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

    if (!response) {
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
