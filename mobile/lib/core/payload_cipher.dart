import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Encrypts the offline queue's patient answers before they reach SQLite.
///
/// The queued payload holds names, phone numbers and free-text answers, and the
/// database file is readable on a lost or rooted phone. AES-256-GCM with a key
/// kept in the Android Keystore (through `flutter_secure_storage`) makes the
/// file useless without the device. Values written before encryption existed
/// have no prefix and are read as-is, then re-written encrypted.
class PayloadCipher {
  PayloadCipher({Future<List<int>> Function()? keyProvider})
    : _keyProvider = keyProvider ?? _secureStorageKey;

  static const prefix = 'enc1:';
  static const _keyName = 'medsurvey_data_key';

  final Future<List<int>> Function() _keyProvider;
  final AesGcm _algorithm = AesGcm.with256bits();
  SecretKey? _key;

  static bool isEncrypted(String stored) => stored.startsWith(prefix);

  Future<SecretKey> _secretKey() async =>
      _key ??= SecretKey(await _keyProvider());

  Future<String> encrypt(String plain) async {
    final box = await _algorithm.encrypt(
      utf8.encode(plain),
      secretKey: await _secretKey(),
    );
    return '$prefix${base64Encode(box.concatenation())}';
  }

  /// Returns [stored] unchanged when it is not encrypted (legacy rows).
  /// Throws [SecretBoxAuthenticationError] when the data was altered or the
  /// key does not match.
  Future<String> decrypt(String stored) async {
    if (!isEncrypted(stored)) return stored;

    final bytes = base64Decode(stored.substring(prefix.length));
    final box = SecretBox.fromConcatenation(
      bytes,
      nonceLength: _algorithm.nonceLength,
      macLength: _algorithm.macAlgorithm.macLength,
    );
    final clear = await _algorithm.decrypt(box, secretKey: await _secretKey());
    return utf8.decode(clear);
  }

  static Future<List<int>> _secureStorageKey() async {
    const storage = FlutterSecureStorage();
    final existing = await storage.read(key: _keyName);
    if (existing != null && existing.isNotEmpty) {
      return base64Decode(existing);
    }

    final created = await AesGcm.with256bits().newSecretKey();
    final bytes = await created.extractBytes();
    await storage.write(key: _keyName, value: base64Encode(bytes));
    return bytes;
  }
}
