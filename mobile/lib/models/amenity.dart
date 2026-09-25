import 'package:carlton/models/localized.dart';

/// A room-type amenity. [icon] is a stable key (`balcony`, `jacuzzi`, `desk`,
/// `tv`, `safe`, `coffee`, …) mapped to the app's own icon set — never a URL.
class Amenity {
  final String uuid;
  final String slug;
  final Localized name;
  final String icon;
  final int sortOrder;

  const Amenity({
    required this.uuid,
    required this.slug,
    required this.name,
    required this.icon,
    required this.sortOrder,
  });

  factory Amenity.fromJson(Map<String, dynamic> json) => Amenity(
    uuid: json['uuid'] as String? ?? '',
    slug: json['slug'] as String? ?? '',
    name: Localized.fromJson(json['name']),
    icon: json['icon'] as String? ?? '',
    sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
  );

  static List<Amenity> listFromJson(dynamic json) => json is List
      ? json.whereType<Map<String, dynamic>>().map(Amenity.fromJson).toList()
      : const [];
}
