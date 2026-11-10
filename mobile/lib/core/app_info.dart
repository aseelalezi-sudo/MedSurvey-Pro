/// Version shown in the app footer and the Account tab. Keep it in step with
/// `version:` in `pubspec.yaml`; a single constant avoids the two screens
/// drifting apart (they used to lag behind the published APK).
const String appVersion = '1.1.16';

/// Android versionCode of this build. The server's `/api/v1/app/version`
/// response is compared against this number, so it must be raised together
/// with the `+N` suffix in `pubspec.yaml`.
const int appBuildNumber = 18;
