import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart' show debugPrint;
import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../core/payload_cipher.dart';
import 'models.dart';
import 'sync_service.dart' show PendingStore;

class LocalDb implements PendingStore {
  LocalDb._({PayloadCipher? cipher}) : _cipher = cipher ?? PayloadCipher();

  static final LocalDb instance = LocalDb._();

  final PayloadCipher _cipher;
  Database? _database;
  Future<Database>? _opening;

  /// Two parts of the app (the UI and a background sync task) can ask for the
  /// database at the same moment. Without sharing the in-flight future both
  /// would call `openDatabase` on the same file, and one of them can fail.
  Future<Database> get database {
    final open = _database;
    if (open != null) return Future.value(open);
    return _opening ??= _openOnce();
  }

  Future<Database> _openOnce() async {
    try {
      final db = await _open();
      _database = db;
      try {
        await _createSchema(db);
      } catch (error) {
        // Logged only: the real error surfaces when a row is written, and the
        // open itself should still succeed.
        debugPrint('Could not verify the local schema: $error');
      }
      // Housekeeping starts after the database is usable. Reading the
      // encryption key touches the Android Keystore, which can be slow or fail
      // on a device with a reset lock screen; that must never keep the queue
      // from opening.
      unawaited(_encryptLegacyPayloads(db));
      return db;
    } finally {
      _opening = null;
    }
  }

  Future<Database> _open() async {
    final path = p.join(await getDatabasesPath(), 'medsurvey_pro.db');

    try {
      return await _openAt(path);
    } catch (error) {
      // A file left half-written by an interrupted launch (or a migration that
      // failed) would otherwise break every later start of the app.
      debugPrint('Local database $path could not be opened: $error');
      await _quarantine(path);
      return _openAt(path);
    }
  }

  Future<Database> _openAt(String path) {
    return openDatabase(
      path,
      version: 1,
      onConfigure: (db) async {
        // The UI and the WorkManager background isolate can access the same
        // database. WAL plus a busy timeout avoids "database is locked" errors.
        //
        // Both PRAGMAs return a row, and Android's `execSQL` (which sqflite's
        // `execute` uses) refuses statements that return data with
        // "Queries can be performed using SQLiteDatabase query or rawQuery".
        // Using `execute` here made opening the database fail on every device,
        // so they must go through `rawQuery`.
        try {
          await db.rawQuery('PRAGMA journal_mode=WAL');
          await db.rawQuery('PRAGMA busy_timeout = 5000');
        } catch (error) {
          // Tuning is an optimization; a database that opens is far better
          // than a banner on the login screen.
          debugPrint('Could not tune the local database: $error');
        }
      },
      onCreate: (db, version) => _createSchema(db),
    );
  }

  /// Creates the queue tables if they are missing. Called on first open and
  /// again after every open, so a database file that was created without its
  /// tables (for example by a build whose startup failed half way) repairs
  /// itself instead of rejecting every saved response.
  Future<void> _createSchema(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS pending_responses (
        clientResponseId TEXT PRIMARY KEY,
        collectorId TEXT NOT NULL,
        surveyId TEXT NOT NULL,
        surveyTitle TEXT NOT NULL,
        payload TEXT NOT NULL,
        status TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        lastError TEXT,
        createdAt INTEGER NOT NULL,
        updatedAt INTEGER NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX IF NOT EXISTS pending_collector_idx '
      'ON pending_responses (collectorId, status)',
    );
    await db.execute('''
      CREATE TABLE IF NOT EXISTS app_meta (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
      )
    ''');
  }

  /// Moves an unreadable database aside so a fresh one can be created. The old
  /// file is kept (never deleted) so nothing disappears silently.
  Future<void> _quarantine(String path) async {
    final stamp = DateTime.now().millisecondsSinceEpoch;
    for (final suffix in ['', '-wal', '-shm']) {
      final file = File('$path$suffix');
      try {
        if (!await file.exists()) continue;
        await file.rename('$path.broken-$stamp$suffix');
      } catch (error) {
        debugPrint('Could not move $path$suffix aside: $error');
        try {
          await file.delete();
        } catch (_) {
          // Nothing else we can do; the retry will report the real error.
        }
      }
    }
  }

