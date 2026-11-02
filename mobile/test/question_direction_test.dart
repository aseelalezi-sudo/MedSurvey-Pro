import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/data/models.dart';
import 'package:medsurvey_app/ui/widgets/question_input.dart';

void main() {
  testWidgets('in Arabic "نعم" sits on the left of "لا"', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ar'),
        supportedLocales: const [Locale('ar'), Locale('en')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: Padding(
              padding: const EdgeInsets.all(16),
              child: QuestionInput(
                question: const SurveyQuestion(
                  id: 'q1',
                  type: 'yes_no',
                  title: 'هل كانت الخدمة جيدة؟',
                ),
                value: null,
                onChanged: (_) {},
                reason: '',
                onReasonChanged: (_) {},
              ),
            ),
          ),
        ),
      ),
    );

    final yes = tester.getCenter(find.text('نعم'));
    final no = tester.getCenter(find.text('لا'));

    // RTL reads right to left, so the positive answer belongs on the left,
    // matching the star scale where the highest rating is leftmost.
    expect(yes.dx, lessThan(no.dx));
  });
}
