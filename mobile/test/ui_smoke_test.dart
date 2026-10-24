import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/data/models.dart';
import 'package:medsurvey_app/ui/widgets/hospital_logo.dart';
import 'package:medsurvey_app/ui/widgets/question_input.dart';

Widget _wrap(Widget child) {
  return MaterialApp(
    theme: buildAppTheme(),
    locale: const Locale('ar'),
    home: Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: child,
        ),
      ),
    ),
  );
}

SurveyQuestion _question(
  String type, {
  List<QuestionOption> options = const [],
}) {
  return SurveyQuestion(
    id: 'q-$type',
    type: type,
    title: 'ما تقييمك لخدمة الاستقبال والتعامل من فريق المستشفى؟',
    description: 'وصف قصير يوضح الهدف من السؤال.',
    required: true,
    options: options,
  );
}

void main() {
  testWidgets('all answer inputs render on a narrow phone', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 760));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final questions = [
      _question('stars'),
      _question('emoji'),
      _question('rating'),
      _question('nps'),
      _question('yes_no'),
      _question(
        'multiple_choice',
        options: const [
          QuestionOption(value: 'a', label: 'الاستقبال'),
          QuestionOption(value: 'b', label: 'الطوارئ'),
        ],
      ),
      _question('text'),
    ];

    await tester.pumpWidget(
      _wrap(
        Column(
          children: [
            for (final question in questions)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: QuestionInput(
                  question: question,
                  value: null,
                  reason: '',
                  onChanged: (_) {},
                  onReasonChanged: (_) {},
                ),
              ),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.byType(QuestionInput), findsNWidgets(questions.length));
  });

  testWidgets('selected answers keep the layout stable', (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _wrap(
        QuestionInput(
          question: _question('stars'),
          value: 1,
          reason: 'انتظار طويل',
          onChanged: (_) {},
          onReasonChanged: (_) {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
  });

  testWidgets('hospital logo falls back without a server path', (tester) async {
    await tester.pumpWidget(
      _wrap(
        const HospitalLogo(
          baseUrl: 'https://survey.example.com',
          path: '',
          fallbackText: 'مستشفى',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.byType(HospitalLogo), findsOneWidget);
  });
}
