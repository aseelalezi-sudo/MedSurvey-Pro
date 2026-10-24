import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/data/models.dart';
import 'package:medsurvey_app/state/app_state.dart';
import 'package:medsurvey_app/ui/home_screen.dart';
import 'package:provider/provider.dart';

AppState _stateWith(BootstrapData data) {
  return AppState(storage: AppStorage(), db: LocalDb.instance)
    ..bootstrap = data;
}

BootstrapData _bootstrap() {
  return BootstrapData.fromJson({
    'user': {
      'id': 'u1',
      'username': 'staff',
      'name': 'جامع الاستبيانات',
      'role': 'staff',
    },
    'surveys': [
      {
        'id': 's1',
        'title': 'استبيان تجربة المريض',
        'description': 'قيّم الخدمات التي حصلت عليها.',
        'sections': [
          {
            'id': 'sec1',
            'title': 'الاستقبال',
            'questions': [
              {'id': 'q1', 'type': 'stars', 'title': 'ما تقييمك؟'},
            ],
          },
        ],
      },
    ],
    'settings': {
      'hospital': {
        'name': 'مستشفى الاختبار',
        'welcomeMessage': 'أهلاً بك، نسعد بمشاركتنا رأيك',
        'description': 'رعاية صحية متكاملة لعائلتك.',
      },
      'surveySettings': {'allowAnonymous': true},
    },
  });
}

void main() {
  testWidgets('home renders the website-style hero above the surveys', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ChangeNotifierProvider<AppState>.value(
        value: _stateWith(_bootstrap()),
        child: MaterialApp(
          theme: buildAppTheme(),
          builder: (context, child) =>
              Directionality(textDirection: TextDirection.rtl, child: child!),
          home: const Scaffold(body: HomeScreen()),
        ),
      ),
    );
    await tester.pump();

    // Welcome pill uses the hospital welcome message configured on the site.
    expect(
      find.textContaining('نسعد بمشاركتنا', findRichText: true),
      findsOneWidget,
    );
    // Headline + trust chips mirror the website hero.
    expect(
      find.textContaining('رأيكم يصنع', findRichText: true),
      findsOneWidget,
    );
    expect(find.text('3 دقائق فقط'), findsOneWidget);
    expect(find.text('آمن 100%'), findsOneWidget);
    // The hospital tagline configured on the site replaces the default copy.
    expect(find.text('رعاية صحية متكاملة لعائلتك.'), findsOneWidget);
    // Survey list is still on the home tab.
    expect(find.text('الاستبيانات المتاحة'), findsOneWidget);
    expect(find.text('استبيان تجربة المريض'), findsOneWidget);
    // Stats moved to the account tab, so they are not on the home tab.
    expect(find.text('بانتظار الرفع'), findsNothing);
    expect(find.text('لم يتم التحديث بعد'), findsOneWidget);
  });
}
