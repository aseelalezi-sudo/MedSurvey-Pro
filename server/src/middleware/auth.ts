import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import crypto from 'crypto';
import { prisma } from '../lib/prisma.js';

function getRequiredSecret(name: string): string {
  const secret = process.env[name];
  if (!secret) {
    if (process.env.NODE_ENV === 'production' || process.env.NODE_ENV === 'test') {
      throw new Error(`⛔ ${name} غير مُعرّف في متغيرات البيئة (.env).`);
    }
    const generated = crypto.randomBytes(32).toString('hex');
    console.warn(`⚠️  ${name} غير مُعرّف. تم توليد مفتاح عشوائي مؤقت للبيئة التطويرية.`);
    return generated;
  }
  return secret;
}

const ACCESS_SECRET = getRequiredSecret('JWT_SECRET');
const REFRESH_SECRET = getRequiredSecret('REFRESH_SECRET');

export interface AuthUser {
  id: string;
  username: string;
  name: string;
  role: 'super_admin' | 'admin' | 'unit_manager' | 'head_of_department' | 'staff';
  department: string | null;
  tenantId: string | null;
}

declare global {
  namespace Express {
    interface Request {
      user?: AuthUser;
    }
  }
}

/**
 * Generates a short-lived access token (15 minutes)
 */
export function generateAccessToken(user: AuthUser): string {
  return jwt.sign(
    { id: user.id, username: user.username, role: user.role, department: user.department, tenantId: user.tenantId },
    ACCESS_SECRET,
    { expiresIn: '15m' }
  );
}

/**
 * Generates a long-lived refresh token (7 days)
 */
export function generateRefreshToken(userId: string): string {
  return jwt.sign({ id: userId }, REFRESH_SECRET, { expiresIn: '7d' });
}

export function verifyRefreshToken(token: string) {
  return jwt.verify(token, REFRESH_SECRET) as { id: string };
}

export async function authMiddleware(req: Request, res: Response, next: NextFunction): Promise<void> {
  let token: string | null = null;

  const authHeader = req.headers.authorization;
  if (authHeader && authHeader.startsWith('Bearer ')) {
    token = authHeader.split(' ')[1];
  }

  // Also check the old cookie for backwards compatibility during migration, 
  // but prefer Authorization header for the new access token.
  if (!token && req.cookies?.medsurvey_token) {
    token = req.cookies.medsurvey_token;
  }

  if (!token) {
    res.status(401).json({ error: 'غير مصرح - يرجى تسجيل الدخول', code: 'TOKEN_MISSING' });
    return;
  }

  try {
    const decoded = jwt.verify(token, ACCESS_SECRET) as unknown as { id: string };

    const user = await prisma.user.findUnique({
      where: { id: decoded.id },
      select: { id: true, username: true, name: true, role: true, department: true, isActive: true, tenantId: true },
    });

    if (!user || !user.isActive) {
      res.status(401).json({ error: 'حساب غير فعال أو محذوف', code: 'USER_INACTIVE' });
      return;
    }

    req.user = user as AuthUser;
    next();
  } catch (err: any) {
    if (err.name === 'TokenExpiredError') {
      res.status(401).json({ error: 'جلسة منتهية', code: 'TOKEN_EXPIRED' });
    } else {
      res.status(401).json({ error: 'جلسة غير صالحة', code: 'TOKEN_INVALID' });
    }
  }
}

export function requireRole(...roles: AuthUser['role'][]) {
  return (req: Request, res: Response, next: NextFunction): void => {
    if (!req.user) {
      res.status(401).json({ error: 'غير مصرح' });
      return;
    }
    if (!roles.includes(req.user.role)) {
      res.status(403).json({ error: 'ليس لديك صلاحية لهذا الإجراء' });
      return;
    }
    next();
  };
}
