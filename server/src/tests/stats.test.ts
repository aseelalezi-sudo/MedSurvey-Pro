import { describe, it, expect, vi, afterEach } from 'vitest';
import { Request, Response, NextFunction } from 'express';

// Mock Redis to skip caching during tests
vi.mock('../lib/redis.js', () => ({
  redis: {
    get: vi.fn().mockResolvedValue(null),
    set: vi.fn().mockResolvedValue('OK'),
  },
}));

vi.mock('../lib/prisma.js', () => {
  const queryRawMock = vi.fn().mockResolvedValue([]);
  return {
    prisma: {
      surveyResponse: {
        aggregate: vi.fn().mockResolvedValue({
          _count: { id: 2 },
          _avg: { overallScore: 85 },
          _min: { submittedAt: new Date() },
          _max: { submittedAt: new Date() },
        }),
        groupBy: vi.fn().mockResolvedValue([
          { department: 'الباطنية', _avg: { overallScore: 80 }, _count: { id: 1 } },
        ]),
        count: vi.fn().mockResolvedValue(1),
      },
      surveyQuestion: {
        findMany: vi.fn().mockResolvedValue([]),
      },
      $queryRaw: queryRawMock,
    },
  };
});

vi.mock('../middleware/auth.js', () => ({
  authMiddleware: (req: Request, _res: Response, next: NextFunction) => {
    req.user = { id: 'u1', username: 'h', name: 'H', role: 'head_of_department', department: 'الباطنية', tenantId: null };
    next();
  },
}));

import { prisma } from '../lib/prisma.js';
import responseRouter from '../routes/responses';

describe('Stats Route Department Restrictions', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it('restricts department scores to the user department for head_of_department', async () => {
    const statsLayer = responseRouter.stack.find(l => l.route && l.route.path === '/stats');
    if (!statsLayer || !statsLayer.route) throw new Error('Stats route not found');
    const handler = statsLayer.route.stack[statsLayer.route.stack.length - 1].handle;

    const req = {
      user: { role: 'head_of_department', department: 'الباطنية' },
      query: {},
      cookies: {}
    } as any;

    const res = {
      json: vi.fn().mockReturnThis(),
      status: vi.fn().mockReturnThis(),
    } as any;

    await handler(req, res, vi.fn());

    // Verify aggregate was called with department filter
    expect(vi.mocked(prisma.surveyResponse.aggregate)).toHaveBeenCalled();
    const calls = vi.mocked(prisma.surveyResponse.aggregate).mock.calls;
    const deptFilter = calls.find(c => c[0].where?.department === 'الباطنية');
    expect(deptFilter).toBeDefined();

    // Verify groupBy was called with department filter
    expect(vi.mocked(prisma.surveyResponse.groupBy)).toHaveBeenCalled();
    const groupByCalls = vi.mocked(prisma.surveyResponse.groupBy).mock.calls;
    const groupDeptFilter = groupByCalls.find(c => c[0].where?.department === 'الباطنية');
    expect(groupDeptFilter).toBeDefined();
  });
});