  /// Decrypts the stored payload before building the entry. A row that cannot
  /// be decrypted (wrong key after a reinstall, or tampering) keeps an empty
  /// payload so the queue keeps working; the sync service then fails it
  /// visibly instead of crashing the whole list.
  Future<PendingEntry> _entryFromRow(Map<String, Object?> row) async {
    final data = Map<String, dynamic>.from(row);
    try {
      data['payload'] = await _cipher.decrypt('${data['payload'] ?? '{}'}');
    } catch (_) {
      data['payload'] = '{}';
    }
    return PendingEntry.fromRow(data);
  }

  /// Encrypts rows saved by older app versions, which stored plain JSON.
  ///
  /// This is housekeeping: if the encryption key cannot be read (unreadable
  /// keystore, reinstall) the rows stay in plain text and the queue keeps
  /// working, instead of the whole database refusing to open.
  Future<void> _encryptLegacyPayloads(Database db) async {
    try {
      final rows = await db.query(
        'pending_responses',
        columns: ['clientResponseId', 'payload'],
        where: "payload NOT LIKE '${PayloadCipher.prefix}%'",
      );
      for (final row in rows) {
        await db.update(
          'pending_responses',
          {'payload': await _cipher.encrypt('${row['payload']}')},
          where: 'clientResponseId = ?',
          whereArgs: [row['clientResponseId']],
        );
      }
    } catch (error) {
      debugPrint('Could not encrypt legacy queue rows: $error');
    }
  }

  Future<void> saveBootstrap(
    String collectorId,
    Map<String, dynamic> json,
  ) async {
    await _putMeta('bootstrap:$collectorId', jsonEncode(json));
  }

  Future<Map<String, dynamic>?> loadBootstrap(String collectorId) async {
    final raw = await _getMeta('bootstrap:$collectorId');
    if (raw == null || raw.isEmpty) return null;
    try {
      return Map<String, dynamic>.from(jsonDecode(raw) as Map);
    } catch (_) {
      return null;
    }
  }

  Future<void> saveLastSync(String collectorId, DateTime value) async {
    await _putMeta('last_sync:$collectorId', value.toIso8601String());
  }

