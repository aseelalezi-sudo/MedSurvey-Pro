String normalizeBaseUrl(String value) {
  var url = value.trim();
  if (url.isEmpty) return '';

  final lower = url.toLowerCase();
  if (!lower.startsWith('http://') && !lower.startsWith('https://')) {
    url = '${_defaultSchemeFor(url)}://$url';
  }

  while (url.endsWith('/')) {
    url = url.substring(0, url.length - 1);
  }

  if (url.endsWith('/api/v1')) {
    url = url.substring(0, url.length - '/api/v1'.length);
  } else if (url.endsWith('/api')) {
    url = url.substring(0, url.length - '/api'.length);
  }

  return url;
}

/// Picks the scheme for an address entered without one.
///
/// A local development server (the phone talking to the XAMPP/Laravel machine
/// over Wi-Fi) almost never has a TLS certificate, so assuming HTTPS makes the
/// app wait for a handshake that never completes and then fail with a timeout.
/// Private/loopback addresses therefore default to plain HTTP, while public
/// domains keep HTTPS.
String _defaultSchemeFor(String url) {
  var host = url.split('/').first;
  if (host.startsWith('[')) return 'http'; // [::1]:8080

  final portIndex = host.lastIndexOf(':');
  if (portIndex > 0 &&
      RegExp(r'^\d+$').hasMatch(host.substring(portIndex + 1))) {
    host = host.substring(0, portIndex);
  }
  host = host.toLowerCase();

  if (host == 'localhost' ||
      host.endsWith('.localhost') ||
      host.endsWith('.local') ||
      host.endsWith('.lan') ||
      host.endsWith('.internal') ||
      host.endsWith('.test')) {
    return 'http';
  }

  final ipv4 = RegExp(
    r'^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$',
  ).firstMatch(host);
  if (ipv4 != null) {
    final first = int.parse(ipv4.group(1)!);
    final second = int.parse(ipv4.group(2)!);
    final private =
        first == 10 ||
        first == 127 ||
        (first == 192 && second == 168) ||
        (first == 172 && second >= 16 && second <= 31) ||
        (first == 169 && second == 254);
    return private ? 'http' : 'https';
  }

  return 'https';
}

/// Turns a media path returned by the API (for example
/// `/storage/settings/logos/global/logo.png`) into an absolute URL that the
/// phone can load. Absolute URLs are returned untouched.
String resolveMediaUrl(String baseUrl, String path) {
  final raw = path.trim();
  if (raw.isEmpty) return '';

  final lower = raw.toLowerCase();
  if (lower.startsWith('http://') || lower.startsWith('https://')) {
    return raw;
  }

  final root = normalizeBaseUrl(baseUrl);
  if (root.isEmpty) return '';

  return raw.startsWith('/') ? '$root$raw' : '$root/$raw';
}

String twoDigits(int value) => value.toString().padLeft(2, '0');

String formatDateTime(DateTime value) {
  final local = value.toLocal();
  return '${local.year}/${twoDigits(local.month)}/${twoDigits(local.day)} '
      '${twoDigits(local.hour)}:${twoDigits(local.minute)}';
}

String formatTime(DateTime value) {
  final local = value.toLocal();
  return '${twoDigits(local.hour)}:${twoDigits(local.minute)}';
}

String formatRelative(DateTime value) {
  final diff = DateTime.now().difference(value);
  if (diff.inMinutes < 1) return 'الآن';
  if (diff.inMinutes < 60) return 'قبل ${diff.inMinutes} دقيقة';
  if (diff.inHours < 24) return 'قبل ${diff.inHours} ساعة';
  if (diff.inDays < 7) return 'قبل ${diff.inDays} يوم';
  return formatDateTime(value);
}

String digitsOnly(String value) => value.replaceAll(RegExp(r'[^0-9]'), '');

bool isValidPhone(String value) {
  final digits = digitsOnly(value);
  return digits.length == 9 && digits.startsWith('7');
}
