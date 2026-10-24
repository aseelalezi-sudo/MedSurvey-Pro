import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/app_storage.dart';
import '../core/theme.dart';
import '../core/utils.dart';
import '../state/app_state.dart';
import 'widgets/common.dart';
import 'widgets/hospital_logo.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _serverController;
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  bool _obscurePassword = true;
  bool _showServerSettings = false;

  @override
  void initState() {
    super.initState();
    final state = context.read<AppState>();
    _serverController = TextEditingController(text: state.baseUrl);
    // The production build is locked to the hospital server. Only debug and
    // profile builds expose the address field for local testing.
    _showServerSettings =
        AppStorage.allowCustomServer &&
        state.baseUrl == AppStorage.defaultBaseUrl;
  }

  @override
  void dispose() {
    _serverController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final state = context.read<AppState>();
    if (state.busy) return;
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();

    await state.login(
      serverUrl: _serverController.text,
      username: _usernameController.text,
      password: _passwordController.text,
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final data = state.bootstrap;
    final palette = context.palette;
    final bottomInset = MediaQuery.viewPaddingOf(context).bottom;
    final fieldStyle = TextStyle(
      fontFamily: 'Tajawal',
      color: palette.ink,
      fontSize: 14,
      fontWeight: FontWeight.w600,
    );

    return Scaffold(
      backgroundColor: palette.background,
      body: Container(
        decoration: BoxDecoration(gradient: palette.heroGradient),
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              _BrandHeader(
                hospitalName: data?.hospitalName ?? '',
                hospitalLogo: data?.hospitalLogo ?? '',
                baseUrl: state.baseUrl,
              ),
              Expanded(
                child: Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: palette.background,
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(30),
                      topRight: Radius.circular(30),
                    ),
                  ),
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    keyboardDismissBehavior:
                        ScrollViewKeyboardDismissBehavior.onDrag,
                    padding: EdgeInsets.fromLTRB(18, 24, 18, 26 + bottomInset),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            'تسجيل الدخول',
                            style: TextStyle(
                              color: palette.ink,
                              fontSize: 21,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            'سجّل الدخول مرة واحدة، ثم يمكنك تعبئة الاستبيانات حتى بدون إنترنت.',
                            style: TextStyle(
                              color: palette.muted,
                              fontSize: 12.5,
                              height: 1.6,
                            ),
                          ),
                          const SizedBox(height: 16),
                          if (state.authError != null)
                            MessageBanner(
                              message: state.authError!,
                              isError: true,
                            ),
                          SoftCard(
                            padding: const EdgeInsets.all(16),
                            radius: 24,
                            child: Column(
                              children: [
                                TextFormField(
                                  controller: _usernameController,
                                  textInputAction: TextInputAction.next,
                                  autofillHints: const [AutofillHints.username],
                                  textDirection: TextDirection.rtl,
                                  cursorColor: palette.accent,
                                  style: fieldStyle,
                                  onTapOutside: (_) =>
                                      FocusScope.of(context).unfocus(),
                                  decoration: const InputDecoration(
                                    labelText: 'اسم المستخدم',
                                    hintText: 'مثال: staff',
                                    prefixIcon: Icon(
                                      Icons.person_outline_rounded,
                                      size: 20,
                                    ),
                                  ),
                                  validator: (value) =>
                                      (value == null || value.trim().isEmpty)
                                      ? 'يرجى إدخال اسم المستخدم'
                                      : null,
                                ),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  textInputAction: TextInputAction.done,
                                  autofillHints: const [AutofillHints.password],
                                  cursorColor: palette.accent,
                                  style: fieldStyle,
                                  onTapOutside: (_) =>
                                      FocusScope.of(context).unfocus(),
                                  onFieldSubmitted: (_) => _submit(),
                                  decoration: InputDecoration(
                                    labelText: 'كلمة المرور',
                                    prefixIcon: const Icon(
                                      Icons.lock_outline_rounded,
                                      size: 20,
                                    ),
                                    suffixIcon: IconButton(
                                      tooltip: _obscurePassword
                                          ? 'إظهار كلمة المرور'
                                          : 'إخفاء كلمة المرور',
                                      onPressed: () => setState(
                                        () => _obscurePassword =
                                            !_obscurePassword,
                                      ),
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                        size: 20,
                                      ),
                                    ),
                                  ),
                                  validator: (value) =>
                                      (value == null || value.isEmpty)
                                      ? 'يرجى إدخال كلمة المرور'
                                      : null,
                                ),
                                const SizedBox(height: 12),
                                if (AppStorage.allowCustomServer)
                                  _ServerSettings(
                                    expanded: _showServerSettings,
                                    controller: _serverController,
                                    onToggle: () => setState(
                                      () => _showServerSettings =
                                          !_showServerSettings,
                                    ),
                                  )
                                else
                                  const _LockedServerNote(),
                                const SizedBox(height: 16),
                                GradientButton(
                                  label: state.busy
                                      ? 'جارٍ تسجيل الدخول...'
                                      : 'تسجيل الدخول',
                                  icon: Icons.login_rounded,
                                  loading: state.busy,
                                  onPressed: state.busy ? null : _submit,
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                          const _LoginNote(),
                          const SizedBox(height: 18),
                          Text(
                            'MedSurvey Pro · الإصدار 1.1.8',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: palette.faint,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader({
    required this.hospitalName,
    required this.hospitalLogo,
    required this.baseUrl,
  });

  final String hospitalName;
  final String hospitalLogo;
  final String baseUrl;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(
          top: -40,
          left: -30,
          child: Container(
            width: 150,
            height: 150,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.06),
              shape: BoxShape.circle,
            ),
          ),
        ),
        Positioned(
          bottom: 0,
          right: -40,
          child: Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.05),
              shape: BoxShape.circle,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 30),
          child: Column(
            children: [
              HospitalLogo(
                baseUrl: baseUrl,
                path: hospitalLogo,
                size: 88,
                radius: 26,
                padding: 12,
                fallbackText: hospitalName,
                showShadow: true,
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.6),
                  width: 3,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'MedSurvey Pro',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 25,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.2,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                hospitalName.isEmpty
                    ? 'نظام استبيانات رضا المرضى'
                    : hospitalName,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.92),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  height: 1.5,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LockedServerNote extends StatelessWidget {
  const _LockedServerNote();

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: palette.surfaceAlt,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: palette.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.verified_user_outlined, size: 17, color: palette.accent),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'خادم المستشفى مُعدّ مسبقًا في التطبيق — لا حاجة لإدخال أي '
              'تفاصيل اتصال.',
              style: TextStyle(
                color: palette.muted,
                fontSize: 11.5,
                height: 1.55,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ServerSettings extends StatefulWidget {
  const _ServerSettings({
    required this.expanded,
    required this.controller,
    required this.onToggle,
  });

  final bool expanded;
  final TextEditingController controller;
  final VoidCallback onToggle;

  @override
  State<_ServerSettings> createState() => _ServerSettingsState();
}

class _ServerSettingsState extends State<_ServerSettings> {
  bool _testing = false;
  ServerCheck? _result;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_clearResult);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_clearResult);
    super.dispose();
  }

  /// The previous test result no longer applies once the address changes.
  void _clearResult() {
    if (_result != null && mounted) setState(() => _result = null);
  }

  Future<void> _test() async {
    if (_testing) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _testing = true;
      _result = null;
    });

    final result = await context.read<AppState>().checkServer(
      widget.controller.text,
    );

    if (!mounted) return;
    setState(() {
      _testing = false;
      _result = result;
    });
  }

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    final result = _result;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        color: palette.surfaceAlt,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.line),
      ),
      child: Column(
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: widget.onToggle,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: palette.mint,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Icon(
                      Icons.dns_outlined,
                      size: 16,
                      color: palette.accent,
                    ),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      'إعدادات خادم الموقع',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: palette.ink,
                      ),
                    ),
                  ),
                  AnimatedRotation(
                    turns: widget.expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 200),
                    child: Icon(
                      Icons.expand_more_rounded,
                      color: palette.muted,
                      size: 20,
                    ),
                  ),
                ],
              ),
            ),
          ),
          AnimatedSize(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            alignment: Alignment.topCenter,
            child: widget.expanded
                ? Padding(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ValueListenableBuilder<TextEditingValue>(
                          valueListenable: widget.controller,
                          builder: (context, value, _) {
                            final preview = normalizeBaseUrl(value.text);
                            final isEmulatorDefault =
                                preview == AppStorage.defaultBaseUrl;
                            return Directionality(
                              // Keep URL text left-to-right while the label,
                              // hint and helper follow the Arabic layout.
                              textDirection: TextDirection.rtl,
                              child: TextFormField(
                                controller: widget.controller,
                                keyboardType: TextInputType.url,
                                textDirection: TextDirection.ltr,
                                textAlign: TextAlign.left,
                                cursorColor: palette.accent,
                                onTapOutside: (_) =>
                                    FocusScope.of(context).unfocus(),
                                style: TextStyle(
                                  fontFamily: 'Tajawal',
                                  color: palette.ink,
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w600,
                                ),
                                decoration: InputDecoration(
                                  labelText: 'عنوان الموقع',
                                  hintText: 'http://192.168.1.100:8080',
                                  prefixIcon: const Icon(
                                    Icons.link_rounded,
                                    size: 20,
                                  ),
                                  helperText: preview.isEmpty
                                      ? 'أدخل عنوان الخادم الأساسي فقط دون /api'
                                      : isEmulatorDefault
                                      ? 'هذا العنوان يعمل على المحاكي فقط. على الهاتف أدخل عنوان الكمبيوتر مثل http://192.168.1.100:8080'
                                      : 'سيُستخدم: $preview',
                                  helperMaxLines: 3,
                                  fillColor: palette.inputFill,
                                ),
                                validator: (value) =>
                                    (value == null || value.trim().isEmpty)
                                    ? 'يرجى إدخال عنوان الخادم'
                                    : null,
                              ),
                            );
                          },
                        ),
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          onPressed: _testing ? null : _test,
                          icon: _testing
                              ? const SizedBox(
                                  width: 14,
                                  height: 14,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(
                                  Icons.wifi_tethering_rounded,
                                  size: 18,
                                ),
                          label: Text(
                            _testing
                                ? 'جارٍ اختبار الاتصال...'
                                : 'اختبار الاتصال',
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: palette.accent,
                            side: BorderSide(color: palette.mintBorder),
                            padding: const EdgeInsets.symmetric(vertical: 11),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            textStyle: const TextStyle(
                              fontFamily: 'Tajawal',
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        if (result != null) ...[
                          const SizedBox(height: 9),
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: result.ok
                                  ? palette.mint
                                  : palette.dangerSurface,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: result.ok
                                    ? palette.mintBorder
                                    : palette.dangerBorder,
                              ),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Icon(
                                  result.ok
                                      ? Icons.check_circle_outline_rounded
                                      : Icons.error_outline_rounded,
                                  size: 17,
                                  color: result.ok
                                      ? palette.accent
                                      : palette.dangerFg,
                                ),
                                const SizedBox(width: 7),
                                Expanded(
                                  child: Text(
                                    result.message,
                                    style: TextStyle(
                                      color: result.ok
                                          ? palette.accent
                                          : palette.dangerFg,
                                      fontSize: 11.5,
                                      height: 1.55,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  )
                : const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }
}

class _LoginNote extends StatelessWidget {
  const _LoginNote();

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: palette.mint,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.mintBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.cloud_sync_outlined, color: palette.accent, size: 19),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              'بعد أول تسجيل دخول، تبقى بيانات الاستبيانات محفوظة على الجهاز. يمكنك إدخال الاستبيانات دون اتصال وستُرفع تلقائيًا عند عودة الإنترنت.',
              style: TextStyle(
                color: palette.accent,
                fontSize: 11.5,
                height: 1.6,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
