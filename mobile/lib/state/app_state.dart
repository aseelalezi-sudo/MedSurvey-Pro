import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart' show ThemeMode;
import 'package:flutter/widgets.dart';
import 'package:uuid/uuid.dart';

import '../core/api_client.dart';
import '../core/app_storage.dart';
import '../core/utils.dart';
import '../data/local_db.dart';
import '../data/models.dart';
import '../data/sync_service.dart';

/// Outcome of the "test connection" button on the login screen.
class ServerCheck {
  const ServerCheck({required this.ok, required this.message, this.url = ''});

  final bool ok;
  final String message;

  /// The normalized address that was actually called, so the screen can show
  /// the user how their input was interpreted (for example adding http://).
  final String url;
}

class AppState extends ChangeNotifier with WidgetsBindingObserver {
  AppState({required this.storage, required this.db});

  final AppStorage storage;
  final LocalDb db;

  AppUser? user;
  String? token;
  String baseUrl = AppStorage.defaultBaseUrl;
  BootstrapData? bootstrap;
  ThemeMode themeMode = ThemeMode.system;

  bool initialized = false;
  bool online = true;
  bool busy = false;
  bool syncing = false;

  String? authError;
  String? refreshError;
  String? syncError;

  DateTime? lastSyncAt;
  DateTime? lastBootstrapAt;
  List<PendingEntry> pending = const [];

  ApiClient? _api;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  bool get isLoggedIn => token != null && token!.isNotEmpty && user != null;
  List<Survey> get surveys => bootstrap?.surveys ?? const [];
  int get pendingCount => pending.where((entry) => !entry.isFailed).length;
  int get failedCount => pending.where((entry) => entry.isFailed).length;
  int get totalPending => pending.length;

  ApiClient get _client => _api ??= ApiClient(baseUrl: baseUrl);

  Future<void> initialize() async {
    final storedBaseUrl = normalizeBaseUrl(await storage.getBaseUrl());

    baseUrl = AppStorage.resolveBaseUrl(storedBaseUrl);

    if (!AppStorage.allowCustomServer) {
      // The production app is locked to the hospital server. An address saved
      // by an older build must not survive the upgrade, and its token belongs
      // to a different host anyway, so start with a clean login.
      if (storedBaseUrl.isNotEmpty && storedBaseUrl != baseUrl) {
        await storage.clearSession();
      }
      await storage.saveBaseUrl(baseUrl);
    }

    themeMode = await storage.getThemeMode();

    try {
      token = await storage.getToken();
      user = await storage.getUser();
    } catch (_) {
      // Encrypted storage can become unreadable (for example after a device
      // lock-screen change). Recover by requiring a fresh login instead of
      // crashing on startup.
      try {
        await storage.clearSession();
      } catch (_) {
        // Ignore secondary storage failures.
      }
      token = null;
      user = null;
    }

    if (user != null) {
      final cached = await db.loadBootstrap(user!.id);
      if (cached != null) {
        try {
          bootstrap = BootstrapData.fromJson(cached);
        } catch (_) {
          bootstrap = null;
        }
      }
      lastSyncAt = await db.loadLastSync(user!.id);
      lastBootstrapAt = await db.loadBootstrapAt(user!.id);
      await _reloadPending();
    }

    online = await _checkOnline();
    _listenToConnectivity();
    WidgetsBinding.instance.addObserver(this);

    initialized = true;
    notifyListeners();

    if (isLoggedIn) {
      unawaited(_refreshAfterStart());
    }
  }

  Future<void> _refreshAfterStart() async {
    await refreshBootstrap(silent: true);
    if (isLoggedIn) {
      await syncNow(silent: true);
    }
  }

