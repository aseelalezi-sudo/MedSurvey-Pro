import { Prisma } from '@prisma/client';
import { prisma } from './prisma.js';
import { createLogger } from './logger.js';
import winston from 'winston';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const logsDir = path.resolve(__dirname, '../../logs');

const logger = createLogger('ErrorLogger');

// Add file transports to the shared Winston logger
import { logger as rootLogger } from './logger.js';
const isProduction = process.env.NODE_ENV === 'production';
rootLogger.add(new winston.transports.File({
  filename: path.join(logsDir, 'error.log'),
  level: 'error',
  maxsize: 5 * 1024 * 1024,
  maxFiles: 10,
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
}));
rootLogger.add(new winston.transports.File({
  filename: path.join(logsDir, 'combined.log'),
  maxsize: 10 * 1024 * 1024,
  maxFiles: 5,
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
}));

// Human-readable summary for quick diagnosis in Notepad
const summaryFormat = winston.format.printf((info: Record<string, unknown>) => {
  const ts = typeof info.timestamp === 'string' ? info.timestamp.replace('T', ' ').slice(0, 19) : new Date().toISOString().slice(0, 19).replace('T', ' ');
  const levelTag = String(info.level).toUpperCase().padEnd(5);
  const message = String(info.message || '');
  const source = typeof info.source === 'string' ? info.source : '';
  const src = source ? ` (${source})` : '';
  const uid = typeof info.userId === 'string' ? info.userId : '';
  const user = uid ? ` [user:${uid.slice(0, 8)}]` : '';
  let stackInfo = '';
  if (typeof info.stack === 'string') {
    const lines = info.stack.split('\n').filter(l => l.trim());
    if (lines.length > 1) {
      stackInfo = '\n       ↓ ' + lines.slice(1, 3).map(l => l.trim()).join('\n       ↓ ');
    }
  }
  return `[${ts}] ${levelTag} ${message}${src}${user}${stackInfo}`;
});

rootLogger.add(new winston.transports.File({
  filename: path.join(logsDir, 'error-summary.log'),
  maxsize: 10 * 1024 * 1024,
  maxFiles: 3,
  format: winston.format.combine(
    winston.format.timestamp(),
    summaryFormat
  ),
}));

export type ErrorLevel = 'error' | 'warn' | 'info';
export type ErrorStatus = 'new' | 'in_progress' | 'resolved';

interface LogErrorParams {
  level?: ErrorLevel;
  message: string;
  stack?: string;
  source?: string;
  metadata?: Record<string, unknown>;
  userId?: string;
}

function errorLevelToDb(level: string): ErrorLevel {
  if (level === 'warn' || level === 'info') return level;
  return 'error';
}

export const errorLogger = {
  async log(params: LogErrorParams): Promise<void> {
    const { level = 'error', message, stack, source, metadata, userId } = params;

    // Always log to file via Winston
    rootLogger.log(level, message, { source, metadata, stack, userId });

    // Deduplicate: if same message+source exists within 1 hour and still open, increment count
    try {
      const oneHourAgo = new Date(Date.now() - 3600000);
      const existing = await prisma.errorLog.findFirst({
        where: {
          message,
          source: source || null,
          createdAt: { gte: oneHourAgo },
          status: { in: ['new', 'in_progress'] },
        },
        orderBy: { createdAt: 'desc' },
      });

      if (existing) {
        await prisma.errorLog.update({
          where: { id: existing.id },
          data: { count: { increment: 1 } },
        });
        return;
      }

      await prisma.errorLog.create({
        data: {
          level: errorLevelToDb(level),
          message,
          stack: stack || null,
          source: source || null,
          metadata: (metadata || {}) as Prisma.InputJsonValue,
          status: 'new',
          userId: userId || null,
        },
      });
    } catch (dbErr) {
      logger.error('Failed to persist error log to database:', dbErr);
    }
  },

  async getLogs(filters: {
    level?: string;
    status?: string;
    source?: string;
    startDate?: string;
    endDate?: string;
    search?: string;
    page?: number;
    limit?: number;
  }) {
    const page = filters.page || 1;
    const limit = Math.min(filters.limit || 50, 200);
    const skip = (page - 1) * limit;

    const where: Prisma.ErrorLogWhereInput = {};
    const and: Prisma.ErrorLogWhereInput[] = [];

    if (filters.level && filters.level !== 'all') and.push({ level: filters.level });
    if (filters.status && filters.status !== 'all') and.push({ status: filters.status });
    if (filters.source) and.push({ source: { contains: filters.source } });
    if (filters.search) {
      and.push({
        OR: [
          { message: { contains: filters.search } },
          { source: { contains: filters.search } },
        ],
      });
    }
    if (filters.startDate || filters.endDate) {
      const dateFilter: Prisma.DateTimeFilter = {};
      if (filters.startDate) dateFilter.gte = new Date(filters.startDate);
      if (filters.endDate) {
        const end = new Date(filters.endDate);
        end.setHours(23, 59, 59, 999);
        dateFilter.lte = end;
      }
      and.push({ createdAt: dateFilter });
    }

    if (and.length > 0) where.AND = and;

    const [logs, total] = await prisma.$transaction([
      prisma.errorLog.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
      prisma.errorLog.count({ where }),
    ]);

    return {
      data: logs.map(l => ({
        ...l,
        createdAt: l.createdAt.toISOString(),
        resolvedAt: l.resolvedAt?.toISOString() || null,
      })),
      pagination: { total, page, limit, totalPages: Math.ceil(total / limit) },
    };
  },

  async updateStatus(id: string, status: ErrorStatus, resolutionNotes?: string) {
    const updateData: Prisma.ErrorLogUpdateInput = { status };
    if (resolutionNotes !== undefined) updateData.resolutionNotes = resolutionNotes;
    if (status === 'resolved') updateData.resolvedAt = new Date();
    return prisma.errorLog.update({ where: { id }, data: updateData });
  },

  async clearAll() {
    return prisma.errorLog.deleteMany({});
  },

  async deleteOne(id: string) {
    return prisma.errorLog.delete({ where: { id } });
  },

  async getStats(days = 7) {
    const dateLimit = new Date();
    dateLimit.setDate(dateLimit.getDate() - days);

    const levelGroups = await prisma.errorLog.groupBy({
      by: ['level'],
      _count: { id: true },
      where: { createdAt: { gte: dateLimit } },
    });

    const statusGroups = await prisma.errorLog.groupBy({
      by: ['status'],
      _count: { id: true },
      where: { createdAt: { gte: dateLimit } },
    });

    const topSources = await prisma.errorLog.groupBy({
      by: ['source'],
      _count: { id: true },
      where: {
        createdAt: { gte: dateLimit },
        source: { not: null },
      },
      orderBy: { _count: { id: 'desc' } },
      take: 10,
    });

    return {
      byLevel: levelGroups.map(g => ({ level: g.level, count: g._count.id })),
      byStatus: statusGroups.map(g => ({ status: g.status, count: g._count.id })),
      topSources: topSources.map(s => ({ source: s.source, count: s._count.id })),
    };
  },

  async cleanupOldResolved(daysOld = 90) {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - daysOld);
    await prisma.errorLog.deleteMany({
      where: { status: 'resolved', createdAt: { lt: cutoff } },
    });
  },
};
