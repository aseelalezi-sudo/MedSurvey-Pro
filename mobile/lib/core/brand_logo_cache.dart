import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'utils.dart';

/// Loads the hospital logo once and keeps a copy on the device so the brand
/// still appears while the collector works without a connection.
class BrandLogoCache {
  BrandLogoCache._();

  static final BrandLogoCache instance = BrandLogoCache._();

  static const String _urlKey = 'brandLogoUrl';
  static const String _bytesKey = 'brandLogoBytes';
  static const int _maxStoredBytes = 600 * 1024;
  static const Duration _retryAfter = Duration(seconds: 45);

  final Map<String, Uint8List?> _memory = {};
  final Map<String, DateTime> _failedAt = {};
  final Map<String, Future<Uint8List?>> _inFlight = {};

  Future<Uint8List?> load(String baseUrl, String rawPath) {
    final url = resolveMediaUrl(baseUrl, rawPath);
    if (url.isEmpty) return Future<Uint8List?>.value();

    if (_memory.containsKey(url)) {
      final cached = _memory[url];
      if (cached != null) return Future<Uint8List?>.value(cached);

      final failed = _failedAt[url];
      if (failed != null && DateTime.now().difference(failed) < _retryAfter) {
        return Future<Uint8List?>.value();
      }
    }

    return _inFlight[url] ??= _load(url).whenComplete(() {
      _inFlight.remove(url);
    });
  }

  Future<Uint8List?> _load(String url) async {
    final prefs = await SharedPreferences.getInstance();

    if (prefs.getString(_urlKey) == url) {
      final stored = prefs.getString(_bytesKey);
      if (stored != null && stored.isNotEmpty) {
        try {
          final bytes = base64Decode(stored);
          _memory[url] = bytes;
          return bytes;
        } catch (_) {
          // Corrupt cache entry: fall through and download again.
        }
      }
    }

    try {
      final response = await http
          .get(Uri.parse(url), headers: {'Accept': 'image/*'})
          .timeout(const Duration(seconds: 12));

      if (response.statusCode != 200 || response.bodyBytes.isEmpty) {
        return _fail(url);
      }

      final contentType = (response.headers['content-type'] ?? '')
          .toLowerCase();
      if (contentType.isNotEmpty && !contentType.startsWith('image/')) {
        return _fail(url);
      }

      final bytes = response.bodyBytes;
      _memory[url] = bytes;

      if (bytes.length <= _maxStoredBytes) {
        try {
          await prefs.setString(_urlKey, url);
          await prefs.setString(_bytesKey, base64Encode(bytes));
        } catch (_) {
          // A very large logo may not fit in preferences; keep it in memory.
        }
      }

      return bytes;
    } catch (_) {
      return _fail(url);
    }
  }

  Uint8List? _fail(String url) {
    _memory[url] = null;
    _failedAt[url] = DateTime.now();
    return null;
  }

  /// Drops the in-memory copy, used when the server changes.
  void forget() {
    _memory.clear();
    _failedAt.clear();
  }
}
