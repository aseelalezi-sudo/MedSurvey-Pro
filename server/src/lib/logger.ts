/**
 * Lightweight structured logger for MedSurvey Pro server.
 * Outputs JSON in production for log aggregation, human-readable in development.
 */

const isProduction = process.env.NODE_ENV === 'production';

type LogLevel = 'info' | 'warn' | 'error' | 'debug';

function formatMessage(level: LogLevel, context: string, message: string, meta?: unknown): string {
  if (isProduction) {
    return JSON.stringify({
      timestamp: new Date().toISOString(),
      level,
      context,
      message,
      ...(meta !== undefined ? { meta } : {}),
    });
  }
  const prefix = { info: 'ℹ️', warn: '⚠️', error: '❌', debug: '🔍' }[level];
  const metaStr = meta !== undefined ? ` ${JSON.stringify(meta)}` : '';
  return `${prefix} [${context}] ${message}${metaStr}`;
}

function createLogger(context: string) {
  return {
    info: (message: string, meta?: unknown) => console.log(formatMessage('info', context, message, meta)),
    warn: (message: string, meta?: unknown) => console.warn(formatMessage('warn', context, message, meta)),
    error: (message: string, meta?: unknown) => console.error(formatMessage('error', context, message, meta)),
    debug: (message: string, meta?: unknown) => {
      if (!isProduction) console.log(formatMessage('debug', context, message, meta));
    },
  };
}

export { createLogger };
