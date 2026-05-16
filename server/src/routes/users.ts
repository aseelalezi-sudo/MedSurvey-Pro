import { Router, Request, Response } from 'express';
import bcrypt from 'bcryptjs';
import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { createUserSchema, updateUserSchema } from '../lib/validations.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('UsersRoute');
const router = Router();

router.use(authMiddleware);

router.get('/', requireRole('super_admin'), async (_req: Request, res: Response): Promise<void> => {
  try {
    const users = await prisma.user.findMany({
      select: {
        id: true,
        username: true,
        name: true,
        email: true,
        role: true,
        department: true,
        createdAt: true,
        lastLogin: true,
        isActive: true,
        avatar: true,
      },
      orderBy: { createdAt: 'desc' },
    });
    res.json(users);
  } catch (error) {
    logger.error('Get users error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.post('/', requireRole('super_admin'), validateRequest(createUserSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const { username, password, name, email, role, department, isActive } = req.body;

    if (!username || !password || !name || !role) {
      res.status(400).json({ error: 'يرجى ملء جميع الحقول المطلوبة' });
      return;
    }

    const existing = await prisma.user.findUnique({ where: { username } });
    if (existing) {
      res.status(409).json({ error: 'اسم المستخدم مستخدم بالفعل' });
      return;
    }

    const hashedPassword = await bcrypt.hash(password, 12);
    const user = await prisma.user.create({
      data: {
        username,
        password: hashedPassword,
        name,
        email: email || '',
        role,
        department,
        isActive: isActive ?? true,
      },
      select: {
        id: true,
        username: true,
        name: true,
        email: true,
        role: true,
        department: true,
        createdAt: true,
        isActive: true,
      },
    });

    await writeAuditLog(req.user!.id, 'create_user', {
      messageKey: 'audit.details.create_user',
      params: { name: user.name, username: user.username, role: user.role },
    });

    res.status(201).json(user);
  } catch (error) {
    logger.error('Create user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.put('/:id', requireRole('super_admin'), validateRequest(updateUserSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;
    const { username, password, name, email, role, department, isActive, avatar } = req.body;

    const updateData: Prisma.UserUpdateInput = {};
    if (username !== undefined) updateData.username = username;
    if (name !== undefined) updateData.name = name;
    if (email !== undefined) updateData.email = email;
    if (role !== undefined) updateData.role = role;
    if (department !== undefined) updateData.department = department;
    if (isActive !== undefined) updateData.isActive = isActive;
    if (avatar !== undefined) updateData.avatar = avatar;
    if (password) updateData.password = await bcrypt.hash(password, 12);

    const user = await prisma.user.update({
      where: { id },
      data: updateData,
      select: {
        id: true,
        username: true,
        name: true,
        email: true,
        role: true,
        department: true,
        createdAt: true,
        lastLogin: true,
        isActive: true,
        avatar: true,
      },
    });

    await writeAuditLog(req.user!.id, 'update_user', {
      messageKey: 'audit.details.update_user',
      params: { name: user.name, username: user.username },
    });

    res.json(user);
  } catch (error) {
    logger.error('Update user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.delete('/:id', requireRole('super_admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;

    if (id === req.user!.id) {
      res.status(400).json({ error: 'لا يمكنك حذف حسابك الخاص' });
      return;
    }

    const deletedUser = await prisma.user.delete({ where: { id } });

    await writeAuditLog(req.user!.id, 'delete_user', {
      messageKey: 'audit.details.delete_user',
      params: { name: deletedUser.name, username: deletedUser.username },
    });

    res.json({ message: 'تم حذف المستخدم بنجاح' });
  } catch (error) {
    logger.error('Delete user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

router.patch('/:id/toggle', requireRole('super_admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;
    const user = await prisma.user.findUnique({ where: { id } });

    if (!user) {
      res.status(404).json({ error: 'المستخدم غير موجود' });
      return;
    }

    const updated = await prisma.user.update({
      where: { id },
      data: { isActive: !user.isActive },
      select: {
        id: true,
        username: true,
        name: true,
        email: true,
        role: true,
        department: true,
        createdAt: true,
        lastLogin: true,
        isActive: true,
        avatar: true,
      },
    });

    await writeAuditLog(req.user!.id, updated.isActive ? 'activate_user' : 'deactivate_user', {
      messageKey: updated.isActive ? 'audit.details.activate_user' : 'audit.details.deactivate_user',
      params: { name: updated.name, username: updated.username },
    });

    res.json(updated);
  } catch (error) {
    logger.error('Toggle user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
