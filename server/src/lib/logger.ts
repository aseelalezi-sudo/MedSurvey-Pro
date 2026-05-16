import winston from 'winston';

const isProduction = process.env.NODE_ENV === 'production';

/**
 * Configure Winston for structured logging.
 * - In production: JSON format for easy log aggregation.
 * - In development: Colorized, human-readable format.
 */
const loggerInstance = winston.createLogger({
  level: isProduction ? 'info' : 'debug',
  format: winston.format.combine(
    winston.format.timestamp(),
    isProduction ? winston.format.json() : winston.format.combine(
      winston.format.colorize(),
      winston.format.printf(({ timestamp, level, message, context, ...meta }) => {
        const metaStr = Object.keys(meta).length ? ` ${JSON.stringify(meta)}` : '';
        return `${timestamp} [${level}] ${context ? `[${context}] ` : ''}${message}${metaStr}`;
      })
    )
  ),
  transports: [
    new winston.transports.Console()
  ],
});

/**
 * Creates a scoped logger instance for a specific context/module.
 */
function createLogger(context: string) {
  return {
    info: (message: string, meta?: any) => loggerInstance.info(message, { context, ...meta }),
    warn: (message: string, meta?: any) => loggerInstance.warn(message, { context, ...meta }),
    error: (message: string, meta?: any) => loggerInstance.error(message, { context, ...meta }),
    debug: (message: string, meta?: any) => loggerInstance.debug(message, { context, ...meta }),
  };
}

export { createLogger, loggerInstance as logger };
