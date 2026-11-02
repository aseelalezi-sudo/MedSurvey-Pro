import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/session_expiry.dart';
import '../core/theme.dart';
import '../state/app_state.dart';
import 'account_screen.dart';
import 'home_screen.dart';
import 'pending_screen.dart';
import 'widgets/app_bar_background.dart';
import 'widgets/hospital_logo.dart';

class RootShell extends StatefulWidget {
  const RootShell({super.key});

  @override
  State<RootShell> createState() => _RootShellState();
}

class _RootShellState extends State<RootShell> {
  int _index = 0;

  static const _titles = ['الرئيسية', 'بانتظار المزامنة', 'الحساب'];

  Future<void> _sync() async {
    final state = context.read<AppState>();
    final result = await state.syncNow();

    if (!mounted) return;

    final message = result.uploaded > 0
        ? 'تم رفع ${result.uploaded} استبيان بنجاح.'
        : (result.message ?? 'لا توجد استبيانات بانتظار الرفع.');

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final hospitalName = state.hospitalName;
    final palette = context.palette;

    return Scaffold(
      backgroundColor: palette.background,
      appBar: AppBar(
        backgroundColor: palette.heroGradient.colors.first,
        flexibleSpace: BrandAppBarBackground(gradient: palette.heroGradient),
        // Keep the mark close to the two title lines: the app bar is compact,
        // and a wide gap made the name look detached from the logo.
        leadingWidth: 62,
        titleSpacing: 4,
        leading: Center(
          child: HospitalLogo(
            baseUrl: state.baseUrl,
            path: state.hospitalLogo,
            size: 46,
            radius: 14,
            padding: 4,
            fallbackText: hospitalName,
            border: Border.all(
              color: Colors.white.withValues(alpha: 0.55),
              width: 1.2,
            ),
          ),
        ),
        title: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _titles[_index],
              style: const TextStyle(
                fontFamily: 'Tajawal',
                color: Colors.white,
                fontSize: 17.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (hospitalName.isNotEmpty)
              Text(
                hospitalName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(left: 10),
            child: Material(
              color: Colors.white.withValues(alpha: 0.16),
              shape: const CircleBorder(),
              child: IconButton(
                tooltip: 'مزامنة الآن',
                onPressed: state.syncing ? null : _sync,
                icon: state.syncing
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Badge(
                        isLabelVisible: state.pendingCount > 0,
                        backgroundColor: appGold,
                        label: Text('${state.pendingCount}'),
                        child: const Icon(Icons.sync_rounded, size: 21),
                      ),
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          if (state.isLoggedIn && state.sessionExpiringSoon)
            _SessionBanner(
              message: SessionExpiry.hasExpired(state.sessionExpiresAt)
                  ? 'انتهت صلاحية الجلسة على الخادم. سيُطلب تسجيل الدخول '
                        'عند أول اتصال بالإنترنت.'
                  : '${SessionExpiry.label(state.sessionExpiresAt)}. '
                        'ستُجدَّد تلقائيًا عند الاتصال بالإنترنت.',
            ),
          Expanded(
            child: IndexedStack(
              index: _index,
              children: const [HomeScreen(), PendingScreen(), AccountScreen()],
            ),
          ),
        ],
      ),
      // Android 15 draws the app behind the gesture / navigation bar. The
      // safe area keeps the tabs above it instead of hiding them underneath.
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          color: palette.surface,
          border: Border(top: BorderSide(color: palette.line)),
        ),
        child: SafeArea(
          top: false,
          child: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: (value) {
              setState(() => _index = value);
              // Coming back to the home tab is the natural moment to pull
              // edits made on the website; only fires when the cached copy is
              // older than the freshness window.
              if (value == 0) {
                unawaited(context.read<AppState>().refreshSurveysIfStale());
              }
            },
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home_rounded),
                label: 'الرئيسية',
              ),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: state.totalPending > 0,
                  backgroundColor: appGold,
                  label: Text('${state.totalPending}'),
                  child: const Icon(Icons.cloud_upload_outlined),
                ),
                selectedIcon: Badge(
                  isLabelVisible: state.totalPending > 0,
                  backgroundColor: appGold,
                  label: Text('${state.totalPending}'),
                  child: const Icon(Icons.cloud_upload_rounded),
                ),
                label: 'المزامنة',
              ),
              const NavigationDestination(
                icon: Icon(Icons.person_outline_rounded),
                selectedIcon: Icon(Icons.person_rounded),
                label: 'الحساب',
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SessionBanner extends StatelessWidget {
  const _SessionBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      width: double.infinity,
      color: palette.warnSurface,
      padding: const EdgeInsets.fromLTRB(16, 9, 16, 9),
      child: Row(
        children: [
          Icon(Icons.schedule_rounded, size: 16, color: palette.warnFg),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: palette.warnFg,
                fontSize: 11,
                height: 1.4,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
