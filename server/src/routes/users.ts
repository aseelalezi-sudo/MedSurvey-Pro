import { Router, Request, Response } from 'express';
import bcrypt from 'bcryptjs';
import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { createUserSchema, updateUserSchema } from '../lib/validations.js';

const logger = createLogger('UsersRoute');

const router = Router();

// All routes require authentication
router.use(authMiddleware);

// GET /api/users
router.get('/', async (req: Request, res: Response): Promise<void> => {
  try {
    const users = await prisma.user.findMany({
      select: {
        id: true, username: true, name: true, email: true,
        role: true, department: true, createdAt: true,
        lastLogin: true, isActive: true, avatar: true,
      },
      orderBy: { createdAt: 'desc' },
    });
    res.json(users);
  } catch (error) {
    logger.error('Get users error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// POST /api/users
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
        username, password: hashedPassword, name,
        email: email || '', role, department,
        isActive: isActive ?? true,
      },
      select: {
        id: true, username: true, name: true, email: true,
        role: true, department: true, createdAt: true,
        isActive: true,
      },
    });

    await prisma.auditLog.create({
      data: {
        userId: req.user!.id,
        action: 'create_user',
        details: `إنشاء مستخدم جديد: ${name}`,
      },
    });

    res.status(201).json(user);
  } catch (error) {
    logger.error('Create user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// PUT /api/users/:id
router.put('/:id', requireRole('super_admin', 'admin'), validateRequest(updateUserSchema), async (req: Request, res: Response): Promise<void> => {
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
        id: true, username: true, name: true, email: true,
        role: true, department: true, createdAt: true,
        lastLogin: true, isActive: true, avatar: true,
      },
    });

    await prisma.auditLog.create({
      data: {
        userId: req.user!.id,
        action: 'update_user',
        details: `تحديث المستخدم: ${user.name}`,
      },
    });

    res.json(user);
  } catch (error) {
    logger.error('Update user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// DELETE /api/users/:id
router.delete('/:id', requireRole('super_admin'), async (req: Request, res: Response): Promise<void> => {
  try {
    const id = req.params.id as string;

    if (id === req.user!.id) {
      res.status(400).json({ error: 'لا يمكنك حذف حسابك الخاص' });
      return;
    }

    await prisma.user.delete({ where: { id } });

    await prisma.auditLog.create({
      data: {
        userId: req.user!.id,
        action: 'delete_user',
        details: `حذف المستخدم: ${id}`,
      },
    });

    res.json({ message: 'تم حذف المستخدم بنجاح' });
  } catch (error) {
    logger.error('Delete user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// PATCH /api/users/:id/toggle
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
        id: true, username: true, name: true, email: true,
        role: true, department: true, createdAt: true,
        lastLogin: true, isActive: true, avatar: true,
      },
    });

    res.json(updated);
  } catch (error) {
    logger.error('Toggle user error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
