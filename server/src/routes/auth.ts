import { Router, Request, Response } from 'express';
import bcrypt from 'bcryptjs';
import rateLimit from 'express-rate-limit';
import { prisma } from '../lib/prisma.js';
import { 
  generateAccessToken, 
  generateRefreshToken, 
  verifyRefreshToken, 
  authMiddleware,
  AuthUser
} from '../middleware/auth.js';
import { createLogger } from '../lib/logger.js';
import { validateRequest } from '../middleware/validate.js';
import { loginSchema } from '../lib/validations.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('AuthRoute');
const router = Router();

const loginLimiter = rateLimit({
  windowMs: 2 * 60 * 1000,
  max: 5,
  message: { error: 'تم تجاوز الحد المسموح لمحاولات تسجيل الدخول. يرجى المحاولة بعد دقيقتين.' },
  skipSuccessfulRequests: true,
  standardHeaders: true,
  legacyHeaders: false,
});

const refreshLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  message: { error: 'تم تجاوز الحد المسموح لمحاولات التحديث. يرجى المحاولة لاحقاً.' },
  standardHeaders: true,
  legacyHeaders: false,
});

// POST /api/auth/login
router.post('/login', loginLimiter, validateRequest(loginSchema), async (req: Request, res: Response): Promise<void> => {
  try {
    const { username, password } = req.body;
    const user = await prisma.user.findUnique({ where: { username } });

    if (!user || !user.isActive || !(await bcrypt.compare(password, user.password))) {
      await writeAuditLog(user?.id, 'login_failed', {
        messageKey: 'audit.details.login_failed',
        params: { username },
      }).catch(() => {});
      res.status(401).json({ error: 'اسم المستخدم أو كلمة المرور غير صحيحة' });
      return;
    }

    // Tokens
    const authUser: AuthUser = {
      id: user.id,
      username: user.username,
      name: user.name,
      role: user.role,
      department: user.department,
      tenantId: user.tenantId,
    };

    const accessToken = generateAccessToken(authUser);
    const refreshToken = generateRefreshToken(user.id);

    // Clean up expired refresh tokens for this user
    await prisma.refreshToken.deleteMany({
      where: { userId: user.id, expiresAt: { lt: new Date() } },
    });

    // Save refresh token in DB
    await prisma.refreshToken.create({
      data: {
        token: refreshToken,
        userId: user.id,
        expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000), // 7 days
      },
    });

    // Update last login
    await prisma.user.update({ where: { id: user.id }, data: { lastLogin: new Date() } });
    await writeAuditLog(user.id, 'login', {
      messageKey: 'audit.details.login',
      params: { username: user.username },
    });

    // Cookie settings for refresh token
    res.cookie('medsurvey_refresh_token', refreshToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
    });

    const { password: _, ...userWithoutPassword } = user;
    res.json({ token: accessToken, user: userWithoutPassword });
  } catch (error) {
    logger.error('Login error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// POST /api/auth/refresh
router.post('/refresh', refreshLimiter, async (req: Request, res: Response): Promise<void> => {
  try {
    const refreshToken = req.cookies?.medsurvey_refresh_token;

    if (!refreshToken) {
      res.status(401).json({ error: 'رمز التحديث مفقود' });
      return;
    }

    // Verify JWT
    const decoded = verifyRefreshToken(refreshToken);
    
    // Check DB
    const storedToken = await prisma.refreshToken.findUnique({
      where: { token: refreshToken },
      include: { user: true }
    });

    if (!storedToken || storedToken.expiresAt < new Date()) {
      if (storedToken) await prisma.refreshToken.delete({ where: { id: storedToken.id } });
      res.status(401).json({ error: 'رمز التحديث غير صالح أو منتهي الصلاحية' });
      return;
    }

    const user = storedToken.user;
    if (!user.isActive) {
      res.status(401).json({ error: 'الحساب غير نشط' });
      return;
    }

    // Generate new access token
    const newAccessToken = generateAccessToken({
      id: user.id,
      username: user.username,
      name: user.name,
      role: user.role,
      department: user.department,
      tenantId: user.tenantId,
    });

    res.json({ token: newAccessToken });
  } catch (error) {
    logger.error('Refresh error:', error);
    res.status(401).json({ error: 'رمز التحديث غير صالح' });
  }
});

// POST /api/auth/logout
router.post('/logout', async (req: Request, res: Response): Promise<void> => {
  try {
    const refreshToken = req.cookies?.medsurvey_refresh_token;
    let userId: string | undefined;
    if (refreshToken) {
      try {
        userId = verifyRefreshToken(refreshToken).id;
      } catch {
        userId = undefined;
      }
      await prisma.refreshToken.deleteMany({ where: { token: refreshToken } });
    }
    await writeAuditLog(userId, 'logout', {
      messageKey: 'audit.details.logout',
    });

    res.clearCookie('medsurvey_refresh_token', {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
    });

    res.json({ message: 'تم تسجيل الخروج بنجاح' });
  } catch (error) {
    logger.error('Logout error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

// GET /api/auth/me
router.get('/me', authMiddleware, async (req: Request, res: Response): Promise<void> => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: {
        id: true, username: true, name: true, email: true,
        role: true, department: true, createdAt: true,
        lastLogin: true, isActive: true, avatar: true,
      },
    });
    res.json(user);
  } catch (error) {
    logger.error('Get me error:', error);
    res.status(500).json({ error: 'خطأ في الخادم' });
  }
});

export default router;
