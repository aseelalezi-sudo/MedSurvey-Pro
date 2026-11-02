import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/diagnostics.dart';

void main() {
  test('copied log text never carries phone numbers or e-mails', () {
    final text = Diagnostics.sanitize(
      'فشل الاتصال بالرقم 777123456 والبريد patient@example.com',
    );

    expect(text, isNot(contains('777123456')));
    expect(text, isNot(contains('patient@example.com')));
  });

  test('long messages are truncated', () {
    final text = Diagnostics.sanitize('x' * 900, maxLength: 120);

    expect(text.length, lessThanOrEqualTo(121));
    expect(text.endsWith('…'), isTrue);
  });

  test('records newest first and keeps a bounded buffer', () async {
    final diagnostics = Diagnostics(
      directoryProvider: () =>
          Directory.systemTemp.createTemp('medsurvey-diag'),
    );

    for (var index = 0; index < Diagnostics.maxEntries + 10; index++) {
      diagnostics.record('failure $index');
    }

    expect(diagnostics.count, Diagnostics.maxEntries);
    expect(diagnostics.entries.first.message, 'failure 69');
    expect(diagnostics.entries.last.message, 'failure 10');

    await diagnostics.clear();
    expect(diagnostics.count, 0);
    expect(diagnostics.asText(), contains('0 سجل'));
  });

  test('saving and restoring keeps the log across launches', () async {
    final directory = await Directory.systemTemp.createTemp('medsurvey-diag');
    final diagnostics = Diagnostics(directoryProvider: () async => directory);

    diagnostics.record('first failure');
    // The write is fire-and-forget; give the microtask a moment to finish.
    await Future<void>.delayed(const Duration(milliseconds: 50));

    final restored = Diagnostics(directoryProvider: () async => directory);
    await restored.restore();

    expect(restored.count, 1);
    expect(restored.entries.first.message, 'first failure');
  });
}
