import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../core/theme.dart';
import '../core/utils.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import 'widgets/app_bar_background.dart';
import 'widgets/common.dart';
import 'widgets/hospital_logo.dart';
import 'widgets/question_input.dart';

class SurveyFormScreen extends StatefulWidget {
  const SurveyFormScreen({super.key, required this.survey});

  final Survey survey;

  @override
  State<SurveyFormScreen> createState() => _SurveyFormScreenState();
}

class _SurveyFormScreenState extends State<SurveyFormScreen> {
  static const _fallbackAgeGroups = [
    'أقل من 18 سنة',
    '18 - 30 سنة',
    '31 - 45 سنة',
    '46 - 60 سنة',
    'أكثر من 60 سنة',
  ];
  static const _fallbackVisitTypes = [
    'زيارة طارئة',
    'موعد مسبق',
    'تنويم',
    'مراجعة',
  ];

  final Map<String, dynamic> _answers = <String, dynamic>{};
  final ValueNotifier<int> _revision = ValueNotifier<int>(0);
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _departmentTextController =
      TextEditingController();
  final ScrollController _scrollController = ScrollController();

  final GlobalKey _patientCardKey = GlobalKey();
  final Map<String, GlobalKey> _questionKeys = <String, GlobalKey>{};

  String? _department;
  String? _ageGroup;
  String? _gender;
  String? _visitType;
  String? _error;
  bool _submitting = false;

  /// Field the collector should look at next. It stays outlined for a few
  /// seconds after jumping to it.
  GlobalKey? _highlightKey;
  Timer? _highlightTimer;
  Timer? _scrollRetry;

  GlobalKey _keyFor(String questionId) {
    return _questionKeys.putIfAbsent(
      questionId,
      () => GlobalKey(debugLabel: questionId),
    );
  }

  List<SurveyQuestion> get _questions => widget.survey.questions;

  int get _answeredCount =>
      _questions.where((question) => !_isEmpty(_answers[question.id])).length;

  @override
  void initState() {
    super.initState();

    final state = context.read<AppState>();
    final departments = _departmentsFor(widget.survey, state.bootstrap);
    final userDepartment = state.user?.department;

    if (departments.isNotEmpty) {
      final match = departments.where((d) => d.name == userDepartment);
      if (match.isNotEmpty) {
        _department = match.first.name;
      }
    }
  }

  @override
  void dispose() {
    _highlightTimer?.cancel();
    _scrollRetry?.cancel();
    _nameController.dispose();
    _phoneController.dispose();
    _departmentTextController.dispose();
    _scrollController.dispose();
    _revision.dispose();
    super.dispose();
  }

  List<Department> _departmentsFor(Survey survey, BootstrapData? data) {
    final all = data?.departments ?? const <Department>[];
    final assigned = survey.assignedDepartments;

    if (assigned == null || assigned.isEmpty) return all;

    return all
        .where((department) => assigned.contains(department.name))
        .toList();
  }

  bool _isEmpty(dynamic value) {
    if (value == null) return true;
    if (value is String) return value.trim().isEmpty;
    if (value is List) return value.isEmpty;
    return false;
  }

  void _setAnswer(String questionId, dynamic value) {
    if (_isEmpty(value)) {
      _answers.remove(questionId);
    } else {
      _answers[questionId] = value;
    }

    if (questionId.endsWith('_reason') &&
        value is String &&
        value.trim().isEmpty) {
      _answers.remove(questionId);
    }

    if (!questionId.endsWith('_reason') &&
        value is int &&
        value > 2 &&
        _answers.containsKey('${questionId}_reason')) {
      _answers.remove('${questionId}_reason');
    }

    // Only the progress indicators listen to this notifier, so answering a
    // question no longer rebuilds the whole survey.
    _revision.value++;
  }

  String _selectedDepartment(List<Department> departments) {
    if (departments.isNotEmpty) {
      final valid =
          _department != null &&
          departments.any((department) => department.name == _department);
      return valid ? _department! : '';
    }
    return _departmentTextController.text.trim();
  }

