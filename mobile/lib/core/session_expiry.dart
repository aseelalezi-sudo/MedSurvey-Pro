/// How much of the server session is left.
///
/// The API issues tokens that expire 30 days after login and never extends
/// them on use, so the app has to renew deliberately. The maths lives here,
/// away from the widget tree, so it can be tested without a device.
class SessionExpiry {
  const SessionExpiry._();

  /// The app renews the token when this much (or less) is left.
  static const warningWindow = Duration(days: 5);

  /// Time left before [expiresAt], clamped at zero. Returns null when the
  /// expiry is unknown (an install from before the app stored it, or a server
  /// that does not report one).
  static Duration? remaining(DateTime? expiresAt, {DateTime? now}) {
    if (expiresAt == null) return null;

    final left = expiresAt.difference(now ?? DateTime.now());
    return left.isNegative ? Duration.zero : left;
  }

  /// Whole days left, rounded up so "0" only means already expired.
  static int? daysLeft(DateTime? expiresAt, {DateTime? now}) {
    final left = remaining(expiresAt, now: now);
    if (left == null) return null;
    if (left == Duration.zero) return 0;
    return (left.inMinutes / Duration.minutesPerDay).ceil();
  }

  static bool isExpiringSoon(
    DateTime? expiresAt, {
    DateTime? now,
    Duration window = warningWindow,
  }) {
    final left = remaining(expiresAt, now: now);
    if (left == null) return false;
    return left <= window;
  }

  static bool hasExpired(DateTime? expiresAt, {DateTime? now}) {
    final left = remaining(expiresAt, now: now);
    return left != null && left == Duration.zero;
  }

  /// Short Arabic sentence for the account screen and the warning banner.
  static String label(DateTime? expiresAt, {DateTime? now}) {
    final days = daysLeft(expiresAt, now: now);
    if (days == null) return 'غير معروفة';

    return switch (days) {
      0 => 'انتهت الجلسة — سجّل الدخول من جديد',
      1 => 'تنتهي اليوم',
      2 => 'تنتهي خلال يومين',
      _ => 'تنتهي خلال $days أيام',
    };
  }
}
