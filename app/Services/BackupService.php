<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\File;
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
    }

    /**
     * Scan SQL content for dangerous patterns before allowing restore.
     */
    private function validateSqlContent(string $path): void
    {
        $content = str_ends_with($path, '.sql.gz')
            ? (gzdecode(File::get($path)) ?: '')
            : File::get($path);

        foreach (self::DANGEROUS_SQL_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \RuntimeException(
                    'Backup file contains potentially dangerous SQL statements and cannot be restored through the web interface.'
                );
            }
        }
    }

    public function verify(string $filename): array
    {
        return $this->verificationPayload($filename);
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

    public function scanExternal(string $directory): array
    {
        $backupDir = $this->backupDir();
        $targetDir = realpath($directory) ?: $directory;

        if (! $this->isWithinDirectory($backupDir, $targetDir)) {
            throw new \RuntimeException('Directory is outside the configured backup directory');
        }

        if (! File::isDirectory($targetDir)) {
            throw new \RuntimeException('Directory not found');
        }

        $files = collect(File::files($targetDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql.gz') || str_ends_with($file->getFilename(), '.sql'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => array_merge($this->fileInfo($file->getPathname()), [
                'fullPath' => $file->getPathname(),
            ]))
            ->values();

        return ['backups' => $files];
    }

    public function uploadAndRestore(string $filename, string $content): array
    {
        if (! is_string($filename) || (! str_ends_with($filename, '.sql.gz') && ! str_ends_with($filename, '.sql'))) {
            throw new \RuntimeException('Invalid backup extension. Expected .sql or .sql.gz');
        }

        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);

        $cleanFilename = 'upload_'.time().'_'.preg_replace("/[^a-zA-Z0-9_.\-]/", '', basename($filename));
        $filepath = $backupDir.DIRECTORY_SEPARATOR.$cleanFilename;

        if (! $this->isWithinDirectory($backupDir, $filepath)) {
            throw new \RuntimeException('Invalid backup filename');
        }

        $buffer = base64_decode($content, true);
        if ($buffer === false) {
            throw new \RuntimeException('Invalid base64 content');
        }

        if (strlen($buffer) > self::MAX_RESTORE_BYTES) {
            throw new \RuntimeException('Backup file is too large');
        }

        File::put($filepath, $buffer);

        $verification = $this->verificationPayload($cleanFilename);
        if (! ($verification['valid'] ?? false)) {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }
            throw new \RuntimeException('Invalid backup file: '.($verification['error'] ?? 'unsupported format'));
        }

        try {
            $this->restore($filepath);
        } catch (\Throwable $e) {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }
            throw $e;
        }

        if (File::exists($filepath)) {
            File::delete($filepath);
        }

        return ['message' => 'Database restored successfully from uploaded file', 'filename' => $filename];
    }

    public function restoreEnabled(): bool
    {
        return (bool) config('medsurvey.backup.restore_enabled', false);
    }

    public function clearCache(): void
    {
        $this->cachedSettings = null;
    }

    // ─── Private Helpers ───

    private function getSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $tenantId = request()->user()?->tenantId;
        $settings = $tenantId
            ? Settings::query()->where('tenantId', $tenantId)->first()
            : Settings::query()->where('id', 'global')->first();

        if (! $settings && $tenantId) {
            $settings = Settings::query()->where('id', 'global')->first();
        }

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

        $content = str_ends_with($filepath, '.sql.gz')
            ? gzdecode(File::get($filepath))
            : File::get($filepath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read backup file.');
        }

        $process = new Process($command);
        $process->setInput($content);
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
    }

    private function compressFile(string $source, string $destination): void
    {
        $fp = @fopen($source, 'rb');
        $zp = @gzopen($destination, 'wb9');

        if ($fp && $zp) {
            while (! feof($fp)) {
                gzwrite($zp, fread($fp, 1024 * 512));
            }
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

        if (str_ends_with($path, '.sql.gz')) {
            $content = gzdecode(File::get($path));
            $content = $content === false ? '' : $content;
        } else {
            $content = File::get($path);
        }

        preg_match_all('/CREATE TABLE/i', $content, $tableMatches);
        preg_match_all('/INSERT INTO/i', $content, $insertMatches);
        preg_match('/USE `?([^`;]+)`?/i', $content, $dbMatch);

        return [
            'valid' => $sizeBytes > 0 && count($tableMatches[0]) > 0,
            'filename' => $filename,
            'sizeBytes' => $sizeBytes,
            'sizeMb' => round($sizeBytes / 1024 / 1024, 2),
            'hasDatabaseSelection' => isset($dbMatch[1]),
            'databaseName' => $dbMatch[1] ?? null,
            'tableCount' => count($tableMatches[0]),
            'hasData' => count($insertMatches[0]) > 0,
            'estimatedRows' => count($insertMatches[0]),
            'error' => null,
            'checkedAt' => now()->toISOString(),
        ];
    }
}
