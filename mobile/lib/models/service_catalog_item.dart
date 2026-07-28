import 'package:carlton/models/bilingual.dart';

/// A microservice option inside a `catalog` service category.
class ServiceCatalogOption {
  final String uuid;
  final Bilingual name;
  final Bilingual description;
  final int? expectedMinutes;
  final String? priceUsd;

  const ServiceCatalogOption({
    required this.uuid,
    required this.name,
    required this.description,
    this.expectedMinutes,
    this.priceUsd,
  });

  factory ServiceCatalogOption.fromJson(Map<String, dynamic> json) =>
      ServiceCatalogOption(
        uuid: json['uuid'] as String? ?? '',
        name: Bilingual.fromJson(json['name']),
        description: Bilingual.fromJson(json['description']),
        expectedMinutes: (json['expected_minutes'] as num?)?.toInt(),
        priceUsd: json['price_usd']?.toString(),
      );
}

/// One of the eight Services categories (`/public/service-catalog`). The screen
/// switches on [kind]: `catalog` (show [items]), `direct` (notes sheet →
/// [defaultItemUuid]), `link` (navigate via [linkTarget]), `toggle` (DND).
/// Treat an unknown [kind] as "hide".
class ServiceCatalogItem {
  final String uuid;
  final String code;
  final String kind;
  final Bilingual name;
  final Bilingual description;
  final String icon;
  final String? linkTarget;
  final String? defaultItemUuid;
  final String? department;
  final int sortOrder;
  final bool isActive;
  final List<ServiceCatalogOption> items;

  const ServiceCatalogItem({
    required this.uuid,
    required this.code,
    required this.kind,
    required this.name,
    required this.description,
    required this.icon,
    this.linkTarget,
    this.defaultItemUuid,
    this.department,
    this.sortOrder = 0,
    this.isActive = true,
    this.items = const [],
  });

  factory ServiceCatalogItem.fromJson(Map<String, dynamic> json) =>
      ServiceCatalogItem(
        uuid: json['uuid'] as String? ?? '',
        code: json['code'] as String? ?? '',
        kind: json['kind'] as String? ?? '',
        name: Bilingual.fromJson(json['name']),
        description: Bilingual.fromJson(json['description']),
        icon: json['icon'] as String? ?? '',
        linkTarget: json['link_target'] as String?,
        defaultItemUuid: json['default_item_uuid'] as String?,
        department: json['department'] as String?,
        sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
        isActive: json['is_active'] as bool? ?? true,
        items:
            (json['items'] as List?)
                ?.whereType<Map<String, dynamic>>()
                .map(ServiceCatalogOption.fromJson)
                .toList() ??
            const [],
      );
}
