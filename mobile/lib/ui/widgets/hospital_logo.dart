import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../core/brand_logo_cache.dart';
import '../../core/theme.dart';
import '../../core/utils.dart';

/// Hospital logo loaded from the API, with the logo bundled in the app as the
/// instant / offline fallback. The bundled asset is the same logo used on the
/// website, so the brand is identical before the first login.
class HospitalLogo extends StatefulWidget {
  const HospitalLogo({
    super.key,
    required this.baseUrl,
    required this.path,
    this.size = 56,
    this.radius = 18,
    this.padding = 7,
    this.background = Colors.white,
    this.fallbackText = '',
    this.showShadow = false,
    this.border,
  });

  final String baseUrl;
  final String path;
  final double size;
  final double radius;
  final double padding;
  final Color background;
  final String fallbackText;
  final bool showShadow;
  final BoxBorder? border;

  @override
  State<HospitalLogo> createState() => _HospitalLogoState();
}

class _HospitalLogoState extends State<HospitalLogo> {
  Future<Uint8List?>? _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void didUpdateWidget(covariant HospitalLogo oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.baseUrl != widget.baseUrl || oldWidget.path != widget.path) {
      _future = _load();
    }
  }

  Future<Uint8List?>? _load() {
    if (resolveMediaUrl(widget.baseUrl, widget.path).isEmpty) return null;
    return BrandLogoCache.instance.load(widget.baseUrl, widget.path);
  }

  int? _decodeWidth(BuildContext context) {
    final ratio = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final width = (widget.size * ratio).round();
    return width <= 0 ? null : width;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: widget.size,
      height: widget.size,
      decoration: BoxDecoration(
        color: widget.background,
        borderRadius: BorderRadius.circular(widget.radius),
        border: widget.border,
        boxShadow: widget.showShadow ? appSoftShadow : null,
      ),
      clipBehavior: Clip.antiAlias,
      child: _future == null
          ? _bundledLogo(context)
          : FutureBuilder<Uint8List?>(
              future: _future,
              builder: (context, snapshot) {
                final bytes = snapshot.data;
                if (bytes == null || bytes.isEmpty) {
                  return _bundledLogo(context);
                }

                return Padding(
                  padding: EdgeInsets.all(widget.padding),
                  child: Image.memory(
                    bytes,
                    fit: BoxFit.contain,
                    filterQuality: FilterQuality.medium,
                    cacheWidth: _decodeWidth(context),
                    gaplessPlayback: true,
                  ),
                );
              },
            ),
    );
  }

  /// The website logo shipped inside the APK. Works before login and offline.
  Widget _bundledLogo(BuildContext context) {
    return Padding(
      padding: EdgeInsets.all(widget.padding),
      child: Image.asset(
        'assets/images/brand_logo.png',
        fit: BoxFit.contain,
        filterQuality: FilterQuality.medium,
        cacheWidth: _decodeWidth(context),
        errorBuilder: (context, error, stackTrace) => _fallback(),
      ),
    );
  }

  Widget _fallback() {
    final label = widget.fallbackText.trim();
    return Container(
      decoration: const BoxDecoration(gradient: appHeroGradient),
      alignment: Alignment.center,
      child: label.isNotEmpty
          ? Text(
              String.fromCharCode(label.runes.first),
              style: TextStyle(
                color: Colors.white,
                fontSize: widget.size * 0.4,
                fontWeight: FontWeight.w800,
                height: 1,
              ),
            )
          : Icon(
              Icons.local_hospital_rounded,
              color: Colors.white,
              size: widget.size * 0.44,
            ),
    );
  }
}
