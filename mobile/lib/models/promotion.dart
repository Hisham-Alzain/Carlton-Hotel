import 'package:carlton/models/localized.dart';

/// A published offer from `GET /public/promotions`.
///
/// The resource splits its copy across two description fields on purpose: the
/// offer card shows [description] above the banner and [secondaryDescription]
/// below it. [terms] is the small print, shown only on the detail sheet.
class Promotion {
  final String uuid;
  final Localized title;
  final Localized description;
  final Localized secondaryDescription;
  final Localized terms;

  /// Hero image URL, or null when the CMS published no banner for this offer.
  final String? banner;

  /// Validity window. Either end may be null (an open-ended offer).
  final DateTime? validFrom;
  final DateTime? validUntil;

  const Promotion({
    required this.uuid,
    required this.title,
    this.description = Localized.empty,
    this.secondaryDescription = Localized.empty,
    this.terms = Localized.empty,
    this.banner,
    this.validFrom,
    this.validUntil,
  });

  factory Promotion.fromJson(Map<String, dynamic> json) {
    final banner = json['banner'];
    return Promotion(
      uuid: json['uuid'] as String? ?? '',
      title: Localized.fromJson(json['title']),
      description: Localized.fromJson(json['description']),
      secondaryDescription: Localized.fromJson(json['secondary_description']),
      terms: Localized.fromJson(json['terms']),
      // `banner` is a bare URL string, unlike the `images` collection.
      banner: banner is Map ? banner['url'] as String? : banner as String?,
      validFrom: _date(json['valid_from']),
      validUntil: _date(json['valid_until']),
    );
  }

  static DateTime? _date(dynamic value) =>
      value is String && value.isNotEmpty ? DateTime.tryParse(value) : null;

  static List<Promotion> listFromJson(dynamic json) => json is List
      ? json.whereType<Map<String, dynamic>>().map(Promotion.fromJson).toList()
      : const [];
}
