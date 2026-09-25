import 'package:carlton/models/localized.dart';
import 'package:carlton/models/json_num.dart';
import 'package:carlton/models/media_image.dart';

/// A restaurant / dining venue — list (`/public/dining-venues`) + details
/// (`/public/dining-venues/{uuid}`).
class DiningVenue {
  final String uuid;
  final Localized name;
  final Localized description;

  // The live API returns these as bilingual `{en, ar}` objects (not plain
  // strings), so they're parsed as [Localized] — resolve with `.value`.
  final Localized cuisineType;
  final Localized hours;
  final Localized location;
  final double? rating;
  final int ratingCount;
  final List<MediaImage> images;
  final MediaImage? banner;

  const DiningVenue({
    required this.uuid,
    required this.name,
    required this.description,
    this.cuisineType = Localized.empty,
    this.hours = Localized.empty,
    this.location = Localized.empty,
    this.rating,
    this.ratingCount = 0,
    this.images = const [],
    this.banner,
  });

  factory DiningVenue.fromJson(Map<String, dynamic> json) {
    final images = MediaImage.listFromJson(json['images']);
    return DiningVenue(
      uuid: json['uuid'] as String? ?? '',
      name: Localized.fromJson(json['name']),
      description: Localized.fromJson(json['description']),
      cuisineType: Localized.fromJson(json['cuisine_type']),
      hours: Localized.fromJson(json['hours']),
      location: Localized.fromJson(json['location']),
      rating: asDouble(json['rating']),
      ratingCount: asInt(json['rating_count']) ?? 0,
      images: images,
      banner: json['banner'] is Map
          ? MediaImage.fromJson(json['banner'] as Map<String, dynamic>)
          : MediaImage.banner(images),
    );
  }
}
