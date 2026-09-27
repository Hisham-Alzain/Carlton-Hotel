import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:get/get.dart';

/// Holds the rewards standing the Loyalty screen renders.
///
/// **The only screen in the app not backed by the API.** The backend exposes no
/// loyalty routes at all — no points, tier or ledger tables, nothing under
/// `routes/api.php` — so there is nothing to call. The figures below are
/// hardcoded and shaped like the eventual payload, so wiring it up later
/// replaces these two getters and nothing in the view.
///
/// Until those endpoints exist this screen shows invented numbers to the guest.
/// That is a deliberate, known gap, recorded here rather than left to be
/// discovered.
class LoyaltyController extends GetxController {
  LoyaltyAccount get account => const LoyaltyAccount(
    memberId: 'CH-48219',
    tierLabel: 'Gold Member',
    nextTierLabel: 'Platinum',
    balance: 2450,
    earnedTotal: 3150,
    redeemedTotal: 700,
    staysCount: 6,
    pointsToNextTier: 850,
  );

  /// Newest first — the ledger reads top-down like a statement.
  List<LoyaltyTransaction> get transactions => [
    LoyaltyTransaction(
      title: 'Deluxe Sea View · 3 nights',
      bookingRef: 'RES-48219',
      date: DateTime(2026, 9, 12),
      points: 620,
      kind: LoyaltyEntryKind.earned,
      source: LoyaltyEntrySource.stay,
    ),
    LoyaltyTransaction(
      title: 'Spa credit redeemed',
      bookingRef: 'RES-48219',
      date: DateTime(2026, 9, 14),
      points: 400,
      kind: LoyaltyEntryKind.redeemed,
      source: LoyaltyEntrySource.spa,
    ),
    LoyaltyTransaction(
      title: 'Dining at Azure Restaurant',
      bookingRef: 'RES-47660',
      date: DateTime(2026, 8, 3),
      points: 180,
      kind: LoyaltyEntryKind.earned,
      source: LoyaltyEntrySource.dining,
    ),
    LoyaltyTransaction(
      title: 'Executive Suite · 2 nights',
      bookingRef: 'RES-47660',
      date: DateTime(2026, 8, 1),
      points: 540,
      kind: LoyaltyEntryKind.earned,
      source: LoyaltyEntrySource.stay,
    ),
  ];

  /// Redemption needs the folio and the rate table, neither of which is wired
  /// yet — same coming-soon fallback the Account rows use.
  void redeemPoints() =>
      CustomSnackbars.showInfo(message: AppTranslations.loyaltyRedeemSoon);
}
