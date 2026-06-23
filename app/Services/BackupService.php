<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class BackupService
{
    private const MAX_RESTORE_BYTES = 50 * 1024 * 1024;

    /**
     * Dangerous SQL patterns that should never appear in a legitimate mysqldump backup.
     * These could be used for privilege escalation, file system access, or code execution.
     */
    private const DANGEROUS_SQL_PATTERNS = [
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
        '/\bLOAD\s+DATA\b/i',
        '/\bLOAD_FILE\s*\(/i',
        '/\bSYSTEM\s*\(/i',
        '/(?:^|\s)\\\\!\s*(?:system|exec|shell|sh)/i',
        '/\bSOURCE\s+/i',
        '/\bCREATE\s+(?:FUNCTION|PROCEDURE|TRIGGER|EVENT)\b/i',
        '/\bGRANT\s+/i',
        '/\bREVOKE\s+/i',
        '/\bCREATE\s+USER\b/i',
        '/\bALTER\s+USER\b/i',
        '/\bDROP\s+USER\b/i',
        '/\bSET\s+GLOBAL\b/i',
        '/\bSHUTDOWN\b/i',
    ];

    private SettingsService $settingsService;

    private ?array $cachedSettings = null;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function list(): array
    {
        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);

        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql') || str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => $this->fileInfo($file->getPathname()))
            ->values();

        $settings = $this->getSettings();

        return [
            'backups' => $backups,
            'config' => [
                'enabled' => true,
                'retentionDays' => (int) ($settings['retentionDays'] ?? 30),
                'backupDir' => $backupDir,
                'displayBackupDir' => $settings['backupDir']
                    ?? config('medsurvey.backup.backup_dir')
                    ?? 'storage/app/backups',
                'schedule' => $settings['schedule'] ?? '03:00',
                'compressGzip' => filter_var($settings['compressGzip'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    }

    public function create(): array
    {
        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);

        $filename = 'medsurvey_backup_'.now()->format('Ymd_His').'.sql';
        $path = $backupDir.DIRECTORY_SEPARATOR.$filename;

        $this->runMysqldump($path);

        $settings = $this->getSettings();
        $compressGzip = filter_var($settings['compressGzip'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($compressGzip) {
            $gzFilename = $filename.'.gz';
            $gzPath = $path.'.gz';

            $this->compressFile($path, $gzPath);

            if (! File::exists($gzPath) || File::size($gzPath) === 0) {
                throw new \RuntimeException('Backup compression failed. The original SQL file was kept.');
            }

            File::delete($path);
            $path = $gzPath;
            $filename = $gzFilename;
        }

        return [
            'message' => 'Backup created and compressed successfully',
            'file' => $path,
            'timestamp' => now()->toISOString(),
            'verification' => $this->verificationPayload($filename),
        ];
    }

    public function restore(string $path): void
    {
        if (File::size($path) > self::MAX_RESTORE_BYTES) {
            throw new \RuntimeException('Backup file is too large to restore through the web interface.');
        }

        $this->validateSqlContent($path);
        $this->runMysqlRestore($path);
        $this->finalizeRestore();
    }

    /**
     * Scan SQL content for dangerous patterns before allowing restore.
     */
    private function validateSqlContent(string $path): void
    {
        $stream = $this->openSqlStream($path);
        $tail = '';

        try {
            while (! $this->sqlStreamEof($stream, $path)) {
                $chunk = $this->readSqlChunk($stream, $path);
                if ($chunk === '') {
                    break;
                }

                $scanBuffer = $tail.$chunk;
                foreach (self::DANGEROUS_SQL_PATTERNS as $pattern) {
                    if (preg_match($pattern, $scanBuffer)) {
                        throw new \RuntimeException(
                            'Backup file contains potentially dangerous SQL statements and cannot be restored through the web interface.'
                        );
                    }
                }

                $tail = substr($scanBuffer, -2048);
            }
        } finally {
            $this->closeSqlStream($stream, $path);
        }
    }

    public function verify(string $filename): array
    {
        return $this->verificationPayload($filename);
    }

    public function verifyPath(string $path): array
    {
        return $this->verificationPayloadForPath($path, basename($path));
    }

    public function delete(string $filename): void
    {
        $path = $this->backupPath($filename);
        if (! File::exists($path)) {
            throw new \RuntimeException('Backup not found');
        }

        File::delete($path);
    }

    public function verifyExternalPath(string $filepath): string
    {
        $backupDir = $this->backupDir();
        $cleanPath = realpath($filepath) ?: $filepath;

        if (! $this->isWithinDirectory($backupDir, $cleanPath)) {
            throw new \RuntimeException('File is outside the configured backup directory');
        }

        if (! File::exists($cleanPath)) {
            throw new \RuntimeException('Backup file not found');
        }

        if (! str_ends_with($cleanPath, '.sql.gz') && ! str_ends_with($cleanPath, '.sql')) {
            throw new \RuntimeException('Expected a .sql or .sql.gz backup file');
        }

        return $cleanPath;
    }

    public function download(string $filename): string
    {
        $path = $this->backupPath($filename);
        if (! File::exists($path)) {
            throw new \RuntimeException('Backup not found');
        }

        return $path;
    }

    public function clearCache(): void
    {
        $this->cachedSettings = null;
    }

    // ─── Private Helpers ───

    private function finalizeRestore(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->ensureGlobalSettingsRow();
        $this->clearCache();

        Artisan::call('optimize:clear');
    }

    private function ensureGlobalSettingsRow(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $hasSettings = Settings::query()
            ->where('id', 'global')
            ->orWhereNull('tenantId')
            ->exists();

        if ($hasSettings) {
            return;
        }

        Settings::query()->create([
            'id' => 'global',
            'tenantId' => null,
            'data' => $this->settingsService->defaults(),
        ]);
    }

    private function getSettings(?string $tenantId = null): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        // Accept tenantId as parameter; fall back to reading from current request if null
        $resolvedTenantId = $tenantId ?? request()->user()?->tenantId;
        $settings = $this->settingsService->resolve($resolvedTenantId);

        $defaults = $this->settingsService->defaults();

        $this->cachedSettings = $settings?->data['backupSettings'] ?? ($defaults['backupSettings'] ?? []);

        return $this->cachedSettings;
    }

    private function backupDir(): string
    {
        $settings = $this->getSettings();
        $dir = $settings['backupDir']
            ?? config('medsurvey.backup.backup_dir')
            ?? 'storage/app/backups';

        $resolved = str_starts_with($dir, '/') || preg_match('/^[a-zA-Z]:\\\\/', $dir)
            ? $dir
            : base_path(trim($dir, '/\\'));

        return $resolved;
    }

    private function backupPath(string $filename): string
    {
        return $this->backupDir().DIRECTORY_SEPARATOR.basename($filename);
    }

    private function isWithinDirectory(string $parent, string $child): bool
    {
        $parentReal = realpath($parent);
        if ($parentReal === false) {
            return false;
        }

        $childReal = realpath($child);
        if ($childReal === false) {
            $childDir = realpath(dirname($child));
            if ($childDir === false) {
                return false;
            }
            $childReal = $childDir.DIRECTORY_SEPARATOR.basename($child);
        }

        $parentNorm = rtrim(str_replace('\\', '/', $parentReal), '/').'/';
        $childNorm = str_replace('\\', '/', $childReal);

        return str_starts_with($childNorm, $parentNorm) || $childNorm === rtrim($parentNorm, '/');
    }

    private function mysqldumpPath(): string
    {
        $configured = config('medsurvey.backup.mysqldump_path');
        if ($configured) {
            return $configured;
        }

        $xamppPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        return File::exists($xamppPath) ? $xamppPath : 'mysqldump';
    }

    private function mysqlPath(): string
    {
        $configured = config('medsurvey.backup.mysql_path');
        if ($configured) {
            return $configured;
        }

        $xamppPath = 'C:\\xampp\\mysql\\bin\\mysql.exe';

        return File::exists($xamppPath) ? $xamppPath : 'mysql';
    }

    private function runMysqldump(string $path): void
    {
        $command = [
            $this->mysqldumpPath(),
            '--protocol=tcp',
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            '--result-file='.$path,
            config('database.connections.mysql.database'),
        ];

        $password = (string) config('database.connections.mysql.password');

        $process = new Process($command);
        $process->setEnv([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: '',
            'MYSQL_PWD' => $password,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Backup command failed: '.$process->getErrorOutput());
        }
    }

    private function runMysqlRestore(string $filepath): void
    {
        $command = [
            $this->mysqlPath(),
            '--protocol=tcp',
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            config('database.connections.mysql.database'),
        ];

        $password = (string) config('database.connections.mysql.password');

        $restoreInputPath = $this->restoreInputPath($filepath);
        $input = @fopen($restoreInputPath, 'rb');
        if ($input === false) {
            $this->deleteTemporaryRestoreInput($restoreInputPath, $filepath);

            throw new \RuntimeException('Unable to read backup file.');
        }

        try {
            $process = new Process($command);
            $process->setInput($input);
            $process->setEnv([
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                'PATH' => getenv('PATH') ?: '',
                'MYSQL_PWD' => $password,
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException('Database restore failed. Check server logs for details.');
            }
        } finally {
            fclose($input);
            $this->deleteTemporaryRestoreInput($restoreInputPath, $filepath);
        }
    }

    private function restoreInputPath(string $filepath): string
    {
        if (! str_ends_with($filepath, '.sql.gz')) {
            return $filepath;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'medsurvey_restore_');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to prepare temporary restore file.');
        }

        $source = @gzopen($filepath, 'rb');
        $destination = @fopen($temporaryPath, 'wb');

        if (! $source || ! $destination) {
            if ($source) {
                gzclose($source);
            }
            if ($destination) {
                fclose($destination);
            }
            File::delete($temporaryPath);

            throw new \RuntimeException('Unable to read compressed backup file.');
        }

        try {
            while (! gzeof($source)) {
                fwrite($destination, gzread($source, 1024 * 512));
            }
        } finally {
            gzclose($source);
            fclose($destination);
        }

        return $temporaryPath;
    }

    private function deleteTemporaryRestoreInput(string $restoreInputPath, string $originalPath): void
    {
        if ($restoreInputPath !== $originalPath && File::exists($restoreInputPath)) {
            File::delete($restoreInputPath);
        }
    }

    private function compressFile(string $source, string $destination): void
    {
        $fp = @fopen($source, 'rb');
        $zp = @gzopen($destination, 'wb9');

        if (! $fp || ! $zp) {
            if ($fp) {
                fclose($fp);
            }
            if ($zp) {
                gzclose($zp);
            }

            throw new \RuntimeException('Unable to open backup file for compression.');
        }

        try {
            while (! feof($fp)) {
                $chunk = fread($fp, 1024 * 512);
                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read backup file during compression.');
                }

                if (gzwrite($zp, $chunk) === false) {
                    throw new \RuntimeException('Unable to write compressed backup file.');
                }
            }
        } finally {
            fclose($fp);
            gzclose($zp);
        }
    }

    private function fileInfo(string $path): array
    {
        $sizeBytes = File::size($path);

        return [
            'filename' => basename($path),
            'sizeBytes' => $sizeBytes,
            'sizeMb' => round($sizeBytes / 1024 / 1024, 2),
            'createdAt' => date('c', File::lastModified($path)),
            'modifiedAt' => date('c', File::lastModified($path)),
        ];
    }

    private function verificationPayload(string $filename): array
    {
        $path = $this->backupPath($filename);

        return $this->verificationPayloadForPath($path, basename($filename));
    }

    private function verificationPayloadForPath(string $path, ?string $displayName = null): array
    {
        $filename = $displayName ?: basename($path);

        if (! File::exists($path)) {
            return [
                'valid' => false,
                'filename' => $filename,
                'sizeBytes' => 0,
                'sizeMb' => 0,
                'hasDatabaseSelection' => false,
                'databaseName' => null,
                'tableCount' => 0,
                'hasData' => false,
                'estimatedRows' => 0,
                'error' => 'Backup not found',
                'checkedAt' => now()->toISOString(),
            ];
        }

        $sizeBytes = File::size($path);

        if ($sizeBytes > self::MAX_RESTORE_BYTES) {
            return [
                'valid' => false,
                'filename' => $filename,
                'sizeBytes' => $sizeBytes,
                'sizeMb' => round($sizeBytes / 1024 / 1024, 2),
                'hasDatabaseSelection' => false,
                'databaseName' => null,
                'tableCount' => 0,
                'hasData' => false,
                'estimatedRows' => 0,
                'error' => 'Backup file exceeds the web restore size limit',
                'checkedAt' => now()->toISOString(),
            ];
        }

        $tableCount = 0;
        $insertCount = 0;
        $databaseName = null;
        $tail = '';
        $stream = $this->openSqlStream($path);

        try {
            while (! $this->sqlStreamEof($stream, $path)) {
                $chunk = $this->readSqlChunk($stream, $path);
                if ($chunk === '') {
                    break;
                }

                $scanBuffer = $tail.$chunk;
                preg_match_all('/CREATE TABLE/i', $scanBuffer, $tableMatches);
                preg_match_all('/INSERT INTO/i', $scanBuffer, $insertMatches);
                $tableCount += count($tableMatches[0]);
                $insertCount += count($insertMatches[0]);

                if ($databaseName === null && preg_match('/USE `?([^`;]+)`?/i', $scanBuffer, $dbMatch)) {
                    $databaseName = $dbMatch[1];
                }

                $tail = substr($scanBuffer, -2048);
            }
        } finally {
            $this->closeSqlStream($stream, $path);
        }

        return [
            'valid' => $sizeBytes > 0 && $tableCount > 0,
            'filename' => $filename,
            'sizeBytes' => $sizeBytes,
            'sizeMb' => round($sizeBytes / 1024 / 1024, 2),
            'hasDatabaseSelection' => $databaseName !== null,
            'databaseName' => $databaseName,
            'tableCount' => $tableCount,
            'hasData' => $insertCount > 0,
            'estimatedRows' => $insertCount,
            'error' => null,
            'checkedAt' => now()->toISOString(),
        ];
    }

    private function openSqlStream(string $path): mixed
    {
        $stream = str_ends_with($path, '.sql.gz')
            ? @gzopen($path, 'rb')
            : @fopen($path, 'rb');

        if (! $stream) {
            throw new \RuntimeException('Unable to read backup file.');
        }

        return $stream;
    }

    private function readSqlChunk(mixed $stream, string $path): string
    {
        $chunk = str_ends_with($path, '.sql.gz')
            ? gzread($stream, 1024 * 512)
            : fread($stream, 1024 * 512);

        if ($chunk === false) {
            throw new \RuntimeException('Unable to read backup file.');
        }

        return $chunk;
    }

    private function sqlStreamEof(mixed $stream, string $path): bool
    {
        return str_ends_with($path, '.sql.gz') ? gzeof($stream) : feof($stream);
    }

    private function closeSqlStream(mixed $stream, string $path): void
    {
        if (str_ends_with($path, '.sql.gz')) {
            gzclose($stream);

            return;
        }

        fclose($stream);
    }
}
