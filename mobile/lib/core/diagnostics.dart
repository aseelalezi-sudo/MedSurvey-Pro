import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

import 'app_info.dart';

/// One recorded failure, kept on the device so a field problem can be reported
/// without asking the collector to read Android logs.
class DiagnosticEntry {
  const DiagnosticEntry({
    required this.time,
    required this.message,
    this.stack = '',
  });

  final DateTime time;
  final String message;
  final String stack;

  Map<String, dynamic> toJson() => {
    'time': time.toIso8601String(),
    'message': message,
    'stack': stack,
  };

  factory DiagnosticEntry.fromJson(Map<String, dynamic> json) {
    return DiagnosticEntry(
      time:
          DateTime.tryParse('${json['time'] ?? ''}') ??
          DateTime.fromMillisecondsSinceEpoch(0),
      message: '${json['message'] ?? ''}',
      stack: '${json['stack'] ?? ''}',
    );
  }
}

/// A privacy-conscious, on-device crash log.
///
/// The hospital did not want a third-party crash service receiving anything
/// from patient devices, so failures stay in the app's private storage and are
/// only copied out when a collector taps "نسخ السجل" and sends it deliberately.
/// [sanitize] strips phone numbers and e-mail addresses, so even that copy does
/// not carry patient identifiers.
class Diagnostics {
  Diagnostics({Future<Directory> Function()? directoryProvider})
    : _directoryProvider = directoryProvider ?? getApplicationSupportDirectory;

  static final Diagnostics instance = Diagnostics();

  static const maxEntries = 60;
  static const _fileName = 'diagnostics.json';

  final Future<Directory> Function() _directoryProvider;
  final List<DiagnosticEntry> _entries = [];
  bool _installed = false;
  bool _writing = false;
  bool _dirty = false;

  /// Newest first.
  List<DiagnosticEntry> get entries => List.unmodifiable(_entries);

  int get count => _entries.length;

  /// Hooks Flutter's uncaught-error handlers. Safe to call more than once.
  void install() {
    if (_installed) return;
    _installed = true;

    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      record(
        details.exception,
        stack: details.stack,
        context: details.context?.toString(),
      );
      previous?.call(details);
    };

    PlatformDispatcher.instance.onError = (error, stack) {
      record(error, stack: stack);
      return true;
    };

    unawaited(restore());
  }

  /// Loads the log written by an earlier launch. Failures here are ignored:
  /// diagnostics must never be a reason for the app not to start.
  Future<void> restore() async {
    try {
      final file = await _file().timeout(const Duration(seconds: 2));
      if (!await file.exists()) return;

      final decoded = jsonDecode(await file.readAsString());
      if (decoded is! List) return;

      _entries
        ..clear()
        ..addAll(
          decoded
              .whereType<Map>()
              .map(
                (row) =>
                    DiagnosticEntry.fromJson(Map<String, dynamic>.from(row)),
              )
              .take(maxEntries),
        );
    } catch (error) {
      debugPrint('Could not read the diagnostic log: $error');
    }
  }

  void record(Object error, {StackTrace? stack, String? context}) {
    final message = sanitize(
      [context, error.toString()].whereType<String>().join(' — '),
    );
    if (message.isEmpty) return;

    _entries.insert(
      0,
      DiagnosticEntry(
        time: DateTime.now(),
        message: message,
        stack: sanitize('${stack ?? ''}', maxLength: 900),
      ),
    );
    if (_entries.length > maxEntries) {
      _entries.removeRange(maxEntries, _entries.length);
    }

    unawaited(_persist());
  }

  void recordMessage(String message) => record(message);

  Future<void> clear() async {
    _entries.clear();
    await _persist();
  }

  /// Plain text for the "copy" button in the account screen.
  String asText() {
    final buffer = StringBuffer()
      ..writeln('MedSurvey Pro $appVersion')
      ..writeln('${DateTime.now().toIso8601String()} · ${_entries.length} سجل')
      ..writeln();

    for (final entry in _entries) {
      buffer.writeln('• ${entry.time.toIso8601String()}');
      buffer.writeln(entry.message);
      if (entry.stack.isNotEmpty) {
        buffer.writeln(entry.stack);
      }
      buffer.writeln();
    }

    return buffer.toString().trimRight();
  }

  Future<File> _file() async {
    final directory = await _directoryProvider();
    return File('${directory.path}/$_fileName');
  }

  Future<void> _persist() async {
    if (_writing) {
      _dirty = true;
      return;
    }

    _writing = true;
    try {
      do {
        _dirty = false;
        final file = await _file();
        await file.writeAsString(
          jsonEncode(_entries.map((entry) => entry.toJson()).toList()),
          flush: false,
        );
      } while (_dirty);
    } catch (error) {
      debugPrint('Could not save the diagnostic log: $error');
    } finally {
      _writing = false;
    }
  }

  /// Strips identifiers that must never leave a patient device. The log is
  /// meant for a developer, not for the hospital's records.
  static String sanitize(String value, {int maxLength = 500}) {
    var text = value.replaceAll(RegExp(r'\s+'), ' ').trim();
    text = text.replaceAll(RegExp(r'\d{9,}'), '••••••');
    text = text.replaceAll(RegExp(r'[\w.+-]+@[\w-]+\.[\w.]+'), '•••@•••');
    if (text.length > maxLength) {
      text = '${text.substring(0, maxLength)}…';
    }
    return text;
  }
}
