import 'package:carlton/models/localized.dart';

/// A dining-menu filter chip (`/dining-venues/{uuid}/menu-categories`).
class MenuCategory {
  final String uuid;
  final String slug;
  final Localized name;
  final int sortOrder;

  const MenuCategory({
    required this.uuid,
    required this.slug,
    required this.name,
    required this.sortOrder,
  });

  factory MenuCategory.fromJson(Map<String, dynamic> json) => MenuCategory(
    uuid: json['uuid'] as String? ?? '',
    slug: json['slug'] as String? ?? '',
    name: Localized.fromJson(json['name']),
    sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
  );
}

/// A dish (`/dining-venues/{uuid}/menu`). [type] is its category slug.
class MenuItem {
  final String uuid;
  final String type;
  final Localized name;
  final Localized description;
  final String? priceUsd;
  final bool isVegan;
  final String? photo;

  const MenuItem({
    required this.uuid,
    required this.type,
    required this.name,
    required this.description,
    this.priceUsd,
    this.isVegan = false,
    this.photo,
  });

  factory MenuItem.fromJson(Map<String, dynamic> json) => MenuItem(
    uuid: json['uuid'] as String? ?? '',
    type: json['type'] as String? ?? '',
    name: Localized.fromJson(json['name']),
    description: Localized.fromJson(json['description']),
    priceUsd: json['price_usd']?.toString(),
    isVegan: json['is_vegan'] as bool? ?? false,
    photo: json['photo'] as String?,
  );
}
