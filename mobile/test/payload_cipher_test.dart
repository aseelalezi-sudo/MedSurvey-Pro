import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/payload_cipher.dart';

PayloadCipher _cipher([int seed = 1]) =>
    PayloadCipher(keyProvider: () async => List<int>.filled(32, seed));

void main() {
  const sample = '{"patientInfo":{"name":"منى","phone":"0500000000"}}';

  test('round-trips a payload and hides it from plain-text search', () async {
    final cipher = _cipher();

    final stored = await cipher.encrypt(sample);

    expect(PayloadCipher.isEncrypted(stored), isTrue);
    expect(stored, isNot(contains('0500000000')));
    expect(await cipher.decrypt(stored), sample);
  });

  test('uses a fresh nonce so equal payloads encrypt differently', () async {
    final cipher = _cipher();

    expect(await cipher.encrypt(sample), isNot(await cipher.encrypt(sample)));
  });

  test('reads rows written before encryption existed', () async {
    expect(await _cipher().decrypt(sample), sample);
  });

  test('rejects a tampered ciphertext', () async {
    final cipher = _cipher();
    final stored = await cipher.encrypt(sample);
    final bytes = base64Decode(stored.substring(PayloadCipher.prefix.length));
    bytes[bytes.length - 1] ^= 0x01;
    final tampered = '${PayloadCipher.prefix}${base64Encode(bytes)}';

    expect(
      cipher.decrypt(tampered),
      throwsA(isA<SecretBoxAuthenticationError>()),
    );
  });

  test('cannot be decrypted with a different key', () async {
    final stored = await _cipher(1).encrypt(sample);

    expect(
      _cipher(2).decrypt(stored),
      throwsA(isA<SecretBoxAuthenticationError>()),
    );
  });
}
