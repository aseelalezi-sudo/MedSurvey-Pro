import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/utils.dart';
import 'package:medsurvey_app/data/models.dart';

void main() {
  group('normalizeBaseUrl', () {
    test('adds https and removes trailing slashes', () {
      expect(
        normalizeBaseUrl(' survey.example.com/ '),
        'https://survey.example.com',
      );
    });

    test('keeps local http addresses and strips api suffix', () {
      expect(
        normalizeBaseUrl('http://10.0.2.2:8000/api/v1/'),
        'http://10.0.2.2:8000',
      );
      expect(
        normalizeBaseUrl('https://example.com/api'),
        'https://example.com',
      );
    });

    test('assumes http for LAN and loopback addresses', () {
      // A local server has no TLS certificate; https:// would hang and the
      // app would report a connection timeout.
      expect(
        normalizeBaseUrl('192.168.1.100:8080'),
        'http://192.168.1.100:8080',
      );
      expect(normalizeBaseUrl('10.0.2.2:8000'), 'http://10.0.2.2:8000');
      expect(normalizeBaseUrl('127.0.0.1:9000'), 'http://127.0.0.1:9000');
      expect(normalizeBaseUrl('localhost:8080'), 'http://localhost:8080');
      expect(normalizeBaseUrl('hospital.local'), 'http://hospital.local');
    });

    test('keeps https for a public IP address', () {
      expect(normalizeBaseUrl('203.0.113.10'), 'https://203.0.113.10');
    });
  });

  group('phone validation', () {
    test('accepts nine digits starting with seven', () {
      expect(isValidPhone('712345678'), isTrue);
    });

    test('rejects short or wrongly prefixed numbers', () {
      expect(isValidPhone('612345678'), isFalse);
      expect(isValidPhone('71234567'), isFalse);
    });
  });

  group('media urls', () {
    test('joins the hospital logo path with the server root', () {
      expect(
        resolveMediaUrl(
          'http://192.168.1.100:8000',
          '/storage/settings/logos/global/logo.png',
        ),
        'http://192.168.1.100:8000/storage/settings/logos/global/logo.png',
      );
      expect(
        resolveMediaUrl('https://survey.example.com/', 'storage/logo.png'),
        'https://survey.example.com/storage/logo.png',
      );
    });

    test('keeps absolute urls and ignores empty paths', () {
      expect(
        resolveMediaUrl('https://survey.example.com', 'https://cdn.test/a.png'),
        'https://cdn.test/a.png',
      );
      expect(resolveMediaUrl('https://survey.example.com', '  '), '');
    });
  });

  group('bootstrap parsing', () {
    test('parses surveys, questions and departments', () {
      final data = BootstrapData.fromJson({
        'user': {
          'id': 'u1',
          'username': 'staff',
          'name': 'جامع الاستبيانات',
          'role': 'staff',
          'department': 'الطوارئ',
          'tenantId': null,
        },
        'surveys': [
          {
            'id': 's1',
            'title': 'استبيان الطوارئ',
            'description': 'وصف',
            'isActive': true,
            'requireName': false,
            'requirePhone': false,
            'assignedDepartments': ['الطوارئ'],
            'sections': [
              {
                'id': 'sec1',
                'title': 'القسم الأول',
                'questions': [
                  {
                    'id': 'q1',
                    'type': 'stars',
                    'title': 'ما تقييمك؟',
                    'required': true,
                    'options': null,
                  },
                  {
                    'id': 'q2',
                    'type': 'multiple_choice',
                    'title': 'اختر',
                    'options': ['أ', 'ب'],
                  },
                ],
              },
            ],
          },
        ],
        'settings': {
          'hospital': {
            'name': 'المستشفى',
            'logo': '/storage/settings/logos/global/hospital.png',
          },
          'departments': [
            {'id': 'd1', 'name': 'الطوارئ', 'color': '#EF4444'},
          ],
          'ageGroups': [
            {'id': 'a1', 'label': '18 - 30 سنة'},
          ],
          'visitTypes': [
            {'id': 'v1', 'label': 'زيارة طارئة'},
          ],
          'surveySettings': {'allowAnonymous': true},
        },
      });

      expect(data.user.name, 'جامع الاستبيانات');
      expect(data.hospitalName, 'المستشفى');
      expect(data.hospitalLogo, '/storage/settings/logos/global/hospital.png');
      expect(data.surveys, hasLength(1));
      expect(data.surveys.first.questionCount, 2);
      expect(data.surveys.first.assignedDepartments, ['الطوارئ']);
      expect(data.departments.first.name, 'الطوارئ');
      expect(data.surveys.first.questions[1].options[0].value, 'أ');
    });

    test('reads the hospital tagline and welcome message from settings', () {
      final data = BootstrapData.fromJson({
        'user': {'id': 'u1', 'username': 'staff', 'name': 'جامع'},
        'settings': {
          'hospital': {
            'name': 'المستشفى',
            'description': 'رعاية صحية متكاملة',
            'welcomeMessage': 'أهلاً بكم',
          },
          'surveySettings': {},
        },
      });

      expect(data.hospitalTagline, 'رعاية صحية متكاملة');
      expect(data.hospitalWelcomeMessage, 'أهلاً بكم');
      expect(data.surveys, isEmpty);
    });

    test('copyWith replaces surveys without touching the rest', () {
      final data = BootstrapData.fromJson({
        'user': {'id': 'u1', 'username': 'staff', 'name': 'جامع'},
        'surveys': [
          {'id': 's1', 'title': 'قديم'},
        ],
        'settings': {
          'hospital': {'name': 'المستشفى'},
          'surveySettings': {},
        },
      });

      final updated = data.copyWith(
        surveys: [
          Survey.fromJson({'id': 's1', 'title': 'جديد'}),
        ],
      );

      expect(updated.surveys.single.title, 'جديد');
      expect(updated.hospitalName, 'المستشفى');
      expect(data.surveys.single.title, 'قديم');
    });
  });

  group('pending entry', () {
    test('round-trips a JSON payload and reads status', () {
      final entry = PendingEntry.fromRow({
        'clientResponseId': '11111111-1111-4111-8111-111111111111',
        'collectorId': 'u1',
        'surveyId': 's1',
        'surveyTitle': 'استبيان',
        'payload':
            '{"surveyId":"s1","clientResponseId":"11111111-1111-4111-8111-111111111111","answers":[]}',
        'status': 'failed',
        'attempts': 2,
        'lastError': 'خطأ في البيانات',
        'createdAt': 1700000000000,
        'updatedAt': 1700000001000,
      });

      expect(entry.isFailed, isTrue);
      expect(entry.attempts, 2);
      expect(entry.payload['surveyId'], 's1');
      expect(entry.statusLabel, 'فشل الرفع');
    });
  });
}
