import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Request, Response } from 'express';

import './setup.js';

// Mock Prisma
vi.mock('../lib/prisma.js', () => {
  return {
    prisma: {
      survey: {
        findMany: vi.fn(),
      },
    },
  };
});

// Mock Redis
vi.mock('../lib/redis.js', () => {
  return {
    redis: {
      get: vi.fn(),
      set: vi.fn(),
    },
  };
});

// Dynamic imports to prevent hoisting errors before JWT_SECRET is loaded
const { prisma } = await import('../lib/prisma.js');
const { redis } = await import('../lib/redis.js');
const surveysRouter = (await import('../routes/surveys.js')).default;

describe('Backend API: Surveys Integrations & Caching Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const mockSurveysList = [
    {
      id: 'survey-reception',
      title: 'استبيان رضا المراجعين للمستشفى',
      isActive: true,
      createdAt: new Date(),
      sections: [
        {
          id: 'sec-1',
          title: 'الاستقبال والتعامل',
          questions: [
            { id: 'q1', text: 'لباقة موظفي الاستقبال', type: 'stars' }
          ]
        }
      ]
    }
  ];

  // Find GET /api/surveys route handler
  const getSurveysLayer = surveysRouter.stack.find(
    (layer: any) => layer.route && layer.route.path === '/' && layer.route.methods.get
  );

  it('should serve surveys from Database and populate Redis cache when cache is cold (empty)', async () => {
    const handler = getSurveysLayer!.route!.stack[0].handle;

    const req = {
      query: { active: 'true' }
    } as unknown as Request;

    let responseData: any = null;
    const res = {
      status: vi.fn().mockReturnThis(),
      json: vi.fn().mockImplementation((data) => {
        responseData = data;
        return res;
      })
    } as unknown as Response;

    // Mock cache to return empty (null)
    vi.mocked(redis.get).mockResolvedValue(null);
    // Mock DB to return full survey list
    vi.mocked(prisma.survey.findMany).mockResolvedValue(mockSurveysList as any);

    await handler(req, res, vi.fn());

    expect(redis.get).toHaveBeenCalledWith('surveys_cache_version');
    expect(redis.get).toHaveBeenCalledWith('surveys:v1:global:active');
    expect(prisma.survey.findMany).toHaveBeenCalled();
    expect(redis.set).toHaveBeenCalled(); // Should populate Redis
    expect(responseData).toBeDefined();
    expect(responseData[0].id).toBe('survey-reception');
    expect(responseData[0].title).toBe('استبيان رضا المراجعين للمستشفى');
  });

  it('should serve surveys immediately from Redis cache without querying DB when cache is hot (populated)', async () => {
    const handler = getSurveysLayer!.route!.stack[0].handle;

    const req = {
      query: { active: 'false' }
    } as unknown as Request;

    let responseData: any = null;
    const res = {
      status: vi.fn().mockReturnThis(),
      json: vi.fn().mockImplementation((data) => {
        responseData = data;
        return res;
      })
    } as unknown as Response;

    // Mock cache version and pre-populated JSON data
    vi.mocked(redis.get).mockImplementation(async (key) => {
      const cacheKey = String(key);
      if (cacheKey === 'surveys_cache_version') return 'v1';
      if (cacheKey === 'surveys:v1:global:all') return JSON.stringify(mockSurveysList);
      return null;
    });

    await handler(req, res, vi.fn());

    expect(redis.get).toHaveBeenCalledWith('surveys_cache_version');
    expect(redis.get).toHaveBeenCalledWith('surveys:v1:global:all');
    // Database query should NEVER be touched when cache is hot!
    expect(prisma.survey.findMany).not.toHaveBeenCalled();
    expect(responseData).toBeDefined();
    expect(responseData[0].id).toBe('survey-reception');
    expect(responseData[0].title).toBe('استبيان رضا المراجعين للمستشفى');
  });
});
