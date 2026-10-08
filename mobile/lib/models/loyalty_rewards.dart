part of 'loyalty.dart';

/// What a reward or voucher is.
enum LoyaltyRewardType {
  discountVoucher,
  freeNight,
  roomUpgrade,
  unknown;

  static LoyaltyRewardType parse(String? value) => switch (value) {
    'discount_voucher' => discountVoucher,
    'free_night' => freeNight,
    'room_upgrade' => roomUpgrade,
    _ => unknown,
  };
}

/// A catalogue reward (`GET /loyalty/rewards`).
class LoyaltyReward {
  final String uuid;
  final Localized name;
  final Localized description;
  final LoyaltyRewardType type;

  /// Localized by the server.
  final String typeLabel;
  final int pointsCost;

  /// Set for a discount voucher only.
  final String? discountUsd;
  final int voucherValidDays;

  const LoyaltyReward({
    required this.uuid,
    required this.name,
    required this.description,
    required this.type,
    required this.typeLabel,
    required this.pointsCost,
    this.discountUsd,
    this.voucherValidDays = 0,
  });

  factory LoyaltyReward.fromJson(Map<String, dynamic> json) => LoyaltyReward(
    uuid: json['uuid'] as String? ?? '',
    name: Localized.fromJson(json['name']),
    description: Localized.fromJson(json['description']),
    type: LoyaltyRewardType.parse(json['type'] as String?),
    typeLabel: json['type_label'] as String? ?? '',
    pointsCost: _int(json['points_cost']),
    discountUsd: json['discount_usd']?.toString(),
    voucherValidDays: _int(json['voucher_valid_days']),
  );
}

/// A voucher the guest holds (`GET /loyalty/vouchers`, redeem response).
class LoyaltyVoucher {
  final String uuid;

  /// `LOY-XXXXXXXX`, entered at booking.
  final String code;
  final LoyaltyRewardType type;
  final String typeLabel;
  final Localized rewardName;

  /// Null for a free night or an upgrade, which are worth a night or a room
  /// rather than a fixed sum.
  final String? valueUsd;
  final int pointsSpent;

  /// `active`, `used`, `expired` or `void`. Anything else is unusable.
  final String status;
  final DateTime? expiresAt;
  final DateTime? usedAt;

  const LoyaltyVoucher({
    required this.uuid,
    required this.code,
    required this.type,
    required this.typeLabel,
    required this.rewardName,
    required this.pointsSpent,
    required this.status,
    this.valueUsd,
    this.expiresAt,
    this.usedAt,
  });

  factory LoyaltyVoucher.fromJson(Map<String, dynamic> json) => LoyaltyVoucher(
    uuid: json['uuid'] as String? ?? '',
    code: json['code'] as String? ?? '',
    type: LoyaltyRewardType.parse(json['type'] as String?),
    typeLabel: json['type_label'] as String? ?? '',
    rewardName: Localized.fromJson(json['reward_name']),
    valueUsd: json['value_usd']?.toString(),
    pointsSpent: _int(json['points_spent']),
    status: json['status'] as String? ?? '',
    expiresAt: _date(json['expires_at']),
    usedAt: _date(json['used_at']),
  );

  /// Expired by status, or by clock. The server only flips `status` in a
  /// nightly sweep, so between `expires_at` and that sweep a voucher still
  /// reads `active` while booking already refuses it.
  bool get isExpired =>
      status == 'expired' ||
      (status == 'active' &&
          expiresAt != null &&
          !expiresAt!.isAfter(DateTime.now()));

  bool get isUsable => status == 'active' && !isExpired;
  bool get isUsed => status == 'used';
}
