import { Request, Response, NextFunction } from 'express';
import morgan from 'morgan';
import { createLogger } from '../lib/logger.js';

const logger = createLogger('Monitoring');

/**
 * Performance middleware to measure and log request execution time.
 * Alerts if a request takes longer than 1 second.
 */
export function performanceMiddleware(req: Request, res: Response, next: NextFunction) {
  const start = process.hrtime();

  res.on('finish', () => {
    const diff = process.hrtime(start);
    const timeInMs = (diff[0] * 1e9 + diff[1]) / 1e6;
    
    const logData = {
      method: req.method,
      url: req.originalUrl,
      status: res.statusCode,
      durationMs: Math.round(timeInMs),
      ip: req.ip,
      userAgent: req.get('user-agent'),
    };

    if (timeInMs > 1000) {
      logger.warn(`Slow Request Detected: ${req.method} ${req.originalUrl}`, logData);
    } else {
      logger.debug(`${req.method} ${req.originalUrl} completed`, logData);
    }
  });

  next();
}

/**
 * Morgan configuration for HTTP request logging.
 */
export const httpLogger = morgan(':method :url :status :res[content-length] - :response-time ms', {
  stream: {
    write: (message: string) => logger.info(message.trim()),
  },
});
