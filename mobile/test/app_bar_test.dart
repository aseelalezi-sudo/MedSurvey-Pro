import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/ui/widgets/app_bar_background.dart';

void main() {
  testWidgets('brand app bar background fills the whole bar', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
          appBar: AppBar(
            title: const Text('الاستبيانات'),
            flexibleSpace: const BrandAppBarBackground(
              gradient: appHeroGradient,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // AppBar lays flexibleSpace out with loose constraints; before the fix the
    // gradient collapsed to zero height and white titles sat on white.
    final size = tester.getSize(find.byType(BrandAppBarBackground));
    expect(size.height, greaterThanOrEqualTo(60));
    expect(size.width, greaterThan(200));
  });
}
