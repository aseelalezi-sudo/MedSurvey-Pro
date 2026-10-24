import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/theme.dart';
import 'state/app_state.dart';
import 'ui/login_screen.dart';
import 'ui/root_shell.dart';
import 'ui/widgets/hospital_logo.dart';

class MedSurveyApp extends StatelessWidget {
  const MedSurveyApp({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    return MaterialApp(
      title: 'MedSurvey Pro',
      debugShowCheckedModeBanner: false,
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: buildAppTheme(),
      darkTheme: buildAppTheme(brightness: Brightness.dark),
      themeMode: state.themeMode,
      builder: (context, child) {
        final dark = Theme.of(context).brightness == Brightness.dark;

        // Android 15 draws the app behind the status and navigation bars, so
        // both bars stay transparent and every screen reserves its own inset.
        return Directionality(
          textDirection: TextDirection.rtl,
          child: AnnotatedRegion<SystemUiOverlayStyle>(
            value: SystemUiOverlayStyle(
              statusBarColor: Colors.transparent,
              statusBarIconBrightness: Brightness.light,
              statusBarBrightness: Brightness.dark,
              systemNavigationBarColor: Colors.transparent,
              systemNavigationBarDividerColor: Colors.transparent,
              systemNavigationBarIconBrightness: dark
                  ? Brightness.light
                  : Brightness.dark,
            ),
            child: child ?? const SizedBox.shrink(),
          ),
        );
      },
      home: !state.initialized
          ? const _SplashScreen()
          : state.isLoggedIn
          ? const RootShell()
          : const LoginScreen(),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final hospitalName = state.bootstrap?.hospitalName ?? '';
    final palette = context.palette;

    return Scaffold(
      backgroundColor: appTealDark,
      body: Container(
        decoration: BoxDecoration(gradient: palette.heroGradient),
        child: Stack(
          children: [
            Positioned(
              top: -60,
              left: -40,
              child: Container(
                width: 190,
                height: 190,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.06),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Positioned(
              bottom: -70,
              right: -50,
              child: Container(
                width: 220,
                height: 220,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.05),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  HospitalLogo(
                    baseUrl: state.baseUrl,
                    path: state.bootstrap?.hospitalLogo ?? '',
                    size: 100,
                    radius: 30,
                    padding: 14,
                    fallbackText: hospitalName,
                    showShadow: true,
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.55),
                      width: 3,
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'MedSurvey Pro',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 25,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.2,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    hospitalName.isEmpty
                        ? 'استبيانات رضا المرضى'
                        : hospitalName,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.92),
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 26),
                  SizedBox(
                    width: 26,
                    height: 26,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.4,
                      color: Colors.white.withValues(alpha: 0.9),
                      backgroundColor: Colors.white.withValues(alpha: 0.2),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
