import 'package:flutter/material.dart';

import '../../core/theme.dart';

/// Rounded surface with the soft shadow used across the app. It picks the
/// light or dark surface colour from the active theme automatically.
class SoftCard extends StatelessWidget {
  const SoftCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin,
    this.radius = 20,
    this.color,
    this.borderColor,
    this.shadow = true,
    this.gradient,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final double radius;
  final Color? color;
  final Color? borderColor;
  final bool shadow;
  final Gradient? gradient;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: gradient == null ? (color ?? palette.surface) : null,
        gradient: gradient,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: borderColor ?? palette.line),
        boxShadow: shadow ? palette.shadow : null,
      ),
      child: child,
    );
  }
}

/// Primary call to action with the brand gradient and a loading state.
class GradientButton extends StatelessWidget {
  const GradientButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.height = 54,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final double height;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !loading;
    final palette = context.palette;

    return AnimatedOpacity(
      duration: const Duration(milliseconds: 150),
      opacity: enabled || loading ? 1 : 0.6,
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          height: height,
          decoration: BoxDecoration(
            gradient: palette.heroGradient,
            borderRadius: BorderRadius.circular(16),
            boxShadow: enabled
                ? [
                    BoxShadow(
                      color: (palette.isDark ? Colors.black : appTeal)
                          .withValues(alpha: palette.isDark ? 0.35 : 0.2),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ]
                : null,
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: enabled ? onPressed : null,
            child: Center(
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (loading)
                    const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  else if (icon != null)
                    Icon(icon, color: Colors.white, size: 21),
                  if (loading || icon != null) const SizedBox(width: 9),
                  Text(
                    label,
                    style: const TextStyle(
                      fontFamily: 'Tajawal',
                      color: Colors.white,
                      fontSize: 15.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Small translucent pill used for survey metadata.
class MetaPill extends StatelessWidget {
  const MetaPill({
    super.key,
    required this.icon,
    required this.label,
    this.onDark = false,
  });

  final IconData icon;
  final String label;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final foreground = onDark ? Colors.white : palette.muted;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: onDark
            ? Colors.white.withValues(alpha: 0.16)
            : palette.surfaceAlt,
        borderRadius: BorderRadius.circular(30),
        border: Border.all(
          color: onDark ? Colors.white.withValues(alpha: 0.22) : palette.line,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: foreground),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: foreground,
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return SoftCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      color: palette.warnSurface,
      borderColor: palette.warnBorder,
      shadow: false,
      child: Row(
        children: [
          _BannerIcon(
            icon: Icons.wifi_off_rounded,
            background: palette.warnIconBg,
            foreground: palette.warnFg,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'وضع عدم الاتصال: يمكنك تعبئة الاستبيانات، وسيتم رفعها تلقائيًا عند عودة الإنترنت.',
              style: TextStyle(
                color: palette.warnFg,
                fontSize: 12,
                height: 1.55,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class MessageBanner extends StatelessWidget {
  const MessageBanner({
    super.key,
    required this.message,
    this.isError = false,
    this.icon,
  });

  final String message;
  final bool isError;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final background = isError ? palette.dangerSurface : palette.mint;
    final foreground = isError ? palette.dangerFg : palette.accent;
    final border = isError ? palette.dangerBorder : palette.mintBorder;

    return SoftCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      color: background,
      borderColor: border,
      shadow: false,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _BannerIcon(
            icon:
                icon ??
                (isError
                    ? Icons.error_outline_rounded
                    : Icons.info_outline_rounded),
            background: isError ? palette.dangerIconBg : palette.mintStrong,
            foreground: foreground,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: foreground,
                fontSize: 12,
                height: 1.6,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BannerIcon extends StatelessWidget {
  const _BannerIcon({
    required this.icon,
    required this.background,
    required this.foreground,
  });

  final IconData icon;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(icon, color: foreground, size: 17),
    );
  }
}

class SectionHeading extends StatelessWidget {
  const SectionHeading({super.key, required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12, top: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 4,
            height: 22,
            margin: const EdgeInsets.only(top: 3, left: 10),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [appTeal, appTealLight],
              ),
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: palette.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (subtitle != null && subtitle!.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: TextStyle(
                      color: palette.muted,
                      fontSize: 12,
                      height: 1.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return SoftCard(
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 34),
      child: Column(
        children: [
          Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              color: palette.mint,
              shape: BoxShape.circle,
              border: Border.all(color: palette.mintBorder, width: 6),
            ),
            child: Icon(icon, color: palette.accent, size: 30),
          ),
          const SizedBox(height: 16),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: palette.ink,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            message,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: palette.muted,
              fontSize: 12.5,
              height: 1.65,
            ),
          ),
          if (action != null) ...[const SizedBox(height: 18), action!],
        ],
      ),
    );
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
    this.ltr = false,
  });

  final IconData icon;
  final String label;
  final String value;

  /// URLs, IDs and version numbers read left-to-right; normal Arabic values
  /// stay aligned with the rest of the RTL layout.
  final bool ltr;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: palette.mint,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, size: 16, color: palette.accent),
          ),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: palette.muted, fontSize: 12.5)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              value,
              textAlign: ltr ? TextAlign.left : TextAlign.right,
              textDirection: ltr ? TextDirection.ltr : null,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: palette.ink,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
