import { Router } from 'express';
import { existsSync, readdirSync, statSync, unlinkSync } from 'fs';
import { writeFile } from 'fs/promises';
import { basename, isAbsolute, join, relative, resolve } from 'path';
import { authMiddleware, requireRole } from '../middleware/auth.js';
import { createDatabaseBackup, restoreDatabaseBackup, verifyBackupFile } from '../services/backupService.js';
import { createLogger } from '../lib/logger.js';
import { writeAuditLog } from '../lib/auditLog.js';

const logger = createLogger('BackupsRoute');
const router = Router();

router.use(authMiddleware);

function getBackupDir(): string {
  return resolve(process.env.DB_BACKUP_DIR || './backups');
}

function isWithinDirectory(parent: string, child: string): boolean {
  const relativePath = relative(parent, child);
  return relativePath === '' || (!!relativePath && !relativePath.startsWith('..') && !isAbsolute(relativePath));
}

function isValidManagedBackupFilename(filename: string): boolean {
  return filename.startsWith('medsurvey_') && filename.endsWith('.sql.gz') && basename(filename) === filename;
}

function resolveManagedBackupPath(filename: string): string | null {
  if (!isValidManagedBackupFilename(filename)) return null;
  const backupDir = getBackupDir();
  const filepath = resolve(backupDir, filename);
  return isWithinDirectory(backupDir, filepath) ? filepath : null;
}

function getParamString(value: string | string[] | undefined): string {
  return Array.isArray(value) ? value[0] || '' : value || '';
}

function getBackupConfig() {
  return {
    enabled: process.env.DB_BACKUP_ENABLED !== 'false',
    retentionDays: Number(process.env.DB_BACKUP_RETENTION_DAYS) || 30,
    backupDir: process.env.DB_BACKUP_DIR || './backups',
  };
}

router.get('/', requireRole('super_admin', 'admin'), (_req, res) => {
  try {
    const backupDir = getBackupDir();
    if (!existsSync(backupDir)) {
      return res.json({ backups: [], config: getBackupConfig() });
    }

    const files = readdirSync(backupDir)
      .filter(isValidManagedBackupFilename)
      .map(filename => {
        const fullPath = join(backupDir, filename);
        const stat = statSync(fullPath);
        return {
          filename,
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
    res.status(500).json({ error: 'Failed to list backups' });
  }
});

router.get('/:filename/verify', requireRole('super_admin', 'admin'), (req, res) => {
  try {
    const filepath = resolveManagedBackupPath(getParamString(req.params.filename));
    if (!filepath) {
      return res.status(400).json({ error: 'Invalid backup filename' });
    }

    res.json(verifyBackupFile(filepath));
  } catch (error) {
    logger.error('Failed to verify backup:', error);
    res.status(500).json({ error: 'Failed to verify backup' });
  }
});

router.post('/', requireRole('super_admin', 'admin'), async (req, res) => {
  try {
    logger.info('Manual backup triggered via API');
    const filepath = await createDatabaseBackup();
    const verification = verifyBackupFile(filepath);
    const filename = basename(filepath);

    await writeAuditLog(req.user!.id, 'create_backup', {
      messageKey: 'audit.details.create_backup',
      params: { filename },
    });

    res.json({
      message: 'Backup created successfully',
      file: filepath,
      timestamp: new Date().toISOString(),
      verification,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to create backup';
    logger.error('Manual backup failed:', error);
    res.status(500).json({ error: message });
  }
});

router.delete('/:filename', requireRole('super_admin'), async (req, res) => {
  try {
    const filename = getParamString(req.params.filename);
    const filepath = resolveManagedBackupPath(filename);
    if (!filepath) {
      return res.status(400).json({ error: 'Invalid backup filename' });
    }

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'Backup file not found' });
    }

    unlinkSync(filepath);
    logger.info(`Backup file deleted: ${filename}`);

    await writeAuditLog(req.user!.id, 'delete_backup', {
      messageKey: 'audit.details.delete_backup',
      params: { filename },
    });

    res.json({ message: 'Backup deleted successfully', filename });
  } catch (error) {
    logger.error('Failed to delete backup:', error);
    res.status(500).json({ error: 'Failed to delete backup' });
  }
});

router.post('/:filename/restore', requireRole('super_admin'), async (req, res) => {
  try {
    const filename = getParamString(req.params.filename);
    const filepath = resolveManagedBackupPath(filename);
    if (!filepath) {
      return res.status(400).json({ error: 'Invalid backup filename' });
    }

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'Backup file not found' });
    }

    logger.info(`Manual restore triggered for: ${filename}`);
    await restoreDatabaseBackup(filepath);

    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename },
    });

    res.json({ message: 'Database restored successfully', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to restore backup';
    logger.error('Database restore failed:', error);
    res.status(500).json({ error: message });
  }
});

