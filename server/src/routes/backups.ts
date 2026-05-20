import { Router } from 'express';
import { readdirSync, statSync, existsSync, unlinkSync } from 'fs';
import { join, resolve } from 'path';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createDatabaseBackup, restoreDatabaseBackup, verifyBackupFile, invalidateAllCaches, BackupVerification } from '../services/backupService.js';
import { createLogger } from '../lib/logger.js';
import { writeAuditLog } from '../lib/auditLog.js';

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
router.delete('/:filename', async (req, res) => {
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

    await writeAuditLog(req.user!.id, 'delete_backup', {
      messageKey: 'audit.details.delete_backup',
      params: { filename },
    });

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
router.post('/', async (req, res) => {
  try {
    logger.info('Manual backup triggered via API');
    const filepath = await createDatabaseBackup();
    const verification = verifyBackupFile(filepath);
    const filename = filepath.split(/[/\\]/).pop() || filepath;

    await writeAuditLog(req.user!.id, 'create_backup', {
      messageKey: 'audit.details.create_backup',
      params: { filename },
    });

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

    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename },
    });

    res.json({ message: 'تم استعادة قاعدة البيانات بنجاح', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'فشل في استعادة قاعدة البيانات';
    logger.error('Database restore failed:', error);
    res.status(500).json({ error: message });
  }
});

/**
 * GET /api/backups/:filename/download
 * Download a specific backup file
 */
router.get('/:filename/download', (req, res) => {
  try {
    const filename = req.params.filename;
    if (!filename.startsWith('medsurvey_') || !filename.endsWith('.sql.gz') || filename.includes('..') || filename.includes('/') || filename.includes('\\')) {
      return res.status(400).json({ error: 'اسم ملف غير صالح' });
    }

    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    const filepath = join(backupDir, filename);

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'الملف غير موجود' });
    }

    res.download(filepath, filename);
  } catch (error) {
    logger.error('Failed to download backup:', error);
    res.status(500).json({ error: 'فشل في تحميل الملف' });
  }
});

/**
 * POST /api/backups/upload-restore
 * Restore a database backup from an uploaded base64 string
 */
router.post('/upload-restore', async (req, res) => {
  try {
    const { filename, content } = req.body;
    if (!filename || !content) {
      return res.status(400).json({ error: 'الاسم أو المحتوى للملف مطلوب' });
    }

    if (!filename.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'امتداد الملف غير صالح. يجب أن يكون .sql.gz' });
    }

    // Safety clean filename
    const cleanFilename = 'upload_' + Date.now() + '_' + filename.replace(/[^a-zA-Z0-9_.-]/g, '');
    const backupDir = resolve(process.env.DB_BACKUP_DIR || './backups');
    const filepath = join(backupDir, cleanFilename);

    // Decode and save the file
    const buffer = Buffer.from(content, 'base64');
    const { writeFileSync } = await import('fs');
    writeFileSync(filepath, buffer);

    logger.info(`Uploaded backup saved to: ${filepath}`);

    // Verify it
    const verification = verifyBackupFile(filepath);
    if (!verification.valid) {
      if (existsSync(filepath)) unlinkSync(filepath);
      return res.status(400).json({ 
        error: `الملف غير صالح للاستعادة: ${verification.error || 'تنسيق غير مدعوم'}`,
        verification 
      });
    }

    // Perform restore
    logger.info(`Starting restore from uploaded file: ${cleanFilename}`);
    await restoreDatabaseBackup(filepath);

    // Write audit log
    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename: `مرفوع (${filename})` },
    });

    // Clean up uploaded file
    if (existsSync(filepath)) unlinkSync(filepath);

    res.json({ message: 'تم استعادة قاعدة البيانات بنجاح من الملف المرفوع', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'فشل في استعادة قاعدة البيانات';
    logger.error('Database restore from upload failed:', error);
    res.status(500).json({ error: message });
  }
});

/**
 * POST /api/backups/scan-external
 * List backup files in an arbitrary directory on the server
 */
router.post('/scan-external', (req, res) => {
  try {
    const { directory } = req.body;
    if (!directory) {
      return res.status(400).json({ error: 'مسار المجلد مطلوب' });
    }

    const targetDir = resolve(directory);
    if (!existsSync(targetDir)) {
      return res.status(404).json({ error: 'المجلد المحدد غير موجود' });
    }

    const files = readdirSync(targetDir)
      .filter(f => f.endsWith('.sql.gz'))
      .map(f => {
        const fullPath = join(targetDir, f);
        const stat = statSync(fullPath);
        return {
          filename: f,
          sizeBytes: stat.size,
          sizeMb: parseFloat((stat.size / (1024 * 1024)).toFixed(2)),
          createdAt: stat.birthtime.toISOString(),
          modifiedAt: stat.mtime.toISOString(),
          fullPath: fullPath,
        };
      })
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

    res.json({ backups: files });
  } catch (error) {
    logger.error('Failed to scan external backups:', error);
    res.status(500).json({ error: 'فشل في قراءة محتويات المجلد المحدد' });
  }
});

/**
 * POST /api/backups/restore-external
 * Restore a backup file from an arbitrary file path on the server
 */
router.post('/restore-external', async (req, res) => {
  try {
    const { filepath } = req.body;
    if (!filepath) {
      return res.status(400).json({ error: 'مسار الملف مطلوب' });
    }

    const cleanPath = resolve(filepath);
    if (!existsSync(cleanPath)) {
      return res.status(404).json({ error: 'ملف النسخة الاحتياطية غير موجود' });
    }

    if (!cleanPath.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'الملف يجب أن يكون بامتداد .sql.gz' });
    }

    logger.info(`Manual external restore triggered for: ${cleanPath}`);
    await restoreDatabaseBackup(cleanPath);

    const filename = cleanPath.split(/[/\\]/).pop() || cleanPath;

    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename: `خارجي (${filename})` },
    });

    res.json({ message: 'تم استعادة قاعدة البيانات بنجاح', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'فشل في استعادة قاعدة البيانات';
    logger.error('External database restore failed:', error);
    res.status(500).json({ error: message });
  }
});

export default router;
