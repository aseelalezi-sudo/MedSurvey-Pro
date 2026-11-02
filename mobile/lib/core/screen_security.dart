import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Blocks screenshots and the recent-apps preview while the app is open.
///
/// The survey form shows patient names, phone numbers and free-text answers,
/// so Android's `FLAG_SECURE` keeps that content out of the gallery and out of
/// the task switcher. The flag is enabled natively by default; this channel
/// only turns it off when the facility prefers screenshots for training.
///
/// Every failure is swallowed: a missing platform channel (unit tests, a
/// future desktop build) must never break the app.
class ScreenSecurity {
  const ScreenSecurity._();

  static const _channel = MethodChannel('com.medsurvey.pro/screen_security');

  static Future<void> apply(bool enabled) async {
    try {
      await _channel.invokeMethod<void>('setSecure', {'enabled': enabled});
    } catch (error) {
      debugPrint('Could not update screen protection: $error');
    }
  }
}
