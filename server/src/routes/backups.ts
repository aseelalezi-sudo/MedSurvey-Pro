import { Router } from 'express';
import { readdirSync, statSync, existsSync, unlinkSync } from 'fs';
import { join, resolve } from 'path';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createDatabaseBackup, restoreDatabaseBackup, verifyBackupFile, BackupVerification } from '../services/backupService.js';
import { createLogger } from '../lib/logger.js';

const logger = createLogger('BackupsRoute');
const router = Router();

// All backup routes require authentication and super_admin/admin role
router.use(authMiddleware);
router.use(requireRole('super_admin', 'admin'));

/**
 * GET /api/backups
 * List all backup files with metadata
 */
router.get('/', (_req, res) => {
  try {
    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    if (!existsSync(backupDir)) {
      return res.json({ backups: [], config: getBackupConfig() });
    }

    const files = readdirSync(backupDir)
      .filter(f => f.startsWith('medsurvey_') && f.endsWith('.sql.gz'))
      .map(f => {
        const fullPath = join(backupDir, f);
        const stat = statSync(fullPath);
        return {
          filename: f,
          sizeBytes: stat.size,
          sizeMb: parseFloat((stat.size / (1024 * 1024)).toFixed(2)),
          createdAt: stat.birthtime.toISOString(),
          modifiedAt: stat.mtime.toISOString(),
        };
      })
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

    res.json({ backups: files, config: getBackupConfig() });
  } catch (error) {
    logger.error('Failed to list backups:', error);
    res.status(500).json({ error: 'فشل في عرض قائمة النسخ الاحتياطية' });
  }
});

/**
 * GET /api/backups/:filename/verify
 * Verify a backup file's integrity and contents
 */
router.get('/:filename/verify', (req, res) => {
  try {
    const filename = req.params.filename;
    if (!filename.startsWith('medsurvey_') || !filename.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'اسم ملف غير صالح' });
    }

    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    const filepath = join(backupDir, filename);

    const verification = verifyBackupFile(filepath);
    res.json(verification);
  } catch (error) {
    logger.error('Failed to verify backup:', error);
    res.status(500).json({ error: 'فشل في التحقق من الملف' });
  }
});

function getBackupConfig() {
  return {
    enabled: process.env.DB_BACKUP_ENABLED !== 'false',
    retentionDays: Number(process.env.DB_BACKUP_RETENTION_DAYS) || 30,
    backupDir: process.env.DB_BACKUP_DIR || './backups',
  };
}

/**
 * DELETE /api/backups/:filename
 * Delete a specific backup file
 */
router.delete('/:filename', (req, res) => {
  try {
    const filename = req.params.filename;
    // Security: only allow deleting medsurvey_ files
    if (!filename.startsWith('medsurvey_') || !filename.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'اسم ملف غير صالح' });
    }

    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    const filepath = join(backupDir, filename);

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'الملف غير موجود' });
    }

    unlinkSync(filepath);
    logger.info(`Backup file deleted: ${filename}`);

    res.json({ message: 'تم حذف الملف بنجاح', filename });
  } catch (error) {
    logger.error('Failed to delete backup:', error);
    res.status(500).json({ error: 'فشل في حذف الملف' });
  }
});

/**
 * POST /api/backups
 * Trigger a manual database backup
 */
router.post('/', async (_req, res) => {
  try {
    logger.info('Manual backup triggered via API');
    const filepath = await createDatabaseBackup();
    const verification = verifyBackupFile(filepath);
    res.json({
      message: 'تم إنشاء نسخة احتياطية بنجاح',
      file: filepath,
      timestamp: new Date().toISOString(),
      verification,
    });
  } catch (error) {
      const message = error instanceof Error ? error.message : 'فشل في إنشاء النسخة الاحتياطية';
    logger.error('Manual backup failed:', error);
    res.status(500).json({ error: message });
  }
});

/**
 * POST /api/backups/:filename/restore
 * Restore a database backup
 */
router.post('/:filename/restore', async (req, res) => {
  try {
    const filename = req.params.filename;
    if (!filename.startsWith('medsurvey_') || !filename.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'اسم ملف غير صالح' });
    }

    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    const filepath = join(backupDir, filename);

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'الملف غير موجود' });
    }

    logger.info(`Manual restore triggered for: ${filename}`);
    await restoreDatabaseBackup(filepath);
    res.json({ message: 'تم استعادة قاعدة البيانات بنجاح', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'فشل في استعادة قاعدة البيانات';
    logger.error('Database restore failed:', error);
    res.status(500).json({ error: message });
  }
});

export default router;
