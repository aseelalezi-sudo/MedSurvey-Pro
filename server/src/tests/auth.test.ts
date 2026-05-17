import { describe, it, expect, vi, afterEach } from 'vitest';
import jwt from 'jsonwebtoken';
import { Request, Response, NextFunction } from 'express';

import type { AuthUser } from '../middleware/auth';
import './setup.js';

const { generateAccessToken, authMiddleware, requireRole } = await import('../middleware/auth');

// Mock the Prisma module
vi.mock('../lib/prisma.js', () => ({
  prisma: {
    user: {
      findUnique: vi.fn(),
    },
  },
}));

import { prisma } from '../lib/prisma.js';

describe('Middleware: auth', () => {
  const mockUser: AuthUser = {
    id: 'user-123',
    username: 'ahmed_test',
    name: 'Ahmed Test',
    role: 'admin',
    tenantId: null,
    department: 'الطوارئ',
  };

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('generateAccessToken', () => {
    it('should generate a valid JWT token signed with user details', () => {
      const token = generateAccessToken(mockUser);
      expect(token).toBeDefined();
      expect(typeof token).toBe('string');

      const decoded = jwt.verify(token, process.env.JWT_SECRET as string) as any;
      expect(decoded.id).toBe(mockUser.id);
      expect(decoded.username).toBe(mockUser.username);
      expect(decoded.role).toBe(mockUser.role);
    });
  });

  describe('authMiddleware', () => {
    it('should return 401 if Authorization header is missing', async () => {
      const req = { headers: {} } as Request;
      const res = { status: vi.fn().mockReturnThis(), json: vi.fn() } as any;
      const next = vi.fn() as NextFunction;

      await authMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith(expect.objectContaining({ 
        code: 'TOKEN_MISSING' 
      }));
    });

    it('should return 401 if token user does not exist or is inactive', async () => {
      const token = generateAccessToken(mockUser);
      const req = { headers: { authorization: `Bearer ${token}` } } as Request;
      const res = { status: vi.fn().mockReturnThis(), json: vi.fn() } as any;
      const next = vi.fn() as NextFunction;

      vi.mocked(prisma.user.findUnique).mockResolvedValue({ ...mockUser, isActive: false } as any);

      await authMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith(expect.objectContaining({ 
        code: 'USER_INACTIVE' 
      }));
    });

    it('should assign req.user and call next() on valid token', async () => {
      const token = generateAccessToken(mockUser);
      const req = { headers: { authorization: `Bearer ${token}` } } as Request;
      const res = { status: vi.fn().mockReturnThis(), json: vi.fn() } as any;
      const next = vi.fn() as NextFunction;

      vi.mocked(prisma.user.findUnique).mockResolvedValue({ ...mockUser, isActive: true } as any);

      await authMiddleware(req, res, next);

      expect(req.user).toBeDefined();
      expect(next).toHaveBeenCalled();
    });

    it('should return 401 if jwt verification fails', async () => {
      const req = { headers: { authorization: `Bearer invalid-token` } } as Request;
      const res = { status: vi.fn().mockReturnThis(), json: vi.fn() } as any;
      const next = vi.fn() as NextFunction;

      await authMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith(expect.objectContaining({ 
        code: 'TOKEN_INVALID' 
      }));
    });
  });

  describe('requireRole', () => {
    it('should return 403 if user role is not authorized', () => {
      const req = { user: { role: 'staff' } } as any;
      const res = { status: vi.fn().mockReturnThis(), json: vi.fn() } as any;
      const next = vi.fn() as NextFunction;

      const middleware = requireRole('admin');
      middleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(403);
    });
  });
});
