import express from 'express';
import crypto from 'crypto';
import cors from 'cors';
import helmet from 'helmet';
import 'dotenv/config';
import cookieParser from 'cookie-parser';

import authRoutes from './routes/auth.js';
import userRoutes from './routes/users.js';
import surveyRoutes from './routes/surveys.js';
import responseRoutes from './routes/responses.js';
import ticketRoutes from './routes/tickets.js';
import settingsRoutes from './routes/settings.js';
import auditRoutes from './routes/audit.js';
import { sanitizeInput } from './middleware/sanitize.js';
import { performanceMiddleware, httpLogger } from './middleware/monitoring.js';
import monitoringRoutes from './routes/monitoring.js';
import errorLogRoutes from './routes/errorLogs.js';
import { errorCapture, setupGlobalErrorHandlers } from './middleware/errorCapture.js';
import * as Sentry from "@sentry/node";
import { nodeProfilingIntegration } from "@sentry/profiling-node";

// Initialize global error handlers
setupGlobalErrorHandlers();

const app = express();

// Initialize Sentry
if (process.env.SENTRY_DSN) {
  Sentry.init({
    dsn: process.env.SENTRY_DSN,
    integrations: [
      nodeProfilingIntegration(),
    ],
    tracesSampleRate: 1.0,
    profilesSampleRate: 1.0,
  });
}

// Security Headers
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'"],
      styleSrc: ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com"],
      fontSrc: ["'self'", "https://fonts.gstatic.com"],
      imgSrc: ["'self'", "data:", "blob:"],
      connectSrc: ["'self'"],
      frameAncestors: ["'none'"],
      formAction: ["'self'"],
    },
  },
  referrerPolicy: { policy: 'strict-origin-when-cross-origin' },
  crossOriginEmbedderPolicy: false,
}));

// Performance & Logging Middleware
app.use(httpLogger);
app.use(performanceMiddleware);

// CORS Configuration
const allowedOrigins = process.env.ALLOWED_ORIGINS
  ? process.env.ALLOWED_ORIGINS.split(',').map(o => o.trim())
  : ['http://localhost:8080', 'http://localhost:5173', 'http://localhost:3000', 'http://127.0.0.1:3000'];

app.use(cors({
  origin: (origin, callback) => {
    if (!origin) return callback(null, true);
    if (allowedOrigins.includes('*')) {
      callback(new Error('CORS: wildcard origin not allowed with credentials'), false);
      return;
    }
    if (allowedOrigins.indexOf(origin) !== -1) {
      callback(null, true);
    } else {
      callback(new Error('CORS Policy Blocked This Origin'), false);
    }
  },
  credentials: true,
}));
app.use(express.json({ limit: '10mb' }));
app.use(cookieParser());

// Cache-Control for all API routes
app.use('/api', (_req, res, next) => {
  res.set('Cache-Control', 'no-cache, no-store, must-revalidate');
  res.set('Pragma', 'no-cache');
  res.set('Expires', '0');
  next();
});

// XSS Sanitization
app.use(sanitizeInput);

// CSRF Protection (Double-Submit Cookie Pattern)
// The server issues a random CSRF token inside a readable cookie.
// The frontend must read it and send it back as an X-CSRF-Token header
// on every state-changing request (POST, PUT, PATCH, DELETE).
const CSRF_COOKIE = 'medsurvey_csrf';
const CSRF_HEADER = 'x-csrf-token';

// Paths that are exempt from CSRF checks (public / non-mutating)
const csrfExemptPaths = [
  '/api/auth/login',
  '/api/auth/refresh',
  '/api/auth/logout',
  '/api/responses',   // Public patient survey submission (POST only exempted below)
  '/api/health',
  '/api/error-logs/client',
];

// Issue a CSRF cookie if one doesn't exist yet
app.use('/api', (req, res, next) => {
  if (!req.cookies[CSRF_COOKIE]) {
    const token = crypto.randomBytes(32).toString('hex');
    res.cookie(CSRF_COOKIE, token, {
      httpOnly: false,   // Must be readable by frontend JS
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      maxAge: 24 * 60 * 60 * 1000, // 24 hours
    });
  }
  next();
});

// Validate CSRF token on mutating requests
app.use('/api', (req, res, next) => {
  const isMutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method);
  if (!isMutating) return next();

  // Check exemptions (use originalUrl which preserves the full path)
  const fullPath = req.originalUrl.split('?')[0]; // strip query string
  const isExempt = csrfExemptPaths.some(p => fullPath === p || fullPath.startsWith(p + '/'));
  if (isExempt) return next();

  const cookieToken = req.cookies[CSRF_COOKIE];
  const headerToken = req.headers[CSRF_HEADER] as string | undefined;

  if (!cookieToken || !headerToken || cookieToken !== headerToken) {
    res.status(403).json({ error: 'رمز CSRF غير صالح. يرجى تحديث الصفحة والمحاولة مجدداً.' });
    return;
  }

  next();
});

// Routes
app.use('/api/auth', authRoutes);
app.use('/api/users', userRoutes);
app.use('/api/surveys', surveyRoutes);
app.use('/api/responses', responseRoutes);
app.use('/api/tickets', ticketRoutes);
app.use('/api/settings', settingsRoutes);
app.use('/api/audit', auditRoutes);
app.use('/api/monitoring', monitoringRoutes);
app.use('/api/error-logs', errorLogRoutes);

// Health check
app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Sentry Error Handler (must be after all controllers)
if (process.env.SENTRY_DSN) {
  Sentry.setupExpressErrorHandler(app);
}

// Global error capture (must be last)
app.use(errorCapture);

export { app };
