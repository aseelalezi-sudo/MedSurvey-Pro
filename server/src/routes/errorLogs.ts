import { Router, Request, Response } from 'express';
import rateLimit from 'express-rate-limit';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { errorLogger, ErrorStatus } from '../lib/errorLogger.js';
import { z } from 'zod';
import { validateRequest } from '../middleware/validate.js';

const logger = createLogger('ErrorLogsRoute');
const router = Router();

const updateErrorLogSchema = z.object({
  status: z.enum(['new', 'in_progress', 'resolved']),
  resolutionNotes: z.string().optional(),
});

const clientErrorLogSchema = z.object({
  level: z.enum(['error', 'warn', 'info']).optional(),
  message: z.string().min(1).max(1000),
  stack: z.string().max(4000).optional(),
  source: z.string().max(200).optional(),
  metadata: z.record(z.unknown()).optional(),
});

const clientErrorLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 60,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Too many client error reports' },
});

router.post('/client', clientErrorLimiter, validateRequest(clientErrorLogSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const { level = 'error', message, stack, source, metadata } = req.body;
    await errorLogger.log({
      level,
      message,
      stack,
      source: source || 'client',
      metadata: metadata && typeof metadata === 'object' ? metadata : undefined,
    });
    res.json({ ok: true });
  } catch (error) {
    logger.error('Create client error log failed:', error);
    res.status(500).json({ error: 'Failed to log client error' });
  }
});

router.use(authMiddleware);

router.get('/', requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const result = await errorLogger.getLogs({
      level: req.query.level as string | undefined,
      status: req.query.status as string | undefined,
      source: req.query.source as string | undefined,
      search: req.query.search as string | undefined,
      startDate: req.query.startDate as string | undefined,
      endDate: req.query.endDate as string | undefined,
      page: req.query.page ? parseInt(req.query.page as string) : undefined,
      limit: req.query.limit ? parseInt(req.query.limit as string) : undefined,
    });
    res.json(result);
  } catch (error) {
    logger.error('Get error logs error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.get('/stats', requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const days = parseInt(req.query.days as string) || 7;
    const stats = await errorLogger.getStats(days);
    res.json(stats);
  } catch (error) {
    logger.error('Get error stats error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.delete('/', requireRole('super_admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const result = await errorLogger.clearAll();
    res.json({ ok: true, deleted: result.count });
  } catch (error) {
    logger.error('Clear error logs failed:', error);
    res.status(500).json({ error: 'Failed to clear error logs' });
  }
});

router.delete('/:id', requireRole('super_admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = Array.isArray(req.params.id) ? req.params.id[0] : req.params.id;
    if (!id) {
      res.status(400).json({ error: 'Error log id is required' });
      return;
    }
    await errorLogger.deleteOne(id);
    res.json({ ok: true });
  } catch (error) {
    logger.error('Delete error log failed:', error);
    res.status(500).json({ error: 'Failed to delete error log' });
  }
});

router.patch('/:id', requireRole('super_admin', 'admin'), validateRequest(updateErrorLogSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const { status, resolutionNotes } = req.body as { status: ErrorStatus; resolutionNotes?: string };
    const id = Array.isArray(req.params.id) ? req.params.id[0] : req.params.id;
    const updated = await errorLogger.updateStatus(id, status, resolutionNotes);
    res.json(updated);
  } catch (error) {
    logger.error('Update error log error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
