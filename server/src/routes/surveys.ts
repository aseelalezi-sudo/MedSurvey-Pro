import { Router, Request, Response } from 'express';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { createSurveySchema, updateSurveySchema } from '../lib/validations.js';
import { redis } from '../lib/redis.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('SurveysRoute');
import type { Survey, SurveySection, SurveyQuestion, Prisma } from '@prisma/client';

/** Input shape for a question when creating/updating a survey */
interface QuestionInput {
  id?: string;
  type?: string;
  title?: string;
  description?: string | null;
  required?: boolean;
  category?: string;
  options?: unknown;
  followUp?: unknown;
}

/** Input shape for a section when creating/updating a survey */
interface SectionInput {
  id?: string;
  title?: string;
  description?: string;
  icon?: string;
  questions?: QuestionInput[];
}

/** Prisma survey model with its nested relations included */
type SurveyWithSections = Survey & {
  sections: (SurveySection & { questions: SurveyQuestion[] })[];
};

const router = Router();

function resolvePublicTenantId(req: Request, res: Response): string | null | undefined {
  const configuredTenantId = process.env.PUBLIC_TENANT_ID?.trim() || null;
  const requestedTenantId = typeof req.query.tenantId === 'string' ? req.query.tenantId.trim() : null;
  const allowQueryTenant = process.env.ALLOW_PUBLIC_TENANT_QUERY === 'true' || process.env.NODE_ENV !== 'production';

  if (configuredTenantId) {
    if (requestedTenantId && requestedTenantId !== configuredTenantId) {
      res.status(404).json({ error: 'الاستبيان غير موجود' });
      return undefined;
    }
    return configuredTenantId;
  }

  if (requestedTenantId && !allowQueryTenant) {
    res.status(400).json({ error: 'تحديد نطاق الاستبيان غير مسموح من الطلب العام' });
    return undefined;
  }

  return requestedTenantId || null;
}

function parseJsonSafe<T>(raw: string | null, fallback: T): T {
  if (!raw) return fallback;
  try { return JSON.parse(raw); } catch { return fallback; }
}

