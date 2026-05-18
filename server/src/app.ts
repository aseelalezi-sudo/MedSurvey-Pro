import express from 'express';
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
      scriptSrc: ["'self'", "'unsafe-inline'"],
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
