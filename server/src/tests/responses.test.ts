import { describe, it, expect } from 'vitest';
import request from 'supertest';
import { app } from '../app';

describe('Responses API Integration', () => {
  it('POST /api/responses should validate input and return 400 for empty body', async () => {
    const res = await request(app)
      .post('/api/responses')
      .send({});
    
    expect(res.status).toBe(400);
    expect(res.body).toHaveProperty('error');
  });

  it('GET /api/responses should require authentication', async () => {
    const res = await request(app).get('/api/responses');
    expect(res.status).toBe(401);
  });

  it('GET /api/responses/stats should require authentication', async () => {
    const res = await request(app).get('/api/responses/stats');
    expect(res.status).toBe(401);
  });
});
