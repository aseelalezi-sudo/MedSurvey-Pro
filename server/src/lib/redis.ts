import Redis from 'ioredis';
import { createLogger } from './logger.js';

const logger = createLogger('Redis');
const REDIS_URL = process.env.REDIS_URL || 'redis://localhost:6379';

// Define the real Redis instance
const hasCustomRedis = !!process.env.REDIS_URL;
let hasParserError = false;

const redisInstance = new Redis(REDIS_URL, {
  lazyConnect: true, // Do not connect automatically on startup
  reconnectOnError: () => false, // Do not reconnect on parser/protocol errors
  retryStrategy: (times) => {
    if (hasParserError || !hasCustomRedis) {
      return null; // Stop retrying entirely
    }
    if (times <= 3 || times % 30 === 0) {
      logger.warn(`Retrying redis connection: attempt ${times}`);
    }
    return Math.min(times * 500, 30000);
  },
  maxRetriesPerRequest: null,
});

// Resilient in-memory fallback cache with TTL support
const inMemoryCache = new Map<string, { value: string; expiresAt: number | null }>();

let lastErrorLoggedAt = 0;
let hasLoggedFallback = false;

redisInstance.on('error', (err: any) => {
  const isConnRefused = err?.code === 'ECONNREFUSED';
  const isParserError = err?.buffer !== undefined || err?.offset !== undefined || err?.message?.includes('ParserError') || err?.message?.includes('HTTP/') || err?.message?.includes('ReplyError');
  
  if (isParserError) {
    hasParserError = true;
  }

  if (isConnRefused || isParserError || !hasCustomRedis) {
    if (!hasLoggedFallback) {
      logger.info('Redis is not available or in-memory mode active. MedSurvey Pro is automatically falling back to a highly-resilient, type-safe in-memory cache with TTL support.');
      hasLoggedFallback = true;
    }
    return;
  }

  const now = Date.now();
  if (now - lastErrorLoggedAt > 30000) {
    logger.error('Redis Client Error (using in-memory fallback):', err);
    lastErrorLoggedAt = now;
  }
});

redisInstance.on('connect', () => logger.info('Successfully connected to Redis'));
redisInstance.on('ready', () => logger.info('Redis is ready to accept commands'));

// If custom REDIS_URL is provided, initiate connection
if (hasCustomRedis) {
  redisInstance.connect().catch(() => {});
} else {
  if (!hasLoggedFallback) {
    logger.info('MedSurvey Pro is running in standalone mode (using highly-resilient, type-safe in-memory cache with TTL support).');
    hasLoggedFallback = true;
  }
}

// A resilient proxy wrapper around the Redis instance to provide in-memory fallback
export const redis = new Proxy(redisInstance, {
  get(target, prop, receiver) {
    // Intercept 'get' method
    if (prop === 'get') {
      return async (key: string): Promise<string | null> => {
        if (target.status === 'ready') {
          try {
            return await target.get(key);
          } catch (err) {
            logger.error(`Redis GET failed for key "${key}", falling back to in-memory:`, err);
          }
        }
        
        const entry = inMemoryCache.get(key);
        if (!entry) return null;
        
        if (entry.expiresAt && entry.expiresAt < Date.now()) {
          inMemoryCache.delete(key);
          return null;
        }
        
        return entry.value;
      };
    }

    // Intercept 'set' method (supports: set(key, value) or set(key, value, 'EX', seconds))
    if (prop === 'set') {
      return async (key: string, value: string, ...args: any[]): Promise<'OK' | string> => {
        if (target.status === 'ready') {
          try {
            return await target.set(key, value, ...args);
          } catch (err) {
            logger.error(`Redis SET failed for key "${key}", falling back to in-memory:`, err);
          }
        }
        
        let expiresAt: number | null = null;
        const exIdx = args.indexOf('EX');
        if (exIdx !== -1 && args[exIdx + 1]) {
          const seconds = parseInt(args[exIdx + 1]);
          if (!isNaN(seconds)) {
            expiresAt = Date.now() + (seconds * 1000);
          }
        }
        
        inMemoryCache.set(key, { value, expiresAt });
        return 'OK';
      };
    }

    // Intercept 'del' method
    if (prop === 'del') {
      return async (...keys: string[]): Promise<number> => {
        if (target.status === 'ready') {
          try {
            return await target.del(...keys);
          } catch (err) {
            logger.error(`Redis DEL failed for keys [${keys.join(', ')}], falling back to in-memory:`, err);
          }
        }
        let deletedCount = 0;
        for (const key of keys) {
          if (inMemoryCache.has(key)) {
            inMemoryCache.delete(key);
            deletedCount++;
          }
        }
        return deletedCount;
      };
    }

    // Intercept 'keys' method
    if (prop === 'keys') {
      return async (pattern: string): Promise<string[]> => {
        if (target.status === 'ready') {
          try {
            return await target.keys(pattern);
          } catch (err) {
            logger.error(`Redis KEYS failed for pattern "${pattern}", falling back to in-memory:`, err);
          }
        }
        
        const regexPattern = pattern.replace(/\*/g, '.*').replace(/\?/g, '.');
        const regex = new RegExp(`^${regexPattern}$`);
        
        const now = Date.now();
        const results: string[] = [];
        
        for (const [key, entry] of inMemoryCache.entries()) {
          if (entry.expiresAt && entry.expiresAt < now) {
            inMemoryCache.delete(key);
            continue;
          }
          if (regex.test(key)) {
            results.push(key);
          }
        }
        
        return results;
      };
    }

    // Default: forward all other properties/methods
    const value = Reflect.get(target, prop, receiver);
    return typeof value === 'function' ? value.bind(target) : value;
  }
});
