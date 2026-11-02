import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../core/app_info.dart';
import '../core/diagnostics.dart';
import '../core/session_expiry.dart';
import '../core/theme.dart';
import '../core/utils.dart';
import '../state/app_state.dart';
import 'privacy_screen.dart';
import 'widgets/common.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  Future<void> _logout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Text('تسجيل الخروج'),
        content: const Text(
          'سيتم تسجيل الخروج من هذا الجهاز. الاستبيانات المحفوظة ولم تُرفع ستبقى محفوظة، وستُرفع عند تسجيل الدخول مرة أخرى بنفس الحساب.',
          style: TextStyle(height: 1.6, fontSize: 13.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: appDanger,
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('تسجيل الخروج'),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AppState>().logout();
    }
  }

  /// Shows the on-device failure log. Everything here stays local unless the
  /// collector explicitly copies it, and the entries never contain patient
  /// identifiers (see [Diagnostics.sanitize]).
  Future<void> _showDiagnostics(BuildContext context) async {
    final palette = context.palette;
    final diagnostics = Diagnostics.instance;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: palette.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (sheetContext) {
        final entries = diagnostics.entries;

        return DraggableScrollableSheet(
          expand: false,
          initialChildSize: 0.6,
          minChildSize: 0.35,
          maxChildSize: 0.92,
          builder: (context, controller) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'سجل الأعطال',
                        style: TextStyle(
                          color: palette.ink,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: entries.isEmpty
                          ? null
                          : () async {
                              await Clipboard.setData(
                                ClipboardData(text: diagnostics.asText()),
                              );
                              if (!sheetContext.mounted) return;
                              ScaffoldMessenger.of(sheetContext)
                                ..hideCurrentSnackBar()
                                ..showSnackBar(
                                  const SnackBar(
                                    content: Text(
                                      'تم نسخ السجل. أرسله للدعم عند الحاجة.',
                                    ),
                                  ),
                                );
                            },
                      icon: const Icon(Icons.copy_rounded, size: 16),
                      label: const Text('نسخ'),
                    ),
                    IconButton(
                      tooltip: 'مسح السجل',
                      onPressed: entries.isEmpty
                          ? null
                          : () async {
                              await diagnostics.clear();
                              if (sheetContext.mounted) {
                                Navigator.of(sheetContext).pop();
                              }
                            },
                      icon: const Icon(Icons.delete_outline_rounded, size: 20),
                    ),
                  ],
                ),
                Text(
                  'تُحفظ الأعطال على هذا الجهاز فقط، دون أسماء المرضى أو '
                  'أرقام هواتفهم، ولا تُرسل تلقائيًا.',
                  style: TextStyle(
                    color: palette.muted,
                    fontSize: 10.5,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 10),
                Expanded(
                  child: entries.isEmpty
                      ? Center(
                          child: Text(
                            'لا توجد أعطال مسجلة.',
                            style: TextStyle(color: palette.muted),
                          ),
                        )
                      : ListView.separated(
                          controller: controller,
                          itemCount: entries.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final entry = entries[index];
                            return Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: palette.surfaceAlt,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: palette.line),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    formatDateTime(entry.time),
                                    style: TextStyle(
                                      color: palette.faint,
                                      fontSize: 9.5,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    entry.message,
                                    style: TextStyle(
                                      color: palette.ink,
                                      fontSize: 11.5,
                                      height: 1.5,
                                    ),
                                  ),
                                  if (entry.stack.isNotEmpty) ...[
                                    const SizedBox(height: 4),
                                    Text(
                                      entry.stack,
                                      maxLines: 3,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: palette.muted,
                                        fontSize: 9,
                                        fontFamily: 'monospace',
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            );
                          },
                        ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final user = state.user;
    final palette = context.palette;

    if (user == null) {
      return const Center(child: CircularProgressIndicator());
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 30),
      children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: palette.heroGradient,
            borderRadius: BorderRadius.circular(22),
            boxShadow: palette.shadow,
          ),
          child: Row(
            children: [
              Container(
                width: 60,
                height: 60,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.18),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.35),
                  ),
                ),
                child: Text(
                  user.initials,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '@${user.username}',
                      textDirection: TextDirection.ltr,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.95),
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.16),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        user.roleLabel,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _Card(
          title: 'بيانات الحساب',
          icon: Icons.person_outline_rounded,
          child: Column(
            children: [
              InfoRow(
                icon: Icons.apartment_rounded,
                label: 'القسم',
                value: user.department ?? 'غير محدد',
              ),
              InfoRow(
                icon: Icons.dns_outlined,
                label: 'خادم الموقع',
                value: state.baseUrl,
                ltr: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _Card(
          title: 'الجلسة والأمان',
          icon: Icons.shield_outlined,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              InfoRow(
                icon: Icons.schedule_rounded,
                label: 'حالة الجلسة',
                value: SessionExpiry.label(state.sessionExpiresAt),
              ),
              InfoRow(
                icon: Icons.event_available_outlined,
                label: 'تنتهي بتاريخ',
                value: state.sessionExpiresAt == null
                    ? 'غير متاح'
                    : formatDateTime(state.sessionExpiresAt!),
              ),
              if (state.sessionExpiringSoon) ...[
                const SizedBox(height: 10),
                MessageBanner(
                  message:
                      'جلستك على وشك الانتهاء. ستُجدَّد تلقائيًا عند توفر '
                      'الإنترنت، أو اضغط «تجديد الجلسة» الآن.',
                ),
              ],
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: state.busy
                    ? null
                    : () async {
                        final messenger = ScaffoldMessenger.of(context);
                        final renewed = await context
                            .read<AppState>()
                            .renewSession();
                        messenger
                          ..hideCurrentSnackBar()
                          ..showSnackBar(
                            SnackBar(
                              content: Text(
                                renewed
                                    ? 'تم تجديد الجلسة بنجاح لمدة 30 يومًا.'
                                    : 'تعذّر تجديد الجلسة الآن. تحقق من الاتصال.',
                              ),
                            ),
                          );
                      },
                icon: const Icon(Icons.autorenew_rounded, size: 18),
                label: const Text('تجديد الجلسة الآن'),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'منع لقطات الشاشة',
                          style: TextStyle(
                            color: palette.ink,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'يمنع تصوير بيانات المرضى وإظهارها في قائمة التطبيقات.',
                          style: TextStyle(
                            color: palette.muted,
                            fontSize: 10.5,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Switch(
                    value: state.blockScreenshots,
                    onChanged: state.setBlockScreenshots,
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _Card(
          title: 'مظهر التطبيق',
          icon: Icons.brightness_6_outlined,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: _ThemeChoice(
                      icon: Icons.brightness_auto_rounded,
                      label: 'تلقائي',
                      selected: state.themeMode == ThemeMode.system,
                      onTap: () => state.setThemeMode(ThemeMode.system),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _ThemeChoice(
                      icon: Icons.light_mode_outlined,
                      label: 'فاتح',
                      selected: state.themeMode == ThemeMode.light,
                      onTap: () => state.setThemeMode(ThemeMode.light),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _ThemeChoice(
                      icon: Icons.dark_mode_outlined,
                      label: 'داكن',
                      selected: state.themeMode == ThemeMode.dark,
                      onTap: () => state.setThemeMode(ThemeMode.dark),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                '«تلقائي» يتبع إعداد الوضع الداكن في هاتفك. يمكنك تثبيت الوضع '
                'الفاتح أو الداكن من هنا في أي وقت.',
                style: TextStyle(
                  color: palette.muted,
                  fontSize: 11,
                  height: 1.6,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _Card(
          title: 'المزامنة والبيانات',
          icon: Icons.cloud_sync_outlined,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: _StatBox(
                      icon: Icons.assignment_rounded,
                      value: '${state.surveys.length}',
                      label: 'استبيان متاح',
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _StatBox(
                      icon: Icons.cloud_upload_rounded,
                      value: '${state.pendingCount}',
                      label: 'بانتظار الرفع',
                      tone: palette.warnFg,
                      surface: palette.warnSurface,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _StatBox(
                      icon: Icons.error_outline_rounded,
                      value: '${state.failedCount}',
                      label: 'بحاجة لمراجعة',
                      tone: palette.dangerFg,
                      surface: palette.dangerSurface,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              InfoRow(
                icon: Icons.cloud_done_outlined,
                label: 'آخر مزامنة للرفع',
                value: state.lastSyncAt == null
                    ? 'لم تتم بعد'
                    : formatDateTime(state.lastSyncAt!),
              ),
              InfoRow(
                icon: Icons.update_rounded,
                label: 'آخر تحديث للاستبيانات',
                value: state.lastBootstrapAt == null
                    ? 'لم يتم بعد'
                    : formatDateTime(state.lastBootstrapAt!),
              ),
              if (state.syncing) ...[
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: const LinearProgressIndicator(minHeight: 4),
                ),
              ],
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: state.syncing ? null : () => state.syncNow(),
                icon: state.syncing
                    ? SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: palette.isDark
                              ? const Color(0xFF04231F)
                              : Colors.white,
                        ),
                      )
                    : const Icon(Icons.sync_rounded),
                label: Text(state.syncing ? 'جارٍ الرفع...' : 'مزامنة الآن'),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: state.busy ? null : () => state.refreshBootstrap(),
                icon: state.busy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('تحديث الاستبيانات من الموقع'),
              ),
              if (state.refreshError != null) ...[
                const SizedBox(height: 10),
                MessageBanner(message: state.refreshError!, isError: true),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        _Card(
          title: 'الخصوصية والبيانات',
          icon: Icons.shield_outlined,
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const PrivacyScreen()),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: palette.mint,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Icon(
                      Icons.description_outlined,
                      size: 16,
                      color: palette.accent,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'إشعار الخصوصية وسياسة البيانات',
                          style: TextStyle(
                            color: palette.ink,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'ما نجمعه، ولماذا، وكيف يُحمى',
                          style: TextStyle(color: palette.muted, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    // Mirrored by Flutter in Arabic, so `chevron_right` points
                    // left (forward) at the trailing edge of the row.
                    Icons.chevron_right_rounded,
                    size: 20,
                    color: palette.faint,
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        _Card(
          title: 'التشخيص',
          icon: Icons.bug_report_outlined,
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: () => _showDiagnostics(context),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: palette.surfaceAlt,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Icon(
                      Icons.receipt_long_outlined,
                      size: 16,
                      color: palette.muted,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'سجل الأعطال',
                          style: TextStyle(
                            color: palette.ink,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          Diagnostics.instance.count == 0
                              ? 'لا توجد أعطال مسجلة'
                              : '${Diagnostics.instance.count} سجل — يُحفظ على الجهاز فقط',
                          style: TextStyle(color: palette.muted, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 20,
                    color: palette.faint,
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        const _Card(
          title: 'عن التطبيق',
          icon: Icons.info_outline_rounded,
          child: Column(
            children: [
              InfoRow(
                icon: Icons.phone_android_rounded,
                label: 'الإصدار',
                value: appVersion,
              ),
              InfoRow(
                icon: Icons.offline_bolt_outlined,
                label: 'وضع العمل',
                value: 'يدعم عدم الاتصال',
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        OutlinedButton.icon(
          onPressed: () => _logout(context),
          style: OutlinedButton.styleFrom(
            foregroundColor: palette.dangerFg,
            side: BorderSide(color: palette.dangerBorder),
          ),
          icon: const Icon(Icons.logout_rounded, size: 19),
          label: const Text('تسجيل الخروج'),
        ),
        const SizedBox(height: 14),
        Text(
          'MedSurvey Pro · نظام إدارة استبيانات رضا المرضى',
          textAlign: TextAlign.center,
          style: TextStyle(color: palette.faint, fontSize: 10.5),
        ),
      ],
    );
  }
}

class _ThemeChoice extends StatelessWidget {
  const _ThemeChoice({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: selected ? palette.mint : palette.surfaceAlt,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? palette.accent : palette.line,
              width: selected ? 1.6 : 1,
            ),
          ),
          child: Column(
            children: [
              Icon(
                icon,
                size: 20,
                color: selected ? palette.accent : palette.muted,
              ),
              const SizedBox(height: 5),
              Text(
                label,
                style: TextStyle(
                  fontFamily: 'Tajawal',
                  fontSize: 11.5,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                  color: selected ? palette.accent : palette.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatBox extends StatelessWidget {
  const _StatBox({
    required this.icon,
    required this.value,
    required this.label,
    this.tone,
    this.surface,
  });

  final IconData icon;
  final String value;
  final String label;
  final Color? tone;
  final Color? surface;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final foreground = tone ?? palette.accent;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: surface ?? palette.surfaceAlt,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: palette.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 13, color: foreground),
              const SizedBox(width: 4),
              Text(
                value,
                style: TextStyle(
                  color: palette.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  height: 1,
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: palette.muted,
              fontSize: 9.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.title, required this.icon, required this.child});

  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return SoftCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: palette.mint,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(icon, size: 18, color: palette.accent),
              ),
              const SizedBox(width: 9),
              Text(
                title,
                style: TextStyle(
                  color: palette.ink,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}
