import 'dart:developer';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:carlton/services/cache/cache_manager.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:shimmer/shimmer.dart';

class CustomImage extends StatelessWidget {
  final String path;
  final double? height;
  final double? width;
  final BoxFit? fit;
  final Color? iconColor;
  final double? iconSize;

  const CustomImage({
    required this.path,
    this.height,
    this.width,
    this.fit,
    this.iconColor,
    this.iconSize,
    super.key,
  });

  String get url => 'https://api.cartxpress.net/api/file/$path';

  @override
  Widget build(BuildContext context) {
    if (path.endsWith('.svg')) return _buildSvg();
    return _buildRaster(context);
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
    // Note: wrap in a retry widget if SVG load failures are common
  }

  Widget _buildRaster(BuildContext context) {
    final dpr = MediaQuery.devicePixelRatioOf(context);
    return CachedNetworkImage(
      imageUrl: url,
      cacheManager: MyCacheManager(),
      height: height,
      width: width,
      fit: fit ?? BoxFit.cover,
      memCacheWidth: width != null ? (width! * dpr).round() : null,
      memCacheHeight: height != null ? (height! * dpr).round() : null,
      placeholder: (_, _) => _shimmer(),
      errorWidget: (_, url, error) {
        if (!kReleaseMode) log('Image Error [$url]: $error');
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
          borderRadius: BorderRadius.circular(10),
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
