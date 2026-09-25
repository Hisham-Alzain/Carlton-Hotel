import 'package:carlton/models/amenity.dart';
import 'package:carlton/models/localized.dart';
import 'package:carlton/models/json_num.dart';
import 'package:carlton/models/media_image.dart';

/// A bookable room type — list (`/public/room-types`) + details
/// (`/public/room-types/{uuid}`). The list carries the card fields; the detail
/// adds the gallery, amenities, rating, and cancellation window.
class RoomType {
  final String uuid;
  final Localized name;
  final Localized description;
  final String basePriceUsd;
  final String? sizeSqm;
  final String? viewType;
  final List<String> bedTypes;
  final List<MediaImage> images;
  final MediaImage? banner;
  final double? rating;
  final int ratingCount;
  final List<Amenity> highlights;
  final List<Amenity> amenities;
  final int? cancellationHours;

  const RoomType({
    required this.uuid,
    required this.name,
    required this.description,
    required this.basePriceUsd,
    this.sizeSqm,
    this.viewType,
    this.bedTypes = const [],
    this.images = const [],
    this.banner,
    this.rating,
    this.ratingCount = 0,
    this.highlights = const [],
    this.amenities = const [],
    this.cancellationHours,
  });

  factory RoomType.fromJson(Map<String, dynamic> json) {
    final images = MediaImage.listFromJson(json['images']);
    return RoomType(
      uuid: json['uuid'] as String? ?? '',
      name: Localized.fromJson(json['name']),
      description: Localized.fromJson(json['description']),
      basePriceUsd: json['base_price_usd']?.toString() ?? '0',
      sizeSqm: json['size_sqm']?.toString(),
      viewType: json['view_type'] as String?,
      bedTypes:
          (json['bed_types'] as List?)?.map((e) => '$e').toList() ?? const [],
      images: images,
      banner: json['banner'] is Map
          ? MediaImage.fromJson(json['banner'] as Map<String, dynamic>)
          : MediaImage.banner(images),
      rating: asDouble(json['rating']),
      ratingCount: asInt(json['rating_count']) ?? 0,
      highlights: Amenity.listFromJson(json['highlights']),
      amenities: Amenity.listFromJson(json['amenities']),
      cancellationHours: asInt(json['cancellation_hours']),
    );
  }
}
