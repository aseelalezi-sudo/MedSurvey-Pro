import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:medsurvey_app/core/app_info.dart';
import 'package:medsurvey_app/core/app_release.dart';

void main() {
  test('the server answer is trimmed and typed', () {
    final release = AppRelease.fromJson({
      'version': ' 1.2.0 ',
      'build': 21,
      'downloadUrl': ' https://example.test/app.apk ',
      'notes': ' تحسينات في المزامنة ',
      'minBuild': 18,
    });

    expect(release.version, '1.2.0');
    expect(release.build, 21);
    expect(release.downloadUrl, 'https://example.test/app.apk');
    expect(release.notes, 'تحسينات في المزامنة');
    expect(release.minBuild, 18);
    expect(release.isConfigured, isTrue);
  });

  test('only a higher build counts as an update', () {
    final release = AppRelease.fromJson({
      'version': '1.2.0',
      'build': 21,
      'downloadUrl': 'https://example.test/app.apk',
      'minBuild': 18,
    });

    expect(release.isNewerThan(20), isTrue);
    expect(release.isNewerThan(21), isFalse);
    expect(release.isNewerThan(22), isFalse);

    expect(release.isRequiredFor(17), isTrue);
    expect(release.isRequiredFor(18), isFalse);
    expect(release.isRequiredFor(21), isFalse);
  });

  test('an empty download URL or build 0 disables the notice', () {
    final noUrl = AppRelease.fromJson({
      'version': '1.2.0',
      'build': 21,
      'downloadUrl': '   ',
    });
    expect(noUrl.isConfigured, isFalse);
    expect(noUrl.isNewerThan(1), isFalse);
    expect(noUrl.isRequiredFor(1), isFalse);

    final noBuild = AppRelease.fromJson({
      'version': '1.2.0',
      'build': 0,
      'downloadUrl': 'https://example.test/app.apk',
    });
    expect(noBuild.isConfigured, isFalse);
    expect(noBuild.isNewerThan(0), isFalse);
  });

  test('string numbers are accepted and invalid values become zero', () {
    final release = AppRelease.fromJson({'build': '19', 'minBuild': 'غير رقم'});

    expect(release.build, 19);
    expect(release.minBuild, 0);
    expect(release.version, '');
    expect(release.isConfigured, isFalse);
  });

  test('the version constants match pubspec.yaml', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final match = RegExp(
      r'^version:\s*([0-9]+\.[0-9]+\.[0-9]+)\+([0-9]+)\s*$',
      multiLine: true,
    ).firstMatch(pubspec);

    expect(match, isNotNull, reason: 'pubspec.yaml needs version: x.y.z+N');
    expect(match!.group(1), appVersion);
    expect(int.parse(match.group(2)!), appBuildNumber);
  });
}
