import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/data/models.dart';
import 'package:medsurvey_app/state/app_state.dart';
import 'package:medsurvey_app/ui/survey_form_screen.dart';
import 'package:provider/provider.dart';

const _user = AppUser(
  id: 'u1',
  username: 'collector',
  name: 'أحمد العلي',
  role: 'staff',
);

const _survey = Survey(
  id: 's1',
  title: 'تقييم تجربة المريض في المستشفى',
  requireName: true,
  requirePhone: true,
  sections: [
    SurveySection(
      id: 'sec1',
      title: 'الاستقبال والبيئة العامة',
      questions: [
        SurveyQuestion(
          id: 'q1',
          type: 'stars',
          title: 'ما تقييمك لخدمة الاستقبال؟',
          required: true,
        ),
      ],
    ),
  ],
);

/// Long enough that the visit card scrolls out of view, which is what used to
/// break "jump to the first missing field".
final _longSurvey = Survey(
  id: 's2',
  title: 'استبيان طويل',
  requirePhone: true,
  sections: [
    SurveySection(
      id: 'sec1',
      title: 'قسم طويل',
      questions: [
        for (var i = 1; i <= 10; i++)
          SurveyQuestion(
            id: 'q$i',
            type: 'stars',
            title: 'سؤال رقم $i',
            required: true,
          ),
      ],
    ),
  ],
);

AppState _state({
  SurveySettings settings = const SurveySettings(
    allowAnonymous: false,
    requirePhone: true,
  ),
  Survey survey = _survey,
}) {
  return AppState(storage: AppStorage(), db: LocalDb.instance)
    ..initialized = true
    ..token = 'token'
    ..user = _user
    ..bootstrap = BootstrapData(
      user: _user,
      surveys: [survey],
      departments: const [],
      ageGroups: const [],
      visitTypes: const [],
      settings: settings,
      hospitalName: 'مستشفى تجريبي',
    );
}

Widget _app(AppState state, Widget home) {
  return ChangeNotifierProvider<AppState>.value(
    value: state,
    child: MaterialApp(
      theme: buildAppTheme(),
      locale: const Locale('ar'),
      home: home,
    ),
  );
}