  Future<DateTime?> loadLastSync(String collectorId) async {
    final raw = await _getMeta('last_sync:$collectorId');
    if (raw == null || raw.isEmpty) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> saveBootstrapAt(String collectorId, DateTime value) async {
    await _putMeta('bootstrap_at:$collectorId', value.toIso8601String());
  }

  Future<DateTime?> loadBootstrapAt(String collectorId) async {
    final raw = await _getMeta('bootstrap_at:$collectorId');
    if (raw == null || raw.isEmpty) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> enqueue(PendingEntry entry) async {
    final db = await database;
    final encoded = jsonEncode(entry.payload);

    String payload;
    try {
      payload = await _cipher.encrypt(encoded);
    } catch (error) {
      // An unavailable keystore (reset lock screen, restored backup) must not
      // cost the collector the interview they just finished. The row still
      // lives inside the app's private database, and the reader treats a row
      // without the encryption prefix as legacy plain JSON.
      debugPrint('Could not encrypt the queued payload: $error');
      payload = encoded;
    }

    final values = {
      'clientResponseId': entry.clientResponseId,
      'collectorId': entry.collectorId,
      'surveyId': entry.surveyId,
      'surveyTitle': entry.surveyTitle,
      'payload': payload,
      'status': entry.status,
      'attempts': entry.attempts,
      'lastError': entry.lastError,
      'createdAt': entry.createdAt.millisecondsSinceEpoch,
      'updatedAt': entry.updatedAt.millisecondsSinceEpoch,
    };

    try {
      await db.insert(
        'pending_responses',
        values,
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    } catch (error) {
      // The background sync isolate can hold a write lock for a moment; one
      // retry keeps a finished interview from being rejected.
      debugPrint('Queue insert failed, retrying: $error');
      await Future<void>.delayed(const Duration(milliseconds: 300));
      await db.insert(
        'pending_responses',
        values,
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
  }

  Future<PendingEntry?> find(String clientResponseId) async {
    final db = await database;
    final rows = await db.query(
      'pending_responses',
      where: 'clientResponseId = ?',
      whereArgs: [clientResponseId],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return _entryFromRow(rows.first);
  }

  Future<List<PendingEntry>> entriesFor(String collectorId) async {
    final db = await database;
    final rows = await db.query(
      'pending_responses',
      where: 'collectorId = ?',
      whereArgs: [collectorId],
      orderBy: 'createdAt ASC',
    );
    return Future.wait(rows.map(_entryFromRow));
  }

  @override
  Future<List<PendingEntry>> pendingFor(String collectorId) async {
    final db = await database;
    final rows = await db.query(
      'pending_responses',
      where: "collectorId = ? AND status IN ('pending', 'syncing')",
      whereArgs: [collectorId],
      orderBy: 'createdAt ASC',
    );
    return Future.wait(rows.map(_entryFromRow));
  }

  Future<int> pendingCount(String collectorId) async {
    final db = await database;
    final result = await db.rawQuery(
      "SELECT COUNT(*) AS total FROM pending_responses WHERE collectorId = ? AND status != 'failed'",
      [collectorId],
    );
    return Sqflite.firstIntValue(result) ?? 0;
  }

  @override
  Future<void> markSyncing(String clientResponseId) async {
    await _updateStatus(clientResponseId, 'syncing');
  }

  @override
  Future<void> markPending(String clientResponseId, String? error) async {
    final db = await database;
    await db.rawUpdate(
      '''
      UPDATE pending_responses
      SET status = 'pending', attempts = attempts + 1, lastError = ?, updatedAt = ?
      WHERE clientResponseId = ?
      ''',
      [error, DateTime.now().millisecondsSinceEpoch, clientResponseId],
    );
  }

  @override
  Future<void> markFailed(String clientResponseId, String? error) async {
    final db = await database;
    await db.rawUpdate(
      '''
      UPDATE pending_responses
      SET status = 'failed', attempts = attempts + 1, lastError = ?, updatedAt = ?
      WHERE clientResponseId = ?
      ''',
      [error, DateTime.now().millisecondsSinceEpoch, clientResponseId],
    );
  }

  Future<void> resetToPending(String clientResponseId) async {
    final db = await database;
    await db.update(
      'pending_responses',
      {
        'status': 'pending',
        'lastError': null,
        'updatedAt': DateTime.now().millisecondsSinceEpoch,
      },
      where: 'clientResponseId = ?',
      whereArgs: [clientResponseId],
    );
  }

  Future<void> resetAllFailed(String collectorId) async {
    final db = await database;
    await db.update(
      'pending_responses',
      {
        'status': 'pending',
        'lastError': null,
        'updatedAt': DateTime.now().millisecondsSinceEpoch,
      },
      where: "collectorId = ? AND status = 'failed'",
      whereArgs: [collectorId],
    );
  }

  @override
  Future<void> delete(String clientResponseId) async {
    final db = await database;
    await db.delete(
      'pending_responses',
      where: 'clientResponseId = ?',
      whereArgs: [clientResponseId],
    );
  }

  Future<void> _updateStatus(String clientResponseId, String status) async {
    final db = await database;
    await db.update(
      'pending_responses',
      {'status': status, 'updatedAt': DateTime.now().millisecondsSinceEpoch},
      where: 'clientResponseId = ?',
      whereArgs: [clientResponseId],
    );
  }

  Future<void> _putMeta(String key, String value) async {
    final db = await database;
    await db.insert('app_meta', {
      'key': key,
      'value': value,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> _getMeta(String key) async {
    final db = await database;
    final rows = await db.query(
      'app_meta',
      columns: ['value'],
      where: 'key = ?',
      whereArgs: [key],
      limit: 1,
    );
    return rows.isEmpty ? null : rows.first['value'] as String?;
  }
}
