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
  const queryRawMock = vi.fn().mockResolvedValue([
    { excellent: BigInt(0), good: BigInt(0), average: BigInt(0), poor: BigInt(0) },
  ]);

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
        findMany: vi.fn().mockResolvedValue([]),
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
    req.user = {
      id: 'u1',
      username: 'h',
      name: 'H',
      role: 'head_of_department',
      department: 'الباطنية',
      tenantId: null,
    };
    next();
  },
}));

import { prisma } from '../lib/prisma.js';
import responseRouter from '../routes/responses';

describe('Stats Route Department Restrictions', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it('restricts aggregate stats to the user department for head_of_department', async () => {
    const statsLayer = responseRouter.stack.find(l => l.route && l.route.path === '/stats');
    if (!statsLayer || !statsLayer.route) throw new Error('Stats route not found');

    const handler = statsLayer.route.stack[statsLayer.route.stack.length - 1].handle;

    const req = {
      user: { role: 'head_of_department', department: 'الباطنية', tenantId: null },
      query: {},
      cookies: {},
    } as any;

    const res = {
      json: vi.fn().mockReturnThis(),
      status: vi.fn().mockReturnThis(),
    } as any;

    await handler(req, res, vi.fn());

    // Main totals must be restricted to the user's department
    expect(vi.mocked(prisma.surveyResponse.aggregate)).toHaveBeenCalled();

    const aggregateCalls = vi.mocked(prisma.surveyResponse.aggregate).mock.calls;
    const deptFilter = aggregateCalls.find(c => c[0].where?.department === 'الباطنية');

    expect(deptFilter).toBeDefined();

    // Department scores are intentionally not restricted,
    // because they are used for all-department ranking / Hall of Fame
    expect(vi.mocked(prisma.surveyResponse.groupBy)).toHaveBeenCalled();

    const groupByCalls = vi.mocked(prisma.surveyResponse.groupBy).mock.calls;
    const departmentScoreCall = groupByCalls.find(c =>
      Array.isArray(c[0].by) && c[0].by.includes('department')
    );

    expect(departmentScoreCall).toBeDefined();
    expect(departmentScoreCall?.[0].where?.department).toBeUndefined();
  });
});

import { statsService } from '../services/statsService.js';

describe('statsService.getPredictiveStats', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it('generates early warnings and dynamically detects the key driver based on question categories', async () => {
    // Mock questions in the database
    vi.mocked(prisma.surveyQuestion.findMany).mockResolvedValue([
      { id: 'q_custom_rec', sectionId: 's1', type: 'stars', title: 'الاستقبال', category: 'Reception', sortOrder: 0, required: true, description: '', options: null, followUp: null } as any,
      { id: 'q_custom_med', sectionId: 's2', type: 'stars', title: 'الرعاية الطبية', category: 'Medical', sortOrder: 0, required: true, description: '', options: null, followUp: null } as any,
    ]);

    // Mock answers with a drop in the medical score for الباطنية
    const mockResponses = [
      // 5 responses in previous period (highly satisfied)
      { id: '1', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-01'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      { id: '2', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-02'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      { id: '3', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-03'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      { id: '4', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-04'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      { id: '5', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-05'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      { id: '6', department: 'الباطنية', overallScore: 95, submittedAt: new Date('2026-05-06'), answers: { q_custom_rec: 5, q_custom_med: 5 } },
      // 6 responses in current period (satisfaction dropped, particularly for medical)
      { id: '7', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-11'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
      { id: '8', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-12'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
      { id: '9', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-13'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
      { id: '10', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-14'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
      { id: '11', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-15'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
      { id: '12', department: 'الباطنية', overallScore: 70, submittedAt: new Date('2026-05-16'), answers: { q_custom_rec: 5, q_custom_med: 1 } },
    ];

    vi.mocked(prisma.surveyResponse.findMany).mockResolvedValue(mockResponses as any);

    const result = await statsService.getPredictiveStats(null);

    // Should detect one warning
    expect(result.alerts).toHaveLength(1);
    expect(result.alerts[0].department).toBe('الباطنية');
    // Medical category has the lowest score/biggest drop, should be identified as key driver
    expect(result.alerts[0].keyDriver).toBe('الرعاية والخدمة الطبية');
    expect(result.stats.activeWarnings).toBe(1);
  });
});