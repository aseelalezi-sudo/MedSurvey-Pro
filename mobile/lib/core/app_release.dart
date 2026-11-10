/// The newest Android build the hospital has published.
///
/// The server exposes this at `GET /api/v1/app/version`; the app compares
/// [build] with its own build number. A release without a download URL is
/// treated as "not configured", so deployments that hand out APKs directly
/// keep working without any notice.
class AppRelease {
  const AppRelease({
    required this.version,
    required this.build,
    required this.downloadUrl,
    this.notes = '',
    this.minBuild = 0,
  });

  final String version;
  final int build;
  final String downloadUrl;
  final String notes;

  /// Builds older than this must update; the banner cannot be dismissed.
  final int minBuild;

  bool get isConfigured => build > 0 && downloadUrl.trim().isNotEmpty;

  bool isNewerThan(int currentBuild) => isConfigured && build > currentBuild;

  bool isRequiredFor(int currentBuild) =>
      isNewerThan(currentBuild) && minBuild > currentBuild;

  factory AppRelease.fromJson(Map<String, dynamic> json) {
    return AppRelease(
      version: '${json['version'] ?? ''}'.trim(),
      build: _asInt(json['build']),
      downloadUrl: '${json['downloadUrl'] ?? ''}'.trim(),
      notes: '${json['notes'] ?? ''}'.trim(),
      minBuild: _asInt(json['minBuild']),
    );
  }

  static int _asInt(Object? value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}
