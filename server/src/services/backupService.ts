import { spawn, execSync } from 'child_process';
import { createReadStream, createWriteStream, existsSync, mkdirSync, readdirSync, statSync, unlinkSync, openSync, readSync, closeSync } from 'fs';
import { join, resolve } from 'path';
import { createGzip, createGunzip, gunzipSync } from 'zlib';
import { pipeline } from 'stream/promises';
import { Readable } from 'stream';
import cron from 'node-cron';
import { createLogger } from '../lib/logger.js';
import { redis } from '../lib/redis.js';

const logger = createLogger('BackupService');

interface DBConfig {
  host: string;
  port: number;
  user: string;
  password: string;
  database: string;
}

const COMMON_MYSQL_PATHS: string[] = [];

// Common Windows MySQL installation paths
const WINDOWS_PATHS = [
  'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin',
  'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin',
  'C:\\Program Files\\MySQL\\MySQL Server 9.0\\bin',
  'C:\\Program Files\\MySQL\\MySQL Server 9.1\\bin',
  'C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin',
  'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin',
  'C:\\xampp\\mysql\\bin',
  'C:\\wamp64\\bin\\mysql\\mysql*\\bin',
  'C:\\wamp\\bin\\mysql\\mysql*\\bin',
];

const isWindows = process.platform === 'win32';

function findMysqldump(): string {
  // 1. Try PATH first
  try {
    const whichCmd = isWindows ? 'where' : 'which';
    const result = execSync(`${whichCmd} mysqldump`, { encoding: 'utf8', timeout: 5000 }).trim();
    if (result) {
      const exePath = result.split('\n')[0].trim();
      if (existsSync(exePath)) {
        logger.info(`Found mysqldump in PATH: ${exePath}`);
        return exePath;
      }
    }
  } catch {
    // not in PATH
  }

  // 2. Try common Windows paths
  if (isWindows) {
    for (const base of WINDOWS_PATHS) {
      // Handle wildcard patterns (e.g. mysql*)
      if (base.includes('*')) {
        const parentDir = base.substring(0, base.lastIndexOf('\\'));
        const pattern = base.substring(base.lastIndexOf('\\') + 1);
        if (existsSync(parentDir)) {
          try {
            const dirs = readdirSync(parentDir);
            for (const dir of dirs) {
              if (dir.startsWith(pattern.replace('*', ''))) {
                const candidate = join(parentDir, dir, 'mysqldump.exe');
                if (existsSync(candidate)) {
                  logger.info(`Found mysqldump at: ${candidate}`);
                  return candidate;
                }
              }
            }
          } catch {
            // skip
          }
        }
      } else {
        const candidate = join(base, 'mysqldump.exe');
        if (existsSync(candidate)) {
          logger.info(`Found mysqldump at: ${candidate}`);
          return candidate;
        }
      }
    }
  }

  // 3. Try common Linux paths
  const linuxPaths = [
    '/usr/bin/mysqldump',
    '/usr/local/bin/mysqldump',
    '/opt/homebrew/bin/mysqldump',
  ];
  for (const p of linuxPaths) {
    if (existsSync(p)) {
      logger.info(`Found mysqldump at: ${p}`);
      return p;
    }
  }

  throw new Error(
    'mysqldump not found. Please install MySQL client tools.\n' +
    '  - Windows: Add MySQL bin directory to PATH or re-run MySQL installer and check "MySQL Command Line Client"\n' +
    '    Common paths: C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\n' +
    '    Or from XAMPP: C:\\xampp\\mysql\\bin\n' +
    '  - Ubuntu/Debian: apt-get install mysql-client\n' +
    '  - macOS: brew install mysql-client'
  );
}

