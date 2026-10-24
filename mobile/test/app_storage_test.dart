import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_storage.dart';

void main() {
  test('the checked-in production endpoint is only a placeholder', () {
    // The real hospital domain is injected at build time with
    // `--dart-define=MEDSURVEY_BASE_URL=...` so it is never committed here.
    expect(AppStorage.productionBaseUrl, 'https://medsurvey.example.com');
  });

  test('release builds always use the hospital server', () {
    expect(
      AppStorage.resolveBaseUrl(
        'https://old.example.com',
        customAllowed: false,
      ),
      AppStorage.productionBaseUrl,
    );
    expect(
      AppStorage.resolveBaseUrl(
        'http://192.168.1.100:8080',
        customAllowed: false,
      ),
      AppStorage.productionBaseUrl,
    );
    expect(
      AppStorage.resolveBaseUrl('', customAllowed: false),
      AppStorage.productionBaseUrl,
    );
  });

  test('development builds keep the address entered by the collector', () {
    expect(
      AppStorage.resolveBaseUrl(
        'http://192.168.1.100:8080',
        customAllowed: true,
      ),
      'http://192.168.1.100:8080',
    );
    expect(
      AppStorage.resolveBaseUrl('', customAllowed: true),
      AppStorage.defaultBaseUrl,
    );
  });
}
