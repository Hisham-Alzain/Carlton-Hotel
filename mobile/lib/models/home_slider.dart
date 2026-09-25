import 'package:carlton/models/localized.dart';

/// A home hero-slider card (`/public/home-sliders`).
class HomeSlider {
  final String? photo;
  final Localized headerText;
  final Localized descriptionText;
  final Localized location;

  const HomeSlider({
    this.photo,
    required this.headerText,
    required this.descriptionText,
    this.location = Localized.empty,
  });

  factory HomeSlider.fromJson(Map<String, dynamic> json) {
    final photo = json['photo'];
    return HomeSlider(
      photo: photo is Map ? photo['url'] as String? : photo as String?,
      headerText: Localized.fromJson(json['header_text']),
      descriptionText: Localized.fromJson(json['description_text']),
      // Wire sends a locale map ({en, ar}), not a plain string.
      location: Localized.fromJson(json['location']),
    );
  }

  static List<HomeSlider> listFromJson(dynamic json) {
    if (json is! List) return const [];
    return json
        .whereType<Map<String, dynamic>>()
        .map(HomeSlider.fromJson)
        .toList();
  }
}
