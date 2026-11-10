import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/app_release.dart';
import '../../core/theme.dart';
import '../../core/update_installer.dart';

/// Hands the download page to the phone's browser. When no browser handles the
/// link (or the platform channel is unavailable) the address is shown in a
/// copyable dialog instead, so the collector is never stuck with a dead button.
Future<void> openReleaseDownload(BuildContext context, String url) async {
  final opened = await UpdateInstaller.openDownload(url);
  if (opened || !context.mounted) return;

  final palette = context.palette;

  await showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      title: const Text('رابط التحديث'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'تعذّر فتح المتصفح تلقائيًا. انسخ الرابط وافتحه على هاتفك.',
            style: TextStyle(height: 1.6, fontSize: 12.5),
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: palette.surfaceAlt,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: palette.line),
            ),
            child: SelectableText(
              url,
              textDirection: TextDirection.ltr,
              style: TextStyle(fontSize: 11, color: palette.muted),
            ),
          ),
        ],
      ),
      actions: [
        TextButton.icon(
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: url));
            if (!dialogContext.mounted) return;
            Navigator.of(dialogContext).pop();
            ScaffoldMessenger.of(context)
              ..hideCurrentSnackBar()
              ..showSnackBar(
                const SnackBar(content: Text('تم نسخ رابط التحديث.')),
              );
          },
          icon: const Icon(Icons.copy_rounded, size: 16),
          label: const Text('نسخ الرابط'),
        ),
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('إغلاق'),
        ),
      ],
    ),
  );
}

/// Banner above the tabs announcing a newer Android build. Mandatory releases
/// (below the server's `minBuild`) hide the dismiss button.
class UpdateBanner extends StatelessWidget {
  const UpdateBanner({
    super.key,
    required this.release,
    required this.required,
    required this.onUpdate,
    required this.onDismiss,
  });

  final AppRelease release;
  final bool required;
  final VoidCallback onUpdate;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      width: double.infinity,
      color: palette.mint,
      padding: const EdgeInsetsDirectional.fromSTEB(16, 8, 8, 8),
      child: Row(
        children: [
          Icon(Icons.system_update_rounded, size: 18, color: palette.accent),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  required
                      ? 'يتوفر تحديث إلزامي (${release.version})'
                      : 'يتوفر إصدار جديد (${release.version})',
                  style: TextStyle(
                    color: palette.accent,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (release.notes.isNotEmpty)
                  Text(
                    release.notes,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: palette.muted,
                      fontSize: 10,
                      height: 1.4,
                    ),
                  ),
              ],
            ),
          ),
          TextButton(onPressed: onUpdate, child: const Text('تحديث')),
          if (!required)
            IconButton(
              tooltip: 'إخفاء',
              visualDensity: VisualDensity.compact,
              onPressed: onDismiss,
              icon: Icon(Icons.close_rounded, size: 17, color: palette.muted),
            ),
        ],
      ),
    );
  }
}
