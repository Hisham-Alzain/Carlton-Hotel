import 'package:carlton/models/bilingual.dart';
import 'package:carlton/models/json_num.dart';
import 'package:carlton/models/media_image.dart';

/// A restaurant / dining venue — list (`/public/dining-venues`) + details
/// (`/public/dining-venues/{uuid}`).
class DiningVenue {
  final String uuid;
  final Bilingual name;
  final Bilingual description;

  // The live API returns these as bilingual `{en, ar}` objects (not plain
  // strings), so they're parsed as [Bilingual] — resolve with `.value`.
  final Bilingual cuisineType;
  final Bilingual hours;
  final Bilingual location;
  final double? rating;
  final int ratingCount;
  final List<MediaImage> images;
  final MediaImage? banner;

  const DiningVenue({
    required this.uuid,
    required this.name,
    required this.description,
    this.cuisineType = const Bilingual(en: '', ar: ''),
    this.hours = const Bilingual(en: '', ar: ''),
    this.location = const Bilingual(en: '', ar: ''),
    this.rating,
    this.ratingCount = 0,
    this.images = const [],
    this.banner,
  });

  factory DiningVenue.fromJson(Map<String, dynamic> json) {
    final images = MediaImage.listFromJson(json['images']);
    return DiningVenue(
      uuid: json['uuid'] as String? ?? '',
      name: Bilingual.fromJson(json['name']),
      description: Bilingual.fromJson(json['description']),
      cuisineType: Bilingual.fromJson(json['cuisine_type']),
      hours: Bilingual.fromJson(json['hours']),
      location: Bilingual.fromJson(json['location']),
      rating: asDouble(json['rating']),
      ratingCount: asInt(json['rating_count']) ?? 0,
      images: images,
      banner: json['banner'] is Map
          ? MediaImage.fromJson(json['banner'] as Map<String, dynamic>)
          : MediaImage.banner(images),
    );
  }
}
