/**
 * Manual database backup script.
 * Usage: npx tsx src/utils/backup.ts
 */
import { config } from 'dotenv';
import { resolve } from 'path';

// Load .env from server root
config({ path: resolve(import.meta.dirname, '../../.env') });

const { createDatabaseBackup } = await import('../services/backupService.js');

try {
  const filepath = await createDatabaseBackup();
  console.log(`\n✅ Database backup created successfully:`);
  console.log(`   ${filepath}`);
  process.exit(0);
} catch (error) {
  console.error(`\n❌ Database backup failed:`);
  console.error(`   ${error instanceof Error ? error.message : error}`);
  process.exit(1);
}
