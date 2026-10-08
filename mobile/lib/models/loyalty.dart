import 'package:carlton/models/localized.dart';

part 'loyalty_booking.dart';

part 'loyalty_rewards.dart';

part 'loyalty_ledger.dart';

/// Which parts of the points programme the hotel has switched on, from
/// `GET /loyalty/account → program`. The three are independent: a hotel can
/// sell rewards before it has set an earn rate, so each feature hides on its
/// own switch and never assumes the others.
class LoyaltyProgram {
  /// The hotel set an earn rate, so settled stays credit points.
  final bool earning;

  /// The hotel set a point value and a booking cap, so part of a booking can be
  /// paid with points.
  final bool pointsDiscount;

  /// The rewards catalogue. The API says `true` always.
  final bool rewards;

  const LoyaltyProgram({
    this.earning = false,
    this.pointsDiscount = false,
    this.rewards = true,
  });

  factory LoyaltyProgram.fromJson(dynamic json) {
    if (json is! Map) return const LoyaltyProgram();
    return LoyaltyProgram(
      earning: json['earning'] == true,
      pointsDiscount: json['points_discount'] == true,
      rewards: json['rewards'] != false,
    );
  }
}

/// The guest's points standing (`GET /loyalty/account`).
///
/// There are no tiers, member id or stay count: the programme is a points
/// balance, a ledger, rewards and vouchers, and nothing else is on the wire.
class LoyaltyAccount {
  /// Spendable now — batches whose expiry is still in the future.
  final int availablePoints;

  /// Points that lapse within [expiringSoonWindowDays].
  final int expiringSoonPoints;
  final int expiringSoonWindowDays;

  /// When the next batch lapses, or null with no balance.
  final DateTime? nextExpiryAt;

  /// Earned plus positive adjustments minus clawbacks. Expiry is in neither
  /// lifetime figure.
  final int lifetimeEarnedPoints;
  final int lifetimeRedeemedPoints;
  final LoyaltyProgram program;

  /// USD one point is worth, or null while the hotel has not set a value.
  final double? redeemValueUsd;

  /// The smallest free-form points payment on a booking.
  final int minRedeemPoints;

  /// The largest share of a booking payable in points (`50` = 50%), or null
  /// when paying with points is off. Never read null as 100%.
  final double? maxRedeemPercent;

  const LoyaltyAccount({
    this.availablePoints = 0,
    this.expiringSoonPoints = 0,
    this.expiringSoonWindowDays = 30,
    this.nextExpiryAt,
    this.lifetimeEarnedPoints = 0,
    this.lifetimeRedeemedPoints = 0,
    this.program = const LoyaltyProgram(),
    this.redeemValueUsd,
    this.minRedeemPoints = 1,
    this.maxRedeemPercent,
  });

  factory LoyaltyAccount.fromJson(Map<String, dynamic> json) => LoyaltyAccount(
    availablePoints: _int(json['available_points']),
    expiringSoonPoints: _int(json['expiring_soon_points']),
    expiringSoonWindowDays: _int(json['expiring_soon_window_days'], 30),
    nextExpiryAt: _date(json['next_expiry_at']),
    lifetimeEarnedPoints: _int(json['lifetime_earned_points']),
    lifetimeRedeemedPoints: _int(json['lifetime_redeemed_points']),
    program: LoyaltyProgram.fromJson(json['program']),
    redeemValueUsd: double.tryParse('${json['redeem_value_usd'] ?? ''}'),
    minRedeemPoints: _int(json['min_redeem_points'], 1),
    maxRedeemPercent: double.tryParse('${json['max_redeem_percent'] ?? ''}'),
  );

  bool get hasExpiringPoints => expiringSoonPoints > 0 && nextExpiryAt != null;

  /// What the balance is worth in USD, or null when points have no set value.
  double? get balanceValueUsd =>
      redeemValueUsd == null ? null : availablePoints * redeemValueUsd!;
}

int _int(dynamic value, [int fallback = 0]) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

DateTime? _date(dynamic value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;
