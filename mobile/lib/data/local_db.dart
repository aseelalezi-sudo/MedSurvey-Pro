import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../core/payload_cipher.dart';
import 'models.dart';

class LocalDb {
  LocalDb._({PayloadCipher? cipher}) : _cipher = cipher ?? PayloadCipher();

  static final LocalDb instance = LocalDb._();

  final PayloadCipher _cipher;
  Database? _database;

  Future<Database> get database async => _database ??= await _open();

  Future<Database> _open() async {
    final path = p.join(await getDatabasesPath(), 'medsurvey_pro.db');

    return openDatabase(
      path,
      version: 1,
      onConfigure: (db) async {
        // The UI and the WorkManager background isolate can access the same
        // database. WAL plus a busy timeout avoids "database is locked" errors.
        await db.rawQuery('PRAGMA journal_mode=WAL');
        await db.execute('PRAGMA busy_timeout = 5000');
      },
      onOpen: _encryptLegacyPayloads,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE pending_responses (
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
          'CREATE INDEX pending_collector_idx ON pending_responses (collectorId, status)',
        );
        await db.execute('''
          CREATE TABLE app_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
          )
        ''');
      },
    );
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
  Future<void> _encryptLegacyPayloads(Database db) async {
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
    await db.insert('pending_responses', {
      'clientResponseId': entry.clientResponseId,
      'collectorId': entry.collectorId,
      'surveyId': entry.surveyId,
      'surveyTitle': entry.surveyTitle,
      'payload': await _cipher.encrypt(jsonEncode(entry.payload)),
      'status': entry.status,
      'attempts': entry.attempts,
      'lastError': entry.lastError,
      'createdAt': entry.createdAt.millisecondsSinceEpoch,
      'updatedAt': entry.updatedAt.millisecondsSinceEpoch,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
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

  Future<void> markSyncing(String clientResponseId) async {
    await _updateStatus(clientResponseId, 'syncing');
  }

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
