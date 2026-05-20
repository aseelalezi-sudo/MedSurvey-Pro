import { Router, Request, Response } from 'express';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { updateSettingsSchema } from '../lib/validations.js';
import { redis } from '../lib/redis.js';

const logger = createLogger('Settings');

const router = Router();

// GET /api/settings — Public (frontend needs settings for patient forms)
router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const tenantId = req.query.tenantId as string || null;
    const cacheKey = `settings:${tenantId || 'global'}`;
    const cached = await redis.get(cacheKey);
    if (cached) {
      logger.info(`Serving settings from cache for tenant: ${tenantId || 'global'}`);
      res.json(JSON.parse(cached));
      return;
    }

    let settings = null;
    if (tenantId) {
      settings = await prisma.settings.findFirst({ where: { tenantId } });
    }

    if (!settings) {
      // Fallback to legacy global settings row
      settings = await prisma.settings.findFirst({ where: { id: 'global' } });
    }

    if (!settings) {
      // Fallback to record with null tenantId
      settings = await prisma.settings.findFirst({ where: { tenantId: null } });
    }

    if (!settings) {
      // Create default settings record under "global" id
      settings = await prisma.settings.create({
        data: { id: 'global', tenantId: null, data: getDefaultSettings() },
      });
    }

    // Cache TTL: 30 min (invalidated on settings update)
    await redis.set(cacheKey, JSON.stringify(settings.data), 'EX', 1800);

    res.json(settings.data);
    return;
  } catch (error) {
    logger.error('Failed to load settings', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// PUT /api/settings
router.put('/', authMiddleware, requireRole('super_admin', 'admin'), validateRequest(updateSettingsSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const newData = req.body;
    const userTenantId = req.user?.tenantId || null;

    let existing = null;
    if (userTenantId) {
      existing = await prisma.settings.findFirst({ where: { tenantId: userTenantId } });
    } else {
      existing = await prisma.settings.findFirst({ where: { id: 'global' } });
    }

    let result;
    if (existing) {
      result = await prisma.settings.update({
        where: { id: existing.id },
        data: { data: newData },
      });
    } else {
      result = await prisma.settings.create({
        data: { 
          tenantId: userTenantId, 
          data: newData 
        },
      });
    }

    // Verify the save by re-reading
    const verified = await prisma.settings.findUnique({ where: { id: result.id } });
    const verifiedData = verified?.data as Record<string, unknown> | null;

    // Create audit log
    try {
      await prisma.auditLog.create({
        data: {
          userId: req.user!.id,
          action: 'update_settings',
          details: `تحديث إعدادات النظام للمستأجر: ${userTenantId || 'العام'}`,
        },
      });
    } catch (auditError) {
      logger.warn('Audit log write failed (non-fatal)', auditError);
    }

    const finalData = verifiedData || result.data;
    const cacheKey = `settings:${userTenantId || 'global'}`;
    // Re-cache: 30 min TTL
    await redis.set(cacheKey, JSON.stringify(finalData), 'EX', 1800);

    res.json(finalData);
  } catch (error) {
    logger.error('Failed to save settings', error);
    res.status(500).json({ error: 'خطأ في حفظ الإعدادات' });
  }
});

function getDefaultSettings() {
  return {
    hospital: {
      name: '',
      shortName: '',
      logo: '',
      address: '',
      phone: '',
      email: '',
      website: '',
      description: '',
      workingHours: '',
      operatingTitle: '',
      welcomeMessage: '',
    },
    departments: [
      { id: 'dept-1', name: 'الطوارئ', isActive: true, color: '#EF4444' },
      { id: 'dept-2', name: 'العيادات الخارجية', isActive: true, color: '#3B82F6' },
      { id: 'dept-3', name: 'الباطنية', isActive: true, color: '#10B981' },
      { id: 'dept-4', name: 'الجراحة', isActive: true, color: '#8B5CF6' },
      { id: 'dept-5', name: 'الأطفال', isActive: true, color: '#F59E0B' },
      { id: 'dept-6', name: 'النساء والولادة', isActive: true, color: '#EC4899' },
      { id: 'dept-7', name: 'العظام', isActive: true, color: '#6366F1' },
      { id: 'dept-8', name: 'العيون', isActive: true, color: '#14B8A6' },
      { id: 'dept-9', name: 'الأنف والأذن والحنجرة', isActive: true, color: '#F97316' },
      { id: 'dept-10', name: 'الأسنان', isActive: true, color: '#06B6D4' },
      { id: 'dept-11', name: 'القلب', isActive: true, color: '#DC2626' },
      { id: 'dept-12', name: 'المختبر والأشعة', isActive: true, color: '#7C3AED' },
    ],
    ageGroups: [
      { id: 'age-1', label: 'أقل من 18 سنة' },
      { id: 'age-2', label: '18 - 30 سنة' },
      { id: 'age-3', label: '31 - 45 سنة' },
      { id: 'age-4', label: '46 - 60 سنة' },
      { id: 'age-5', label: 'أكثر من 60 سنة' },
    ],
    visitTypes: [
      { id: 'vt-1', label: 'زيارة طارئة' },
      { id: 'vt-2', label: 'موعد مسبق' },
      { id: 'vt-3', label: 'تنويم' },
      { id: 'vt-4', label: 'مراجعة' },
      { id: 'vt-5', label: 'عملية جراحية' },
    ],
    surveySettings: {
      allowAnonymous: true,
      requireAllQuestions: false,
      requireName: false,
      requirePhone: false,
      showProgressBar: true,
      enableThankYouPage: true,
      thankYouMessage: 'شكراً لمشاركتكم! رأيكم يساعدنا في تحسين خدماتنا.',
    },
    appearance: {
      primaryColor: '#0d9488',
      secondaryColor: '#10b981',
      fontFamily: 'Cairo',
    },
  };
}

export default router;
