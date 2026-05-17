import { app } from './app.js';
import { createLogger } from './lib/logger.js';
import { initArchiveScheduler } from './services/archiveService.js';

const logger = createLogger('Server');
const DEFAULT_PORT = process.env.NODE_ENV === 'production' ? '3001' : '4001';
const PORT = parseInt(process.env.PORT || DEFAULT_PORT);
const HOST = process.env.HOST || (process.env.NODE_ENV === 'production' ? '0.0.0.0' : '127.0.0.1');

// Start server
app.listen(PORT, HOST, () => {
  logger.info(`🚀 MedSurvey Pro API Server`);
  logger.info(`   Running on: http://${HOST}:${PORT}`);
  logger.info(`   Health:     http://${HOST}:${PORT}/api/health`);
  
  // Initialize and run the automated archiving scheduler
  initArchiveScheduler();
});
