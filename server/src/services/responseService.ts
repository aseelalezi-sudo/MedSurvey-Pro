import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { redis } from '../lib/redis.js';
import { createLogger } from '../lib/logger.js';
import { ticketService } from './ticketService.js';

const logger = createLogger('ResponseService');

export const responseService = {
  /**
   * Creates a new survey response and performs side effects (normalization, ticketing, cache invalidation).
   */
  async createResponse(data: any) {
    const { surveyId, answers, patientInfo, department, overallScore } = data;

    const response = await prisma.surveyResponse.create({
      data: {
        surveyId,
        answers,
        patientName: patientInfo?.name || null,
        patientPhone: patientInfo?.phone || null,
        ageGroup: patientInfo?.ageGroup || null,
        gender: patientInfo?.gender || null,
        visitType: patientInfo?.visitType || null,
        department,
        overallScore: overallScore || 0,
      },
    });

    // Side Effect 1: Normalize answers
    this.normalizeAnswers(response.id, surveyId, answers).catch(err => 
      logger.error('Normalization error (non-fatal):', err)
    );

    // Side Effect 2: Auto-ticketing for low scores
    if (overallScore < 50) {
      ticketService.createAutoTicket(response.id, overallScore, department, patientInfo).catch(err =>
        logger.error('Auto-ticketing error (non-fatal):', err)
      );
    }

    // Side Effect 3: Cache Invalidation
    this.invalidateStatsCache().catch(err => 
      logger.error('Cache invalidation error (non-fatal):', err)
    );

    return this.transformResponse(response);
  },

  /**
   * Normalizes JSON answers into separate rows for advanced analytics.
   */
  async normalizeAnswers(responseId: string, surveyId: string, answers: any) {
    const surveyQuestions = await prisma.surveyQuestion.findMany({
      where: { section: { surveyId } },
      select: { id: true },
    });
    const validQuestionIds = new Set(surveyQuestions.map(q => q.id));

    const answersObj = answers as Record<string, any>;
    const answerEntries = Object.entries(answersObj)
      .filter(([questionId]) => validQuestionIds.has(questionId))
      .map(([questionId, value]) => ({
        responseId,
        questionId,
        value: typeof value === 'object' ? JSON.stringify(value) : String(value),
      }));

    if (answerEntries.length > 0) {
      await prisma.surveyAnswer.createMany({
        data: answerEntries,
        skipDuplicates: true,
      });
    }
  },

  async invalidateStatsCache() {
    try {
      const cacheKeys = await (redis as any).keys('dashboard_stats:*');
      if (cacheKeys.length > 0) {
        await redis.del(...cacheKeys);
      }
    } catch (err) {
      logger.error('Redis cache invalidation failed:', err);
    }
  },

  /**
   * Get paginated responses with filters.
   */
  async getResponses(filters: any, user: any) {
    const { page = 1, limit = 50, sortBy = 'submittedAt', order = 'desc', exportAll } = filters;
    const where = this.buildWhereClause(filters, user);

    if (exportAll === 'true') {
      const responses = await prisma.surveyResponse.findMany({
        where,
        orderBy: { [sortBy]: order },
      });
      return {
        data: responses.map(r => this.transformResponse(r)),
        pagination: { total: responses.length, page: 1, limit: responses.length, totalPages: 1 }
      };
    }

    const skip = (page - 1) * limit;
    const [responses, total, aggregate] = await prisma.$transaction([
      prisma.surveyResponse.findMany({ where, orderBy: { [sortBy]: order }, skip, take: Number(limit) }),
      prisma.surveyResponse.count({ where }),
      prisma.surveyResponse.aggregate({ where, _avg: { overallScore: true } })
    ]);

    return {
      data: responses.map(r => this.transformResponse(r)),
      pagination: { total, page: Number(page), limit: Number(limit), totalPages: Math.ceil(total / Number(limit)) },
      meta: { averageScore: Math.round(aggregate._avg.overallScore ?? 0), filteredTotal: total }
    };
  },

  buildWhereClause(filters: any, user: any) {
    const where: Prisma.SurveyResponseWhereInput = {
      AND: []
    };
    const and = where.AND as any[];

    // Auth-based filtering
    if (user.role === 'head_of_department' && user.department) {
      and.push({ department: user.department });
    } else if (filters.department && filters.department !== 'all') {
      and.push({ department: filters.department });
    }

    // Search
    if (filters.search) {
      and.push({
        OR: [
          { department: { contains: filters.search } },
          { patientName: { contains: filters.search } },
          { patientPhone: { contains: filters.search } }
        ]
      });
    }

    // Score Filter (New)
    if (filters.score && filters.score !== 'all') {
      if (filters.score === 'excellent') and.push({ overallScore: { gte: 85 } });
      else if (filters.score === 'good') and.push({ overallScore: { gte: 70, lt: 85 } });
      else if (filters.score === 'average') and.push({ overallScore: { gte: 50, lt: 70 } });
      else if (filters.score === 'poor') and.push({ overallScore: { lt: 50 } });
    }

    // Gender Filter (New)
    if (filters.gender && filters.gender !== 'all') {
      and.push({ gender: filters.gender });
    }

    // Identity Filters
    if (filters.hasName === 'true') {
      and.push({ patientName: { not: null }, NOT: { patientName: '' } });
    }
    if (filters.hasPhone === 'true') {
      and.push({ patientPhone: { not: null }, NOT: { patientPhone: '' } });
    }

    // Date Filter
    if (filters.dateFilter && filters.dateFilter !== 'all') {
      const now = new Date();
      const dateRange: any = {};
      if (filters.dateFilter === 'today') {
        dateRange.gte = new Date(now.setHours(0, 0, 0, 0));
      } else if (filters.dateFilter === 'week') {
        dateRange.gte = new Date(now.setDate(now.getDate() - 7));
      } else if (filters.dateFilter === 'month') {
        dateRange.gte = new Date(now.setDate(now.getDate() - 30));
      } else if (filters.dateFilter === 'custom') {
        if (filters.startDate) dateRange.gte = new Date(filters.startDate);
        if (filters.endDate) {
          const end = new Date(filters.endDate);
          end.setHours(23, 59, 59, 999);
          dateRange.lte = end;
        }
      }
      and.push({ submittedAt: dateRange });
    }

    // Cleanup if no conditions added
    if (and.length === 0) delete where.AND;

    return where;
  },

  transformResponse(r: any) {
    return {
      id: r.id,
      surveyId: r.surveyId,
      answers: r.answers,
      patientInfo: {
        name: r.patientName || '',
        phone: r.patientPhone || '',
        ageGroup: r.ageGroup || '',
        gender: r.gender || '',
        visitType: r.visitType || '',
        department: r.department,
      },
      submittedAt: r.submittedAt.toISOString(),
      department: r.department,
      overallScore: r.overallScore,
    };
  }
};
