import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_release.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/ui/widgets/update_notice.dart';

void main() {
  const release = AppRelease(
    version: '1.2.0',
    build: 21,
    downloadUrl: 'https://example.test/app.apk',
    notes: 'تحسينات في المزامنة',
    minBuild: 18,
  );

  Future<void> pumpBanner(
    WidgetTester tester, {
    required bool required,
    VoidCallback? onDismiss,
  }) {
    return tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
          body: UpdateBanner(
            release: release,
            required: required,
            onUpdate: () {},
            onDismiss: onDismiss ?? () {},
          ),
        ),
      ),
    );
  }

  testWidgets('an optional update can be dismissed', (tester) async {
    var dismissed = false;
    await pumpBanner(
      tester,
      required: false,
      onDismiss: () => dismissed = true,
    );

    expect(find.textContaining('يتوفر إصدار جديد'), findsOneWidget);
    expect(find.text('تحسينات في المزامنة'), findsOneWidget);

    await tester.tap(find.byTooltip('إخفاء'));
    expect(dismissed, isTrue);
  });

  testWidgets('a mandatory update cannot be dismissed', (tester) async {
    await pumpBanner(tester, required: true);

    expect(find.textContaining('تحديث إلزامي'), findsOneWidget);
    expect(find.byTooltip('إخفاء'), findsNothing);
    expect(find.text('تحديث'), findsOneWidget);
  });
}
