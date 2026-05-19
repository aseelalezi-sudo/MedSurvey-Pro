import { Router, Request, Response } from 'express';
import { Prisma, TicketStatus } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { authMiddleware } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { updateTicketSchema } from '../lib/validations.js';
import { writeAuditLog } from '../lib/auditLog.js';
import { redis } from '../lib/redis.js';

const logger = createLogger('TicketsRoute');
const router = Router();

router.use(authMiddleware);

const getTicketAuditCode = (id: string) => `#${id.slice(-8).toUpperCase()}`;

router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const where: Prisma.TicketWhereInput = {};

    if (req.user!.role === 'head_of_department' && req.user!.department) {
      where.department = req.user!.department;
    }

    if (req.query.status) where.status = req.query.status as TicketStatus;
    if (req.query.department && req.user!.role !== 'head_of_department') {
      where.department = req.query.department as string;
    }

    const tickets = await prisma.ticket.findMany({
      where,
      orderBy: { createdAt: 'desc' },
      take: 200,
    });

    res.json(tickets.map(t => ({
      ...t,
      createdAt: t.createdAt.toISOString(),
      resolvedAt: t.resolvedAt?.toISOString() || undefined,
    })));
  } catch (error) {
    logger.error('Get tickets error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.patch('/:id', validateRequest(updateTicketSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;
    const { status, resolutionNotes, assignedTo } = req.body;

    const updateData: Prisma.TicketUpdateInput = {};
    if (status !== undefined) updateData.status = status;
    if (resolutionNotes !== undefined) updateData.resolutionNotes = resolutionNotes;
    if (assignedTo !== undefined) updateData.assignedTo = assignedTo;
    if (status === 'resolved') updateData.resolvedAt = new Date();

    const ticket = await prisma.ticket.update({
      where: { id },
      data: updateData,
    });

    await writeAuditLog(req.user!.id, 'update_ticket', {
      messageKey: 'audit.details.update_ticket',
      params: { ticketCode: getTicketAuditCode(ticket.id), status: status || 'unchanged' },
    });

    // Invalidate dashboard stats cache reactively
    try {
      await redis.set('dashboard_stats_version', Date.now().toString());
    } catch (cacheErr) {
      logger.error('Failed to invalidate stats cache on ticket update:', cacheErr);
    }

    res.json({
      ...ticket,
      createdAt: ticket.createdAt.toISOString(),
      resolvedAt: ticket.resolvedAt?.toISOString() || undefined,
    });
  } catch (error) {
    logger.error('Update ticket error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