// GET /api/surveys — Public (for patients) - returns active surveys
router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const activeOnly = req.query.active === 'true';
    const tenantId = resolvePublicTenantId(req, res);
    if (tenantId === undefined) return;
    const surveysCacheVersion = await redis.get('surveys_cache_version') || 'v1';
    const cacheKey = `surveys:${surveysCacheVersion}:${tenantId || 'global'}:${activeOnly ? 'active' : 'all'}`;

    const cached = await redis.get(cacheKey);
    if (cached) {
      logger.info(`Serving surveys from cache: ${cacheKey}`);
      res.json(parseJsonSafe(cached, []));
      return;
    }

    const where: Prisma.SurveyWhereInput = {};
    if (activeOnly) where.isActive = true;
    if (tenantId) where.tenantId = tenantId;

    const surveys = await prisma.survey.findMany({
      where: Object.keys(where).length > 0 ? where : undefined,
      include: {
        sections: {
          orderBy: { sortOrder: 'asc' },
          include: {
            questions: {
              orderBy: { sortOrder: 'asc' },
            },
          },
        },
      },
      orderBy: { createdAt: 'desc' },
    });

    // Transform to match frontend SurveyTemplate shape
    const transformed = surveys.map(survey => ({
      id: survey.id,
      title: survey.title,
      description: survey.description,
      isActive: survey.isActive,
      requireName: survey.requireName,
      requirePhone: survey.requirePhone,
      assignedDepartments: survey.assignedDepartments as string[] | undefined,
      tips: survey.tips as string[] | undefined,
      createdAt: survey.createdAt.toISOString(),
      sections: survey.sections.map(section => ({
        id: section.id,
        title: section.title,
        description: section.description,
        icon: section.icon,
        questions: section.questions.map(q => ({
          id: q.id,
          type: q.type,
          title: q.title,
          description: q.description,
          required: q.required,
          category: q.category,
          options: q.options,
          followUp: q.followUp,
        })),
      })),
    }));

    // Cache TTL: 30 min (cache is invalidated via surveys_cache_version on write ops)
    await redis.set(cacheKey, JSON.stringify(transformed), 'EX', 1800);
    res.json(transformed);
  } catch (error) {
    logger.error('Get surveys error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// POST /api/surveys
router.post('/', authMiddleware, requireRole('super_admin', 'admin'), validateRequest(createSurveySchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const { title, description, isActive, requireName, requirePhone, assignedDepartments, tips, sections } = req.body;

    if (!title) {
      res.status(400).json({ error: 'يرجى إدخال عنوان الاستبيان' });
      return;
    }

    const survey = await prisma.survey.create({
      data: {
        title,
        description: description || '',
        isActive: isActive ?? true,
        requireName: requireName ?? false,
        requirePhone: requirePhone ?? false,
        assignedDepartments: assignedDepartments || null,
        tips: tips || null,
        tenantId: req.user!.tenantId || null,
        sections: {
          create: (sections || []).map((section: SectionInput, si: number) => ({
            title: section.title || '',
            description: section.description || '',
            icon: section.icon || 'clipboard-check',
            sortOrder: si,
            questions: {
              create: (section.questions || []).map((q: QuestionInput, qi: number) => ({
                type: q.type || 'stars',
                title: q.title || '',
                description: q.description || null,
                required: q.required ?? false,
                category: q.category || '',
                options: q.options || null,
                followUp: q.followUp || null,
                sortOrder: qi,
              })),
            },
          })),
        },
      },
      include: {
        sections: {
          include: { questions: true },
          orderBy: { sortOrder: 'asc' },
        },
      },
    });

    await redis.set('surveys_cache_version', Date.now().toString());
    try {
      await redis.set('dashboard_stats_version', Date.now().toString());
    } catch (cacheErr) {
      logger.error('Failed to invalidate stats cache on survey create:', cacheErr);
    }
    await writeAuditLog(req.user!.id, 'create_survey', {
      messageKey: 'audit.details.create_survey',
      params: { title: survey.title, id: survey.id },
    });
    res.status(201).json(transformSurvey(survey));
  } catch (error) {
    logger.error('Create survey error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// PUT /api/surveys/:id
router.put('/:id', authMiddleware, requireRole('super_admin', 'admin'), validateRequest(updateSurveySchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;
    const { title, description, isActive, requireName, requirePhone, assignedDepartments, tips, sections } = req.body;

    const existingSurvey = await prisma.survey.findUnique({
      where: { id },
      select: { tenantId: true },
    });
    if (!existingSurvey || (req.user!.tenantId && existingSurvey.tenantId !== req.user!.tenantId)) {
      res.status(404).json({ error: 'الاستبيان غير موجود' });
      return;
    }

    // Transaction to prevent data loss on partial failure
    const survey = await prisma.$transaction(async (tx: Prisma.TransactionClient) => {
      // Find existing sections that have collected answers (must NOT be deleted)
      const existingSections = await tx.surveySection.findMany({
        where: { surveyId: id },
        include: { questions: { include: { surveyAnswers: { take: 1 } } } },
      });
      const protectedSectionIds = new Set(
        existingSections
          .filter(s => s.questions.some(q => q.surveyAnswers.length > 0))
          .map(s => s.id)
      );

      // Delete only sections & questions that have no answers
      for (const section of existingSections) {
        if (!protectedSectionIds.has(section.id)) {
          await tx.surveyQuestion.deleteMany({ where: { sectionId: section.id } });
          await tx.surveySection.delete({ where: { id: section.id } });
        }
      }

      // Create/update survey with new sections (protected ones are kept as-is)
      await tx.survey.update({
        where: { id },
        data: {
          title, description,
          isActive: isActive ?? true,
          requireName: requireName ?? false,
          requirePhone: requirePhone ?? false,
          assignedDepartments: assignedDepartments || null,
          tips: tips || null,
        },
      });

      // Update protected sections (title, description, icon) but keep their questions intact
      const protectedSections = (sections || []).filter(
        (s: SectionInput) => protectedSectionIds.has(s.id!)
      );
      for (const section of protectedSections) {
        await tx.surveySection.update({
          where: { id: section.id },
          data: {
            title: section.title || '',
            description: section.description || '',
            icon: section.icon || 'clipboard-check',
          },
        });
      }

      // Create new sections that don't conflict with protected ones
      const newSections = (sections || []).filter(
        (s: SectionInput) => !protectedSectionIds.has(s.id!)
      );
      for (const section of newSections) {
        await tx.surveySection.create({
          data: {
            id: section.id?.startsWith('section-') ? undefined : section.id,
            surveyId: id,
            title: section.title || '',
            description: section.description || '',
            icon: section.icon || 'clipboard-check',
            sortOrder: sections.indexOf(section),
            questions: {
              create: (section.questions || []).map((q: QuestionInput, qi: number) => ({
                type: q.type || 'stars',
                title: q.title || '',
                description: q.description || null,
                required: q.required ?? false,
                category: q.category || '',
                options: q.options || null,
                followUp: q.followUp || null,
                sortOrder: qi,
              })),
            },
          },
        });
      }

      // Return updated survey
      return await tx.survey.findUniqueOrThrow({
        where: { id },
        include: {
          sections: {
            include: { questions: true },
            orderBy: { sortOrder: 'asc' },
          },
        },
      });
    });

    await redis.set('surveys_cache_version', Date.now().toString());
    try {
      await redis.set('dashboard_stats_version', Date.now().toString());
    } catch (cacheErr) {
      logger.error('Failed to invalidate stats cache on survey update:', cacheErr);
    }
    await writeAuditLog(req.user!.id, 'update_survey', {
      messageKey: 'audit.details.update_survey',
      params: { title: survey.title, id: survey.id },
    });
    res.json(transformSurvey(survey));
  } catch (error) {
    logger.error('Update survey error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// DELETE /api/surveys/:id
router.delete('/:id', authMiddleware, requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const existingSurvey = await prisma.survey.findUnique({
      where: { id: req.params.id as string },
      select: { tenantId: true },
    });
    if (!existingSurvey || (req.user!.tenantId && existingSurvey.tenantId !== req.user!.tenantId)) {
      res.status(404).json({ error: 'الاستبيان غير موجود' });
      return;
    }

    const deletedSurvey = await prisma.survey.delete({ where: { id: req.params.id as string } });
    await redis.set('surveys_cache_version', Date.now().toString());
    try {
      await redis.set('dashboard_stats_version', Date.now().toString());
    } catch (cacheErr) {
      logger.error('Failed to invalidate stats cache on survey delete:', cacheErr);
    }
    await writeAuditLog(req.user!.id, 'delete_survey', {
      messageKey: 'audit.details.delete_survey',
      params: { title: deletedSurvey.title, id: deletedSurvey.id },
    });
    res.json({ message: 'تم حذف الاستبيان بنجاح' });
  } catch (error) {
    logger.error('Delete survey error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// Helper to transform Prisma survey to frontend shape
function transformSurvey(survey: SurveyWithSections) {
  return {
    id: survey.id,
    title: survey.title,
    description: survey.description,
    isActive: survey.isActive,
    requireName: survey.requireName,
    requirePhone: survey.requirePhone,
    assignedDepartments: survey.assignedDepartments,
    tips: survey.tips,
    createdAt: survey.createdAt.toISOString(),
    sections: (survey.sections || []).map((s) => ({
      id: s.id,
      title: s.title,
      description: s.description,
      icon: s.icon,
      questions: (s.questions || []).map((q) => ({
        id: q.id,
        type: q.type,
        title: q.title,
        description: q.description,
        required: q.required,
        category: q.category,
        options: q.options,
        followUp: q.followUp,
      })),
    })),
  };
}

export default router;
