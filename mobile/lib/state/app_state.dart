import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart' show ThemeMode;
import 'package:flutter/widgets.dart';
import 'package:uuid/uuid.dart';

import '../core/api_client.dart';
import '../core/app_storage.dart';
import '../core/diagnostics.dart';
import '../core/screen_security.dart';
import '../core/session_expiry.dart';
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
  AppState({
    required this.storage,
    required this.db,
    this.startupTimeout = const Duration(seconds: 5),
  });

  final AppStorage storage;
  final LocalDb db;

  /// Upper bound for a single startup step. A plugin that never answers must
  /// not keep the app on the splash screen, so the step is abandoned instead.
  final Duration startupTimeout;

  AppUser? user;
  String? token;
  String baseUrl = AppStorage.defaultBaseUrl;
  BootstrapData? bootstrap;
  ThemeMode themeMode = ThemeMode.system;

  /// Last hospital name and logo seen from the server, cached in preferences so
  /// the header still identifies the hospital when the local database or the
  /// network is unavailable.
  String cachedHospitalName = '';
  String cachedHospitalLogo = '';

  /// Whether the collector has acknowledged the current privacy notice. Kept in
  /// memory so the root widget can gate the survey shell without a disk read.
  bool privacyAccepted = false;

  bool initialized = false;

  /// Set when a startup step failed or timed out, so the splash screen can show
  /// what went wrong instead of spinning forever.
  String? startupError;

  /// Guards [startupError] from being cleared by a late yet successful step.
  bool _startupRefreshStarted = false;
  bool online = true;
  bool busy = false;
  bool syncing = false;

  String? authError;
  String? refreshError;
  String? syncError;

  DateTime? lastSyncAt;
  DateTime? lastBootstrapAt;

  /// When the server will stop accepting [token]. Administered by the server
  /// at login and renewed automatically before it lapses.
  DateTime? sessionExpiresAt;

  /// Whether Android blocks screenshots and the task-switcher preview. On by
  /// default; the account tab can turn it off for the facility.
  bool blockScreenshots = true;

  List<PendingEntry> pending = const [];

  ApiClient? _api;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  bool _renewing = false;

  bool get isLoggedIn => token != null && token!.isNotEmpty && user != null;
  List<Survey> get surveys => bootstrap?.surveys ?? const [];
  int get pendingCount => pending.where((entry) => !entry.isFailed).length;
  int get failedCount => pending.where((entry) => entry.isFailed).length;
  int get totalPending => pending.length;

  /// Days left before the server token expires, or null when unknown.
  int? get sessionDaysLeft => SessionExpiry.daysLeft(sessionExpiresAt);

  /// True during the last [SessionExpiry.warningWindow] of the session, when
  /// the account tab and the home banner warn the collector.
  bool get sessionExpiringSoon =>
      SessionExpiry.isExpiringSoon(sessionExpiresAt);

  /// Hospital name for the header. Falls back to the last name seen from the
  /// server, so an unavailable database or network never leaves the bar blank.
  String get hospitalName {
    final live = (bootstrap?.hospitalName ?? '').trim();
    return live.isNotEmpty ? live : cachedHospitalName;
  }

  /// Hospital logo path for the header, with the same cached fallback.
  String get hospitalLogo {
    final live = (bootstrap?.hospitalLogo ?? '').trim();
    return live.isNotEmpty ? live : cachedHospitalLogo;
  }

  ApiClient get _client => _api ??= ApiClient(baseUrl: baseUrl);

  /// Prepares the app. The root widget is already on screen when this runs,
  /// and every step is time-boxed, so a slow or broken platform channel
  /// (secure storage, preferences, the database) cannot leave the user on a
  /// blank launch screen with no way to reach the login form.
  Future<void> initialize() async {
    try {
      await _loadState().timeout(startupTimeout * 6);
    } on TimeoutException {
      _recordStartupError('تأخر تجهيز التطبيق');
      debugPrint('Startup timed out');
    } catch (error) {
      _recordStartupError('تعذّر تجهيز التطبيق');
      debugPrint('Startup failed: $error');
    } finally {
      _markInitialized();
    }
  }

  Future<void> _loadState() async {
    // Opening SQLite is fast on a healthy device, but a stuck platform channel
    // would otherwise block every later step. Cold starts on low-end tablets
    // get twice the budget because the first database open also creates it.
    await _attempt<void>(
      'قاعدة البيانات المحلية',
      () => db.database,
      timeout: startupTimeout * 2,
    );

    final stored = await _attempt<String?>('الإعدادات', storage.getBaseUrl);
    final storedBaseUrl = normalizeBaseUrl(stored ?? '');

    baseUrl = AppStorage.resolveBaseUrl(storedBaseUrl);

    if (!AppStorage.allowCustomServer) {
      // The production app is locked to the hospital server. An address saved
      // by an older build must not survive the upgrade, and its token belongs
      // to a different host anyway, so start with a clean login.
      if (storedBaseUrl.isNotEmpty && storedBaseUrl != baseUrl) {
        await _attempt<void>('الجلسة', storage.clearSession);
      }
      await _attempt<void>('الإعدادات', () => storage.saveBaseUrl(baseUrl));
    }

    themeMode =
        await _attempt<ThemeMode>('المظهر', storage.getThemeMode) ??
        ThemeMode.system;
    privacyAccepted =
        await _attempt<bool>('الخصوصية', storage.privacyAccepted) ?? false;

    blockScreenshots =
        await _attempt<bool>('حماية الشاشة', storage.blockScreenshots) ?? true;
    unawaited(ScreenSecurity.apply(blockScreenshots));

    // Read before the database so the header can name the hospital even when
    // the local copy of the surveys cannot be loaded.
    cachedHospitalName =
        await _attempt<String?>('اسم المستشفى', storage.getBrandName) ?? '';
    cachedHospitalLogo =
        await _attempt<String?>('شعار المستشفى', storage.getBrandLogo) ?? '';

    try {
      token = await storage.getToken().timeout(startupTimeout);
      user = await storage.getUser().timeout(startupTimeout);
    } catch (_) {
      // Encrypted storage can become unreadable (for example after a device
      // lock-screen change). Recover by requiring a fresh login instead of
      // crashing on startup.
      try {
        await storage.clearSession().timeout(startupTimeout);
      } catch (_) {
        // Ignore secondary storage failures.
      }
      token = null;
      user = null;
    }

    sessionExpiresAt = await _attempt<DateTime?>(
      'انتهاء الجلسة',
      storage.getSessionExpiry,
    );

    if (user != null) {
      final cached = await _attempt<Map<String, dynamic>?>(
        'البيانات المحفوظة',
        () => db.loadBootstrap(user!.id),
      );
      if (cached != null) {
        try {
          bootstrap = BootstrapData.fromJson(cached);
        } catch (_) {
          bootstrap = null;
        }
      }
      if (bootstrap != null) {
        await _attempt<void>('اسم المستشفى', () => _rememberBrand(bootstrap!));
      }
      lastSyncAt = await _attempt<DateTime?>(
        'البيانات المحفوظة',
        () => db.loadLastSync(user!.id),
      );
      lastBootstrapAt = await _attempt<DateTime?>(
        'البيانات المحفوظة',
        () => db.loadBootstrapAt(user!.id),
      );
      await _attempt<void>('قائمة الانتظار', _reloadPending);
    }

    online = await _checkOnline();
    _listenToConnectivity();
    WidgetsBinding.instance.addObserver(this);
  }

  /// Runs one startup step, catching both failures and hangs. A broken plugin
  /// must never keep the app on the splash screen, so the step is abandoned
  /// after [startupTimeout] and the rest of the startup continues.
  Future<T?> _attempt<T>(
    String label,
    Future<T> Function() step, {
    Duration? timeout,
  }) async {
    try {
      return await step().timeout(timeout ?? startupTimeout);
    } on TimeoutException {
      _recordStartupError('تأخر تحميل $label');
      debugPrint('Startup step "$label" timed out');
    } catch (error) {
      _recordStartupError('تعذّر تحميل $label', error);
      debugPrint('Startup step "$label" failed: $error');
    }
    return null;
  }

  void _recordStartupError(String reason, [Object? error]) {
    if (error != null) {
      Diagnostics.instance.record(
        error,
        stack: StackTrace.current,
        context: reason,
      );
    }
    // The reason is shown in Arabic, with the technical detail in parentheses
    // so a persisting failure can be reported back without a log viewer.
    final detail = error == null ? '' : ' (${_shortError(error)})';
    startupError ??=
        '$reason$detail. يمكنك تسجيل الدخول الآن، وإن تكرر الخطأ أعد فتح التطبيق.';
  }

  /// Trims a platform error down to something that fits in a banner.
  String _shortError(Object error) {
    final text = error.toString().replaceAll(RegExp(r'\s+'), ' ').trim();
    if (text.isEmpty) return 'خطأ غير معروف';
    return text.length <= 120 ? text : '${text.substring(0, 120)}…';
  }

  /// Caches the hospital identity in preferences. It is hospital-level data,
  /// not account data, so it survives a database reset and a failed sync and
  /// the header never loses the hospital name.
  Future<void> _rememberBrand(BootstrapData data) async {
    final name = data.hospitalName.trim();
    final logo = data.hospitalLogo.trim();
    if (name.isEmpty && logo.isEmpty) return;

    final nextName = name.isEmpty ? cachedHospitalName : name;
    final nextLogo = logo.isEmpty ? cachedHospitalLogo : logo;
    if (nextName == cachedHospitalName && nextLogo == cachedHospitalLogo) {
      return;
    }

    cachedHospitalName = nextName;
    cachedHospitalLogo = nextLogo;
    notifyListeners();

    try {
      await storage.saveBrand(
        name: cachedHospitalName,
        logo: cachedHospitalLogo,
      );
    } catch (error) {
      debugPrint('Could not cache hospital identity: $error');
    }
  }

  void _markInitialized() {
    if (initialized) return;

    initialized = true;
    notifyListeners();

    if (isLoggedIn && !_startupRefreshStarted) {
      _startupRefreshStarted = true;
      unawaited(_refreshAfterStart());
    }
  }

  Future<void> _refreshAfterStart() async {
    await _syncSession();
    await refreshBootstrap(silent: true);
    if (isLoggedIn) {
      await syncNow(silent: true);
    }
  }

  /// Keeps the server session usable without asking the collector to log in
  /// again: adopts the expiry reported by the server for installs that predate
  /// it, then renews the token before it lapses.
  Future<void> _syncSession() async {
    if (!isLoggedIn || !online || _renewing) return;

    if (sessionExpiresAt == null) {
      await _adoptServerExpiry();
      if (!isLoggedIn) return;
    }

    if (sessionExpiringSoon) {
      await renewSession(silent: true);
    }
  }

  /// Asks the server for the token's expiry. Only needed once per install.
  Future<void> _adoptServerExpiry() async {
    final currentToken = token;
    if (currentToken == null) return;

    try {
      final data = await _client.me(currentToken);
      final parsed = DateTime.tryParse('${data['expiresAt'] ?? ''}');
      if (parsed == null) return;

      sessionExpiresAt = parsed;
      try {
        await storage.saveSessionExpiry(parsed);
      } catch (error) {
        debugPrint('Could not store the session expiry: $error');
      }
      notifyListeners();
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _expireSession();
      }
    } catch (error) {
      debugPrint('Could not read the session expiry: $error');
    }
  }

  /// Replaces the server token with a fresh 30-day one. Returns false when the
  /// device is offline or the server refused; [silent] keeps the automatic
  /// attempt out of the UI.
  Future<bool> renewSession({bool silent = false}) async {
    final currentToken = token;
    if (!isLoggedIn || currentToken == null || _renewing) return false;

    if (!online) {
      if (!silent) {
        refreshError =
            'أنت غير متصل بالإنترنت. سيتم تجديد الجلسة تلقائيًا عند الاتصال.';
        notifyListeners();
      }
      return false;
    }

    _renewing = true;
    try {
      final result = await _client.refresh(currentToken);
      token = result.token;
      user = result.user;
      sessionExpiresAt = result.expiresAt;

      await storage.saveSession(
        result.token,
        result.user,
        expiresAt: result.expiresAt,
      );
      notifyListeners();
      return true;
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _expireSession();
      } else {
        Diagnostics.instance.record(
          error,
          stack: StackTrace.current,
          context: 'تجديد الجلسة',
        );
        if (!silent) {
          refreshError = error.message;
          notifyListeners();
        }
      }
      return false;
    } catch (error) {
      Diagnostics.instance.record(
        error,
        stack: StackTrace.current,
        context: 'تجديد الجلسة',
      );
      return false;
    } finally {
      _renewing = false;
    }
  }

  /// Blocks screenshots and the recent-apps preview. The setting is stored
  /// immediately so it survives a restart, and applied natively at once.
  Future<void> setBlockScreenshots(bool value) async {
    if (blockScreenshots == value) return;

    blockScreenshots = value;
    notifyListeners();

    await ScreenSecurity.apply(value);
    try {
      await storage.saveBlockScreenshots(value);
    } catch (error) {
      debugPrint('Could not store the screen-protection preference: $error');
    }
  }

  Future<bool> login({
    required String serverUrl,
    required String username,
    required String password,
  }) async {
    busy = true;
    authError = null;
    startupError = null;
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
      sessionExpiresAt = result.expiresAt;
      _api = api;

      await storage.saveBaseUrl(normalized);
      await storage.saveSession(
        result.token,
        result.user,
        expiresAt: result.expiresAt,
      );
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

  /// Records that the collector read and accepted the privacy notice. The app
  /// lets them into the surveys only after this call.
  Future<void> acceptPrivacy() async {
    if (privacyAccepted) return;

    privacyAccepted = true;
    notifyListeners();

    try {
      await storage.acceptPrivacy();
    } catch (_) {
      // If the preference cannot be written the collector simply sees the
      // notice again next launch; never block work on a storage hiccup.
    }
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

    final requestToken = token;
    if (requestToken == null || requestToken.isEmpty) return;

    try {
      final raw = await _client.fetchBootstrap(requestToken);
      final data = BootstrapData.fromJson(raw);

      bootstrap = data;
      await _rememberBrand(data);
      if (data.user.id.isNotEmpty) {
        user = data.user;
        await storage.saveUser(data.user);
      }
      try {
        await db.saveBootstrap(user!.id, raw);
        lastBootstrapAt = DateTime.now();
        await db.saveBootstrapAt(user!.id, lastBootstrapAt!);
      } catch (error) {
        // The fresh data is already in memory; a broken local database only
        // means it will not survive a restart.
        debugPrint('Could not cache bootstrap: $error');
      }
      refreshError = null;
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        // A renewal that finished while this request was in flight revoked the
        // token that was sent; that is not a reason to sign the collector out.
        if (token != requestToken) return;
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
        if (token != currentToken) return survey;
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
        // A token renewal that completed while this upload was in flight
        // invalidated the old token; that 401 must not sign the collector out.
        if (token != currentToken) {
          return result;
        }
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
        try {
          await db.saveLastSync(currentUser.id, lastSyncAt!);
        } catch (error) {
          // A failed metadata write must not turn a successful upload into
          // "sync failed".
          debugPrint('Could not record the sync time: $error');
        }
      }

      await _reloadPending();
      return result;
    } catch (error) {
      debugPrint('Sync failed: $error');
      syncError =
          'تعذرت المزامنة (${_shortError(error)}). سيتم إعادة المحاولة تلقائيًا.';
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

    try {
      await db.enqueue(entry);
      await _reloadPending();
      notifyListeners();
    } catch (error) {
      // Saving is the one step that must never leave the form spinning: if the
      // local queue cannot be written there is nothing to sync later.
      debugPrint('Could not queue the response: $error');
      return SubmissionResult(
        clientResponseId: clientResponseId,
        uploaded: false,
        error:
            'تعذّر حفظ الاستبيان على الجهاز (${_shortError(error)}). '
            'يرجى المحاولة مرة أخرى.',
      );
    }

    if (online && token != null) {
      // The entry is already safe on the device, so the upload gets a short
      // head start only. Waiting for the full HTTP timeout (25s) kept the form
      // on "جارٍ الإرسال" on a half-connected network and blocked the next
      // survey; the sync continues in the background after this returns.
      final sync = syncNow(silent: true);
      unawaited(sync);
      await sync.timeout(
        const Duration(seconds: 8),
        onTimeout: () => const SyncResult(ok: false),
      );
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
    sessionExpiresAt = null;
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
    try {
      pending = await db.entriesFor(user!.id);
    } catch (error) {
      // An unreadable local queue must not block login or syncing; the online
      // path still submits.
      debugPrint('Could not read the pending queue: $error');
      pending = const [];
    }
  }

  Future<void> _expireSession() async {
    await storage.clearSession();
    token = null;
    user = null;
    bootstrap = null;
    sessionExpiresAt = null;
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
    try {
      _connectivitySubscription ??= Connectivity().onConnectivityChanged.listen(
        (results) {
          final nextOnline = results.any(
            (result) => result != ConnectivityResult.none,
          );

          if (nextOnline == online) return;

          online = nextOnline;
          notifyListeners();

          if (nextOnline && isLoggedIn) {
            unawaited(_syncSession());
            unawaited(syncNow(silent: true));
          }
        },
        // A connectivity plugin that cannot deliver events (unsupported
        // device, revoked permission) must not take the app down.
        onError: (Object error) {
          debugPrint('Connectivity stream failed: $error');
        },
      );
    } catch (error) {
      debugPrint('Connectivity listen failed: $error');
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && isLoggedIn) {
      unawaited(_syncSession());
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
