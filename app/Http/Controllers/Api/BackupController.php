<?php

namespace App\Http\Controllers\Api;

use App\Services\BackupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController
{
    public function __construct(
        private readonly BackupService $backupService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->backupService->list());
    }

    public function create(): JsonResponse
    {
        try {
            $result = $this->backupService->create();
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error("Backup command failed", 500, null, [
                "details" => "Backup execution failed. Check server logs for details.",
            ]);
        }
    }

    public function verify(string $filename): JsonResponse
    {
        return response()->json($this->backupService->verify($filename));
    }

    public function verifyExternal(Request $request): JsonResponse
    {
        $filepath = $request->input("filepath");
        if (! $filepath) {
            return ApiResponse::error("File path is required", 400);
        }

        try {
            $this->backupService->verifyExternalPath($filepath);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        return response()->json($this->backupService->verify(basename($filepath)));
    }

    public function destroy(string $filename): JsonResponse
    {
        try {
            $this->backupService->delete($filename);
            return ApiResponse::deleted("Backup deleted successfully");
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    public function restore(string $filename): JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return ApiResponse::error("Database restore is disabled", 403);
        }

        try {
            $path = $this->backupService->download($filename);
            $this->backupService->restore($path);
            return ApiResponse::success(null, "Database restored successfully");
        } catch (\RuntimeException $e) {
            return ApiResponse::error("Database restore failed. Check server logs for details.", 500);
        }
    }

    public function uploadRestore(Request $request): JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return ApiResponse::error("Database restore is disabled", 403);
        }

        $filename = $request->input("filename");
        $content = $request->input("content");

        if (! $filename || ! $content) {
            return ApiResponse::error("Filename and content are required", 400);
        }

        try {
            $result = $this->backupService->uploadAndRestore($filename, $content);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function scanExternal(Request $request): JsonResponse
    {
        $directory = $request->input("directory");
        if (! $directory) {
            return ApiResponse::error("Directory is required", 400);
        }

        try {
            $result = $this->backupService->scanExternal($directory);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === "Directory not found") {
                return ApiResponse::error("Directory not found", 404);
            }
            return ApiResponse::error($e->getMessage(), 403);
        }
    }

    public function restoreExternal(Request $request): JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return ApiResponse::error("Database restore is disabled", 403);
        }

        $filepath = $request->input("filepath");
        if (! $filepath) {
            return ApiResponse::error("File path is required", 400);
        }

        try {
            $cleanPath = $this->backupService->verifyExternalPath($filepath);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        try {
            $this->backupService->restore($cleanPath);
            return ApiResponse::success(null, "Database restored successfully");
        } catch (\RuntimeException $e) {
            return ApiResponse::error("External restore failed. Check server logs for details.", 500);
        }
    }

    public function download(string $filename)
    {
        try {
            $path = $this->backupService->download($filename);
            return response()->download($path, basename($filename));
        } catch (\RuntimeException $e) {
            return ApiResponse::error("Backup not found", 404);
        }
    }
}
