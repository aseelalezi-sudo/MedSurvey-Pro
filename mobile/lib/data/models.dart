import 'dart:convert';

Map<String, dynamic> asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return <String, dynamic>{};
}

List<dynamic> asList(dynamic value) =>
    value is List ? value : const <dynamic>[];

String asString(dynamic value, [String fallback = '']) {
  if (value == null) return fallback;
  return value.toString();
}

bool asBool(dynamic value, {bool fallback = false}) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final normalized = value.toLowerCase().trim();
    if (normalized == 'true' || normalized == '1' || normalized == 'yes') {
      return true;
    }
    if (normalized == 'false' || normalized == '0' || normalized == 'no') {
      return false;
    }
  }
  return fallback;
}

int asInt(dynamic value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(asString(value)) ?? fallback;
}

DateTime? asDate(dynamic value) {
  if (value == null) return null;
  return DateTime.tryParse(value.toString());
}

class AppUser {
  const AppUser({
    required this.id,
    required this.username,
    required this.name,
    required this.role,
    this.department,
    this.tenantId,
  });

  final String id;
  final String username;
  final String name;
  final String role;
  final String? department;
  final String? tenantId;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: asString(json['id']),
      username: asString(json['username']),
      name: asString(json['name']),
      role: asString(json['role']),
      department: asString(json['department']).isEmpty
          ? null
          : asString(json['department']),
      tenantId: asString(json['tenantId']).isEmpty
          ? null
          : asString(json['tenantId']),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'name': name,
    'role': role,
    'department': department,
    'tenantId': tenantId,
  };

  String get roleLabel => switch (role) {
    'super_admin' => 'مدير عام',
    'admin' => 'مدير',
    'unit_manager' => 'مدير وحدة',
    'head_of_department' => 'رئيس قسم',
    'staff' => 'موظف استبيانات',
    _ => role,
  };

  String get initials {
    final source = name.trim().isNotEmpty ? name.trim() : username;
    return source.isEmpty ? '؟' : source.substring(0, 1);
  }
}

class QuestionOption {
  const QuestionOption({required this.value, required this.label});

  final String value;
  final String label;

  factory QuestionOption.fromJson(dynamic raw) {
    if (raw is String) return QuestionOption(value: raw, label: raw);
    final map = asMap(raw);
    final value = asString(map['value'] ?? map['label']);
    return QuestionOption(value: value, label: asString(map['label'] ?? value));
  }
}

class SurveyQuestion {
  const SurveyQuestion({
    required this.id,
    required this.type,
    required this.title,
    this.description = '',
    this.category = '',
    this.required = false,
    this.options = const [],
  });

  final String id;
  final String type;
  final String title;
  final String description;
  final String category;
  final bool required;
  final List<QuestionOption> options;

  bool get isScale => type == 'stars' || type == 'emoji' || type == 'rating';
  bool get isNps => type == 'nps';
  bool get supportsFollowUp => isScale;

  factory SurveyQuestion.fromJson(Map<String, dynamic> json) {
    return SurveyQuestion(
      id: asString(json['id']),
      type: asString(json['type'], 'stars'),
      title: asString(json['title']),
      description: asString(json['description']),
      category: asString(json['category']),
      required: asBool(json['required']),
      options: asList(
        json['options'],
      ).map(QuestionOption.fromJson).where((o) => o.value.isNotEmpty).toList(),
    );
  }
}

class SurveySection {
  const SurveySection({
    required this.id,
    required this.title,
    this.description = '',
    this.questions = const [],
  });

  final String id;
  final String title;
  final String description;
  final List<SurveyQuestion> questions;

  factory SurveySection.fromJson(Map<String, dynamic> json) {
    return SurveySection(
      id: asString(json['id']),
      title: asString(json['title']),
      description: asString(json['description']),
      questions: asList(
        json['questions'],
      ).map((e) => SurveyQuestion.fromJson(asMap(e))).toList(),
    );
  }
}

class Survey {
  const Survey({
    required this.id,
    required this.title,
    this.description = '',
    this.requireName = false,
    this.requirePhone = false,
    this.assignedDepartments,
    this.tips = const [],
    this.sections = const [],
  });

  final String id;
  final String title;
  final String description;
  final bool requireName;
  final bool requirePhone;
  final List<String>? assignedDepartments;
  final List<String> tips;
  final List<SurveySection> sections;

