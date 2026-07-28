import 'package:carlton/models/json_num.dart';

/// Availability check for a room type over a date range
/// (`GET /public/availability`).
class Availability {
  final bool available;
  final int roomsAvailable;

  const Availability({required this.available, required this.roomsAvailable});

  factory Availability.fromJson(Map<String, dynamic> json) => Availability(
    available: json['available'] as bool? ?? false,
    roomsAvailable: asInt(json['rooms_available']) ?? 0,
  );
}

/// A priced stay quote (`GET /public/quote`): base rate → seasonal/weekend
/// rules → promo. [totalUsd] already reflects [discountUsd]; the API returns no
/// separate tax line, so the total is `subtotal - discount`.
class Quote {
  final double dailyRateUsd;
  final int nights;
  final double subtotalUsd;
  final double discountUsd;
  final double totalUsd;

  /// Non-null only when a promo code was accepted server-side.
  final int? promoCodeId;
  final int rulesApplied;

  const Quote({
    required this.dailyRateUsd,
    required this.nights,
    required this.subtotalUsd,
    required this.discountUsd,
    required this.totalUsd,
    this.promoCodeId,
    this.rulesApplied = 0,
  });

  /// True when a promo was accepted AND actually reduced the price — the only
  /// case the breakdown should render a discount row.
  bool get hasPromo => promoCodeId != null && discountUsd > 0;

  factory Quote.fromJson(Map<String, dynamic> json) => Quote(
    dailyRateUsd: asDouble(json['daily_rate_usd']) ?? 0,
    nights: asInt(json['nights']) ?? 0,
    subtotalUsd: asDouble(json['subtotal_usd']) ?? 0,
    discountUsd: asDouble(json['discount_usd']) ?? 0,
    totalUsd: asDouble(json['total_usd']) ?? 0,
    promoCodeId: asInt(json['promo_code_id']),
    rulesApplied: asInt(json['rules_applied']) ?? 0,
  );
}
