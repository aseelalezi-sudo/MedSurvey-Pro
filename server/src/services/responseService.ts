import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { redis } from '../lib/redis.js';
import { createLogger } from '../lib/logger.js';
import { ticketService } from './ticketService.js';

const SETTINGS_CACHE_KEY = 'settings:global';

const logger = createLogger('ResponseService');

interface DepartmentEntry {
  name: string;
  isActive: boolean;
}

interface SettingsData {
  departments?: DepartmentEntry[];
}

interface CreateResponseInput {
  surveyId: string;
  answers: Record<string, unknown>;
  patientInfo?: {
    name?: string;
    phone?: string;
    ageGroup?: string;
    gender?: string;
    visitType?: string;
  };
  department: string;
}

interface GetResponsesFilters {
  page?: number;
  limit?: number;
  order?: string;
  sortBy?: string;
  exportAll?: string;
  department?: string;
  search?: string;
  score?: string;
  dateFilter?: string;
  startDate?: string;
  endDate?: string;
  hasName?: string;
  hasPhone?: string;
  gender?: string;
  [key: string]: unknown;
}

interface AuthUser {
  id: string;
  role: string;
  department?: string | null;
  tenantId?: string | null;
}

class ResponseValidationError extends Error {
  statusCode = 400;
}

export const responseService = {
  /**
   * Creates a new survey response and performs side effects (normalization, ticketing, cache invalidation).
   */
  async createResponse(data: CreateResponseInput) {
    const { surveyId, answers, patientInfo, department } = data;

    // Validate department against configured active departments
    let settingsData: SettingsData | null = null;
    const settingsRaw = await redis.get(SETTINGS_CACHE_KEY);
    if (settingsRaw) {
      try {
        settingsData = JSON.parse(settingsRaw);
      } catch {
        // invalid cache
      }
    }
    if (!settingsData) {
      const settings = await prisma.settings.findFirst({ where: { id: 'global' } });
      if (settings) settingsData = settings.data as unknown as SettingsData;
    }
    const activeDepts: string[] = (settingsData?.departments || [])
      .filter((d: DepartmentEntry) => d.isActive)
      .map((d: DepartmentEntry) => d.name);
    if (activeDepts.length > 0 && !activeDepts.includes(department)) {
      throw new ResponseValidationError('القسم المحدد غير موجود في قائمة الأقسام النشطة');
    }

    const survey = await prisma.survey.findUnique({
      where: { id: surveyId },
      include: {
        sections: {
          include: { questions: true },
        },
      },
    });

    if (!survey || !survey.isActive) {
      throw new ResponseValidationError('الاستبيان غير موجود أو غير نشط');
    }

    const requiredQuestions = survey.sections
      .flatMap(section => section.questions)
      .filter(question => question.required);
    const missingRequired = requiredQuestions.filter(question => {
      const value = answers?.[question.id];
      return value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0);
    });

    if (missingRequired.length > 0) {
      throw new ResponseValidationError('يرجى الإجابة على جميع الأسئلة المطلوبة');
    }

    const overallScore = this.calculateOverallScore(survey.sections, answers);

    const response = await prisma.surveyResponse.create({
      data: {
        surveyId,
        answers: answers as Prisma.InputJsonValue,
        patientName: patientInfo?.name || null,
        patientPhone: patientInfo?.phone || null,
        ageGroup: patientInfo?.ageGroup || null,
        gender: patientInfo?.gender || null,
        visitType: patientInfo?.visitType || null,
        department,
        overallScore,
        tenantId: survey.tenantId,
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

  calculateOverallScore(sections: { questions: { id: string; type: string }[] }[], answers: Record<string, unknown>) {
    let totalScore = 0;
    let maxScore = 0;

    sections.forEach(section => {
      section.questions.forEach(question => {
        const value = answers?.[question.id];

        if (question.type === 'nps' && typeof value === 'number') {
          totalScore += Math.min(10, Math.max(0, value));
          maxScore += 10;
          return;
        }

        if (['stars', 'emoji', 'rating'].includes(question.type) && typeof value === 'number') {
          totalScore += Math.min(5, Math.max(0, value));
          maxScore += 5;
          return;
        }

        if (question.type === 'yes_no' && typeof value === 'boolean') {
          totalScore += value ? 5 : 0;
          maxScore += 5;
        }
      });
    });

    if (maxScore === 0) return 0;
    return Math.min(100, Math.max(0, Math.round((totalScore / maxScore) * 100)));
  },

  /**
   * Normalizes JSON answers into separate rows for advanced analytics.
   */
  async normalizeAnswers(responseId: string, surveyId: string, answers: Record<string, unknown>) {
    const surveyQuestions = await prisma.surveyQuestion.findMany({
      where: { section: { surveyId } },
      select: { id: true },
    });
    const validQuestionIds = new Set(surveyQuestions.map(q => q.id));

    const answerEntries = Object.entries(answers)
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
      await redis.set('dashboard_stats_version', Date.now().toString());
    } catch (err) {
      logger.error('Redis cache invalidation failed:', err);
    }
  },

  /**
   * Get paginated responses with filters.
   */
  async getResponses(filters: GetResponsesFilters, user: AuthUser) {
    const allowedSortFields = ['submittedAt', 'overallScore', 'department', 'patientName', 'patientPhone'];
    const sortByRaw = filters.sortBy;
    const sortBy = typeof sortByRaw === 'string' && allowedSortFields.includes(sortByRaw) ? sortByRaw : 'submittedAt';
    const page = Number(filters.page) || 1;
    const limit = Number(filters.limit) || 50;
    const order = filters.order === 'asc' ? 'asc' : 'desc';
    const exportAll = filters.exportAll;
    const where = this.buildWhereClause(filters, user);

    if (exportAll === 'true') {
      const EXPORT_LIMIT = 5000;
      
      // Prevent memory exhaustion and silent truncation by enforcing a hard limit
      const totalCount = await prisma.surveyResponse.count({ where });
      if (totalCount > EXPORT_LIMIT) {
        throw new ResponseValidationError(`حجم البيانات المطلوب تصديرها ضخم جداً (${totalCount} سجل). الحد الأقصى المسموح به للتصدير دفعة واحدة هو ${EXPORT_LIMIT} سجل. يرجى استخدام فلاتر التاريخ أو القسم لتضييق نطاق البحث.`);
      }

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
      prisma.surveyResponse.findMany({ where, orderBy: { [sortBy]: order }, skip, take: limit }),
      prisma.surveyResponse.count({ where }),
      prisma.surveyResponse.aggregate({ where, _avg: { overallScore: true } })
    ]);

    return {
      data: responses.map(r => this.transformResponse(r)),
      pagination: { total, page, limit, totalPages: Math.ceil(total / limit) },
      meta: { averageScore: Math.round(aggregate._avg.overallScore ?? 0), filteredTotal: total }
    };
  },

  buildWhereClause(filters: GetResponsesFilters, user: AuthUser) {
    const where: Prisma.SurveyResponseWhereInput = {
      AND: []
    };
    const and = where.AND as Prisma.SurveyResponseWhereInput[];

    if (user?.tenantId) {
      and.push({ tenantId: user.tenantId });
    }

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
      const dateRange: { gte?: Date; lte?: Date } = {};
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

  transformResponse(r: { id: string; surveyId: string; answers: unknown; patientName: string | null; patientPhone: string | null; ageGroup: string | null; gender: string | null; visitType: string | null; department: string; overallScore: number; submittedAt: Date }) {
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
