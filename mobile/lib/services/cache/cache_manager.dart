import 'package:flutter_cache_manager/flutter_cache_manager.dart';

/// Scoped cache manager for CartX images.
/// Max 150MB, entries expire after 7 days.
class MyCacheManager extends CacheManager with ImageCacheManager {
  static const key = 'cartx_image_cache';

  static final MyCacheManager _instance = MyCacheManager._();
  factory MyCacheManager() => _instance;

  MyCacheManager._()
    : super(
        Config(
          key,
          stalePeriod: const Duration(days: 7),
          maxNrOfCacheObjects: 300,
          // ~150MB effective cap depending on image sizes
        ),
      );
}