function findMysql(): string {
  try {
    const whichCmd = isWindows ? 'where' : 'which';
    const result = execSync(`${whichCmd} mysql`, { encoding: 'utf8', timeout: 5000 }).trim();
    if (result) {
      const exePath = result.split('\n')[0].trim();
      if (existsSync(exePath)) {
        logger.info(`Found mysql in PATH: ${exePath}`);
        return exePath;
      }
    }
  } catch {
    // not in PATH
  }

  if (isWindows) {
    for (const base of WINDOWS_PATHS) {
      if (base.includes('*')) {
        const parentDir = base.substring(0, base.lastIndexOf('\\'));
        const pattern = base.substring(base.lastIndexOf('\\') + 1);
        if (existsSync(parentDir)) {
          try {
            const dirs = readdirSync(parentDir);
            for (const dir of dirs) {
              if (dir.startsWith(pattern.replace('*', ''))) {
                const candidate = join(parentDir, dir, 'mysql.exe');
                if (existsSync(candidate)) {
                  logger.info(`Found mysql at: ${candidate}`);
                  return candidate;
                }
              }
            }
          } catch {
            // skip
          }
        }
      } else {
        const candidate = join(base, 'mysql.exe');
        if (existsSync(candidate)) {
          logger.info(`Found mysql at: ${candidate}`);
          return candidate;
        }
      }
    }
  }

  const linuxPaths = [
    '/usr/bin/mysql',
    '/usr/local/bin/mysql',
    '/opt/homebrew/bin/mysql',
  ];
  for (const p of linuxPaths) {
    if (existsSync(p)) {
      logger.info(`Found mysql at: ${p}`);
      return p;
    }
  }

  throw new Error(
    'mysql client not found. Please install MySQL client tools.\n' +
    '  - Windows: Add MySQL bin directory to PATH\n' +
    '  - Ubuntu/Debian: apt-get install mysql-client\n' +
    '  - macOS: brew install mysql-client'
  );
}

