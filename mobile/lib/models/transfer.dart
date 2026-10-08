import 'package:carlton/models/localized.dart';

/// A bookable airport/city transfer from `GET /public/transfers`.
///
/// Each vehicle has a description line and a passenger capacity (Figma
/// "Airport Transfer", step 2 of 3). The API sends both, but either may be
/// `null` ("not stated"); the card then hides that line rather than inventing
/// one — do NOT hardcode the mock's "Mercedes C-Class or similar".
class Transfer {
  final String uuid;

  /// Server-localized: the resource returns the whole translations map.
  final Localized name;

  /// USD decimal string, e.g. `"45.00"`. Always render through
  /// `MoneyFormat.usdString` so the guest's selected currency applies.
  final String priceUsd;

  /// Vehicle blurb ("BMW 5-Series or similar"), or null when not stated.
  final Localized? description;

  /// Seats, or null when not stated — the capacity line is then hidden rather
  /// than guessed, since booking a car too small is worse than an unlabelled
  /// one.
  final int? maxPassengers;

  const Transfer({
    required this.uuid,
    required this.name,
    required this.priceUsd,
    this.description,
    this.maxPassengers,
  });

  factory Transfer.fromJson(Map<String, dynamic> json) => Transfer(
    uuid: json['uuid'] as String? ?? '',
    name: Localized.fromJson(json['name']),
    priceUsd: json['price_usd']?.toString() ?? '0',
    description: json['description'] == null
        ? null
        : Localized.fromJson(json['description']),
    maxPassengers: (json['max_passengers'] as num?)?.toInt(),
  );

  static List<Transfer> listFromJson(dynamic json) => json is List
      ? json.whereType<Map<String, dynamic>>().map(Transfer.fromJson).toList()
      : const [];
}
