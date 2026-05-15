import { describe, it, expect, vi } from 'vitest';
import { sanitizeInput } from '../middleware/sanitize';
import { Request, Response } from 'express';

describe('Middleware: sanitizeInput', () => {
  it('should clean dangerous script HTML tags from request body', () => {
    const req = {
      body: {
        title: 'Safe Title',
        description: 'Malicious description <script>alert("xss")</script>',
        nested: {
          comment: 'Click <a href="javascript:alert(1)">here</a> now!',
          count: 42,
          active: true
        },
        tags: ['<img src=x onerror=alert(1)>', 'safe-tag']
      },
    } as unknown as Request;

    const res = {} as Response;
    const next = vi.fn();

    sanitizeInput(req, res, next);

    expect(req.body.title).toBe('Safe Title');
    // xss package will strip or escape dangerous script tags
    expect(req.body.description).not.toContain('<script>');
    expect(req.body.nested.comment).not.toContain('javascript:');
    expect(req.body.nested.count).toBe(42);
    expect(req.body.nested.active).toBe(true);
    expect(req.body.tags[0]).not.toContain('onerror=');
    expect(next).toHaveBeenCalled();
  });

  it('should sanitize request query and params', () => {
    const req = {
      query: {
        search: 'patient <iframe src="javascript:alert(1)"></iframe>',
        page: '1',
        nullValue: null,
      },
      params: {
        id: '123<svg onload=alert(1)>'
      }
    } as unknown as Request;

    const res = {} as Response;
    const next = vi.fn();

    sanitizeInput(req, res, next);

    expect(req.query.search).not.toContain('<iframe');
    expect(req.query.page).toBe('1');
    expect(req.query.nullValue).toBeNull();
    expect(req.params.id).not.toContain('<svg');
    expect(next).toHaveBeenCalled();
  });

  it('should handle missing body, query, or params safely', () => {
    const req = {} as Request;
    const res = {} as Response;
    const next = vi.fn();

    expect(() => sanitizeInput(req, res, next)).not.toThrow();
    expect(next).toHaveBeenCalled();
  });
});
