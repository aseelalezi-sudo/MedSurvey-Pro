import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/state/app_state.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('hospital identity survives a missing local database', () async {
    final storage = AppStorage();
    await storage.saveBrand(name: 'مستشفى النور', logo: '/uploads/logo.png');

    final state = AppState(
      storage: storage,
      db: LocalDb.instance,
      startupTimeout: const Duration(milliseconds: 200),
    );
    await state.initialize();

    // The database is unavailable in this environment, so there is no
    // bootstrap; the header must still name the hospital from preferences.
    expect(state.bootstrap, isNull);
    expect(state.hospitalName, 'مستشفى النور');
    expect(state.hospitalLogo, '/uploads/logo.png');
  });
}
