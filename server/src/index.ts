import { app } from './app.js';
import { createLogger } from './lib/logger.js';
import { initArchiveScheduler } from './services/archiveService.js';
import { initBackupScheduler } from './services/backupService.js';
import { ensureDefaultSuperAdmin } from './lib/initDb.js';

const logger = createLogger('Server');
const DEFAULT_PORT = process.env.NODE_ENV === 'production' ? '3001' : '4001';
const PORT = parseInt(process.env.PORT || DEFAULT_PORT);
const HOST = process.env.HOST || (process.env.NODE_ENV === 'production' ? '0.0.0.0' : '127.0.0.1');

// Start server
app.listen(PORT, HOST, async () => {
  logger.info(`🚀 MedSurvey Pro API Server`);
  logger.info(`   Running on: http://${HOST}:${PORT}`);
  logger.info(`   Health:     http://${HOST}:${PORT}/api/health`);
  
  // Ensure default super admin exists
  await ensureDefaultSuperAdmin();

  // Initialize and run the automated archiving scheduler
  initArchiveScheduler();

  // Initialize and run the automated database backup scheduler
  initBackupScheduler();
});
