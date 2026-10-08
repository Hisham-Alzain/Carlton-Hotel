part of 'loyalty.dart';

/// A booking priced with points or a voucher (`GET /loyalty/preview`). Its
/// [netTotalUsd] is exactly the total `POST /reservations` will carry.
class LoyaltyPreview {
  final String totalUsd;
  final int pointsRedeemed;
  final String pointsDiscountUsd;
  final String? voucherCode;
  final String voucherDiscountUsd;

  /// A room-upgrade voucher: the total is unchanged and the desk upgrades.
  final bool upgradeRequested;
  final String netTotalUsd;
  final int availablePoints;

  /// The most points usable on this booking — the smaller of the balance and
  /// the cap. Null when paying with points is off.
  final int? maxPoints;

  /// Points the stay would earn, or null when earning is off.
  final int? pointsEarnableEstimate;
  final LoyaltyProgram program;

  const LoyaltyPreview({
    required this.totalUsd,
    required this.netTotalUsd,
    this.pointsRedeemed = 0,
    this.pointsDiscountUsd = '0.00',
    this.voucherCode,
    this.voucherDiscountUsd = '0.00',
    this.upgradeRequested = false,
    this.availablePoints = 0,
    this.maxPoints,
    this.pointsEarnableEstimate,
    this.program = const LoyaltyProgram(),
  });

  factory LoyaltyPreview.fromJson(Map<String, dynamic> json) {
    final quote = json['quote'] is Map ? json['quote'] as Map : const {};
    final loyalty = json['loyalty'] is Map ? json['loyalty'] as Map : const {};
    final voucher = loyalty['voucher'];
    return LoyaltyPreview(
      totalUsd: '${quote['total_usd'] ?? '0.00'}',
      netTotalUsd: '${json['net_total_usd'] ?? '0.00'}',
      pointsRedeemed: _int(loyalty['points_redeemed']),
      pointsDiscountUsd: '${loyalty['points_discount_usd'] ?? '0.00'}',
      voucherCode: voucher is Map ? voucher['code'] as String? : null,
      voucherDiscountUsd: '${loyalty['voucher_discount_usd'] ?? '0.00'}',
      upgradeRequested: loyalty['upgrade_requested'] == true,
      availablePoints: _int(json['available_points']),
      maxPoints: json['max_points'] == null ? null : _int(json['max_points']),
      pointsEarnableEstimate: json['points_earnable_estimate'] == null
          ? null
          : _int(json['points_earnable_estimate']),
      program: LoyaltyProgram.fromJson(json['program']),
    );
  }

  bool get hasPointsDiscount => pointsRedeemed > 0;
  bool get hasVoucher => voucherCode != null;
}

/// The `loyalty` block on every reservation read (`GET /reservations[/{uuid}]`,
/// `POST /reservations`): what the booking was paid with. Null when the booking
/// used neither points nor a voucher.
class ReservationLoyalty {
  final int pointsRedeemed;
  final String pointsDiscountUsd;
  final String? voucherCode;
  final String? voucherType;
  final String voucherDiscountUsd;
  final bool upgradeRequested;

  /// `applied`, or `reversed` once a cancel refunded the points / restored
  /// the voucher.
  final String status;

  const ReservationLoyalty({
    this.pointsRedeemed = 0,
    this.pointsDiscountUsd = '0.00',
    this.voucherCode,
    this.voucherType,
    this.voucherDiscountUsd = '0.00',
    this.upgradeRequested = false,
    this.status = 'applied',
  });

  bool get isReversed => status == 'reversed';
  bool get hasPoints => pointsRedeemed > 0;
  bool get hasVoucher => voucherCode != null;

  /// A missing key and `null` both mean "no rewards used".
  static ReservationLoyalty? fromJson(dynamic json) {
    if (json is! Map) return null;
    final voucher = json['voucher'];
    final loyalty = ReservationLoyalty(
      pointsRedeemed: _int(json['points_redeemed']),
      pointsDiscountUsd: '${json['points_discount_usd'] ?? '0.00'}',
      voucherCode: voucher is Map ? voucher['code'] as String? : null,
      voucherType: voucher is Map ? voucher['type'] as String? : null,
      voucherDiscountUsd: '${json['voucher_discount_usd'] ?? '0.00'}',
      upgradeRequested: json['upgrade_requested'] == true,
      status: json['status'] as String? ?? 'applied',
    );
    return loyalty.hasPoints || loyalty.hasVoucher || loyalty.upgradeRequested
        ? loyalty
        : null;
  }
}
