/// Which way a points entry moved the balance.
///
/// Kept as an enum rather than a signed int so the UI can style the sign, the
/// icon and the copy from one switch — a bare negative number would leave each
/// of those three deriving the same fact independently.
enum LoyaltyEntryKind { earned, redeemed, expired }

/// What the entry was for, which is what the ledger row draws its glyph from.
///
/// Separate from [LoyaltyEntryKind] because the two answer different
/// questions: kind decides the sign and the colour, source decides the
/// picture. Collapsing them gave every earned row the same `+` circle, which
/// told the guest nothing a signed amount wasn't already saying.
enum LoyaltyEntrySource { stay, dining, spa, other }

/// One line of the points ledger.
///
/// [bookingRef] is what ties a point movement back to the stay that produced
/// it — the ledger is meaningless without it, which is why it is required
/// rather than optional even for redemptions.
class LoyaltyTransaction {
  final String title;
  final String bookingRef;
  final DateTime date;
  final int points;
  final LoyaltyEntryKind kind;
  final LoyaltyEntrySource source;

  const LoyaltyTransaction({
    required this.title,
    required this.bookingRef,
    required this.date,
    required this.points,
    required this.kind,
    this.source = LoyaltyEntrySource.other,
  });
}

/// The guest's rewards standing, as the Loyalty screen needs it.
///
/// [tierLabel] and [nextTierLabel] arrive as ready strings rather than an enum
/// because the tier ladder is hotel configuration, not app logic — a new tier
/// on the backend must not require a Dart enum case.
class LoyaltyAccount {
  final String memberId;
  final String tierLabel;
  final String? nextTierLabel;
  final int balance;
  final int earnedTotal;
  final int redeemedTotal;
  final int staysCount;

  /// Points still needed to reach [nextTierLabel]. Null on the top tier, which
  /// is also the only state where [nextTierLabel] is null.
  final int? pointsToNextTier;

  const LoyaltyAccount({
    required this.memberId,
    required this.tierLabel,
    required this.balance,
    required this.earnedTotal,
    required this.redeemedTotal,
    required this.staysCount,
    this.nextTierLabel,
    this.pointsToNextTier,
  });

  int get netChange => earnedTotal - redeemedTotal;

  /// 0..1 progress toward [nextTierLabel]. The top tier reads as full rather
  /// than empty — an unreachable bar at 0% looks like a loading failure.
  double get tierProgress {
    final remaining = pointsToNextTier;
    if (remaining == null || remaining <= 0) return 1;
    return earnedTotal / (earnedTotal + remaining);
  }
}
