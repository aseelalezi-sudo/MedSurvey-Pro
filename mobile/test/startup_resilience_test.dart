import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/state/app_state.dart';

/// Stands in for a platform channel that never answers, like the encrypted
/// storage call that used to hang the app on a blank launch screen.
class _StuckStorage extends AppStorage {
  @override
  Future<String> getBaseUrl() => Completer<String>().future;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('startup always finishes, even when a plugin never answers', () async {
    final state = AppState(
      storage: _StuckStorage(),
      db: LocalDb.instance,
      startupTimeout: const Duration(milliseconds: 50),
    );

    await state.initialize().timeout(const Duration(seconds: 5));

    // The app must move past the splash screen and reach the login form no
    // matter which startup step is broken.
    expect(state.initialized, isTrue);
    expect(state.startupError, isNotNull);
  });

  test('startup finishes when every plugin call fails', () async {
    final state = AppState(
      storage: AppStorage(),
      db: LocalDb.instance,
      startupTimeout: const Duration(milliseconds: 50),
    );

    await state.initialize().timeout(const Duration(seconds: 5));

    expect(state.initialized, isTrue);
  });
}