  int get questionCount =>
      sections.fold(0, (sum, section) => sum + section.questions.length);

  List<SurveyQuestion> get questions =>
      sections.expand((section) => section.questions).toList(growable: false);

  factory Survey.fromJson(Map<String, dynamic> json) {
    final rawDepartments = json['assignedDepartments'];
    return Survey(
      id: asString(json['id']),
      title: asString(json['title']),
      description: asString(json['description']),
      requireName: asBool(json['requireName']),
      requirePhone: asBool(json['requirePhone']),
      assignedDepartments: rawDepartments is List
          ? rawDepartments.map(asString).where((e) => e.isNotEmpty).toList()
          : null,
      tips: asList(
        json['tips'],
      ).map(asString).where((e) => e.isNotEmpty).toList(),
      sections: asList(
        json['sections'],
      ).map((e) => SurveySection.fromJson(asMap(e))).toList(),
    );
  }
}

class Department {
  const Department({
    required this.id,
    required this.name,
    this.color = '#0d9488',
  });

  final String id;
  final String name;
  final String color;

  factory Department.fromJson(Map<String, dynamic> json) {
    return Department(
      id: asString(json['id'], asString(json['name'])),
      name: asString(json['name']),
      color: asString(json['color'], '#0d9488'),
    );
  }
}

class LabeledOption {
  const LabeledOption({required this.id, required this.label});

  final String id;
  final String label;

  factory LabeledOption.fromJson(Map<String, dynamic> json) {
    return LabeledOption(
      id: asString(json['id'], asString(json['label'])),
      label: asString(json['label']),
    );
  }
}

class SurveySettings {
  const SurveySettings({
    this.allowAnonymous = true,
    this.requireAllQuestions = false,
    this.requireName = false,
    this.requirePhone = false,
    this.showProgressBar = true,
    this.enableThankYouPage = true,
    this.thankYouMessage = '',
  });

  final bool allowAnonymous;
  final bool requireAllQuestions;
  final bool requireName;
  final bool requirePhone;
  final bool showProgressBar;
  final bool enableThankYouPage;
  final String thankYouMessage;

  factory SurveySettings.fromJson(Map<String, dynamic> json) {
    return SurveySettings(
      allowAnonymous: asBool(json['allowAnonymous'], fallback: true),
      requireAllQuestions: asBool(json['requireAllQuestions']),
      requireName: asBool(json['requireName']),
      requirePhone: asBool(json['requirePhone']),
      showProgressBar: asBool(json['showProgressBar'], fallback: true),
      enableThankYouPage: asBool(json['enableThankYouPage'], fallback: true),
      thankYouMessage: asString(json['thankYouMessage']),
    );
  }
}

class BootstrapData {
  const BootstrapData({
    required this.user,
    required this.surveys,
    required this.departments,
    required this.ageGroups,
    required this.visitTypes,
    required this.settings,
    this.hospitalName = '',
    this.hospitalLogo = '',
    this.hospitalPhone = '',
    this.hospitalAddress = '',
    this.hospitalTagline = '',
    this.hospitalWelcomeMessage = '',
    this.serverTime,
  });

  final AppUser user;
  final List<Survey> surveys;
  final List<Department> departments;
  final List<LabeledOption> ageGroups;
  final List<LabeledOption> visitTypes;
  final SurveySettings settings;
  final String hospitalName;

  /// Raw logo path from the server, e.g. `/storage/settings/logos/...`.
  /// Resolve it with `resolveMediaUrl(baseUrl, hospitalLogo)`.
  final String hospitalLogo;
  final String hospitalPhone;
  final String hospitalAddress;

  /// Optional website copy, used by the home hero: the hospital tagline and
  /// the welcome message configured in the dashboard.
  final String hospitalTagline;
  final String hospitalWelcomeMessage;
  final DateTime? serverTime;

