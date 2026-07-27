import 'dart:developer';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/cache/cache_manager.dart';
import 'package:shimmer/shimmer.dart';

//TODO: do not use local assets here use fluttet=r default image widget
class CustomImage extends StatelessWidget {
  /// Either a bundled asset path (`assets/…`), an absolute URL (`http…`), or a
  /// storage-relative path that [url] expands against the API storage host.
  final String source;
  final double? height;
  final double? width;
  final BoxFit? fit;
  final Color? iconColor;
  final double? iconSize;

  const CustomImage({
    required this.source,
    this.height,
    this.width,
    this.fit,
    this.iconColor,
    this.iconSize,
    super.key,
  });

  String get url => source.startsWith('http')
      ? source
      : '${ApiService.storageBaseUrl}$source';

  bool get _isAsset => source.startsWith('assets/');

  // A cache dimension is only meaningful for a finite, positive size. Callers may
  // pass width/height as double.infinity ("fill available"), which must not reach
  // .round() — Infinity.toInt() throws UnsupportedError.
  static int? _cacheDim(double? logicalSize, double devicePixelRatio) {
    if (logicalSize == null || !logicalSize.isFinite || logicalSize <= 0) {
      return null;
    }
    return (logicalSize * devicePixelRatio).round();
  }

  @override
  Widget build(BuildContext context) {
    if (_isAsset) return _buildAsset(context);
    if (source.endsWith('.svg')) return _buildSvg();
    return _buildRaster(context);
  }

  Widget _buildAsset(BuildContext context) {
    if (source.endsWith('.svg')) {
      return SvgPicture.asset(
        source,
        height: height,
        width: width,
        fit: fit ?? BoxFit.cover,
        colorFilter: iconColor != null
            ? ColorFilter.mode(iconColor!, BlendMode.srcIn)
            : null,
      );
    }
    // Decode-size hint: the bundled Figma exports are up to 4x resolution
    // (2MB+ heroes) — without a cacheWidth they decode at native pixel size.
    // Cap at the displayed width (or the screen width for full-bleed images)
    // times the device pixel ratio: identical on screen, a fraction of the RAM.
    final devicePixelRatio = MediaQuery.maybeDevicePixelRatioOf(context) ?? 1.0;
    final targetWidth = width ?? MediaQuery.maybeSizeOf(context)?.width;
    return Image.asset(
      source,
      height: height,
      width: width,
      fit: fit,
      cacheWidth: _cacheDim(targetWidth, devicePixelRatio),
    );
  }

  Widget _buildSvg() {
    return SvgPicture.network(
      url,
      height: height,
      width: width,
      fit: fit ?? BoxFit.cover,
      colorFilter: iconColor != null
          ? ColorFilter.mode(iconColor!, BlendMode.srcIn)
          : null,
      placeholderBuilder: (_) => _shimmer(),
    );
  }

  Widget _buildRaster(BuildContext context) {
    final devicePixelRatio = MediaQuery.maybeDevicePixelRatioOf(context) ?? 1.0;
    return CachedNetworkImage(
      imageUrl: url,
      cacheManager: MyCacheManager(),
      height: height,
      width: width,
      fit: fit ?? BoxFit.cover,
      memCacheWidth: _cacheDim(width, devicePixelRatio),
      memCacheHeight: _cacheDim(height, devicePixelRatio),
      placeholder: (_, _) => _shimmer(),
      errorWidget: (_, failedUrl, error) {
        if (!kReleaseMode) log('Image Error [$failedUrl]: $error');
        return _error();
      },
    );
  }

  Widget _shimmer() {
    return Shimmer.fromColors(
      baseColor: Colors.grey,
      highlightColor: Colors.grey.shade100,
      child: Container(
        height: height,
        width: width,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
        ),
      ),
    );
  }

  Widget _error() {
    return SizedBox(
      height: height,
      width: width,
      child: Icon(
        Icons.image_not_supported_outlined,
        size: iconSize ?? 24,
        color: iconColor ?? Colors.grey.shade400,
      ),
    );
  }
}