  /// Every required field that still needs an answer, in the same order the
  /// collector sees them on screen.
  List<_ValidationIssue> _collectIssues(
    List<Department> departments,
    SurveySettings settings,
  ) {
    final issues = <_ValidationIssue>[];
    final visitFields = <String>[];

    if (_selectedDepartment(departments).isEmpty) {
      visitFields.add('القسم');
    }
    if (_ageGroup == null || _ageGroup!.isEmpty) {
      visitFields.add('الفئة العمرية');
    }
    if (_gender == null || _gender!.isEmpty) {
      visitFields.add('الجنس');
    }
    if (_visitType == null || _visitType!.isEmpty) {
      visitFields.add('نوع الزيارة');
    }

    // A survey-level requirement always wins over the global anonymous
    // setting, exactly like the website: ticking "require name/phone" in the
    // survey editor must make the field mandatory in the app too.
    final requireName =
        widget.survey.requireName ||
        (!settings.allowAnonymous && settings.requireName);
    final requirePhone =
        widget.survey.requirePhone ||
        (!settings.allowAnonymous && settings.requirePhone);

    if (requireName && _nameController.text.trim().isEmpty) {
      visitFields.add('اسم المريض');
    }

    // The site decides whether the phone is mandatory (survey settings or the
    // per-survey flag). If it is provided at all it must still be a valid
    // 9-digit number starting with 7.
    final phone = _phoneController.text.trim();
    if (phone.isEmpty) {
      if (requirePhone) {
        visitFields.add('رقم الهاتف (9 أرقام تبدأ بالرقم 7)');
      }
    } else if (!isValidPhone(phone)) {
      visitFields.add('رقم الهاتف غير صحيح (9 أرقام تبدأ بالرقم 7)');
    }

    // One row for the whole visit card: five separate rows would fill the
    // dialog and hide the actual unanswered questions.
    if (visitFields.isNotEmpty) {
      issues.add(
        _ValidationIssue(
          key: _patientCardKey,
          section: 'بيانات الزيارة',
          message: visitFields.map((field) => '• $field').join('\n'),
        ),
      );
    }

    for (var index = 0; index < widget.survey.sections.length; index++) {
      final section = widget.survey.sections[index];

      for (var i = 0; i < section.questions.length; i++) {
        final question = section.questions[i];
        final required = question.required || settings.requireAllQuestions;

        if (required && _isEmpty(_answers[question.id])) {
          issues.add(
            _ValidationIssue(
              key: _keyFor(question.id),
              section: section.title.isEmpty
                  ? 'القسم ${index + 1}'
                  : section.title,
              message: 'السؤال ${_numberFor(index, i)}: ${question.title}',
            ),
          );
        }
      }
    }

    return issues;
  }