  factory BootstrapData.fromJson(Map<String, dynamic> json) {
    final settings = asMap(json['settings']);
    final hospital = asMap(settings['hospital']);
    return BootstrapData(
      user: AppUser.fromJson(asMap(json['user'])),
      surveys: asList(
        json['surveys'],
      ).map((e) => Survey.fromJson(asMap(e))).toList(),
      departments: asList(
        settings['departments'],
      ).map((e) => Department.fromJson(asMap(e))).toList(),
      ageGroups: asList(
        settings['ageGroups'],
      ).map((e) => LabeledOption.fromJson(asMap(e))).toList(),
      visitTypes: asList(
        settings['visitTypes'],
      ).map((e) => LabeledOption.fromJson(asMap(e))).toList(),
      settings: SurveySettings.fromJson(asMap(settings['surveySettings'])),
      hospitalName: asString(hospital['name']),
      hospitalLogo: asString(hospital['logo']),
      hospitalPhone: asString(hospital['phone']),
      hospitalAddress: asString(hospital['address']),
      hospitalTagline: asString(hospital['description']),
      hospitalWelcomeMessage: asString(hospital['welcomeMessage']),
      serverTime: asDate(json['serverTime']),
    );
  }

  BootstrapData copyWith({
    AppUser? user,
    List<Survey>? surveys,
    List<Department>? departments,
    List<LabeledOption>? ageGroups,
    List<LabeledOption>? visitTypes,
    SurveySettings? settings,
    String? hospitalName,
    String? hospitalLogo,
    String? hospitalPhone,
    String? hospitalAddress,
    String? hospitalTagline,
    String? hospitalWelcomeMessage,
    DateTime? serverTime,
  }) {
    return BootstrapData(
      user: user ?? this.user,
      surveys: surveys ?? this.surveys,
      departments: departments ?? this.departments,
      ageGroups: ageGroups ?? this.ageGroups,
      visitTypes: visitTypes ?? this.visitTypes,
      settings: settings ?? this.settings,
      hospitalName: hospitalName ?? this.hospitalName,
      hospitalLogo: hospitalLogo ?? this.hospitalLogo,
      hospitalPhone: hospitalPhone ?? this.hospitalPhone,
      hospitalAddress: hospitalAddress ?? this.hospitalAddress,
      hospitalTagline: hospitalTagline ?? this.hospitalTagline,
      hospitalWelcomeMessage:
          hospitalWelcomeMessage ?? this.hospitalWelcomeMessage,
      serverTime: serverTime ?? this.serverTime,
    );
  }
}

class PendingEntry {
  const PendingEntry({
    required this.clientResponseId,
    required this.collectorId,
    required this.surveyId,
    required this.surveyTitle,
    required this.payload,
    required this.status,
    required this.attempts,
    this.lastError,
    required this.createdAt,
    required this.updatedAt,
  });

  final String clientResponseId;
  final String collectorId;
  final String surveyId;
  final String surveyTitle;
  final Map<String, dynamic> payload;
  final String status;
  final int attempts;
  final String? lastError;
  final DateTime createdAt;
  final DateTime updatedAt;

  bool get isFailed => status == 'failed';
  bool get isSyncing => status == 'syncing';

  String get statusLabel => switch (status) {
    'syncing' => 'جارٍ الرفع',
    'failed' => 'فشل الرفع',
    _ => 'بانتظار المزامنة',
  };

  factory PendingEntry.fromRow(Map<String, dynamic> row) {
    Map<String, dynamic> payload;
    try {
      payload = asMap(jsonDecode(asString(row['payload'], '{}')));
    } catch (_) {
      payload = <String, dynamic>{};
    }

    return PendingEntry(
      clientResponseId: asString(row['clientResponseId']),
      collectorId: asString(row['collectorId']),
      surveyId: asString(row['surveyId']),
      surveyTitle: asString(row['surveyTitle']),
      payload: payload,
      status: asString(row['status'], 'pending'),
      attempts: asInt(row['attempts']),
      lastError: asString(row['lastError']).isEmpty
          ? null
          : asString(row['lastError']),
      createdAt: DateTime.fromMillisecondsSinceEpoch(asInt(row['createdAt'])),
      updatedAt: DateTime.fromMillisecondsSinceEpoch(asInt(row['updatedAt'])),
    );
  }
}

class LoginResult {
  const LoginResult({required this.token, required this.user});

  final String token;
  final AppUser user;
}

class SubmissionResult {
  const SubmissionResult({
    required this.clientResponseId,
    required this.uploaded,
    this.queued = false,
    this.error,
  });

  final String clientResponseId;
  final bool uploaded;
  final bool queued;
  final String? error;
}
