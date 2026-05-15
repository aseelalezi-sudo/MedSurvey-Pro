import { Router, Request, Response } from 'express';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { createSurveySchema, updateSurveySchema } from '../lib/validations.js';
import { redis } from '../lib/redis.js';

const logger = createLogger('SurveysRoute');
import type { Survey, SurveySection, SurveyQuestion } from '@prisma/client';

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

// GET /api/surveys — Public (for patients) - returns active surveys
router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const activeOnly = req.query.active === 'true';
    const cacheKey = activeOnly ? 'surveys:active' : 'surveys:all';

    const cached = await redis.get(cacheKey);
    if (cached) {
      logger.info(`Serving surveys from cache: ${cacheKey}`);
      res.json(JSON.parse(cached));
      return;
    }

    const surveys = await prisma.survey.findMany({
      where: activeOnly ? { isActive: true } : undefined,
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
          options: q.options as any,
          followUp: q.followUp as any,
        })),
      })),
    }));

    await redis.set(cacheKey, JSON.stringify(transformed), 'EX', 3600);
    res.json(transformed);
  } catch (error) {
    logger.error('Get surveys error:', error);
    res.status(500).json({ error: 'خطأ في الخادم', details: error instanceof Error ? error.message : String(error) });
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

    await redis.del('surveys:active', 'surveys:all');
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

    // Delete old sections and questions (cascade), then recreate
    await prisma.surveySection.deleteMany({ where: { surveyId: id } });

    const survey = await prisma.survey.update({
      where: { id },
      data: {
        title, description,
        isActive: isActive ?? true,
        requireName: requireName ?? false,
        requirePhone: requirePhone ?? false,
        assignedDepartments: assignedDepartments || null,
        tips: tips || null,
        sections: {
          create: (sections || []).map((section: SectionInput, si: number) => ({
            id: section.id?.startsWith('section-') ? undefined : section.id,
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

    await redis.del('surveys:active', 'surveys:all');
    res.json(transformSurvey(survey));
  } catch (error) {
    logger.error('Update survey error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// DELETE /api/surveys/:id
router.delete('/:id', authMiddleware, requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    await prisma.survey.delete({ where: { id: req.params.id as string } });
    await redis.del('surveys:active', 'surveys:all');
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
