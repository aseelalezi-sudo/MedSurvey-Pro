import { app } from './app.js';
import { createLogger } from './lib/logger.js';
import { initArchiveScheduler } from './services/archiveService.js';

const logger = createLogger('Server');
const PORT = parseInt(process.env.PORT || '3001');

// Start server
app.listen(PORT, () => {
  logger.info(`🚀 MedSurvey Pro API Server`);
  logger.info(`   Running on: http://localhost:${PORT}`);
  logger.info(`   Health:     http://localhost:${PORT}/api/health`);
  
  // Initialize and run the automated archiving scheduler
  initArchiveScheduler();
});