router.get('/:filename/download', requireRole('super_admin'), (req, res) => {
  try {
    const filename = getParamString(req.params.filename);
    const filepath = resolveManagedBackupPath(filename);
    if (!filepath) {
      return res.status(400).json({ error: 'Invalid backup filename' });
    }

    if (!existsSync(filepath)) {
      return res.status(404).json({ error: 'Backup file not found' });
    }

    res.download(filepath, filename);
  } catch (error) {
    logger.error('Failed to download backup:', error);
    res.status(500).json({ error: 'Failed to download backup' });
  }
});

router.post('/upload-restore', requireRole('super_admin'), async (req, res) => {
  const { filename, content } = req.body;
  const cleanFilename = typeof filename === 'string'
    ? `upload_${Date.now()}_${basename(filename).replace(/[^a-zA-Z0-9_.-]/g, '')}`
    : '';
  const filepath = cleanFilename ? resolve(getBackupDir(), cleanFilename) : '';

  try {
    if (!filename || !content) {
      return res.status(400).json({ error: 'Filename and content are required' });
    }

    if (typeof filename !== 'string' || !filename.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'Invalid backup extension. Expected .sql.gz' });
    }

    if (!filepath || !isWithinDirectory(getBackupDir(), filepath)) {
      return res.status(400).json({ error: 'Invalid backup filename' });
    }

    const buffer = Buffer.from(String(content), 'base64');
    await writeFile(filepath, buffer, { flag: 'wx' });

    const verification = verifyBackupFile(filepath);
    if (!verification.valid) {
      if (existsSync(filepath)) unlinkSync(filepath);
      return res.status(400).json({
        error: `Invalid backup file: ${verification.error || 'unsupported format'}`,
        verification,
      });
    }

    logger.info(`Starting restore from uploaded file: ${cleanFilename}`);
    await restoreDatabaseBackup(filepath);

    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename: `uploaded (${basename(filename)})` },
    });

    if (existsSync(filepath)) unlinkSync(filepath);
    res.json({ message: 'Database restored successfully from uploaded file', filename });
  } catch (error) {
    if (filepath && existsSync(filepath)) unlinkSync(filepath);
    const message = error instanceof Error ? error.message : 'Failed to restore uploaded backup';
    logger.error('Database restore from upload failed:', error);
    res.status(500).json({ error: message });
  }
});

router.post('/scan-external', requireRole('super_admin'), (req, res) => {
  try {
    const { directory } = req.body;
    if (!directory) {
      return res.status(400).json({ error: 'Directory is required' });
    }

    const backupDir = getBackupDir();
    const targetDir = resolve(String(directory));
    if (!isWithinDirectory(backupDir, targetDir)) {
      return res.status(403).json({ error: 'Directory is outside the configured backup directory' });
    }

    if (!existsSync(targetDir)) {
      return res.status(404).json({ error: 'Directory not found' });
    }

    const files = readdirSync(targetDir)
      .filter(f => f.endsWith('.sql.gz') && basename(f) === f)
      .map(filename => {
        const fullPath = join(targetDir, filename);
        const stat = statSync(fullPath);
        return {
          filename,
          sizeBytes: stat.size,
          sizeMb: parseFloat((stat.size / (1024 * 1024)).toFixed(2)),
          createdAt: stat.birthtime.toISOString(),
          modifiedAt: stat.mtime.toISOString(),
          fullPath,
        };
      })
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

    res.json({ backups: files });
  } catch (error) {
    logger.error('Failed to scan external backups:', error);
    res.status(500).json({ error: 'Failed to scan backup directory' });
  }
});

router.post('/restore-external', requireRole('super_admin'), async (req, res) => {
  try {
    const { filepath } = req.body;
    if (!filepath) {
      return res.status(400).json({ error: 'File path is required' });
    }

    const backupDir = getBackupDir();
    const cleanPath = resolve(String(filepath));
    if (!isWithinDirectory(backupDir, cleanPath)) {
      return res.status(403).json({ error: 'File is outside the configured backup directory' });
    }

    if (!existsSync(cleanPath)) {
      return res.status(404).json({ error: 'Backup file not found' });
    }

    if (!cleanPath.endsWith('.sql.gz')) {
      return res.status(400).json({ error: 'Expected a .sql.gz backup file' });
    }

    logger.info(`Manual external restore triggered for: ${cleanPath}`);
    await restoreDatabaseBackup(cleanPath);

    const filename = basename(cleanPath);
    await writeAuditLog(req.user!.id, 'restore_backup', {
      messageKey: 'audit.details.restore_backup',
      params: { filename: `external (${filename})` },
    });

    res.json({ message: 'Database restored successfully', filename });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Failed to restore backup';
    logger.error('External database restore failed:', error);
    res.status(500).json({ error: message });
  }
});

export default router;
