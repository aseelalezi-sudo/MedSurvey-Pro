import 'package:flutter/widgets.dart';
import 'package:workmanager/workmanager.dart';

import '../core/api_client.dart';
import '../core/app_storage.dart';
import '../core/utils.dart';
import '../data/local_db.dart';
import '../data/models.dart';
import '../data/sync_service.dart';

class BackgroundSync {
  static const taskName = 'medsurvey_sync';
  static const _uniqueName = 'medsurvey_sync_periodic';

  static Future<void> initialize() async {
    try {
      await Workmanager().initialize(callbackDispatcher);

      final storage = AppStorage();
      if (await storage.workerRegistered()) return;

      await Workmanager().registerPeriodicTask(
        _uniqueName,
        taskName,
        frequency: const Duration(minutes: 15),
        constraints: Constraints(networkType: NetworkType.connected),
      );
      await storage.markWorkerRegistered();
    } catch (error) {
      debugPrint('Background sync initialization failed: $error');
    }
  }

  static Future<bool> runTask() async {
    WidgetsFlutterBinding.ensureInitialized();

    final storage = AppStorage();
    String? token;
    AppUser? user;
    try {
      token = await storage.getToken();
      user = await storage.getUser();
    } catch (_) {
      // Unreadable secure storage is not a transient failure; do not ask
      // WorkManager to retry forever.
      return true;
    }
    if (token == null || token.isEmpty || user == null) {
      return true;
    }

    final baseUrl = AppStorage.allowCustomServer
        ? normalizeBaseUrl(await storage.getBaseUrl())
        : AppStorage.productionBaseUrl;
    final db = LocalDb.instance;

    final result = await SyncService.sync(
      api: ApiClient(baseUrl: baseUrl),
      db: db,
      token: token,
      user: user,
    );

    if (result.uploaded > 0) {
      await db.saveLastSync(user.id, DateTime.now());
    }

    // Returning false asks WorkManager to retry later (transient failures).
    // Expired sessions and permanent validation errors must not loop forever.
    return result.ok || result.unauthorized;
  }
}

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    try {
      return await BackgroundSync.runTask();
    } catch (error) {
      debugPrint('Background sync failed: $error');
      return false;
    }
  });
}
