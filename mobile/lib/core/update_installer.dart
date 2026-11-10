import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Opens the release's download page in the phone's browser.
///
/// Installing an APK is Android's job, not the app's: handing the URL to the
/// system avoids requesting the "install unknown apps" permission inside the
/// survey app. Android 12+ still shows its own confirmation before installing.
class UpdateInstaller {
  const UpdateInstaller._();

  static const _channel = MethodChannel('com.medsurvey.pro/updater');

  /// Returns false when the URL is empty or no browser can handle it, so the
  /// screen can fall back to showing a copyable address.
  static Future<bool> openDownload(String url) async {
    final target = url.trim();
    if (target.isEmpty) return false;

    try {
      final opened = await _channel.invokeMethod<bool>('openUrl', {
        'url': target,
      });
      return opened ?? false;
    } catch (error) {
      debugPrint('Could not open the update link: $error');
      return false;
    }
  }
}
