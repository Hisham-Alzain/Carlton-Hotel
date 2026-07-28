import 'package:carlton/models/bilingual.dart';

/// A home hero-slider card (`/public/home-sliders`).
class HomeSlider {
  final String? photo;
  final Bilingual headerText;
  final Bilingual descriptionText;
  final String? location;

  const HomeSlider({
    this.photo,
    required this.headerText,
    required this.descriptionText,
    this.location,
  });

  factory HomeSlider.fromJson(Map<String, dynamic> json) {
    final photo = json['photo'];
    return HomeSlider(
      photo: photo is Map ? photo['url'] as String? : photo as String?,
      headerText: Bilingual.fromJson(json['header_text']),
      descriptionText: Bilingual.fromJson(json['description_text']),
      location: json['location'] as String?,
    );
  }
}
