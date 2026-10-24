import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/app.dart';
import 'package:medsurvey_app/core/app_storage.dart';
import 'package:medsurvey_app/core/theme.dart';
import 'package:medsurvey_app/data/local_db.dart';
import 'package:medsurvey_app/state/app_state.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  for (final brightness in [Brightness.light, Brightness.dark]) {
    final name = brightness == Brightness.light ? 'light' : 'dark';
    final palette = brightness == Brightness.light
        ? AppPalette.light
        : AppPalette.dark;

    testWidgets('$name theme keeps typed input text readable', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(brightness: brightness),
          home: const Scaffold(body: Center(child: TextField())),
        ),
      );
      await tester.pumpAndSettle();

      final editable = tester.widget<EditableText>(find.byType(EditableText));
      expect(editable.style.color, palette.ink);
      expect(editable.style.color, isNot(palette.inputFill));

      final context = tester.element(find.byType(TextField));
      final theme = Theme.of(context);
      expect(theme.extension<AppPalette>()?.isDark, palette.isDark);
      expect(theme.inputDecorationTheme.fillColor, palette.inputFill);
      expect(theme.scaffoldBackgroundColor, palette.background);
    });
  }

  testWidgets('app builder sees the active theme brightness', (tester) async {
    Brightness? seen;

    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        darkTheme: buildAppTheme(brightness: Brightness.dark),
        themeMode: ThemeMode.dark,
        builder: (context, child) {
          seen = Theme.of(context).brightness;
          return child ?? const SizedBox.shrink();
        },
        home: const Scaffold(body: SizedBox()),
      ),
    );
    await tester.pumpAndSettle();

    // Used to pick light / dark navigation-bar icons in app.dart.
    expect(seen, Brightness.dark);
  });

  testWidgets('dark theme is applied when the platform is dark', (
    tester,
  ) async {
    tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
    addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);

    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        darkTheme: buildAppTheme(brightness: Brightness.dark),
        themeMode: ThemeMode.system,
        home: const Scaffold(body: SizedBox()),
      ),
    );
    await tester.pumpAndSettle();

    final context = tester.element(find.byType(Scaffold));
    expect(Theme.of(context).brightness, Brightness.dark);
    expect(Theme.of(context).extension<AppPalette>()?.isDark, isTrue);
  });

  testWidgets('app follows the theme mode chosen in the account screen', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final state = AppState(storage: AppStorage(), db: LocalDb.instance)
      ..initialized = true;

    await state.setThemeMode(ThemeMode.dark);
    expect(await AppStorage().getThemeMode(), ThemeMode.dark);

    await tester.pumpWidget(
      ChangeNotifierProvider<AppState>.value(
        value: state,
        child: const MedSurveyApp(),
      ),
    );
    await tester.pumpAndSettle();

    final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(app.themeMode, ThemeMode.dark);

    await state.setThemeMode(ThemeMode.light);
    await tester.pumpAndSettle();
    expect(
      tester.widget<MaterialApp>(find.byType(MaterialApp)).themeMode,
      ThemeMode.light,
    );
  });
}
