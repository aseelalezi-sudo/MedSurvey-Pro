import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/api_client.dart';
import 'package:medsurvey_app/data/models.dart';
import 'package:medsurvey_app/data/sync_service.dart';

const _user = AppUser(
  id: 'u1',
  username: 'collector',
  name: 'Collector',
  role: 'staff',
);

PendingEntry _entry(String id) => PendingEntry(
  clientResponseId: id,
  collectorId: _user.id,
  surveyId: 's1',
  surveyTitle: 'Survey',
  payload: {'clientResponseId': id},
  status: 'pending',
  attempts: 0,
  createdAt: DateTime(2026, 1, 1),
  updatedAt: DateTime(2026, 1, 1),
);

class _FakeStore implements PendingStore {
  _FakeStore(List<String> ids) : entries = ids.map(_entry).toList();

  final List<PendingEntry> entries;
  final deleted = <String>[];
  final pending = <String, String?>{};
  final failed = <String, String?>{};
  final syncing = <String>[];

  @override
  Future<List<PendingEntry>> pendingFor(String collectorId) async => entries;

  @override
  Future<void> markSyncing(String id) async => syncing.add(id);

  @override
  Future<void> markPending(String id, String? error) async =>
      pending[id] = error;

  @override
  Future<void> markFailed(String id, String? error) async =>
      failed[id] = error;

  @override
  Future<void> delete(String id) async => deleted.add(id);
}

/// Answers each submission from [outcomes] (an [ApiException] is thrown, any
/// other value is returned), keyed by clientResponseId.
class _FakeApi extends ApiClient {
  _FakeApi(this.outcomes) : super(baseUrl: 'https://example.test');

  final Map<String, Object> outcomes;
  final submitted = <String>[];

  @override
  Future<Map<String, dynamic>> submitResponse({
    required String token,
    required Map<String, dynamic> payload,
  }) async {
    final id = payload['clientResponseId'] as String;
    submitted.add(id);
    final outcome = outcomes[id] ?? <String, dynamic>{'id': id};
    if (outcome is Exception) throw outcome;
    if (outcome is Error) throw outcome;
    return outcome as Map<String, dynamic>;
  }
}

Future<SyncResult> _run(_FakeApi api, _FakeStore store) =>
    SyncService.sync(api: api, db: store, token: 't', user: _user);

void main() {
  test('uploads every queued response and removes it from the queue', () async {
    final store = _FakeStore(['a', 'b']);
    final result = await _run(_FakeApi({}), store);

    expect(result.ok, isTrue);
    expect(result.uploaded, 2);
    expect(store.deleted, ['a', 'b']);
    expect(store.pending, isEmpty);
  });

  test('a duplicate answer from the server still clears the entry', () async {
    final store = _FakeStore(['a']);
    final result = await _run(
      _FakeApi({
        'a': {'id': 'x', 'duplicate': true},
      }),
      store,
    );

    expect(result.ok, isTrue);
    expect(store.deleted, ['a']);
  });

  test('an expired token stops the sync and keeps the entry queued', () async {
    final store = _FakeStore(['a', 'b']);
    final api = _FakeApi({'a': const ApiException('no', statusCode: 401)});

    final result = await _run(api, store);

    expect(result.unauthorized, isTrue);
    expect(result.ok, isFalse);
    expect(api.submitted, ['a'], reason: 'the second entry must not be tried');
    expect(store.deleted, isEmpty);
    expect(store.pending.keys, ['a']);
  });

  test('a network failure stops the sync without marking anything failed',
      () async {
    final store = _FakeStore(['a', 'b']);
    final api = _FakeApi({'a': const ApiException('offline', network: true)});

    final result = await _run(api, store);

    expect(result.ok, isFalse);
    expect(result.message, 'offline');
    expect(api.submitted, ['a']);
    expect(store.pending.keys, ['a']);
    expect(store.failed, isEmpty);
  });

  test('a validation error is marked failed and the queue continues', () async {
    final store = _FakeStore(['a', 'b']);
    final api = _FakeApi({'a': const ApiException('bad', statusCode: 422)});

    final result = await _run(api, store);

    expect(result.ok, isTrue);
    expect(result.failed, 1);
    expect(result.uploaded, 1);
    expect(store.failed.keys, ['a']);
    expect(store.deleted, ['b']);
  });

  test('a server error keeps the entry queued and continues', () async {
    final store = _FakeStore(['a', 'b']);
    final api = _FakeApi({'a': const ApiException('down', statusCode: 503)});

    final result = await _run(api, store);

    expect(result.ok, isFalse, reason: 'a retry is still needed');
    expect(result.uploaded, 1);
    expect(store.pending.keys, ['a']);
    expect(store.failed, isEmpty);
    expect(store.deleted, ['b']);
  });

  test('an unexpected error keeps the entry queued', () async {
    final store = _FakeStore(['a']);
    final api = _FakeApi({'a': StateError('boom')});

    final result = await _run(api, store);

    expect(result.ok, isFalse);
    expect(store.pending.keys, ['a']);
    expect(store.deleted, isEmpty);
  });

  test('a second sync started during one is a no-op', () async {
    final store = _FakeStore(['a']);
    final first = _run(_FakeApi({}), store);
    final second = await _run(_FakeApi({}), store);

    expect(second.uploaded, 0);
    expect(second.message, isNotNull);
    expect((await first).uploaded, 1);
  });
}
