import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/theme.dart';
import '../core/utils.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import 'widgets/common.dart';

class PendingScreen extends StatelessWidget {
  const PendingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final entries = state.pending;

    return RefreshIndicator(
      color: context.palette.accent,
      backgroundColor: context.palette.surface,
      onRefresh: state.reloadPending,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
        children: [
          if (!state.online) const OfflineBanner(),
          _SummaryCard(state: state),
          const SizedBox(height: 18),
          SectionHeading(
            title: 'قائمة الانتظار',
            subtitle: entries.isEmpty
                ? null
                : '${entries.length} استبيان محفوظ على الجهاز',
          ),
          if (entries.isEmpty)
            const EmptyState(
              icon: Icons.cloud_done_outlined,
              title: 'لا توجد استبيانات معلقة',
              message:
                  'كل الاستبيانات التي تم إدخالها تم رفعها إلى الموقع. يمكنك تعبئة استبيان جديد من الشاشة الرئيسية.',
            )
          else
            for (final entry in entries)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _PendingCard(entry: entry),
              ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.state});

  final AppState state;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: palette.line),
      ),
      child: Column(
        children: [
          Row(
            children: [
              _Counter(
                value: state.pendingCount,
                label: 'بانتظار الرفع',
                color: palette.accent,
                icon: Icons.cloud_upload_outlined,
              ),
              const SizedBox(width: 10),
              _Counter(
                value: state.failedCount,
                label: 'تحتاج مراجعة',
                color: palette.dangerFg,
                icon: Icons.error_outline_rounded,
              ),
              const SizedBox(width: 10),
              _Counter(
                value: state.surveys.length,
                label: 'استبيانات محفوظة',
                color: const Color(0xFF2563EB),
                icon: Icons.assignment_outlined,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
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
              ),
              if (state.failedCount > 0) ...[
                const SizedBox(width: 10),
                OutlinedButton.icon(
                  onPressed: state.syncing ? null : state.retryAllFailed,
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: const Text('إعادة الكل'),
                ),
              ],
            ],
          ),
          if (state.syncError != null) ...[
            const SizedBox(height: 12),
            MessageBanner(message: state.syncError!, isError: true),
          ],
          const SizedBox(height: 4),
          Text(
            state.lastSyncAt == null
                ? 'لم تتم أي مزامنة بعد'
                : 'آخر مزامنة ناجحة: ${formatDateTime(state.lastSyncAt!)}',
            style: TextStyle(color: palette.faint, fontSize: 10.5),
          ),
        ],
      ),
    );
  }
}

class _Counter extends StatelessWidget {
  const _Counter({
    required this.value,
    required this.label,
    required this.color,
    required this.icon,
  });

  final int value;
  final String label;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11, horizontal: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: palette.isDark ? 0.16 : 0.07),
          borderRadius: BorderRadius.circular(13),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 19),
            const SizedBox(height: 5),
            Text(
              '$value',
              style: TextStyle(
                color: color,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(color: palette.muted, fontSize: 9.5),
            ),
          ],
        ),
      ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  const _PendingCard({required this.entry});

  final PendingEntry entry;

  Future<void> _delete(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Text('حذف الاستبيان المعلق؟'),
        content: const Text(
          'سيتم حذف هذه الاستجابة من الجهاز نهائيًا، ولن تُرفع إلى الموقع.',
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
            child: const Text('حذف'),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AppState>().deletePending(entry.clientResponseId);
    }
  }

  @override
  Widget build(BuildContext context) {
    final failed = entry.isFailed;
    final palette = context.palette;
    final accent = failed ? palette.dangerFg : palette.accent;

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: failed ? palette.dangerBorder : palette.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: palette.isDark ? 0.18 : 0.08),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(
                  failed
                      ? Icons.error_outline_rounded
                      : entry.isSyncing
                      ? Icons.sync_rounded
                      : Icons.schedule_rounded,
                  color: accent,
                  size: 19,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  entry.surveyTitle,
                  style: TextStyle(
                    color: palette.ink,
                    fontSize: 13.5,
                    fontWeight: FontWeight.bold,
                    height: 1.4,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: palette.isDark ? 0.18 : 0.08),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  entry.statusLabel,
                  style: TextStyle(
                    color: accent,
                    fontSize: 9.5,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          Row(
            children: [
              Icon(Icons.schedule_outlined, size: 13, color: palette.faint),
              const SizedBox(width: 4),
              Text(
                formatRelative(entry.createdAt),
                style: TextStyle(color: palette.faint, fontSize: 10.5),
              ),
              if (entry.attempts > 0) ...[
                const SizedBox(width: 10),
                Text(
                  'محاولات: ${entry.attempts}',
                  style: TextStyle(color: palette.faint, fontSize: 10.5),
                ),
              ],
              const Spacer(),
              Text(
                '#${entry.clientResponseId.length > 8 ? entry.clientResponseId.substring(0, 8) : entry.clientResponseId}',
                textDirection: TextDirection.ltr,
                style: TextStyle(color: palette.faint, fontSize: 9.5),
              ),
            ],
          ),
          if (entry.lastError != null) ...[
            const SizedBox(height: 9),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: palette.dangerSurface,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                entry.lastError!,
                style: TextStyle(
                  color: palette.dangerFg,
                  fontSize: 11,
                  height: 1.5,
                ),
              ),
            ),
          ],
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              if (failed)
                TextButton.icon(
                  onPressed: () =>
                      context.read<AppState>().retryOne(entry.clientResponseId),
                  icon: const Icon(Icons.refresh_rounded, size: 16),
                  label: const Text('إعادة المحاولة'),
                ),
              TextButton.icon(
                onPressed: () => _delete(context),
                style: TextButton.styleFrom(foregroundColor: palette.dangerFg),
                icon: const Icon(Icons.delete_outline_rounded, size: 16),
                label: const Text('حذف'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
