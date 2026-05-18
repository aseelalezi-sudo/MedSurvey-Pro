import { Request, Response, NextFunction } from 'express';
import { errorLogger } from '../lib/errorLogger.js';

export function errorCapture(err: Error, req: Request, res: Response, _next: NextFunction) {
  const statusCode = (err as Error & { statusCode?: number }).statusCode || 500;

  errorLogger.log({
    level: statusCode >= 500 ? 'error' : 'warn',
    message: err.message || 'Unknown server error',
    stack: err.stack,
    source: `${req.method} ${req.originalUrl}`,
    metadata: {
      method: req.method,
      url: req.originalUrl,
      statusCode,
      ip: req.ip,
      userId: (req as Request & { user?: { id: string } }).user?.id,
      query: sanitizeMetadata(req.query),
      body: sanitizeMetadata(req.body),
    },
  });

  if (!res.headersSent) {
    res.status(statusCode).json({
      error: statusCode >= 500 ? 'خطأ داخلي في الخادم' : err.message,
    });
  }
}

function sanitizeMetadata(data: unknown): Record<string, unknown> | undefined {
  if (!data || typeof data !== 'object') return undefined;
  const safe: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
    if (key.toLowerCase().includes('password') || key.toLowerCase().includes('token') || key.toLowerCase().includes('secret')) {
      safe[key] = '***';
    } else if (typeof value === 'string' && value.length > 1000) {
      safe[key] = value.substring(0, 1000) + '...';
    } else {
      safe[key] = value;
    }
  }
  return safe;
}

export function setupGlobalErrorHandlers() {
  process.on('uncaughtException', (err: Error) => {
    errorLogger.log({
      level: 'error',
      message: `UNCAUGHT EXCEPTION: ${err.message}`,
      stack: err.stack,
      source: 'process.uncaughtException',
    }).catch(() => {});
  });

  process.on('unhandledRejection', (reason: unknown) => {
    const err = reason instanceof Error ? reason : new Error(String(reason));
    errorLogger.log({
      level: 'error',
      message: `UNHANDLED REJECTION: ${err.message}`,
      stack: err.stack,
      source: 'process.unhandledRejection',
    }).catch(() => {});
  });
}
