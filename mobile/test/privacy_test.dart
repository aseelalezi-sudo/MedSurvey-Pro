import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/state/app_state.dart';
import 'package:medsurvey_app/ui/privacy_screen.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('collectors must acknowledge the notice before surveys', (
    tester,
  ) async {
    final state = AppState(storage: AppStorage(), db: LocalDb.instance);

    await tester.pumpWidget(
      ChangeNotifierProvider<AppState>.value(
        value: state,
        child: const MaterialApp(home: PrivacyScreen(requireConsent: true)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('إشعار الخصوصية'), findsOneWidget);
    expect(find.text('خصوصية بيانات المرضى'), findsOneWidget);

    // The continue action stays disabled until the box is ticked.
    await tester.tap(find.text('متابعة إلى الاستبيانات'));
    await tester.pumpAndSettle();
    expect(state.privacyAccepted, isFalse);

    await tester.tap(find.byType(Checkbox));
    await tester.pumpAndSettle();
    await tester.tap(find.text('متابعة إلى الاستبيانات'));
    await tester.pumpAndSettle();

    expect(state.privacyAccepted, isTrue);
    expect(await AppStorage().privacyAccepted(), isTrue);
  });

  testWidgets('the notice can be reopened without the consent bar', (
    tester,
  ) async {
    await tester.pumpWidget(
      ChangeNotifierProvider<AppState>.value(
        value: AppState(storage: AppStorage(), db: LocalDb.instance),
        child: const MaterialApp(home: PrivacyScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('الخصوصية والبيانات'), findsOneWidget);
    expect(find.text('متابعة إلى الاستبيانات'), findsNothing);
    expect(find.byType(Checkbox), findsNothing);
  });
}
