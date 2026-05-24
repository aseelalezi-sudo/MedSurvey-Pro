import { Router, Request, Response } from 'express';
import rateLimit from 'express-rate-limit';
import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('AuditRoute');

const router = Router();
router.use(authMiddleware);

const clientAuditActions = new Set([
  'export_responses',
  'export_report',
  'print_report',
]);

const exportAuditActions = new Set(['export_responses', 'export_report']);
const exportAuditRoles = new Set(['super_admin', 'admin', 'unit_manager']);
const printAuditRoles = new Set(['super_admin', 'admin', 'unit_manager', 'head_of_department']);

const auditEventLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'تم تجاوز الحد المسموح لتسجيل الأحداث' },
});

function applyTenantScope(where: Prisma.AuditLogWhereInput, tenantId: string | null | undefined): Prisma.AuditLogWhereInput {
  if (!tenantId) return where;
  return {
    AND: [
      where,
      { user: { is: { tenantId } } },
    ],
  };
}

router.post('/events', auditEventLimiter, async (req: Request, res: Response): Promise<void> => {
  try {
    const { action, messageKey, params } = req.body || {};

    if (!action || typeof action !== 'string' || !clientAuditActions.has(action)) {
      res.status(400).json({ error: 'Invalid audit action' });
      return;
    }

    const userRole = req.user!.role;
    const isAllowed = exportAuditActions.has(action)
      ? exportAuditRoles.has(userRole)
      : printAuditRoles.has(userRole);

    if (!isAllowed) {
      res.status(403).json({ error: 'Forbidden' });
      return;
    }

    await writeAuditLog(req.user!.id, action, {
      messageKey: typeof messageKey === 'string' ? messageKey : `audit.details.${action}`,
      params: params && typeof params === 'object' ? params : undefined,
    });

    res.json({ ok: true });
  } catch (error) {
    logger.error('Create audit event error:', error);
    res.status(500).json({ error: 'Server error' });
  }
});

// GET /api/audit/stats — Aggregate stats for logs visualization
router.get('/stats', requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const days = parseInt(req.query.days as string) || 7;
    const dateLimit = new Date();
    dateLimit.setDate(dateLimit.getDate() - days);

    // Get logs count grouped by action
    const scopedWhere = applyTenantScope({ timestamp: { gte: dateLimit } }, req.user!.tenantId);

    const actionGroups = await prisma.auditLog.groupBy({
      by: ['action'],
      _count: { id: true },
      where: scopedWhere,
    });

    const actionStats = actionGroups.map(g => ({
      action: g.action,
      count: g._count.id,
    }));

    // Get recent logs to build a date-wise trend
    const recentLogs = await prisma.auditLog.findMany({
      where: scopedWhere,
      select: {
        timestamp: true,
      },
    });

    // Aggregate by date (D/M)
    const dailyVolume: Record<string, number> = {};
    for (let i = days - 1; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      const dateString = `${d.getDate()}/${d.getMonth() + 1}`;
      dailyVolume[dateString] = 0;
    }

    recentLogs.forEach(l => {
      const d = l.timestamp;
      const dateString = `${d.getDate()}/${d.getMonth() + 1}`;
      if (dailyVolume[dateString] !== undefined) {
        dailyVolume[dateString]++;
      }
    });

    const trendData = Object.entries(dailyVolume).map(([date, count]) => ({
      date,
      count,
    }));

    // Get top active users in audit log
    const topUsersRaw = await prisma.auditLog.groupBy({
      by: ['userId'],
      _count: { id: true },
      where: scopedWhere,
      orderBy: {
        _count: {
          id: 'desc',
        },
      },
      take: 5,
    });

    const userIds = topUsersRaw.map(u => u.userId);
    const users = userIds.length > 0
      ? await prisma.user.findMany({
          where: { id: { in: userIds } },
          select: { id: true, name: true, username: true },
        })
      : [];
    const userMap = new Map(users.map(u => [u.id, u]));
    const topUsers = topUsersRaw.map(u => ({
      name: userMap.get(u.userId)?.name || 'مستخدم غير معروف',
      username: userMap.get(u.userId)?.username || '',
      count: u._count.id,
    }));

    res.json({
      actionStats,
      trendData,
      topUsers,
    });
  } catch (error) {
    logger.error('Get audit stats error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/audit — Paginated and filterable audit logs
router.get('/', requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const page = parseInt(req.query.page as string) || 1;
    const limit = parseInt(req.query.limit as string) || 50;
    const skip = (page - 1) * limit;

    let where: Prisma.AuditLogWhereInput = {};

    if (req.query.userId) {
      where.userId = req.query.userId as string;
    }

    if (req.query.action) {
      where.action = req.query.action as string;
    }

    if (req.query.startDate || req.query.endDate) {
      where.timestamp = {};
      if (req.query.startDate) {
        where.timestamp.gte = new Date(req.query.startDate as string);
      }
      if (req.query.endDate) {
        const end = new Date(req.query.endDate as string);
        end.setHours(23, 59, 59, 999);
        where.timestamp.lte = end;
      }
    }

    if (req.query.search) {
      const search = req.query.search as string;
      where.OR = [
        { details: { contains: search } },
        { action: { contains: search } },
        {
          user: {
            OR: [
              { name: { contains: search } },
              { username: { contains: search } },
            ],
          },
        },
      ];
    }

    where = applyTenantScope(where, req.user!.tenantId);

    const [logs, total] = await prisma.$transaction([
      prisma.auditLog.findMany({
        where,
        orderBy: { timestamp: 'desc' },
        skip,
        take: limit,
        include: {
          user: {
            select: { id: true, name: true, username: true, role: true },
          },
        },
      }),
      prisma.auditLog.count({ where }),
    ]);

    res.json({
      data: logs.map(l => ({
        ...l,
        timestamp: l.timestamp.toISOString(),
      })),
      pagination: {
        total,
        page,
        limit,
        totalPages: Math.ceil(total / limit),
      },
    });
  } catch (error) {
    logger.error('Get audit logs error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
