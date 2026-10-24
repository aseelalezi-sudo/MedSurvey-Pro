import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'core/app_storage.dart';
import 'data/local_db.dart';
import 'services/background_sync.dart';
import 'state/app_state.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Draw behind the status / navigation bars so no content is clipped and the
  // bottom bar can add its own safe-area padding.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);

  // Open the local database before the UI starts so cached surveys and the
  // pending queue are available immediately, even with no connection.
  await LocalDb.instance.database;

  await BackgroundSync.initialize();

  final state = AppState(storage: AppStorage(), db: LocalDb.instance);
  await state.initialize();

  runApp(
    ChangeNotifierProvider<AppState>.value(
      value: state,
      child: const MedSurveyApp(),
    ),
  );
}
