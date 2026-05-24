import { Router, Request, Response } from 'express';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { updateSettingsSchema } from '../lib/validations.js';
import { redis } from '../lib/redis.js';

const logger = createLogger('Settings');

const router = Router();

function resolvePublicTenantId(req: Request, res: Response): string | null | undefined {
  const configuredTenantId = process.env.PUBLIC_TENANT_ID?.trim() || null;
  const requestedTenantId = typeof req.query.tenantId === 'string' ? req.query.tenantId.trim() : null;
  const allowQueryTenant = process.env.ALLOW_PUBLIC_TENANT_QUERY === 'true' || process.env.NODE_ENV !== 'production';

  if (configuredTenantId) {
    if (requestedTenantId && requestedTenantId !== configuredTenantId) {
      res.status(404).json({ error: 'Settings not found' });
      return undefined;
    }
    return configuredTenantId;
  }

  if (requestedTenantId && !allowQueryTenant) {
    res.status(400).json({ error: 'Public tenant selection is not allowed' });
    return undefined;
  }

  return requestedTenantId || null;
}

// GET /api/settings — Public (frontend needs settings for patient forms)
router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const tenantId = resolvePublicTenantId(req, res);
    if (tenantId === undefined) return;
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
      const oldData = existing?.data as any;
      const tenantStr = userTenantId ? 'فرع محدد' : 'العام';
      let diffMsg = `تحديث إعدادات النظام للمستأجر: ${userTenantId || 'العام'}`;
      
      if (oldData) {
        const changes = getDetailedSettingsDiff(oldData, newData);
        if (changes.length > 0) {
          const joinedChanges = changes.join(' | ');
          const truncatedChanges = joinedChanges.length > 250 
            ? joinedChanges.substring(0, 247) + '...' 
            : joinedChanges;
          diffMsg = `تعديل الإعدادات (${tenantStr}): ${truncatedChanges}`;
        } else {
          diffMsg = `حفظ إعدادات النظام (${tenantStr}) - بدون تغييرات فعلية`;
        }
      } else {
        diffMsg = `تهيئة وإعداد النظام لأول مرة (${tenantStr})`;
      }

      await prisma.auditLog.create({
        data: {
          userId: req.user!.id,
          action: 'update_settings',
          details: diffMsg,
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

/**
 * GET /api/settings/usage-check?type=department&value=الطوارئ
 * Check if a settings item (department, ageGroup, visitType) is used in any survey responses.
 * Returns { inUse: boolean, count: number }
 */
router.get('/usage-check', authMiddleware, requireRole('super_admin', 'admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const type = req.query.type as string;
    const value = req.query.value as string;

    if (!type || !value) {
      res.status(400).json({ error: 'الحقول type و value مطلوبة' });
      return;
    }

    let count = 0;
    const tenantFilter = req.user!.tenantId ? { tenantId: req.user!.tenantId } : {};

    switch (type) {
      case 'department':
        count = await prisma.surveyResponse.count({
          where: { ...tenantFilter, department: value },
        });
        break;
      case 'ageGroup':
        count = await prisma.surveyResponse.count({
          where: { ...tenantFilter, ageGroup: value },
        });
        break;
      case 'visitType':
        count = await prisma.surveyResponse.count({
          where: { ...tenantFilter, visitType: value },
        });
        break;
      default:
        res.status(400).json({ error: 'نوع غير صالح. الأنواع المسموحة: department, ageGroup, visitType' });
        return;
    }

    res.json({ inUse: count > 0, count });
  } catch (error) {
    logger.error('Usage check failed:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
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
      { id: 'age-1', label: 'أقل من 18 سنة', isActive: true },
      { id: 'age-2', label: '18 - 30 سنة', isActive: true },
      { id: 'age-3', label: '31 - 45 سنة', isActive: true },
      { id: 'age-4', label: '46 - 60 سنة', isActive: true },
      { id: 'age-5', label: 'أكثر من 60 سنة', isActive: true },
    ],
    visitTypes: [
      { id: 'vt-1', label: 'زيارة طارئة', isActive: true },
      { id: 'vt-2', label: 'موعد مسبق', isActive: true },
      { id: 'vt-3', label: 'تنويم', isActive: true },
      { id: 'vt-4', label: 'مراجعة', isActive: true },
      { id: 'vt-5', label: 'عملية جراحية', isActive: true },
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

function getDetailedSettingsDiff(oldData: any, newData: any): string[] {
  const changes: string[] = [];

  if (oldData.hospital && newData.hospital) {
    const fields = [
      { key: 'name', ar: 'اسم المستشفى' },
      { key: 'shortName', ar: 'الاسم المختصر' },
      { key: 'address', ar: 'العنوان' },
      { key: 'phone', ar: 'الهاتف' },
      { key: 'email', ar: 'البريد الإلكتروني' },
      { key: 'website', ar: 'الموقع' },
      { key: 'operatingTitle', ar: 'العنوان التشغيلي' },
      { key: 'welcomeMessage', ar: 'الترحيب' },
    ];
    fields.forEach(f => {
      if (oldData.hospital[f.key] !== newData.hospital[f.key]) {
        changes.push(`تغيير ${f.ar} إلى "${newData.hospital[f.key]}"`);
      }
    });
    if (oldData.hospital.logo !== newData.hospital.logo) changes.push('تغيير شعار المستشفى');
  }

  if (oldData.surveySettings && newData.surveySettings) {
    const fields = [
      { key: 'allowAnonymous', ar: 'السماح بالمجهولين' },
      { key: 'requireAllQuestions', ar: 'إجبار الأسئلة' },
      { key: 'requireName', ar: 'إجبار الاسم' },
      { key: 'requirePhone', ar: 'إجبار الهاتف' },
      { key: 'showProgressBar', ar: 'شريط التقدم' },
      { key: 'enableThankYouPage', ar: 'صفحة الشكر' }
    ];
    fields.forEach(f => {
      if (oldData.surveySettings[f.key] !== newData.surveySettings[f.key]) {
        changes.push(`${f.ar}: ${newData.surveySettings[f.key] ? 'مفعل' : 'معطل'}`);
      }
    });
    if (oldData.surveySettings.thankYouMessage !== newData.surveySettings.thankYouMessage) {
      changes.push(`تغيير رسالة الشكر`);
    }
  }

  if (oldData.appearance && newData.appearance) {
    if (oldData.appearance.primaryColor !== newData.appearance.primaryColor) changes.push(`اللون الأساسي إلى ${newData.appearance.primaryColor}`);
    if (oldData.appearance.secondaryColor !== newData.appearance.secondaryColor) changes.push(`اللون الثانوي إلى ${newData.appearance.secondaryColor}`);
    if (oldData.appearance.fontFamily !== newData.appearance.fontFamily) changes.push(`خط النظام إلى ${newData.appearance.fontFamily}`);
  }

  const diffArray = (oldArr: any[], newArr: any[], nameKey: string, sectionName: string) => {
    if (!oldArr || !newArr) return;
    const oldMap = new Map(oldArr.map((i: any) => [i.id, i]));
    const newMap = new Map(newArr.map((i: any) => [i.id, i]));

    for (const newItem of newArr) {
      const oldItem = oldMap.get(newItem.id);
      const itemName = newItem[nameKey];
      if (!oldItem) {
        changes.push(`إضافة ${sectionName}: "${itemName}"`);
      } else {
        if (oldItem[nameKey] !== newItem[nameKey]) changes.push(`تغيير اسم ${sectionName} لـ "${newItem[nameKey]}"`);
        if (oldItem.isActive !== newItem.isActive) changes.push(`${newItem.isActive ? 'تفعيل' : 'إيقاف'} ${sectionName} "${itemName}"`);
      }
    }
    for (const oldItem of oldArr) {
      if (!newMap.has(oldItem.id)) {
        changes.push(`حذف ${sectionName}: "${oldItem[nameKey]}"`);
      }
    }
  };

  diffArray(oldData.departments || [], newData.departments || [], 'name', 'قسم');
  diffArray(oldData.ageGroups || [], newData.ageGroups || [], 'label', 'فئة عمرية');
  diffArray(oldData.visitTypes || [], newData.visitTypes || [], 'label', 'نوع زيارة');

  const oldPlans = oldData.activatedPredictivePlans || [];
  const newPlans = newData.activatedPredictivePlans || [];
  newPlans.forEach((p: string) => {
    if (!oldPlans.includes(p)) changes.push(`تفعيل الاستجابة الذكية للقسم: "${p}"`);
  });
  oldPlans.forEach((p: string) => {
    if (!newPlans.includes(p)) changes.push(`إيقاف الاستجابة الذكية للقسم: "${p}"`);
  });

  return changes;
}

export default router;
