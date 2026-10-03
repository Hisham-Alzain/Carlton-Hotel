import 'package:carlton/models/localized.dart';

/// A concierge-arranged experience (`/public/experiences`).
class Experience {
  final String uuid;
  final String slug;
  final Localized title;
  final Localized description;

  /// Plain, not localized — the wire sends this bare (see `ExperienceResource`).
  final String category;

  /// Prose the site prints verbatim ("2–6 guests", "Half day") — see the
  /// backend migration for why neither is a number.
  final Localized groupSize;
  final Localized durationLabel;

  final double priceUsd;
  final String? image;

  const Experience({
    required this.uuid,
    required this.slug,
    required this.title,
    required this.description,
    required this.category,
    this.groupSize = Localized.empty,
    this.durationLabel = Localized.empty,
    this.priceUsd = 0,
    this.image,
  });

  factory Experience.fromJson(Map<String, dynamic> json) {
    return Experience(
      uuid: json['uuid'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      title: Localized.fromJson(json['title']),
      description: Localized.fromJson(json['description']),
      category: json['category'] as String? ?? '',
      groupSize: Localized.fromJson(json['group_size']),
      durationLabel: Localized.fromJson(json['duration_label']),
      priceUsd: double.tryParse('${json['price_usd'] ?? 0}') ?? 0,
      image: json['image'] as String?,
    );
  }

  static List<Experience> listFromJson(dynamic json) {
    if (json is! List) return const [];
    return json
        .whereType<Map<String, dynamic>>()
        .map(Experience.fromJson)
        .toList();
  }
}