  Future<bool> login({
    required String serverUrl,
    required String username,
    required String password,
  }) async {
    busy = true;
    authError = null;
    notifyListeners();

    try {
      // Production builds ignore any address coming from the UI and always
      // sign in against the hospital server.
      final normalized = AppStorage.allowCustomServer
          ? normalizeBaseUrl(serverUrl)
          : AppStorage.productionBaseUrl;
      if (normalized.isEmpty) {
        throw const ApiException('يرجى إدخال عنوان خادم الموقع.');
      }

      final api = ApiClient(baseUrl: normalized);
      final result = await api.login(
        username: username.trim(),
        password: password,
        deviceName: 'android-app',
      );

      baseUrl = normalized;
      token = result.token;
      user = result.user;
      _api = api;

      await storage.saveBaseUrl(normalized);
      await storage.saveSession(result.token, result.user);
      await _reloadPending();
      online = true;
      await refreshBootstrap(silent: true);

      busy = false;
      notifyListeners();

      unawaited(syncNow(silent: true));
      return true;
    } on ApiException catch (error) {
      authError = error.message;
    } catch (_) {
      authError = 'تعذر الاتصال بالخادم. تحقق من العنوان ومن اتصال الإنترنت.';
    }

    busy = false;
    notifyListeners();
    return false;
  }

  /// Switches between following the phone setting and forcing light/dark.
  Future<void> setThemeMode(ThemeMode mode) async {
    if (themeMode == mode) return;

    themeMode = mode;
    notifyListeners();
    await storage.saveThemeMode(mode);
  }

  /// Verifies the server address before the collector commits to a login.
  /// Uses a short timeout so a wrong address fails in seconds, not 25s.
  Future<ServerCheck> checkServer(String serverUrl) async {
    final normalized = AppStorage.allowCustomServer
        ? normalizeBaseUrl(serverUrl)
        : AppStorage.productionBaseUrl;
    if (normalized.isEmpty) {
      return const ServerCheck(
        ok: false,
        message: 'يرجى إدخال عنوان خادم الموقع.',
      );
    }

    final started = DateTime.now();

    try {
      final api = ApiClient(baseUrl: normalized);
      final data = await api.health();
      final status = (data['status'] ?? '').toString();

      if (status != 'ok') {
        return ServerCheck(
          ok: false,
          message: 'وصل الطلب إلى الخادم لكن الاستجابة غير متوقعة.',
          url: normalized,
        );
      }

      final elapsed = DateTime.now().difference(started).inMilliseconds;
      return ServerCheck(
        ok: true,
        message: 'تم الاتصال بالخادم بنجاح ($elapsed مللي ثانية).',
        url: normalized,
      );
    } on ApiException catch (error) {
      return ServerCheck(ok: false, message: error.message, url: normalized);
    } catch (_) {
      return ServerCheck(
        ok: false,
        message: 'تعذر الاتصال بالخادم. تحقق من العنوان ومن الشبكة.',
        url: normalized,
      );
    }
  }

