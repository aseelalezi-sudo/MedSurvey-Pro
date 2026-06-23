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
}
