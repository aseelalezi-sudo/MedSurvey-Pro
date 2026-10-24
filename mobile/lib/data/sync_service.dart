import '../core/api_client.dart';
import 'local_db.dart';
import 'models.dart';

class SyncResult {
  const SyncResult({
    required this.ok,
    this.uploaded = 0,
    this.failed = 0,
    this.message,
    this.unauthorized = false,
  });

  final bool ok;
  final int uploaded;
  final int failed;
  final String? message;
  final bool unauthorized;
}

class SyncService {
  static bool _running = false;

  static Future<SyncResult> sync({
    required ApiClient api,
    required LocalDb db,
    required String token,
    required AppUser user,
    void Function()? onProgress,
  }) async {
    if (_running) {
      return const SyncResult(
        ok: true,
        message: 'المزامنة قيد التنفيذ بالفعل.',
      );
    }

    _running = true;
    var uploaded = 0;
    var failed = 0;
    String? retryableError;

    try {
      final entries = await db.pendingFor(user.id);

      for (final entry in entries) {
        await db.markSyncing(entry.clientResponseId);

        try {
          await api.submitResponse(token: token, payload: entry.payload);
          await db.delete(entry.clientResponseId);
          uploaded++;
          onProgress?.call();
        } on ApiException catch (error) {
          if (error.statusCode == 401) {
            await db.markPending(entry.clientResponseId, error.message);

            return SyncResult(
              ok: false,
              uploaded: uploaded,
              failed: failed,
              message: 'انتهت الجلسة. يرجى تسجيل الدخول مرة أخرى.',
              unauthorized: true,
            );
          }

          if (error.isRetryable) {
            await db.markPending(entry.clientResponseId, error.message);
            retryableError ??= error.message;

            // A network failure will affect every queued entry, so stop here
            // instead of waiting for a timeout per response. Server errors are
            // isolated to one response, so continue with the rest of the queue.
            if (error.network) {
              return SyncResult(
                ok: false,
                uploaded: uploaded,
                failed: failed,
                message: error.message,
              );
            }

            continue;
          }

          // Validation/conflict errors will not fix themselves by retrying.
          await db.markFailed(entry.clientResponseId, error.message);
          failed++;
        } catch (error) {
          await db.markPending(entry.clientResponseId, error.toString());

          return SyncResult(
            ok: false,
            uploaded: uploaded,
            failed: failed,
            message: 'تعذر رفع بعض الاستبيانات. سيتم إعادة المحاولة تلقائيًا.',
          );
        }
      }

      if (retryableError != null) {
        return SyncResult(
          ok: false,
          uploaded: uploaded,
          failed: failed,
          message: retryableError,
        );
      }

      return SyncResult(ok: true, uploaded: uploaded, failed: failed);
    } finally {
      _running = false;
    }
  }
}
