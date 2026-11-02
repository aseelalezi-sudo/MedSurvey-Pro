import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'core/app_storage.dart';
import 'core/diagnostics.dart';
import 'data/local_db.dart';
import 'services/background_sync.dart';
import 'state/app_state.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Keep uncaught failures on the device so a field problem can be copied out
  // from the account tab instead of being lost with the Android log buffer.
  Diagnostics.instance.install();

  // Draw behind the status / navigation bars so no content is clipped and the
  // bottom bar can add its own safe-area padding.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);

  final state = AppState(storage: AppStorage(), db: LocalDb.instance);

  // Show the splash immediately. Opening the database, reading encrypted
  // storage and registering the background task are native calls that can be
  // slow (or stuck) on some devices; awaiting them before runApp used to leave
  // the app on a blank launch screen with no login form.
  runApp(
    ChangeNotifierProvider<AppState>.value(
      value: state,
      child: const MedSurveyApp(),
    ),
  );

  unawaited(_startBackgroundSync());
  unawaited(state.initialize());
}

/// Registers the periodic sync task without ever blocking the UI.
Future<void> _startBackgroundSync() async {
  try {
    await BackgroundSync.initialize().timeout(const Duration(seconds: 10));
  } catch (error) {
    debugPrint('Background sync unavailable: $error');
  }
}