  Future<void> refreshBootstrap({bool silent = false}) async {
    if (!isLoggedIn) return;

    if (!online) {
      if (!silent) {
        refreshError = null;
        notifyListeners();
      }
      return;
    }

    if (!silent) {
      busy = true;
      refreshError = null;
      notifyListeners();
    }

    try {
      final raw = await _client.fetchBootstrap(token!);
      final data = BootstrapData.fromJson(raw);

      bootstrap = data;
      if (data.user.id.isNotEmpty) {
        user = data.user;
        await storage.saveUser(data.user);
      }
      await db.saveBootstrap(user!.id, raw);
      lastBootstrapAt = DateTime.now();
      await db.saveBootstrapAt(user!.id, lastBootstrapAt!);
      refreshError = null;
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _expireSession();
        return;
      }
      refreshError = error.message;
    } catch (_) {
      refreshError =
          'تعذر تحديث بيانات الاستبيانات. سيتم استخدام النسخة المحفوظة.';
    } finally {
      if (!silent) {
        busy = false;
      }
      notifyListeners();
    }
  }

  /// How old the cached survey definitions may get before the home tab
  /// refreshes them in the background.
  static const staleAfter = Duration(seconds: 30);

  /// Refreshes the survey definitions only when the cached copy is older than
  /// [maxAge]. The home tab calls this when it gains focus, so edits made on
  /// the website (required name/phone, question text, answer type) reach the
  /// app without the collector pulling to refresh.
  Future<void> refreshSurveysIfStale({Duration maxAge = staleAfter}) async {
    if (!isLoggedIn || busy || !online) return;

    final last = lastBootstrapAt;
    if (last != null && DateTime.now().difference(last) < maxAge) return;

    await refreshBootstrap(silent: true);
  }

  /// Fetches the latest definition of [survey] straight from the server and
  /// replaces it in the cached list, so an opened form never shows a stale
  /// question text or answer type. Falls back to the cached copy when the
  /// device is offline or the server cannot be reached.
  Future<Survey> freshSurvey(Survey survey) async {
    final currentToken = token;
    if (!isLoggedIn || !online || currentToken == null) return survey;

    try {
      final raw = await _client.fetchSurvey(currentToken, survey.id);
      final fresh = Survey.fromJson(asMap(raw['survey']));
      if (fresh.id.isEmpty) return survey;

      _replaceSurvey(fresh);
      return fresh;
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _expireSession();
        return survey;
      }

      // Older deployments may not expose `GET /surveys/{id}` yet. The
      // bootstrap endpoint carries the same definitions, so fall back to it
      // instead of silently opening the cached (possibly stale) survey.
      await refreshBootstrap(silent: true);
      return _surveyById(survey.id) ?? survey;
    } catch (_) {
      // The cached copy is still usable offline.
      return survey;
    }
  }

  Survey? _surveyById(String id) {
    for (final item in surveys) {
      if (item.id == id) return item;
    }
    return null;
  }

  void _replaceSurvey(Survey fresh) {
    final current = bootstrap;
    if (current == null) return;

    bootstrap = current.copyWith(
      surveys: current.surveys
          .map((item) => item.id == fresh.id ? fresh : item)
          .toList(growable: false),
    );
    notifyListeners();
  }

  Future<SyncResult> syncNow({bool silent = false}) async {
    if (syncing) {
      return const SyncResult(ok: true, message: 'المزامنة قيد التنفيذ.');
    }
    final currentToken = token;
    final currentUser = user;
    if (currentToken == null || currentToken.isEmpty || currentUser == null) {
      return const SyncResult(ok: false, message: 'يرجى تسجيل الدخول أولًا.');
    }

    syncing = true;
    syncError = null;
    notifyListeners();

    try {
      if (!online) {
        syncError =
            'لا يوجد اتصال بالإنترنت. سيتم رفع الاستبيانات تلقائيًا عند عودة الاتصال.';
        return SyncResult(ok: false, message: syncError);
      }

      final result = await SyncService.sync(
        api: _client,
        db: db,
        token: currentToken,
        user: currentUser,
      );

      if (result.unauthorized) {
        await _expireSession();
        return result;
      }

      if (result.message != null && result.message!.isNotEmpty) {
        syncError = result.message;
      }

      // The collector may have logged out (or switched accounts) while the
      // upload was running. Never write metadata for the wrong account.
      if (user?.id == currentUser.id && (result.ok || result.uploaded > 0)) {
        lastSyncAt = DateTime.now();
        await db.saveLastSync(currentUser.id, lastSyncAt!);
      }

      await _reloadPending();
      return result;
    } catch (_) {
      syncError = 'تعذرت المزامنة. سيتم إعادة المحاولة تلقائيًا.';
      return SyncResult(ok: false, message: syncError);
    } finally {
      syncing = false;
      notifyListeners();
    }
  }

  Future<SubmissionResult> submitResponse(
    Map<String, dynamic> payload,
    Survey survey,
  ) async {
    if (user == null) {
      return const SubmissionResult(
        clientResponseId: '',
        uploaded: false,
        error: 'يرجى تسجيل الدخول أولًا.',
      );
    }

    final clientResponseId = const Uuid().v4();
    final now = DateTime.now();

    final entry = PendingEntry(
      clientResponseId: clientResponseId,
      collectorId: user!.id,
      surveyId: survey.id,
      surveyTitle: survey.title,
      payload: {...payload, 'clientResponseId': clientResponseId},
      status: 'pending',
      attempts: 0,
      createdAt: now,
      updatedAt: now,
    );

    await db.enqueue(entry);
    await _reloadPending();
    notifyListeners();

    if (online && token != null) {
      await syncNow(silent: true);
    }

    final row = await db.find(clientResponseId);

    if (row == null) {
      return SubmissionResult(
        clientResponseId: clientResponseId,
        uploaded: true,
      );
    }

    if (row.isFailed) {
      // An interactive submission that the server rejected (validation or
      // conflict) is removed so the collector can correct it and resubmit.
      await db.delete(clientResponseId);
      await _reloadPending();
      notifyListeners();

      return SubmissionResult(
        clientResponseId: clientResponseId,
        uploaded: false,
        error: row.lastError ?? 'رفض الخادم الاستجابة. يرجى مراجعة الإجابات.',
      );
    }

    return SubmissionResult(
      clientResponseId: clientResponseId,
      uploaded: false,
      queued: true,
    );
  }

  Future<void> retryOne(String clientResponseId) async {
    await db.resetToPending(clientResponseId);
    await _reloadPending();
    notifyListeners();
    await syncNow();
  }

  Future<void> retryAllFailed() async {
    if (user == null) return;
    await db.resetAllFailed(user!.id);
    await _reloadPending();
    notifyListeners();
    await syncNow();
  }

  Future<void> deletePending(String clientResponseId) async {
    await db.delete(clientResponseId);
    await _reloadPending();
    notifyListeners();
  }

  Future<void> reloadPending() async {
    await _reloadPending();
    notifyListeners();
  }

  Future<void> logout() async {
    final currentToken = token;

    if (currentToken != null && online) {
      try {
        await _client.logout(currentToken);
      } catch (_) {
        // Logging out locally must always work, even when offline.
      }
    }

    await storage.clearSession();

    token = null;
    user = null;
    bootstrap = null;
    pending = const [];
    lastSyncAt = null;
    lastBootstrapAt = null;
    authError = null;
    refreshError = null;
    syncError = null;
    busy = false;
    syncing = false;

    notifyListeners();
  }

  Future<void> _reloadPending() async {
    if (user == null) {
      pending = const [];
      return;
    }
    pending = await db.entriesFor(user!.id);
  }

  Future<void> _expireSession() async {
    await storage.clearSession();
    token = null;
    user = null;
    bootstrap = null;
    pending = const [];
    lastSyncAt = null;
    lastBootstrapAt = null;
    authError = 'انتهت جلسة الدخول. يرجى تسجيل الدخول مرة أخرى.';
    refreshError = null;
    syncError = null;
    notifyListeners();
  }

  Future<bool> _checkOnline() async {
    try {
      final results = await Connectivity().checkConnectivity();
      return results.any((result) => result != ConnectivityResult.none);
    } catch (_) {
      return true;
    }
  }

  void _listenToConnectivity() {
    _connectivitySubscription ??= Connectivity().onConnectivityChanged.listen((
      results,
    ) {
      final nextOnline = results.any(
        (result) => result != ConnectivityResult.none,
      );

      if (nextOnline == online) return;

      online = nextOnline;
      notifyListeners();

      if (nextOnline && isLoggedIn) {
        unawaited(syncNow(silent: true));
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && isLoggedIn) {
      unawaited(refreshBootstrap(silent: true));
      unawaited(syncNow(silent: true));
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _connectivitySubscription?.cancel();
    super.dispose();
  }
}