function parseDatabaseUrl(url: string): DBConfig {
  try {
    const parsed = new URL(url);
    return {
      host: parsed.hostname,
      port: Number(parsed.port) || 3306,
      user: decodeURIComponent(parsed.username),
      password: decodeURIComponent(parsed.password),
      database: parsed.pathname.replace(/^\//, ''),
    };
  } catch {
    throw new Error(`Cannot parse DATABASE_URL: ${url}`);
  }
}

function getBackupDir(): string {
  return resolve(process.env.DB_BACKUP_DIR || './backups');
}

function getRetentionDays(): number {
  return Number(process.env.DB_BACKUP_RETENTION_DAYS) || 30;
}

function getMaxBackupSizeMb(): number {
  return Number(process.env.DB_BACKUP_MAX_SIZE_MB) || 0;
}

export interface BackupVerification {
  valid: boolean;
  filename: string;
  sizeBytes: number;
  sizeMb: number;
  hasDatabaseSelection: boolean;
  databaseName: string | null;
  tableCount: number;
  hasData: boolean;
  estimatedRows: number;
  error: string | null;
  checkedAt: string;
}

const VERIFY_READ_LIMIT = 1024 * 1024; // read first 1MB of decompressed content

export function verifyBackupFile(filepath: string): BackupVerification {
  const result: BackupVerification = {
    valid: false,
    filename: '',
    sizeBytes: 0,
    sizeMb: 0,
    hasDatabaseSelection: false,
    databaseName: null,
    tableCount: 0,
    hasData: false,
    estimatedRows: 0,
    error: null,
    checkedAt: new Date().toISOString(),
  };

  try {
    if (!existsSync(filepath)) {
      result.error = 'File not found';
      return result;
    }

    const stat = statSync(filepath);
    result.filename = filepath.split(/[/\\]/).pop() || '';
    result.sizeBytes = stat.size;
    result.sizeMb = parseFloat((stat.size / (1024 * 1024)).toFixed(2));

    if (stat.size === 0) {
      result.error = 'File is empty (0 bytes)';
      return result;
    }

    // Read the full gzip file
    const fd = openSync(filepath, 'r');
    const compressed = Buffer.alloc(stat.size);
    readSync(fd, compressed, 0, compressed.length, 0);
    closeSync(fd);

    let content: string;
    try {
      content = gunzipSync(compressed).toString('utf-8');
    } catch {
      result.error = 'Invalid gzip file';
      return result;
    }

    // Check for database selection
    const dbMatch = content.match(/CREATE DATABASE\s+(?:IF NOT EXISTS\s+)?[`"']?(\w+)[`"']?/i);
    const useMatch = content.match(/USE\s+[`"']?(\w+)[`"']?/i);
    result.hasDatabaseSelection = !!(dbMatch || useMatch);
    result.databaseName = (dbMatch?.[1] || useMatch?.[1] || null);

    // Count tables
    const tableMatches = content.match(/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?[`"']?(\w+)[`"']?/gi);
    result.tableCount = tableMatches?.length || 0;

    // Check for data
    const insertMatches = content.match(/INSERT INTO\s/gi);
    result.hasData = (insertMatches?.length || 0) > 0;
    result.estimatedRows = insertMatches?.length || 0;

    // Determine validity — backups without CREATE DATABASE/USE are valid
    // (they target the database specified in the mysql connection)
    if (result.tableCount === 0) {
      result.error = 'No tables found in backup';
    } else {
      result.valid = true;
    }
  } catch (err) {
    result.error = `Verification failed: ${err instanceof Error ? err.message : String(err)}`;
  }

  return result;
}

export async function createDatabaseBackup(): Promise<string> {
  const databaseUrl = process.env.DATABASE_URL;
  if (!databaseUrl) {
    throw new Error('DATABASE_URL is not set');
  }

  const config = parseDatabaseUrl(databaseUrl);
  const backupDir = getBackupDir();

  if (!existsSync(backupDir)) {
    mkdirSync(backupDir, { recursive: true });
  }

  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').replace('T', '_').slice(0, 19);
  const filename = `medsurvey_${config.database}_${timestamp}.sql.gz`;
  const filepath = join(backupDir, filename);

  logger.info(`Starting database backup to: ${filepath}`);

  const mysqldumpPath = findMysqldump();

  return new Promise((resolvePromise, reject) => {
    const mysqldump = spawn(mysqldumpPath, [
      `--host=${config.host}`,
      `--port=${config.port}`,
      `--user=${config.user}`,
      `--password=${config.password}`,
      '--routines',
      '--triggers',
      '--single-transaction',
      '--quick',
      '--lock-tables=false',
      '--add-drop-table',
      '--set-gtid-purged=OFF',
      config.database,
    ], {
      stdio: ['ignore', 'pipe', 'pipe'],
      timeout: 300_000,
    });

    const gzip = createGzip();
    const outStream = createWriteStream(filepath);

    let stderr = '';
    mysqldump.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString(); });

    let exitCode: number | null = null;
    let resolved = false;

    mysqldump.on('error', (err) => {
      if (!resolved) {
        resolved = true;
        reject(new Error(`Failed to start mysqldump: ${err.message}`));
      }
    });

    mysqldump.on('exit', (code) => {
      exitCode = code;
    });

    pipeline(mysqldump.stdout!, gzip, outStream)
      .then(async () => {
        if (resolved) return;
        if (exitCode !== null && exitCode !== 0) {
          resolved = true;
          if (existsSync(filepath)) unlinkSync(filepath);
          reject(new Error(`mysqldump exited with code ${exitCode}: ${stderr.trim() || 'unknown error'}`));
          return;
        }

        const size = statSync(filepath).size;
        const sizeMb = (size / (1024 * 1024)).toFixed(2);

        if (size === 0) {
          resolved = true;
          unlinkSync(filepath);
          reject(new Error(`Backup file is empty. mysqldump may have failed. Stderr: ${stderr.trim() || 'none'}`));
          return;
        }

        logger.info(`Backup completed: ${filename} (${sizeMb} MB)`);

        if (stderr) {
          logger.warn(`mysqldump warnings: ${stderr.trim()}`);
        }

        const maxSize = getMaxBackupSizeMb();
        if (maxSize > 0 && size > maxSize * 1024 * 1024) {
          logger.warn(`Backup file (${sizeMb} MB) exceeds limit (${maxSize} MB).`);
        }

        await cleanOldBackups();
        resolved = true;
        resolvePromise(filepath);
      })
      .catch((err) => {
        if (!resolved) {
          resolved = true;
          if (existsSync(filepath)) unlinkSync(filepath);
          reject(new Error(`Backup failed: ${err.message}${stderr ? ` - ${stderr.trim()}` : ''}`));
        }
      });
  });
}

export async function restoreDatabaseBackup(filepath: string): Promise<void> {
  if (!existsSync(filepath)) {
    throw new Error(`Backup file not found: ${filepath}`);
  }

  if (!filepath.endsWith('.sql.gz')) {
    throw new Error('Invalid backup file format. Expected .sql.gz');
  }

  const databaseUrl = process.env.DATABASE_URL;
  if (!databaseUrl) {
    throw new Error('DATABASE_URL is not set');
  }

  const config = parseDatabaseUrl(databaseUrl);
  const mysqlPath = findMysql();

  logger.info(`Starting database restore from: ${filepath}`);

  return new Promise((resolvePromise, reject) => {
    const gunzip = createGunzip();
    const fileStream = createReadStream(filepath);

    const mysql = spawn(mysqlPath, [
      `--host=${config.host}`,
      `--port=${config.port}`,
      `--user=${config.user}`,
      `--password=${config.password}`,
      config.database,
    ], {
      stdio: ['pipe', 'pipe', 'pipe'],
      timeout: 600_000,
    });

    let stderr = '';
    let resolved = false;

    mysql.on('error', (err) => {
      if (!resolved) {
        resolved = true;
        reject(new Error(`Failed to start mysql: ${err.message}`));
      }
    });

    mysql.on('exit', async (code) => {
      if (!resolved) {
        resolved = true;
        if (code === 0) {
          logger.info('Database restore completed successfully');
          // Invalidate all caches after successful restore
          await invalidateAllCaches();
          resolvePromise();
        } else {
          reject(new Error(`mysql exited with code ${code}: ${stderr.trim() || 'unknown error'}`));
        }
      }
    });

    mysql.stderr?.on('data', (chunk: Buffer) => { stderr += chunk.toString(); });
    mysql.stdout?.on('data', () => { /* discard */ });

    // Prepend SET FOREIGN_KEY_CHECKS=0 and append SET FOREIGN_KEY_CHECKS=1
    // to prevent cascade delete errors during DROP TABLE statements in the backup
    const prefixStream = Readable.from(Buffer.from('SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";\n'));
    const suffixSQL = Buffer.from('\nSET FOREIGN_KEY_CHECKS=1;\n');

    // First write the prefix
    prefixStream.pipe(mysql.stdin!, { end: false });
    prefixStream.on('end', () => {
      // Then pipe the decompressed backup file
      const decompressed = fileStream.pipe(gunzip);
      decompressed.pipe(mysql.stdin!, { end: false });
      decompressed.on('end', () => {
        // Write suffix and close stdin
        mysql.stdin!.end(suffixSQL);
      });
      decompressed.on('error', (err: NodeJS.ErrnoException) => {
        if (err.code === 'EPIPE') {
          logger.debug('mysql stdin closed (expected on successful restore)');
          return;
        }
        if (!resolved) {
          resolved = true;
          mysql.kill();
          reject(new Error(`Restore pipeline failed: ${err.message}`));
        }
      });
    });

    mysql.stdin!.on('error', (err: NodeJS.ErrnoException) => {
      if (err.code === 'EPIPE') {
        logger.debug('mysql stdin EPIPE (expected on successful restore)');
        return;
      }
      if (!resolved) {
        resolved = true;
        reject(new Error(`mysql stdin error: ${err.message}`));
      }
    });
  });
}

/**
 * Invalidate all application caches in Redis after a database restore.
 * This prevents stale cached data from causing ghost records that
 * disappear when any mutation (like survey delete) triggers a cache refresh.
 */
export async function invalidateAllCaches(): Promise<void> {
  try {
    logger.info('Invalidating all application caches after database restore...');

    // Update cache version keys to force cache misses
    const now = Date.now().toString();
    await redis.set('surveys_cache_version', now);
    await redis.set('dashboard_stats_version', now);

    // Delete known cache key patterns
    const patterns = ['surveys:*', 'dashboard:*', 'stats:*', 'responses:*'];
    for (const pattern of patterns) {
      try {
        const keys = await redis.keys(pattern);
        if (keys.length > 0) {
          await redis.del(...keys);
          logger.info(`Cleared ${keys.length} cache keys matching '${pattern}'`);
        }
      } catch (err) {
        logger.warn(`Failed to clear cache keys for pattern '${pattern}':`, err);
      }
    }

    logger.info('All application caches invalidated successfully');
  } catch (err) {
    logger.error('Failed to invalidate caches (non-fatal):', err);
  }
}

async function cleanOldBackups(): Promise<void> {
  const backupDir = getBackupDir();
  const retentionDays = getRetentionDays();

  if (!existsSync(backupDir)) return;

  const cutoff = Date.now() - retentionDays * 24 * 60 * 60 * 1000;
  let cleaned = 0;

  try {
    const files = readdirSync(backupDir);
    for (const file of files) {
      if (!file.startsWith('medsurvey_') || !file.endsWith('.sql.gz')) continue;
      const filepath = join(backupDir, file);
      const stat = statSync(filepath);
      if (stat.mtimeMs < cutoff) {
        unlinkSync(filepath);
        cleaned++;
      }
    }
    if (cleaned > 0) {
      logger.info(`Cleaned up ${cleaned} old backup(s) older than ${retentionDays} days.`);
    }
  } catch (error) {
    logger.error('Error cleaning old backups:', error);
  }
}

export function initBackupScheduler(): void {
  const enabled = process.env.DB_BACKUP_ENABLED !== 'false';
  if (!enabled) {
    logger.info('Database backup scheduler is disabled via DB_BACKUP_ENABLED=false');
    return;
  }

  logger.info('Initializing database backup scheduler...');

  setTimeout(() => {
    createDatabaseBackup().catch((err) => {
      logger.error('Error in initial database backup:', err);
    });
  }, 10 * 60 * 1000);

  cron.schedule('0 3 * * *', () => {
    logger.info('Triggering scheduled daily database backup...');
    createDatabaseBackup().catch((err) => {
      logger.error('Error in scheduled database backup:', err);
    });
  });

  logger.info('Database backup scheduled daily at 3:00 AM (initial run delayed 10 min).');
}
