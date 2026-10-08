part of 'loyalty.dart';

/// What a ledger row records. Kept as an enum so the sign, glyph and colour
/// come from one switch; unknown future values fall to [unknown] and render as
/// a plain movement rather than failing.
enum LoyaltyEntryType {
  earn,
  redeem,
  expire,
  adjust,
  clawback,
  refund,
  unknown;

  static LoyaltyEntryType parse(String? value) => LoyaltyEntryType.values
      .firstWhere((t) => t.name == value, orElse: () => unknown);
}

/// What produced the movement; absent on redeem, expire and clawback rows.
enum LoyaltyEntrySource {
  stay,
  service,
  manual,
  refund,
  none;

  static LoyaltyEntrySource parse(String? value) => LoyaltyEntrySource.values
      .firstWhere((s) => s.name == value, orElse: () => none);
}

/// One line of the points ledger (`GET /loyalty/ledger`).
class LoyaltyLedgerEntry {
  final String uuid;
  final LoyaltyEntryType type;

  /// Localized by the server (`Accept-Language`) — display only, never branch.
  final String label;
  final LoyaltyEntrySource source;
  final String? sourceLabel;

  /// Signed: credits positive, debits negative.
  final int points;

  /// Non-zero only on a clawback whose earned points were already spent.
  final int shortfallPoints;
  final DateTime? occurredAt;

  /// The expiry of the batch this row created, when it created one.
  final DateTime? expiresAt;

  /// `CARL-…` of the stay or booking, null on manual adjustments and
  /// catalogue redemptions.
  final String? bookingCode;

  const LoyaltyLedgerEntry({
    required this.uuid,
    required this.type,
    required this.label,
    required this.points,
    this.source = LoyaltyEntrySource.none,
    this.sourceLabel,
    this.shortfallPoints = 0,
    this.occurredAt,
    this.expiresAt,
    this.bookingCode,
  });

  factory LoyaltyLedgerEntry.fromJson(Map<String, dynamic> json) {
    final reservation = json['reservation'];
    return LoyaltyLedgerEntry(
      uuid: json['uuid'] as String? ?? '',
      type: LoyaltyEntryType.parse(json['type'] as String?),
      label: json['label'] as String? ?? '',
      source: LoyaltyEntrySource.parse(json['source'] as String?),
      sourceLabel: json['source_label'] as String?,
      points: _int(json['points']),
      shortfallPoints: _int(json['shortfall_points']),
      occurredAt: _date(json['occurred_at']),
      expiresAt: _date(json['expires_at']),
      bookingCode: reservation is Map
          ? reservation['booking_code'] as String?
          : null,
    );
  }

  /// Whether the balance went up. Read from the sign: an `adjust` row can go
  /// either way, so the type alone cannot say.
  bool get isCredit => points > 0;
}
