import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../data/models.dart';

class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statusCode,
    this.errors,
    this.network = false,
  });

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;
  final bool network;

  bool get isUnauthorized => statusCode == 401;

  bool get isRetryable {
    if (network) return true;
    final code = statusCode;
    if (code == null) return true;
    if (code == 408 || code == 429) return true;
    return code >= 500;
  }

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({required this.baseUrl});

  final String baseUrl;
  static const _timeout = Duration(seconds: 25);

  Uri _uri(String path, {bool noCache = false}) {
    final uri = Uri.parse('$baseUrl/api/v1$path');
    if (!noCache) return uri;

    // Proxies and shared hosts may cache GET responses; a changing query
    // parameter guarantees the app sees edits made on the website.
    return uri.replace(
      queryParameters: {
        ...uri.queryParameters,
        '_t': '${DateTime.now().millisecondsSinceEpoch}',
      },
    );
  }

  Future<LoginResult> login({
    required String username,
    required String password,
    String deviceName = 'android-app',
  }) async {
    final json = await _request(
      'POST',
      '/auth/login',
      body: {
        'username': username,
        'password': password,
        'deviceName': deviceName,
      },
    );

    final token = (json['token'] ?? '').toString();
    final user = AppUser.fromJson(asMap(json['user']));

    if (token.isEmpty || user.id.isEmpty) {
      throw const ApiException('استجابة دخول غير صالحة من الخادم.');
    }

    return LoginResult(token: token, user: user);
  }

  Future<Map<String, dynamic>> fetchBootstrap(String token) {
    return _request('GET', '/bootstrap', token: token);
  }

  /// Fetches one survey definition straight from the server so an open form
  /// never uses a stale cached version after the survey was edited.
  Future<Map<String, dynamic>> fetchSurvey(String token, String id) {
    return _request('GET', '/surveys/${Uri.encodeComponent(id)}', token: token);
  }

  /// Lightweight reachability probe used by the server settings panel.
  Future<Map<String, dynamic>> health({
    Duration timeout = const Duration(seconds: 8),
  }) {
    return _request('GET', '/health', timeout: timeout);
  }

  Future<Map<String, dynamic>> submitResponse({
    required String token,
    required Map<String, dynamic> payload,
  }) {
    return _request('POST', '/responses', token: token, body: payload);
  }

  Future<void> logout(String token) async {
    await _request('POST', '/auth/logout', token: token);
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    String? token,
    Map<String, dynamic>? body,
    Duration timeout = _timeout,
  }) async {
    final client = http.Client();

    try {
      final request = http.Request(
        method,
        _uri(path, noCache: method == 'GET'),
      );
      request.headers['Accept'] = 'application/json';
      request.headers['Content-Type'] = 'application/json; charset=utf-8';
      if (method == 'GET') {
        request.headers['Cache-Control'] = 'no-cache';
        request.headers['Pragma'] = 'no-cache';
      }
      if (token != null && token.isNotEmpty) {
        request.headers['Authorization'] = 'Bearer $token';
      }
      if (body != null) {
        request.body = jsonEncode(body);
      }

      final streamed = await client.send(request).timeout(timeout);
      final responseBody = await streamed.stream.bytesToString().timeout(
        timeout,
      );
      final decoded = _decode(responseBody);

      if (streamed.statusCode >= 200 && streamed.statusCode < 300) {
        return decoded;
      }

      throw ApiException(
        _extractMessage(decoded, streamed.statusCode),
        statusCode: streamed.statusCode,
        errors: decoded['errors'] is Map ? asMap(decoded['errors']) : null,
      );
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw const ApiException(
        'انتهت مهلة الاتصال بالخادم. تأكد أن الخادم يعمل، وأن الهاتف على نفس الشبكة، وأن العنوان صحيح (استخدم http:// لخادم الشبكة المحلية).',
        network: true,
      );
    } on HandshakeException {
      throw const ApiException(
        'الخادم لا يدعم الاتصال المشفر (https). إن كان الخادم محليًا على الشبكة فاستخدم http:// بدل https://.',
        network: true,
      );
    } on TlsException {
      throw const ApiException(
        'تعذر إنشاء اتصال آمن (https) مع الخادم. تحقق من الشهادة، أو استخدم http:// لخادم الشبكة المحلية.',
        network: true,
      );
    } on SocketException {
      throw const ApiException(
        'لا يمكن الوصول إلى الخادم. تحقق من الإنترنت أو عنوان الخادم.',
        network: true,
      );
    } on IOException {
      throw const ApiException(
        'تعذر إكمال الاتصال بالخادم. تحقق من الشبكة وعنوان الخادم.',
        network: true,
      );
    } on http.ClientException {
      throw const ApiException(
        'تعذر إكمال الاتصال بالخادم. تحقق من الشبكة.',
        network: true,
      );
    } on FormatException {
      throw const ApiException('استجابة غير صالحة من الخادم.', network: true);
    } finally {
      client.close();
    }
  }

  Map<String, dynamic> _decode(String body) {
    if (body.trim().isEmpty) return <String, dynamic>{};
    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) return decoded;
    if (decoded is Map) return Map<String, dynamic>.from(decoded);
    return <String, dynamic>{};
  }

  String _extractMessage(Map<String, dynamic> body, int statusCode) {
    final errors = body['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) return value.first.toString();
        if (value != null && value.toString().trim().isNotEmpty) {
          return value.toString();
        }
      }
    }

    final message = (body['message'] ?? '').toString().trim();
    const genericEnglish = {
      'The given data was invalid.',
      'Unauthenticated.',
      'This action is unauthorized.',
      'Forbidden',
      'Not Found',
      'Too Many Attempts.',
      'Server Error',
    };
    if (message.isNotEmpty && !genericEnglish.contains(message)) {
      return message;
    }

    return switch (statusCode) {
      401 => 'انتهت الجلسة أو أن بيانات الدخول غير صالحة.',
      403 => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
      404 => 'العنصر المطلوب غير موجود على الخادم.',
      405 =>
        'الخادم لا يدعم هذه النسخة من واجهة التطبيق (405). تأكد من نشر تحديثات الموقع.',
      409 => 'تم رفع هذه الاستجابة مسبقًا.',
      422 => 'البيانات المدخلة غير صحيحة. يرجى مراجعة الحقول.',
      429 => 'عدد المحاولات كبير. يرجى الانتظار قليلًا ثم إعادة المحاولة.',
      >= 500 => 'حدث خطأ في الخادم. حاول مرة أخرى لاحقًا.',
      _ => 'تعذر إكمال الطلب (رمز $statusCode).',
    };
  }
}
