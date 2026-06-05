<?php

namespace App\Http\Controllers\Web;

use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function backups(Request $request): View|JsonResponse
    {
        $data = $this->backupService->list();
        $backups = $data['backups'] ?? [];
        $config = $data['config'] ?? [];

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['backups' => $backups, 'config' => $config]);
        }

        return view('dashboard.backups', compact('backups', 'config'));
    }

    public function createBackup(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $result = $this->backupService->create();
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'تم إنشاء النسخة الاحتياطية بنجاح', 'result' => $result]);
            }

            return redirect()->back()->with('success', 'تم إنشاء النسخة الاحتياطية بنجاح');
        } catch (Throwable $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل إنشاء النسخة الاحتياطية: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'فشل إنشاء النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    public function restoreBackup(Request $request, string $filename): RedirectResponse|JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return $this->backupRestoreDisabledResponse($request);
        }

        try {
            $path = $this->backupService->download($filename);
            $this->backupService->restore($path);
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'تم استعادة النسخة الاحتياطية بنجاح']);
            }

            return redirect()->back()->with('success', 'تم استعادة النسخة الاحتياطية بنجاح');
        } catch (Throwable $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل استعادة النسخة الاحتياطية: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'فشل استعادة النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    public function destroyBackup(Request $request, string $filename): RedirectResponse|JsonResponse
    {
        try {
            $this->backupService->delete($filename);
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'تم حذف النسخة الاحتياطية بنجاح']);
            }

            return redirect()->back()->with('success', 'تم حذف النسخة الاحتياطية بنجاح');
        } catch (Throwable $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل حذف النسخة الاحتياطية: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'فشل حذف النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    public function verifyBackup(Request $request, string $filename): RedirectResponse|JsonResponse
    {
        try {
            $result = $this->backupService->verify($filename);
            $message = $result['valid']
                ? 'الملف صالح: '.($result['tableCount'] ?? 0).' جداول، '.($result['estimatedRows'] ?? 0).' صفوف'
                : 'الملف غير صالح: '.($result['error'] ?? 'خطأ غير معروف');
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => $result['valid'], 'message' => $message, 'result' => $result]);
            }

            return redirect()->back()->with('success', $message);
        } catch (Throwable $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'فشل التحقق من الملف: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'فشل التحقق من الملف: '.$e->getMessage());
        }
    }

    public function downloadBackup(string $filename): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $this->backupService->download($filename);

            return response()->download($path, $filename);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'فشل تحميل الملف: '.$e->getMessage());
        }
    }

    public function uploadBackup(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return $this->backupRestoreDisabledResponse($request);
        }

        $request->validate([
            'backup_file' => 'required|file',
        ]);

        try {
            $file = $request->file('backup_file');
            $content = base64_encode($file->getContent());
            $filename = $file->getClientOriginalName();

            $this->backupService->uploadAndRestore($filename, $content);

            return redirect()->back()->with('success', 'تم استعادة قاعدة البيانات بنجاح من الملف "'.$filename.'"');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'فشل استعادة قاعدة البيانات من الملف المرفوع: '.$e->getMessage());
        }
    }

    public function uploadRestoreAjax(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return $this->backupRestoreDisabledResponse($request);
        }

        try {
            $data = $request->validate([
                'filename' => 'required|string',
                'content' => 'required|string',
            ]);

            $result = $this->backupService->uploadAndRestore($data['filename'], $data['content']);

            return response()->json(['success' => true, 'message' => $result['message'] ?? 'تم الاستعادة بنجاح']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function scanExternalAjax(Request $request): JsonResponse
    {
        try {
            $data = $request->validate(['path' => 'required|string']);
            $result = $this->backupService->scanExternal($data['path']);

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'backups' => []], 422);
        }
    }

    public function verifyExternalAjax(Request $request): JsonResponse
    {
        try {
            $data = $request->validate(['path' => 'required|string']);
            $path = $this->backupService->verifyExternalPath($data['path']);
            $result = $this->backupService->verify(basename($path));

            // Add a human-readable message
            if ($result['valid']) {
                $result['message'] = 'الملف صالح: '.($result['tableCount'] ?? 0).' جداول، '.($result['estimatedRows'] ?? 0).' صفوف';
            } else {
                $result['message'] = $result['error'] ?? 'الملف غير صالح';
            }

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['valid' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
        }
    }

    public function restoreExternalAjax(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->backupService->restoreEnabled()) {
            return $this->backupRestoreDisabledResponse($request);
        }

        try {
            $data = $request->validate(['path' => 'required|string']);
            $path = $this->backupService->verifyExternalPath($data['path']);
            $this->backupService->restore($path);

            return response()->json(['success' => true, 'message' => 'تم استعادة قاعدة البيانات بنجاح من الملف الخارجي']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function backupRestoreDisabledResponse(Request $request): RedirectResponse|JsonResponse
    {
        $message = app()->getLocale() === 'ar'
            ? 'استعادة النسخ الاحتياطية معطلة من إعدادات البيئة. فعّل DB_BACKUP_RESTORE_ENABLED عند الحاجة فقط.'
            : 'Backup restore is disabled by environment settings. Enable DB_BACKUP_RESTORE_ENABLED only when needed.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return redirect()->back()->with('error', $message);
    }
}