  void _focusIssue(_ValidationIssue issue) {
    // A still-open keyboard would shrink the viewport while we measure the
    // target, so close it first.
    FocusManager.instance.primaryFocus?.unfocus();

    _highlightTimer?.cancel();
    setState(() => _highlightKey = issue.key);
    _highlightTimer = Timer(const Duration(seconds: 5), () {
      if (mounted) setState(() => _highlightKey = null);
    });

    // Scroll after the dialog closes, then once more after the route/keyboard
    // animations settle. On some devices the first frame still reports the
    // old viewport size, which made the jump look like it did nothing.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _scrollTo(issue.key);
    });
    _scrollRetry?.cancel();
    _scrollRetry = Timer(const Duration(milliseconds: 480), () {
      if (mounted) _scrollTo(issue.key);
    });
  }

  void _scrollTo(GlobalKey key) {
    final targetContext = key.currentContext;
    if (targetContext == null) return;

    // Align the missing field near the top of the form's scroll view so it is
    // always clearly visible, whether it sits above or below the old offset.
    Scrollable.ensureVisible(
      targetContext,
      duration: const Duration(milliseconds: 420),
      curve: Curves.easeOutCubic,
      alignment: 0.06,
    );
  }

  /// Lists what is still missing and offers to jump to any item, so the
  /// collector never has to hunt for the unanswered question.
  Future<_ValidationIssue?> _showIncompleteDialog(
    List<_ValidationIssue> issues,
  ) async {
    final shown = issues.take(6).toList();
    final remaining = issues.length - shown.length;

    return showDialog<_ValidationIssue>(
      context: context,
      builder: (dialogContext) {
        final palette = Theme.of(dialogContext).extension<AppPalette>()!;

        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: Row(
            children: [
              Icon(
                Icons.error_outline_rounded,
                color: palette.dangerFg,
                size: 22,
              ),
              const SizedBox(width: 8),
              const Expanded(child: Text('الاستبيان غير مكتمل')),
            ],
          ),
          content: SizedBox(
            width: double.maxFinite,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'اضغط على أي بند للانتقال إليه مباشرة، أو استخدم زر '
                  '«الانتقال لأول حقل»:',
                  style: TextStyle(
                    color: palette.muted,
                    fontSize: 12.5,
                    height: 1.6,
                  ),
                ),
                const SizedBox(height: 10),
                Flexible(
                  child: SingleChildScrollView(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (var i = 0; i < shown.length; i++)
                          _IssueRow(
                            index: i + 1,
                            issue: shown[i],
                            onTap: () =>
                                Navigator.of(dialogContext).pop(shown[i]),
                          ),
                        if (remaining > 0) ...[
                          const SizedBox(height: 2),
                          Text(
                            'و$remaining ملاحظات أخرى — ستظهر بعد استكمال '
                            'ما فوق.',
                            style: TextStyle(
                              color: palette.faint,
                              fontSize: 11.5,
                              height: 1.5,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('إغلاق'),
            ),
            FilledButton.icon(
              onPressed: () => Navigator.of(dialogContext).pop(issues.first),
              icon: const Icon(Icons.my_location_rounded, size: 18),
              label: const Text('الانتقال لأول حقل'),
            ),
          ],
        );
      },
    );
  }

  Future<void> _submit() async {
    if (_submitting) return;

    final state = context.read<AppState>();
    final data = state.bootstrap;
    final settings = data?.settings ?? const SurveySettings();
    final departments = _departmentsFor(widget.survey, data);

    final issues = _collectIssues(departments, settings);

    if (issues.isNotEmpty) {
      HapticFeedback.mediumImpact();

      setState(() {
        _error = 'الاستبيان غير مكتمل — يرجى استكمال الحقول المطلوبة.';
      });

      final target = await _showIncompleteDialog(issues);
      if (target != null && mounted) _focusIssue(target);
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    final answers = <Map<String, dynamic>>[];

    for (final question in _questions) {
      dynamic value = _answers[question.id];
      if (_isEmpty(value)) continue;

      if (value is String) value = value.trim();

      answers.add({'questionId': question.id, 'value': value});

      final reason = _answers['${question.id}_reason'];
      if (question.supportsFollowUp &&
          value is int &&
          value >= 1 &&
          value <= 2 &&
          reason is String &&
          reason.trim().isNotEmpty) {
        answers.add({
          'questionId': '${question.id}_reason',
          'value': reason.trim(),
        });
      }
    }

    final payload = <String, dynamic>{
      'surveyId': widget.survey.id,
      'department': _selectedDepartment(departments),
      'answers': answers,
      'patientInfo': {
        'name': _nameController.text.trim(),
        'phone': _phoneController.text.trim(),
        'ageGroup': _ageGroup ?? '',
        'gender': _gender ?? '',
        'visitType': _visitType ?? '',
      },
    };

    final result = await state.submitResponse(payload, widget.survey);

    if (!mounted) return;
    setState(() => _submitting = false);

    final thanks =
        settings.enableThankYouPage &&
            settings.thankYouMessage.trim().isNotEmpty
        ? settings.thankYouMessage.trim()
        : 'شكرًا لكم، تم حفظ الاستجابة على الموقع.';

    if (result.uploaded) {
      await _showResultDialog(
        title: 'تم إرسال الاستبيان بنجاح',
        message: thanks,
        success: true,
      );
      if (mounted) Navigator.of(context).pop();
      return;
    }

    if (result.error != null) {
      setState(() => _error = result.error);
      await _showResultDialog(
        title: 'لم يتم رفع الاستبيان',
        message:
            '${result.error}\n\nتم إبقاء النموذج مفتوحًا حتى تتمكن من تصحيح البيانات.',
        success: false,
      );
      return;
    }

    await _showResultDialog(
      title: 'تم حفظ الاستبيان على الجهاز',
      message:
          'سيتم رفعه تلقائيًا إلى الموقع عند توفر الإنترنت. يمكنك متابعة حالة الرفع من شاشة المزامنة.',
      success: true,
    );
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _showResultDialog({
    required String title,
    required String message,
    required bool success,
  }) {
    final palette = context.palette;

    return showDialog<void>(
      context: context,
      barrierDismissible: success,
      builder: (context) => AlertDialog(
        iconPadding: const EdgeInsets.only(top: 22),
        icon: Container(
          width: 72,
          height: 72,
          decoration: BoxDecoration(
            color: success ? palette.mint : palette.dangerSurface,
            shape: BoxShape.circle,
            border: Border.all(
              color: success ? palette.mintBorder : palette.dangerBorder,
              width: 5,
            ),
          ),
          child: Icon(
            success ? Icons.check_rounded : Icons.error_outline_rounded,
            color: success ? palette.accent : palette.dangerFg,
            size: 36,
          ),
        ),
        title: Text(title, textAlign: TextAlign.center),
        content: Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(height: 1.7, fontSize: 13, color: palette.muted),
        ),
        actionsPadding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
        actions: [
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('حسنًا'),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final data = context.select<AppState, BootstrapData?>(
      (state) => state.bootstrap,
    );
    final online = context.select<AppState, bool>((state) => state.online);
    final baseUrl = context.select<AppState, String>((state) => state.baseUrl);
    final settings = data?.settings ?? const SurveySettings();
    final departments = _departmentsFor(widget.survey, data);
    final total = _questions.length;
    final palette = context.palette;

    return Scaffold(
      backgroundColor: palette.background,
      appBar: AppBar(
        backgroundColor: palette.heroGradient.colors.first,
        flexibleSpace: BrandAppBarBackground(gradient: palette.heroGradient),
        title: const Text('تعبئة الاستبيان'),
        actions: [
          if (!online)
            const Padding(
              padding: EdgeInsets.only(left: 14),
              child: Tooltip(
                message: 'وضع عدم الاتصال',
                child: Icon(Icons.wifi_off_rounded, size: 20),
              ),
            ),
        ],
      ),
      // A Column inside a scroll view (instead of a lazy ListView) keeps every
      // question mounted, so the "jump to the first missing field" action can
      // always resolve its target even when it is far off screen.
      body: GestureDetector(
        behavior: HitTestBehavior.translucent,
        onTap: () => FocusScope.of(context).unfocus(),
        child: SingleChildScrollView(
          controller: _scrollController,
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 26),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (!online) const OfflineBanner(),
              if (_error != null)
                MessageBanner(message: _error!, isError: true),
              ValueListenableBuilder<int>(
                valueListenable: _revision,
                builder: (context, _, child) => _SurveyHero(
                  survey: widget.survey,
                  hospitalName: data?.hospitalName ?? '',
                  hospitalLogo: data?.hospitalLogo ?? '',
                  baseUrl: baseUrl,
                  answered: _answeredCount,
                  total: total,
                  showProgress: settings.showProgressBar,
                ),
              ),
              const SizedBox(height: 20),
              _buildPatientCard(data, settings, departments),
              const SizedBox(height: 22),
              for (
                var index = 0;
                index < widget.survey.sections.length;
                index++
              )
                ..._buildSection(index, widget.survey.sections[index]),
            ],
          ),
        ),
      ),
      bottomNavigationBar: ValueListenableBuilder<int>(
        valueListenable: _revision,
        builder: (context, _, child) {
          final answered = _answeredCount;
          final progress = total == 0
              ? 0.0
              : (answered / total).clamp(0.0, 1.0);

          return _SubmitBar(
            answered: answered,
            total: total,
            progress: progress,
            online: online,
            submitting: _submitting,
            onSubmit: _submit,
          );
        },
      ),
    );
  }

  Widget _buildPatientCard(
    BootstrapData? data,
    SurveySettings settings,
    List<Department> departments,
  ) {
    final ageGroups = data?.ageGroups.isNotEmpty == true
        ? data!.ageGroups.map((item) => item.label).toList()
        : _fallbackAgeGroups;
    final visitTypes = data?.visitTypes.isNotEmpty == true
        ? data!.visitTypes.map((item) => item.label).toList()
        : _fallbackVisitTypes;

    final requireName =
        widget.survey.requireName ||
        (!settings.allowAnonymous && settings.requireName);
    final requirePhone =
        widget.survey.requirePhone ||
        (!settings.allowAnonymous && settings.requirePhone);
    final palette = context.palette;
    final highlighted = _highlightKey == _patientCardKey;
    final fieldStyle = TextStyle(
      fontFamily: 'Tajawal',
      color: palette.ink,
      fontSize: 13.5,
      fontWeight: FontWeight.w600,
    );

    return AnimatedContainer(
      key: _patientCardKey,
      duration: const Duration(milliseconds: 240),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: highlighted ? palette.mint : palette.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: highlighted ? palette.accent : palette.line,
          width: highlighted ? 2 : 1,
        ),
        boxShadow: palette.shadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _CardHeader(
            icon: Icons.badge_outlined,
            title: 'بيانات الزيارة',
            subtitle: 'تُستخدم لتحليل النتائج وتحسين الخدمة.',
          ),
          const SizedBox(height: 16),
          if (departments.isNotEmpty)
            DropdownButtonFormField<String>(
              initialValue:
                  departments.any(
                    (department) => department.name == _department,
                  )
                  ? _department
                  : null,
              isExpanded: true,
              dropdownColor: palette.surface,
              borderRadius: BorderRadius.circular(16),
              iconEnabledColor: palette.accent,
              style: fieldStyle,
              decoration: const InputDecoration(
                labelText: 'القسم',
                prefixIcon: Icon(Icons.apartment_rounded, size: 20),
              ),
              items: departments
                  .map(
                    (department) => DropdownMenuItem<String>(
                      value: department.name,
                      child: Text(department.name),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _department = value),
            )
          else
            TextFormField(
              controller: _departmentTextController,
              cursorColor: palette.accent,
              style: fieldStyle,
              onTapOutside: (_) => FocusScope.of(context).unfocus(),
              decoration: const InputDecoration(
                labelText: 'القسم',
                prefixIcon: Icon(Icons.apartment_rounded, size: 20),
                hintText: 'اكتب اسم القسم',
              ),
            ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: ageGroups.contains(_ageGroup)
                      ? _ageGroup
                      : null,
                  isExpanded: true,
                  dropdownColor: palette.surface,
                  borderRadius: BorderRadius.circular(16),
                  iconEnabledColor: palette.accent,
                  style: fieldStyle,
                  decoration: const InputDecoration(
                    labelText: 'الفئة العمرية',
                    prefixIcon: Icon(Icons.cake_outlined, size: 20),
                  ),
                  items: ageGroups
                      .map(
                        (label) => DropdownMenuItem<String>(
                          value: label,
                          child: Text(label, overflow: TextOverflow.ellipsis),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => _ageGroup = value),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: visitTypes.contains(_visitType)
                      ? _visitType
                      : null,
                  isExpanded: true,
                  dropdownColor: palette.surface,
                  borderRadius: BorderRadius.circular(16),
                  iconEnabledColor: palette.accent,
                  style: fieldStyle,
                  decoration: const InputDecoration(
                    labelText: 'نوع الزيارة',
                    prefixIcon: Icon(Icons.event_note_outlined, size: 20),
                  ),
                  items: visitTypes
                      .map(
                        (label) => DropdownMenuItem<String>(
                          value: label,
                          child: Text(label, overflow: TextOverflow.ellipsis),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => _visitType = value),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            'الجنس',
            style: TextStyle(
              fontSize: 12.5,
              color: palette.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _GenderChoice(
                  label: 'ذكر',
                  icon: Icons.male_rounded,
                  selected: _gender == 'ذكر',
                  onTap: () => setState(() => _gender = 'ذكر'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _GenderChoice(
                  label: 'أنثى',
                  icon: Icons.female_rounded,
                  selected: _gender == 'أنثى',
                  onTap: () => setState(() => _gender = 'أنثى'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _nameController,
            textInputAction: TextInputAction.next,
            cursorColor: palette.accent,
            style: fieldStyle,
            onTapOutside: (_) => FocusScope.of(context).unfocus(),
            decoration: InputDecoration(
              labelText: requireName ? 'اسم المريض *' : 'اسم المريض (اختياري)',
              prefixIcon: const Icon(Icons.person_outline_rounded, size: 20),
            ),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _phoneController,
            keyboardType: TextInputType.phone,
            textDirection: TextDirection.rtl,
            textAlign: TextAlign.right,
            maxLength: 9,
            cursorColor: palette.accent,
            style: fieldStyle,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            onTapOutside: (_) => FocusScope.of(context).unfocus(),
            decoration: InputDecoration(
              labelText: requirePhone ? 'رقم الهاتف *' : 'رقم الهاتف (اختياري)',
              hintText: '7XXXXXXXX',
              prefixIcon: const Icon(Icons.phone_outlined, size: 20),
              counterText: '',
              helperText: requirePhone
                  ? 'إلزامي: 9 أرقام ويبدأ بالرقم 7'
                  : 'إن أدخلته: 9 أرقام ويبدأ بالرقم 7',
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _buildSection(int index, SurveySection section) {
    return [
      ValueListenableBuilder<int>(
        valueListenable: _revision,
        builder: (context, _, child) {
          final answered = section.questions
              .where((question) => !_isEmpty(_answers[question.id]))
              .length;

          return _SectionHeader(
            index: index,
            title: section.title.isEmpty ? 'القسم ${index + 1}' : section.title,
            description: section.description,
            answered: answered,
            total: section.questions.length,
          );
        },
      ),
      const SizedBox(height: 12),
      for (var i = 0; i < section.questions.length; i++)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: RepaintBoundary(
            child: _QuestionCard(
              key: _keyFor(section.questions[i].id),
              highlighted: _highlightKey == _keyFor(section.questions[i].id),
              question: section.questions[i],
              number: _numberFor(index, i),
              initialValue: _answers[section.questions[i].id],
              initialReason:
                  (_answers['${section.questions[i].id}_reason'] ?? '')
                      .toString(),
              onChanged: (value) => _setAnswer(section.questions[i].id, value),
              onReasonChanged: (value) =>
                  _setAnswer('${section.questions[i].id}_reason', value),
            ),
          ),
        ),
      const SizedBox(height: 10),
    ];
  }

  int _numberFor(int sectionIndex, int questionIndex) {
    var number = 0;
    for (var s = 0; s < sectionIndex; s++) {
      number += widget.survey.sections[s].questions.length;
    }
    return number + questionIndex + 1;
  }
}

/// A required field that still has no answer, plus where to find it.
class _ValidationIssue {
  const _ValidationIssue({
    required this.key,
    required this.section,
    required this.message,
  });

  final GlobalKey key;
  final String section;
  final String message;
}

/// One line inside the "incomplete survey" dialog. Tapping it jumps straight
/// to that field once the dialog closes.
class _IssueRow extends StatelessWidget {
  const _IssueRow({
    required this.index,
    required this.issue,
    required this.onTap,
  });

  final int index;
  final _ValidationIssue issue;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: palette.surfaceAlt,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 22,
                  height: 22,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: palette.mint,
                    borderRadius: BorderRadius.circular(7),
                    border: Border.all(color: palette.mintBorder),
                  ),
                  child: Text(
                    '$index',
                    style: TextStyle(
                      color: palette.accent,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        issue.section,
                        style: TextStyle(
                          color: palette.faint,
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 1),
                      Text(
                        issue.message,
                        style: TextStyle(
                          color: palette.ink,
                          fontSize: 12.5,
                          height: 1.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Icon(
                  Icons.my_location_rounded,
                  size: 16,
                  color: palette.accent,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// One survey question. Owns its answer locally so tapping a star or an option
/// only rebuilds this card instead of the full survey list.
class _QuestionCard extends StatefulWidget {
  const _QuestionCard({
    super.key,
    required this.question,
    required this.number,
    required this.initialValue,
    required this.initialReason,
    required this.onChanged,
    required this.onReasonChanged,
    this.highlighted = false,
  });

  final SurveyQuestion question;
  final int number;
  final dynamic initialValue;
  final String initialReason;
  final ValueChanged<dynamic> onChanged;
  final ValueChanged<String> onReasonChanged;

  /// True while the "jump to the first missing field" highlight is active.
  final bool highlighted;

  @override
  State<_QuestionCard> createState() => _QuestionCardState();
}

class _QuestionCardState extends State<_QuestionCard> {
  dynamic _value;
  late String _reason;

  bool get _answered {
    final value = _value;
    if (value == null) return false;
    if (value is String) return value.trim().isNotEmpty;
    if (value is List) return value.isNotEmpty;
    return true;
  }

  @override
  void initState() {
    super.initState();
    _value = widget.initialValue;
    _reason = widget.initialReason;
  }

  @override
  void didUpdateWidget(covariant _QuestionCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialValue != widget.initialValue) {
      _value = widget.initialValue;
    }
    if (oldWidget.initialReason != widget.initialReason) {
      _reason = widget.initialReason;
    }
  }

  void _handleChanged(dynamic value) {
    setState(() {
      _value = value;
      // A positive score no longer needs a complaint reason.
      if (value is int && value > 2 && _reason.isNotEmpty) {
        _reason = '';
        widget.onReasonChanged('');
      }
    });
    widget.onChanged(value);
  }

  void _handleReasonChanged(String value) {
    _reason = value;
    widget.onReasonChanged(value);
  }

  @override
  Widget build(BuildContext context) {
    final question = widget.question;
    final palette = context.palette;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 240),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: widget.highlighted ? palette.mint : palette.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: widget.highlighted
              ? palette.accent
              : (_answered ? palette.mintBorder : palette.line),
          width: widget.highlighted ? 2 : (_answered ? 1.3 : 1),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: _answered ? palette.accent : palette.mint,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Text(
                  '${widget.number}',
                  style: TextStyle(
                    color: _answered ? Colors.white : palette.accent,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: question.title,
                    children: [
                      if (question.required)
                        const TextSpan(
                          text: ' *',
                          style: TextStyle(color: appDanger),
                        ),
                    ],
                  ),
                  style: TextStyle(
                    color: palette.ink,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    height: 1.55,
                  ),
                ),
              ),
              if (_answered)
                Padding(
                  padding: const EdgeInsets.only(right: 6, top: 2),
                  child: Icon(
                    Icons.check_circle_rounded,
                    size: 17,
                    color: palette.accent,
                  ),
                ),
            ],
          ),
          if (question.description.trim().isNotEmpty) ...[
            const SizedBox(height: 5),
            Padding(
              padding: const EdgeInsets.only(right: 37),
              child: Text(
                question.description,
                style: TextStyle(
                  color: palette.faint,
                  fontSize: 11.5,
                  height: 1.5,
                ),
              ),
            ),
          ],
          const SizedBox(height: 14),
          QuestionInput(
            question: question,
            value: _value,
            reason: _reason,
            onChanged: _handleChanged,
            onReasonChanged: _handleReasonChanged,
          ),
        ],
      ),
    );
  }
}

class _SurveyHero extends StatelessWidget {
  const _SurveyHero({
    required this.survey,
    required this.hospitalName,
    required this.hospitalLogo,
    required this.baseUrl,
    required this.answered,
    required this.total,
    required this.showProgress,
  });

  final Survey survey;
  final String hospitalName;
  final String hospitalLogo;
  final String baseUrl;
  final int answered;
  final int total;
  final bool showProgress;

  @override
  Widget build(BuildContext context) {
    final percent = total == 0 ? 0 : ((answered / total) * 100).round();
    final palette = context.palette;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: palette.heroGradient,
        borderRadius: BorderRadius.circular(26),
        boxShadow: palette.shadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              HospitalLogo(
                baseUrl: baseUrl,
                path: hospitalLogo,
                size: 54,
                radius: 16,
                padding: 7,
                fallbackText: hospitalName,
                showShadow: true,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      hospitalName.isEmpty ? 'MedSurvey Pro' : hospitalName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.94),
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      survey.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16.5,
                        fontWeight: FontWeight.w800,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (survey.description.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              survey.description,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.92),
                fontSize: 11.5,
                height: 1.65,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              MetaPill(
                icon: Icons.help_outline_rounded,
                label: '$total سؤال',
                onDark: true,
              ),
              MetaPill(
                icon: Icons.grid_view_rounded,
                label: '${survey.sections.length} أقسام',
                onDark: true,
              ),
              const MetaPill(
                icon: Icons.schedule_rounded,
                label: 'حوالي 3 دقائق',
                onDark: true,
              ),
            ],
          ),
          if (showProgress && total > 0) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'أجبت على $answered من $total سؤال',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.95),
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                Text(
                  '$percent%',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: LinearProgressIndicator(
                value: total == 0 ? 0 : answered / total,
                minHeight: 7,
                backgroundColor: Colors.white.withValues(alpha: 0.25),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CardHeader extends StatelessWidget {
  const _CardHeader({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: palette.mint,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 19, color: palette.accent),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: palette.ink,
                  fontSize: 14.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (subtitle.trim().isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(color: palette.muted, fontSize: 10.5),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.index,
    required this.title,
    required this.description,
    required this.answered,
    required this.total,
  });

  final int index;
  final String title;
  final String description;
  final int answered;
  final int total;

  @override
  Widget build(BuildContext context) {
    final done = total > 0 && answered == total;
    final palette = context.palette;

    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
                colors: [appTealDark, appTealLight],
              ),
              borderRadius: BorderRadius.circular(13),
              boxShadow: [
                BoxShadow(
                  color: appTeal.withValues(alpha: 0.2),
                  blurRadius: 12,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Text(
              '${index + 1}',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: palette.ink,
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                    height: 1.4,
                  ),
                ),
                if (description.trim().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    description,
                    style: TextStyle(
                      color: palette.muted,
                      fontSize: 10.5,
                      height: 1.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
            decoration: BoxDecoration(
              color: done ? palette.mint : palette.surface,
              borderRadius: BorderRadius.circular(30),
              border: Border.all(
                color: done ? palette.mintBorder : palette.line,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (done) ...[
                  Icon(Icons.check_rounded, size: 12, color: palette.accent),
                  const SizedBox(width: 3),
                ],
                Text(
                  '$answered/$total',
                  style: TextStyle(
                    color: done ? palette.accent : palette.muted,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SubmitBar extends StatelessWidget {
  const _SubmitBar({
    required this.answered,
    required this.total,
    required this.progress,
    required this.online,
    required this.submitting,
    required this.onSubmit,
  });

  final int answered;
  final int total;
  final double progress;
  final bool online;
  final bool submitting;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return Container(
      decoration: BoxDecoration(
        color: palette.surface,
        border: Border(top: BorderSide(color: palette.line)),
        boxShadow: palette.isDark ? null : appSoftShadow,
      ),
      child: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        child: Row(
          children: [
            SizedBox(
              width: 46,
              height: 46,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  SizedBox(
                    width: 46,
                    height: 46,
                    child: CircularProgressIndicator(
                      value: progress,
                      strokeWidth: 4,
                      strokeCap: StrokeCap.round,
                      backgroundColor: palette.mintStrong,
                    ),
                  ),
                  Text(
                    '$answered',
                    style: TextStyle(
                      color: palette.accent,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    '$answered من $total سؤال',
                    style: TextStyle(
                      color: palette.ink,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    online
                        ? 'سيتم الإرسال مباشرة إلى الموقع'
                        : 'سيُحفظ ويُرفع تلقائيًا لاحقًا',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: palette.faint, fontSize: 10),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            FilledButton.icon(
              onPressed: submitting ? null : onSubmit,
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 48),
                padding: const EdgeInsets.symmetric(horizontal: 18),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
                textStyle: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
              icon: submitting
                  ? SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: palette.isDark
                            ? const Color(0xFF04231F)
                            : Colors.white,
                      ),
                    )
                  : const Icon(Icons.send_rounded, size: 18),
              label: Text(submitting ? 'جارٍ...' : 'إرسال'),
            ),
          ],
        ),
      ),
    );
  }
}

class _GenderChoice extends StatelessWidget {
  const _GenderChoice({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          color: selected ? palette.mint : palette.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? palette.accent : palette.line,
            width: selected ? 1.4 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 18,
              color: selected ? palette.accent : palette.faint,
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: selected ? palette.accent : palette.muted,
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
