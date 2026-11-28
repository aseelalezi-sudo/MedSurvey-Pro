<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        $stem = 'medsurvey_backup_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(6));
        $filename = $stem.'.sql';
        $staging = $backupDir.DIRECTORY_SEPARATOR.'.'.$stem;
        File::makeDirectory($staging, 0700);
        $sql = $staging.DIRECTORY_SEPARATOR.$filename;

        try {
            $this->dumpDatabase($sql);
            $output = $sql;
            if (filter_var($this->getSettings()['compressGzip'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $filename .= '.gz';
                $output .= '.gz';
                try {
                    $this->compressFile($sql, $output);
                } catch (\Throwable $error) {
                    // Keep the completed SQL dump if compression fails.
                    File::move($sql, $backupDir.DIRECTORY_SEPARATOR.$stem.'.sql');
                    throw $error;
                }
            }
            $verification = $this->verificationPayloadForPath($output, $filename);
            $verification['sha256'] ??= $this->checksum($output)[0];
            $path = $backupDir.DIRECTORY_SEPARATOR.$filename;
            if (File::put($staging.DIRECTORY_SEPARATOR.'checksum', $verification['sha256']."\n") !== 65
                || ! File::move($staging.DIRECTORY_SEPARATOR.'checksum', $path.'.sha256')) {
                throw new \RuntimeException('Could not publish the backup checksum.');
            }
            if (! File::move($output, $path)) {
                File::delete($path.'.sha256');
                throw new \RuntimeException('Could not publish the completed backup.');
            }
            $verification['checksumVerified'] = true;

            return [
                'message' => 'Backup created successfully',
                'file' => $path,
                'timestamp' => now()->toISOString(),
                'verification' => $verification,
            ];
        } finally {
            File::deleteDirectory($staging);
        }
    }

    public function restore(string $path): void
    {
        if (File::size($path) > self::MAX_RESTORE_BYTES) {
            throw new \RuntimeException('Backup file is too large to restore through the web interface.');
        }

        if ($this->checksum($path)[1] === false) {
            throw new \RuntimeException('Backup SHA-256 does not match its recorded checksum.');
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

        File::delete([$path, $path.'.sha256']);
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
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new \RuntimeException('Restored database migrations did not complete.');
        }

        $this->ensureGlobalSettingsRow();
        $this->clearCache();

        if (Artisan::call('optimize:clear') !== 0) {
            throw new \RuntimeException('Restored application caches could not be cleared.');
        }
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

    private function getSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $settings = Schema::hasTable('settings') ? $this->settingsService->resolve(null) : null;

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

    private function dumpDatabase(string $path): void
    {
        $client = new MySqlBackupClient;
        $executable = $client->executable('mysqldump');

        if ($executable === null || ! function_exists('proc_open')) {
            Log::warning('MySQL dump client unavailable. Using the PHP table dumper.');
            (new PhpSqlDumper)->dump($path);

            return;
        }

        $process = $client->command($executable, [
            '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces',
            '--set-gtid-purged=OFF', '--hex-blob', '--result-file='.$path,
        ]);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('The MySQL dump client failed.');
        }
    }

    private function runMysqlRestore(string $filepath): void
    {
        $client = new MySqlBackupClient;
        $executable = $client->executable('mysql');
        if ($executable === null) {
            throw new \RuntimeException('The MySQL restore client is unavailable.');
        }

        $process = $client->command($executable, ['--binary-mode', '--local-infile=0']);
        $restoreInputPath = $this->restoreInputPath($filepath);
        $input = @fopen($restoreInputPath, 'rb');
        if ($input === false) {
            $this->deleteTemporaryRestoreInput($restoreInputPath, $filepath);

            throw new \RuntimeException('Unable to read backup file.');
        }

        try {
            $process->setInput($input);
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

                if (gzwrite($zp, $chunk) !== strlen($chunk)) {
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

        [$sha256, $checksumVerified] = $this->checksum($path);
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
            'valid' => $sizeBytes > 0 && $tableCount > 0 && $checksumVerified !== false,
            'sha256' => $sha256,
            'checksumVerified' => $checksumVerified,
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

    private function checksum(string $path): array
    {
        $actual = hash_file('sha256', $path);
        if ($actual === false) {
            throw new \RuntimeException('Could not hash the backup file.');
        }

        $sidecar = $path.'.sha256';
        if (! File::exists($sidecar) && ! is_link($sidecar)) {
            return [$actual, null];
        }

        if (is_link($sidecar) || ! is_file($sidecar) || File::size($sidecar) !== 65) {
            return [$actual, false];
        }

        return [$actual, hash_equals($actual."\n", File::get($sidecar))];
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
