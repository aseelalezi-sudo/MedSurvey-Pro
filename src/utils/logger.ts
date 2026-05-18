/**
 * Lightweight structured logger for MedSurvey Pro client (Frontend).
 * Provides beautifully styled prefixes in development for easy debugging,
 * and maintains performance in production.
 */

type LogLevel = 'info' | 'warn' | 'error' | 'debug';

const isProduction = import.meta.env?.PROD ?? false;

function getStyle(level: LogLevel): string {
  switch (level) {
    case 'info':
      return 'color: #3b82f6; font-weight: bold; background: #eff6ff; padding: 2px 4px; border-radius: 4px;';
    case 'warn':
      return 'color: #f59e0b; font-weight: bold; background: #fffbeb; padding: 2px 4px; border-radius: 4px;';
    case 'error':
      return 'color: #ef4444; font-weight: bold; background: #fef2f2; padding: 2px 4px; border-radius: 4px;';
    case 'debug':
      return 'color: #10b981; font-weight: bold; background: #ecfdf5; padding: 2px 4px; border-radius: 4px;';
  }
}

function getPrefix(level: LogLevel): string {
  switch (level) {
    case 'info':
      return 'ℹ️ [INFO]';
    case 'warn':
      return '⚠️ [WARN]';
    case 'error':
      return '❌ [ERROR]';
    case 'debug':
      return '🔍 [DEBUG]';
  }
}

function createLogger(context: string) {
  return {
    info: (message: string, ...optionalParams: unknown[]) => {
      if (isProduction) return;
      console.log(
        `%c${getPrefix('info')} [${context}]`,
        getStyle('info'),
        message,
        ...optionalParams
      );
    },
    warn: (message: string, ...optionalParams: unknown[]) => {
      console.warn(
        `%c${getPrefix('warn')} [${context}]`,
        getStyle('warn'),
        message,
        ...optionalParams
      );
    },
    error: (message: string, ...optionalParams: unknown[]) => {
      console.error(
        `%c${getPrefix('error')} [${context}]`,
        getStyle('error'),
        message,
        ...optionalParams
      );
    },
    debug: (message: string, ...optionalParams: unknown[]) => {
      if (isProduction) return;
      console.log(
        `%c${getPrefix('debug')} [${context}]`,
        getStyle('debug'),
        message,
        ...optionalParams
      );
    }
  };
}

export { createLogger };
export type { LogLevel };
