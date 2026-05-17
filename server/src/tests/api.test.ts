import { describe, it, expect, vi, beforeEach } from 'vitest';
import request from 'supertest';

import './setup.js';

vi.mock('../lib/prisma.js', () => ({
  prisma: {
    survey: {
      findMany: vi.fn(),
    },
  },
}));

vi.mock('../lib/redis.js', () => ({
  redis: {
    get: vi.fn(),
    set: vi.fn(),
  },
}));

const { prisma } = await import('../lib/prisma.js');
const { redis } = await import('../lib/redis.js');
const { app } = await import('../app');

describe('API Integration Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('GET /api/health should return ok', async () => {
    const res = await request(app).get('/api/health');

    expect(res.status).toBe(200);
    expect(res.body.status).toBe('ok');
  });

  it('GET /api/surveys should return 200 (public access)', async () => {
    vi.mocked(redis.get).mockResolvedValue(null);
    vi.mocked(redis.set).mockResolvedValue('OK');

    vi.mocked(prisma.survey.findMany).mockResolvedValue([
      {
        id: 'survey-1',
        title: 'Test Survey',
        description: '',
        isActive: true,
        requireName: false,
        requirePhone: false,
        assignedDepartments: null,
        tips: null,
        createdAt: new Date('2026-01-01T00:00:00.000Z'),
        sections: [
          {
            id: 'section-1',
            title: 'Section 1',
            description: '',
            icon: 'clipboard-check',
            questions: [
              {
                id: 'question-1',
                type: 'stars',
                title: 'Question 1',
                description: null,
                required: true,
                category: 'general',
                options: null,
                followUp: null,
              },
            ],
          },
        ],
      },
    ] as any);

    const res = await request(app).get('/api/surveys');

    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
    expect(res.body[0].id).toBe('survey-1');
    expect(prisma.survey.findMany).toHaveBeenCalled();
    expect(redis.set).toHaveBeenCalled();
  });
});
