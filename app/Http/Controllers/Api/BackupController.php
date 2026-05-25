<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupController
{
    public function index(): JsonResponse
    {
        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);
        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql') || str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => $this->fileInfo($file->getPathname()))
            ->values();

        return response()->json([
            'backups' => $backups,
            'config' => [
                'enabled' => true,
                'retentionDays' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),
                'backupDir' => $backupDir,
            ],
        ]);
    }

    public function create(): JsonResponse
    {
        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);

        $filename = 'medsurvey_backup_'.now()->format('Ymd_His').'.sql';
        $path = $backupDir.DIRECTORY_SEPARATOR.$filename;
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
        if ($password !== '') {
            $command[] = '--password='.$password;
        }

        $process = new Process($command);
        $process->setEnv([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: '',
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return response()->json([
                'error' => 'Backup command failed',
                'details' => trim($process->getErrorOutput() ?: $process->getOutput()),
            ], 500);
        }

        // Compress the backup file using native PHP zlib
        $gzFilename = $filename . '.gz';
        $gzPath = $path . '.gz';
        $fp = @fopen($path, 'rb');
        $zp = @gzopen($gzPath, 'wb9'); // Max compression level 9
        
        if ($fp && $zp) {
            while (!feof($fp)) {
                gzwrite($zp, fread($fp, 1024 * 512));
            }
            fclose($fp);
            gzclose($zp);
            
            // Delete the uncompressed file
            File::delete($path);
            
            $path = $gzPath;
            $filename = $gzFilename;
        }

        return response()->json([
            'message' => 'Backup created and compressed successfully',
            'file' => $path,
            'timestamp' => now()->toISOString(),
            'verification' => $this->verificationPayload($filename),
        ]);
    }

    public function verify(string $filename): JsonResponse
    {
        return response()->json($this->verificationPayload($filename));
    }

    public function destroy(string $filename): JsonResponse
    {
        $path = $this->backupPath($filename);
        if (! File::exists($path)) {
            return response()->json(['error' => 'Backup not found'], 404);
        }

        File::delete($path);

        return response()->json(['message' => 'Backup deleted successfully', 'filename' => basename($filename)]);
    }

    public function restore(string $filename): JsonResponse
    {
        $path = $this->backupPath($filename);
        if (! File::exists($path)) {
            return response()->json(['error' => 'Backup file not found'], 404);
        }

        try {
            $this->runRestore($path);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Database restored successfully', 'filename' => basename($filename)]);
    }

    public function uploadRestore(Request $request): JsonResponse
    {
        $filename = $request->input('filename');
        $content = $request->input('content');

        if (! $filename || ! $content) {
            return response()->json(['error' => 'Filename and content are required'], 400);
        }

        if (! is_string($filename) || (! str_ends_with($filename, '.sql.gz') && ! str_ends_with($filename, '.sql'))) {
            return response()->json(['error' => 'Invalid backup extension. Expected .sql or .sql.gz'], 400);
        }

        $backupDir = $this->backupDir();
        File::ensureDirectoryExists($backupDir);

        $cleanFilename = 'upload_'.time().'_'.preg_replace('/[^a-zA-Z0-9_.\-]/', '', basename($filename));
        $filepath = $backupDir.DIRECTORY_SEPARATOR.$cleanFilename;

        if (! $this->isWithinDirectory($backupDir, $filepath)) {
            return response()->json(['error' => 'Invalid backup filename'], 400);
        }

        try {
            $buffer = base64_decode($content, true);
            if ($buffer === false) {
                return response()->json(['error' => 'Invalid base64 content'], 400);
            }

            File::put($filepath, $buffer);

            // Verify the backup file
            $verification = $this->verificationPayload($cleanFilename);
            if (! ($verification['valid'] ?? false)) {
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }

                return response()->json([
                    'error' => 'Invalid backup file: '.($verification['error'] ?? 'unsupported format'),
                    'verification' => $verification,
                ], 400);
            }

            $this->runRestore($filepath);

            // Cleanup uploaded file after restore
            if (File::exists($filepath)) {
                File::delete($filepath);
            }

            return response()->json(['message' => 'Database restored successfully from uploaded file', 'filename' => $filename]);
        } catch (\Throwable $e) {
            if (File::exists($filepath)) {
                File::delete($filepath);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function scanExternal(Request $request): JsonResponse
    {
        $directory = $request->input('directory');
        if (! $directory) {
            return response()->json(['error' => 'Directory is required'], 400);
        }

        $backupDir = $this->backupDir();
        $targetDir = realpath($directory) ?: $directory;

        if (! $this->isWithinDirectory($backupDir, $targetDir)) {
            return response()->json(['error' => 'Directory is outside the configured backup directory'], 403);
        }

        if (! File::isDirectory($targetDir)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $files = collect(File::files($targetDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql.gz') || str_ends_with($file->getFilename(), '.sql'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => array_merge($this->fileInfo($file->getPathname()), [
                'fullPath' => $file->getPathname(),
            ]))
            ->values();

        return response()->json(['backups' => $files]);
    }

    public function restoreExternal(Request $request): JsonResponse
    {
        $filepath = $request->input('filepath');
        if (! $filepath) {
            return response()->json(['error' => 'File path is required'], 400);
        }

        $backupDir = $this->backupDir();
        $cleanPath = realpath($filepath) ?: $filepath;

        if (! $this->isWithinDirectory($backupDir, $cleanPath)) {
            return response()->json(['error' => 'File is outside the configured backup directory'], 403);
        }

        if (! File::exists($cleanPath)) {
            return response()->json(['error' => 'Backup file not found'], 404);
        }

        if (! str_ends_with($cleanPath, '.sql.gz') && ! str_ends_with($cleanPath, '.sql')) {
            return response()->json(['error' => 'Expected a .sql or .sql.gz backup file'], 400);
        }

        try {
            $this->runRestore($cleanPath);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Database restored successfully', 'filename' => basename($cleanPath)]);
    }

    public function download(string $filename)
    {
        $path = $this->backupPath($filename);
        if (! File::exists($path)) {
            return response()->json(['error' => 'Backup not found'], 404);
        }

        return response()->download($path, basename($filename));
    }

    // ─── Private Helpers ───

    private function backupDir(): string
    {
        return env('DB_BACKUP_DIR')
            ? base_path(trim((string) env('DB_BACKUP_DIR'), '/\\'))
            : storage_path('app/backups');
    }

    private function backupPath(string $filename): string
    {
        return $this->backupDir().DIRECTORY_SEPARATOR.basename($filename);
    }

    private function isWithinDirectory(string $parent, string $child): bool
    {
        $parentReal = realpath($parent) ?: $parent;
        $childReal = realpath($child) ?: $child;

        // Normalize separators
        $parentNorm = rtrim(str_replace('\\', '/', $parentReal), '/').'/';
        $childNorm = str_replace('\\', '/', $childReal);

        return str_starts_with($childNorm, $parentNorm) || $childNorm === rtrim($parentNorm, '/');
    }

    private function mysqldumpPath(): string
    {
        $configured = env('MYSQLDUMP_PATH');
        if ($configured) {
            return $configured;
        }

        $xamppPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        return File::exists($xamppPath) ? $xamppPath : 'mysqldump';
    }

    private function mysqlPath(): string
    {
        $configured = env('MYSQL_PATH');
        if ($configured) {
            return $configured;
        }

        $xamppPath = 'C:\\xampp\\mysql\\bin\\mysql.exe';

        return File::exists($xamppPath) ? $xamppPath : 'mysql';
    }

    private function runRestore(string $filepath): void
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
        if ($password !== '') {
            $command[] = '--password='.$password;
        }

        // If .sql.gz, decompress first, then pipe to mysql
        if (str_ends_with($filepath, '.sql.gz')) {
            // Use a shell pipeline: gunzip -c file.sql.gz | mysql ...
            $mysqlCmd = implode(' ', array_map('escapeshellarg', $command));
            $shellCommand = escapeshellarg($this->gunzipPath()).' -c '.escapeshellarg($filepath).' | '.$mysqlCmd;

            $process = Process::fromShellCommandline($shellCommand);
        } else {
            // Plain .sql file — redirect stdin from file
            $shellCmd = implode(' ', array_map('escapeshellarg', $command)).' < '.escapeshellarg($filepath);
            $process = Process::fromShellCommandline($shellCmd);
        }

        $process->setEnv([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: '',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Database restore failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function gunzipPath(): string
    {
        // Try common Windows paths (Git for Windows ships gunzip)
        $candidates = [
            'C:\\Program Files\\Git\\usr\\bin\\gunzip.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\gunzip.exe',
        ];

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return 'gunzip'; // fallback to PATH
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
        if (! File::exists($path)) {
            return [
                'valid' => false,
                'filename' => basename($filename),
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

        // For .sql.gz files, decompress and read the entire content for verification
        if (str_ends_with($path, '.sql.gz')) {
            $gz = @gzopen($path, 'rb');
            $content = '';
            if ($gz) {
                while (!feof($gz)) {
                    $content .= gzread($gz, 1024 * 512);
                }
                gzclose($gz);
            }
        } else {
            $content = File::get($path);
        }

        preg_match_all('/CREATE TABLE/i', $content, $tableMatches);
        preg_match_all('/INSERT INTO/i', $content, $insertMatches);
        preg_match('/USE `?([^`;]+)`?/i', $content, $dbMatch);

        return [
            'valid' => $sizeBytes > 0 && count($tableMatches[0]) > 0,
            'filename' => basename($filename),
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
