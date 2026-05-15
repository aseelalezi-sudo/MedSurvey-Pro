import { Request, Response, NextFunction } from 'express';
import { z } from 'zod';
import { createLogger } from '../lib/logger.js';

const logger = createLogger('Validation');

export const validateRequest = (schema: z.ZodSchema) => {
  return (req: Request, res: Response, next: NextFunction): void => {
    try {
      req.body = schema.parse(req.body);
      next();
    } catch (error) {
      if (error instanceof z.ZodError) {
        logger.warn('Validation error', { path: req.originalUrl, errors: error.errors });
        res.status(400).json({ error: 'بيانات غير صالحة', details: error.errors });
        return;
      }
      next(error);
    }
  };
};
