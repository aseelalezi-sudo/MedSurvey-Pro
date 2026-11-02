import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/session_expiry.dart';

void main() {
  final now = DateTime.utc(2026, 11, 2, 12);

  test('days left are rounded up so zero only means expired', () {
    expect(
      SessionExpiry.daysLeft(now.add(const Duration(days: 30)), now: now),
      30,
    );
    expect(
      SessionExpiry.daysLeft(now.add(const Duration(hours: 12)), now: now),
      1,
    );
    expect(SessionExpiry.daysLeft(now, now: now), 0);
    expect(
      SessionExpiry.daysLeft(now.subtract(const Duration(days: 3)), now: now),
      0,
    );
    expect(SessionExpiry.daysLeft(null, now: now), isNull);
  });

  test('the warning window starts five days before expiry', () {
    expect(
      SessionExpiry.isExpiringSoon(now.add(const Duration(days: 6)), now: now),
      isFalse,
    );
    expect(
      SessionExpiry.isExpiringSoon(now.add(const Duration(days: 5)), now: now),
      isTrue,
    );
    expect(
      SessionExpiry.isExpiringSoon(now.add(const Duration(hours: 6)), now: now),
      isTrue,
    );
    expect(SessionExpiry.isExpiringSoon(null, now: now), isFalse);
  });

  test('expired tokens are detected', () {
    expect(SessionExpiry.hasExpired(now, now: now), isTrue);
    expect(
      SessionExpiry.hasExpired(
        now.subtract(const Duration(minutes: 1)),
        now: now,
      ),
      isTrue,
    );
    expect(
      SessionExpiry.hasExpired(now.add(const Duration(minutes: 1)), now: now),
      isFalse,
    );
    expect(SessionExpiry.hasExpired(null, now: now), isFalse);
  });

  test('labels are readable in Arabic', () {
    expect(SessionExpiry.label(null, now: now), 'غير معروفة');
    expect(
      SessionExpiry.label(now.add(const Duration(hours: 3)), now: now),
      'تنتهي اليوم',
    );
    expect(
      SessionExpiry.label(now.add(const Duration(days: 2)), now: now),
      'تنتهي خلال يومين',
    );
    expect(
      SessionExpiry.label(now.add(const Duration(days: 7)), now: now),
      'تنتهي خلال 7 أيام',
    );
    expect(
      SessionExpiry.label(now.subtract(const Duration(minutes: 1)), now: now),
      contains('انتهت'),
    );
  });
}
