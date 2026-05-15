import { describe, it, expect } from 'vitest';
import request from 'supertest';
import { app } from '../app';

describe('API Integration Tests', () => {
  it('GET /api/health should return ok', async () => {
    const res = await request(app).get('/api/health');
    expect(res.status).toBe(200);
    expect(res.body.status).toBe('ok');
  });

  it('GET /api/surveys should return 200 (public access)', async () => {
    const res = await request(app).get('/api/surveys');
    expect(res.status).toBe(200);
    expect(Array.isArray(res.body)).toBe(true);
  });
});
