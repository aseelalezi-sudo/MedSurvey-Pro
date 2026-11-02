import 'dart:convert';

import 'package:flutter/foundation.dart' show kReleaseMode;
import 'package:flutter/material.dart' show ThemeMode;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../data/models.dart';

class AppStorage {
  /// The hospital's production endpoint. Release builds are locked to this
  /// address, so a collector never has to know or type connection details.
  ///
  /// The checked-in value is only a placeholder, so the real hospital domain is
  /// never stored in this public repository. Release builds must inject the
  /// address at build time with
  /// `--dart-define=MEDSURVEY_BASE_URL=https://survey.hospital.example`.
  static const productionBaseUrl = String.fromEnvironment(
    'MEDSURVEY_BASE_URL',
    defaultValue: 'https://medsurvey.example.com',
  );

  /// Debug and profile builds keep the editable server field for local tests;
  /// the production APK hides it completely.
  static const allowCustomServer = !kReleaseMode;

  /// Local development server used by debug builds on the Android emulator.
  static const _emulatorBaseUrl = 'http://10.0.2.2:8000';

  static const defaultBaseUrl = allowCustomServer
      ? _emulatorBaseUrl
      : productionBaseUrl;

  /// Address this build should use. Release builds ignore whatever an older
  /// install saved and always talk to the hospital server; debug/profile
  /// builds keep the value the collector entered.
  static String resolveBaseUrl(String stored, {bool? customAllowed}) {
    if (!(customAllowed ?? allowCustomServer)) return productionBaseUrl;
    final value = stored.trim();
    return value.isEmpty ? defaultBaseUrl : value;
  }

  static const _baseUrlKey = 'medsurvey_base_url';
  static const _tokenKey = 'medsurvey_token';
  static const _userKey = 'medsurvey_user';
  static const _expiryKey = 'medsurvey_token_expires';
  static const _workerKey = 'medsurvey_worker_registered';
  static const _themeModeKey = 'medsurvey_theme_mode';
  static const _privacyKey = 'medsurvey_privacy_ack_version';
  static const _brandNameKey = 'medsurvey_brand_name';
  static const _brandLogoKey = 'medsurvey_brand_logo';
  static const _screenshotKey = 'medsurvey_block_screenshots';

  /// Version of the privacy notice wording. Raise [privacyVersion] whenever the
  /// text changes materially, so collectors are asked to read it again.
  static const noticeVersion = '1.0';
  static const privacyVersion = 1;

  final FlutterSecureStorage _secure = const FlutterSecureStorage();
  SharedPreferences? _preferences;

  Future<SharedPreferences> get _prefs async =>
      _preferences ??= await SharedPreferences.getInstance();

  Future<String> getBaseUrl() async {
    final prefs = await _prefs;
    final value = (prefs.getString(_baseUrlKey) ?? '').trim();
    return value.isEmpty ? defaultBaseUrl : value;
  }

  Future<void> saveBaseUrl(String value) async {
    final prefs = await _prefs;
    await prefs.setString(_baseUrlKey, value);
  }

  /// The hospital name and logo path from the last successful sync. Kept in
  /// preferences (not the database) so the header still names the hospital
  /// even if the local database is unavailable.
  Future<String> getBrandName() async {
    final prefs = await _prefs;
    return (prefs.getString(_brandNameKey) ?? '').trim();
  }

  Future<String> getBrandLogo() async {
    final prefs = await _prefs;
    return (prefs.getString(_brandLogoKey) ?? '').trim();
  }

  Future<void> saveBrand({required String name, required String logo}) async {
    final prefs = await _prefs;
    await prefs.setString(_brandNameKey, name);
    await prefs.setString(_brandLogoKey, logo);
  }

  /// True once the collector has acknowledged the current privacy notice.
  Future<bool> privacyAccepted() async {
    final prefs = await _prefs;
    return (prefs.getInt(_privacyKey) ?? 0) >= privacyVersion;
  }

  Future<void> acceptPrivacy() async {
    final prefs = await _prefs;
    await prefs.setInt(_privacyKey, privacyVersion);
  }

  Future<String?> getToken() => _secure.read(key: _tokenKey);

  Future<AppUser?> getUser() async {
    final raw = await _secure.read(key: _userKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      return AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> saveSession(
    String token,
    AppUser user, {
    DateTime? expiresAt,
  }) async {
    await _secure.write(key: _tokenKey, value: token);
    await _secure.write(key: _userKey, value: jsonEncode(user.toJson()));
    if (expiresAt != null) {
      await saveSessionExpiry(expiresAt);
    }
  }

  /// When the server will stop accepting the stored token. Null for sessions
  /// created before the app recorded it.
  Future<DateTime?> getSessionExpiry() async {
    final raw = await _secure.read(key: _expiryKey);
    if (raw == null || raw.isEmpty) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> saveSessionExpiry(DateTime value) async {
    await _secure.write(
      key: _expiryKey,
      value: value.toUtc().toIso8601String(),
    );
  }

  Future<void> saveUser(AppUser user) async {
    await _secure.write(key: _userKey, value: jsonEncode(user.toJson()));
  }

  Future<void> clearSession() async {
    await _secure.delete(key: _tokenKey);
    await _secure.delete(key: _userKey);
    await _secure.delete(key: _expiryKey);
  }

  /// Whether screenshots and the recent-apps preview are blocked. On by
  /// default because the form shows patient data; a facility can turn it off
  /// from the account tab.
  Future<bool> blockScreenshots() async {
    final prefs = await _prefs;
    return prefs.getBool(_screenshotKey) ?? true;
  }

  Future<void> saveBlockScreenshots(bool value) async {
    final prefs = await _prefs;
    await prefs.setBool(_screenshotKey, value);
  }

  Future<bool> workerRegistered() async {
    final prefs = await _prefs;
    return prefs.getBool(_workerKey) ?? false;
  }

  Future<void> markWorkerRegistered() async {
    final prefs = await _prefs;
    await prefs.setBool(_workerKey, true);
  }

  Future<ThemeMode> getThemeMode() async {
    final prefs = await _prefs;

    return switch (prefs.getString(_themeModeKey)) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
  }

  Future<void> saveThemeMode(ThemeMode mode) async {
    final prefs = await _prefs;
    await prefs.setString(_themeModeKey, mode.name);
  }
}