void main() {
  testWidgets('incomplete survey names every missing field', (tester) async {
    tester.view.physicalSize = const Size(420, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(_state(), const SurveyFormScreen(survey: _survey)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();

    final dialog = find.byType(AlertDialog);
    expect(find.text('الاستبيان غير مكتمل'), findsOneWidget);
    expect(
      find.descendant(of: dialog, matching: find.text('بيانات الزيارة')),
      findsOneWidget,
    );
    // The phone is required by the settings, so it is listed as missing.
    expect(
      find.descendant(of: dialog, matching: find.textContaining('رقم الهاتف')),
      findsOneWidget,
    );
    // The unanswered question is named with its section and number.
    expect(
      find.descendant(
        of: dialog,
        matching: find.text('الاستقبال والبيئة العامة'),
      ),
      findsOneWidget,
    );
    expect(
      find.descendant(of: dialog, matching: find.textContaining('السؤال 1')),
      findsOneWidget,
    );
    expect(
      find.descendant(
        of: dialog,
        matching: find.textContaining('ما تقييمك لخدمة الاستقبال'),
      ),
      findsOneWidget,
    );

    await tester.tap(find.text('الانتقال لأول حقل'));
    await tester.pumpAndSettle();

    expect(find.text('الاستبيان غير مكتمل'), findsNothing);
  });

  testWidgets('phone stays optional when the survey settings allow it', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    // Neither the survey flags nor the site settings require the phone.
    await tester.pumpWidget(
      _app(
        _state(settings: const SurveySettings(allowAnonymous: false)),
        const SurveyFormScreen(
          survey: Survey(
            id: 's3',
            title: 'استبيان بلا هاتف',
            sections: [
              SurveySection(
                id: 'sec1',
                title: 'قسم',
                questions: [
                  SurveyQuestion(
                    id: 'q1',
                    type: 'stars',
                    title: 'سؤال',
                    required: true,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();

    final dialog = find.byType(AlertDialog);
    expect(dialog, findsOneWidget);
    expect(
      find.descendant(of: dialog, matching: find.textContaining('رقم الهاتف')),
      findsNothing,
    );
    // The field itself is still available, marked as optional.
    expect(find.text('رقم الهاتف (اختياري)'), findsWidgets);
  });

  testWidgets('survey-level name and phone requirements override anonymous', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    // Global settings allow anonymous responses, but the survey itself asks
    // for the name and the phone: the app must follow the survey flags, just
    // like the website does.
    await tester.pumpWidget(
      _app(
        _state(
          settings: const SurveySettings(
            allowAnonymous: true,
            requireName: false,
            requirePhone: false,
          ),
        ),
        const SurveyFormScreen(survey: _survey),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('اسم المريض *'), findsOneWidget);
    expect(find.text('رقم الهاتف *'), findsOneWidget);

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();

    final dialog = find.byType(AlertDialog);
    expect(dialog, findsOneWidget);
    expect(
      find.descendant(of: dialog, matching: find.textContaining('اسم المريض')),
      findsOneWidget,
    );
    expect(
      find.descendant(of: dialog, matching: find.textContaining('رقم الهاتف')),
      findsOneWidget,
    );
  });

  testWidgets('jump to the first missing field scrolls it into view', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(_state(survey: _longSurvey), SurveyFormScreen(survey: _longSurvey)),
    );
    await tester.pumpAndSettle();

    final visitHeader = find.text('بيانات الزيارة').first;
    final startY = tester.getTopLeft(visitHeader).dy;

    // Scroll the form down; the submit button itself lives in a fixed bottom
    // bar, so drag the scrollable instead of looking for the button.
    await tester.drag(
      find.byType(SingleChildScrollView),
      const Offset(0, -4000),
    );
    await tester.pumpAndSettle();
    expect(tester.getTopLeft(visitHeader).dy, lessThan(startY));
    expect(tester.getTopLeft(visitHeader).dy, lessThan(-100));

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();
    expect(find.text('الاستبيان غير مكتمل'), findsOneWidget);

    await tester.tap(find.text('الانتقال لأول حقل'));
    await tester.pumpAndSettle();

    // The visit card is back inside the viewport, near its top edge.
    final y = tester.getTopLeft(visitHeader).dy;
    expect(y, greaterThanOrEqualTo(0));
    expect(y, lessThan(350));
  });

  testWidgets('tapping a missing question in the dialog jumps straight to it', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(
        _state(
          settings: const SurveySettings(allowAnonymous: true),
          survey: _longSurvey,
        ),
        SurveyFormScreen(survey: _longSurvey),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'الطوارئ');
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('أقل من 18 سنة').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('زيارة طارئة').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('ذكر'));
    await tester.pumpAndSettle();

    await tester.drag(
      find.byType(SingleChildScrollView),
      const Offset(0, -6000),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();
    expect(find.text('الاستبيان غير مكتمل'), findsOneWidget);

    // Tap the question row itself (not the "first field" button).
    await tester.tap(find.textContaining('السؤال 1').first);
    await tester.pumpAndSettle();

    expect(find.text('الاستبيان غير مكتمل'), findsNothing);
    final question = find.text('سؤال رقم 1 *', findRichText: true);
    expect(question, findsOneWidget);
    final questionTop = tester.getTopLeft(question).dy;
    expect(questionTop, greaterThanOrEqualTo(0));
    expect(questionTop, lessThan(400));
  });

  testWidgets('jump scrolls to the unanswered question, not just the top', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      _app(
        _state(
          settings: const SurveySettings(allowAnonymous: true),
          survey: _longSurvey,
        ),
        SurveyFormScreen(survey: _longSurvey),
      ),
    );
    await tester.pumpAndSettle();

    // Fill the visit card so the first remaining issue is the question itself.
    await tester.enterText(find.byType(TextFormField).first, 'الطوارئ');
    await tester.enterText(find.byType(TextFormField).at(2), '712345678');
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('أقل من 18 سنة').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('زيارة طارئة').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('ذكر'));
    await tester.pumpAndSettle();

    // Scroll far away from the first question.
    await tester.drag(
      find.byType(SingleChildScrollView),
      const Offset(0, -6000),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('إرسال'));
    await tester.pumpAndSettle();
    expect(find.text('الاستبيان غير مكتمل'), findsOneWidget);
    // The visit card is complete, so the first issue is question 1.
    expect(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text('بيانات الزيارة'),
      ),
      findsNothing,
    );

    await tester.tap(find.text('الانتقال لأول حقل'));
    await tester.pumpAndSettle();

    final question = find.text('سؤال رقم 1 *', findRichText: true);
    expect(question, findsOneWidget);
    final questionTop = tester.getTopLeft(question).dy;
    expect(questionTop, greaterThanOrEqualTo(0));
    expect(questionTop, lessThan(400));
  });
}
