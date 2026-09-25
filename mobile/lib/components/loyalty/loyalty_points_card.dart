import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The hero balance card at the top of the Loyalty screen, built to read as a
/// physical membership card rather than a stat tile: the gold brand mark over
/// the wordmark, a card-number-style member ID, a foil balance, and the bar
/// toward the next tier.
///
/// Deliberately the most ornamented surface in the whole app — a gold hairline
/// edge, a foil-gradient balance and a diagonal sheen, none of which appear
/// anywhere else. Loyalty is the one screen meant to feel like a benefit, not
/// a utility, so it earns treatment the settings-style Account rows don't.
class LoyaltyPointsCard extends StatelessWidget {
  final LoyaltyAccount account;

  const LoyaltyPointsCard({required this.account, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      // Load-bearing: the sheen and glow layers below overflow the card
      // bounds by design, and without the clip they paint over the scaffold.
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
          colors: [AppColors.primary, AppColors.abyssTeal],
        ),
        // A gold hairline, not white — the edge of a foil-trimmed card rather
        // than a generic dark panel.
        border: Border.all(color: AppColors.antiqueGold56, width: 1),
        boxShadow: [
          // A translucent shadow, not the opaque swatch — a solid dark fill
          // here painted as a flat smudge under the card instead of a soft
          // drop shadow, which was most of why the card read as flat.
          BoxShadow(
            color: AppColors.abyssTeal.withValues(alpha: 0.35),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Stack(
        children: [
          const PositionedDirectional(
            top: -50,
            end: -30,
            child: LoyaltyGlowCircle(size: 170),
          ),
          const PositionedDirectional(
            bottom: -70,
            end: 60,
            child: LoyaltyGlowCircle(size: 130),
          ),
          const Positioned.fill(child: LoyaltySheen()),

          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 22,
              children: [
                // The brand mark and tier badge share a row so they read as
                // opposite corners of a card face; the wordmark sits directly
                // beneath the mark, the way it does on the app's own logo
                // lockup (see custom_app_bar's CARLTON / HOTEL pairing).
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 10,
                  children: [
                    Row(
                      children: [
                        SvgPicture.asset(
                          'assets/icons/badge_logo.svg',
                          width: 30,
                          height: 30,
                          colorFilter: const ColorFilter.mode(
                            AppColors.sandGold,
                            BlendMode.srcIn,
                          ),
                        ),
                        const Spacer(),
                        _TierBadge(label: account.tierLabel),
                      ],
                    ),
                    Text(
                      AppTranslations.loyaltyProgramName.toUpperCase(),
                      style: textStyle.labelSmall
                          ?.copyWith(
                            fontFamily: 'DM Sans',
                            fontWeight: FontWeight.w600,
                            color: AppColors.white50,
                          )
                          .tracked(context, 2),
                    ),
                  ],
                ),

                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 6,
                  children: [
                    Text(
                      AppTranslations.loyaltyAvailablePoints.toUpperCase(),
                      style: textStyle.labelSmall
                          ?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.white73,
                          )
                          .tracked(context, 1.6),
                    ),
                    Row(
                      // Baseline rather than centre so the unit sits on the
                      // line the numerals stand on, not halfway up them.
                      crossAxisAlignment: CrossAxisAlignment.baseline,
                      textBaseline: TextBaseline.alphabetic,
                      spacing: 6,
                      children: [
                        // Foil effect: ShaderMask paints the gradient through
                        // the text's own alpha, so it reads as engraved gold
                        // rather than a flat gold fill.
                        ShaderMask(
                          blendMode: BlendMode.srcIn,
                          shaderCallback: (bounds) => const LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              AppColors.sandGold,
                              AppColors.antiqueGold,
                              AppColors.sandGold,
                            ],
                            stops: [0, 0.55, 1],
                          ).createShader(bounds),
                          child: Text(
                            account.balance.formatPoints(),
                            style: textStyle.displaySmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppColors.white,
                            ),
                          ),
                        ),
                        Text(
                          AppTranslations.loyaltyPointsUnit,
                          style: textStyle.titleMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: AppColors.white73,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                // Letter-spaced and grouped like an embossed card number —
                // the member ID is the one figure on the card meant to be
                // read at a glance, the way a card number is.
                Text(
                  account.memberId.toUpperCase(),
                  style: textStyle.titleSmall
                      ?.copyWith(
                        fontFamily: 'DM Sans',
                        fontWeight: FontWeight.w600,
                        color: AppColors.white88,
                      )
                      .tracked(context, 3),
                ),

                // Gold fading to nothing, not a flat white rule — the rule
                // is part of the card's foil trim rather than a divider
                // between two unrelated blocks.
                Container(
                  height: 1,
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [AppColors.antiqueGold56, AppColors.white00],
                    ),
                  ),
                ),

                _TierProgress(account: account),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The top-right tier pill: a small crown mark plus the tier name in gold,
/// on a glass fill. Split out purely so the crown + label pairing can't drift
/// out of sync at the one call site that composes it.
class _TierBadge extends StatelessWidget {
  final String label;

  const _TierBadge({required this.label});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      padding: const EdgeInsetsDirectional.fromSTEB(10, 6, 12, 6),
      decoration: BoxDecoration(
        color: AppColors.white10,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.antiqueGold56, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        spacing: 6,
        children: [
          const Icon(
            Icons.workspace_premium_rounded,
            size: 15,
            color: AppColors.sandGold,
          ),
          Text(
            label,
            style: textStyle.labelSmall?.copyWith(
              fontFamily: 'DM Sans',
              fontWeight: FontWeight.w700,
              color: AppColors.sandGold,
            ),
          ),
        ],
      ),
    );
  }
}

/// The next-tier bar plus its caption. Split out because the top-tier case
/// swaps the whole block's copy, and a ternary that long inside the card tree
/// hides which branch is which.
class _TierProgress extends StatelessWidget {
  final LoyaltyAccount account;

  const _TierProgress({required this.account});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final String? nextTier = account.nextTierLabel;
    final int? remaining = account.pointsToNextTier;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        ClipRRect(
          // ClipRRect rather than the indicator's own borderRadius: that one
          // rounds the fill only, leaving square track corners behind it.
          borderRadius: BorderRadius.circular(6),
          child: LinearProgressIndicator(
            value: account.tierProgress,
            minHeight: 6,
            backgroundColor: AppColors.white25,
            valueColor: const AlwaysStoppedAnimation(AppColors.sandGold),
          ),
        ),
        Text(
          nextTier == null || remaining == null
              ? AppTranslations.loyaltyTopTier
              : AppTranslations.loyaltyToNextTier(
                  points: remaining.formatPoints(),
                  tier: nextTier,
                ),
          style: textStyle.labelSmall
              ?.copyWith(fontFamily: 'DM Sans', color: AppColors.white73)
              .tracked(context, 0.8),
        ),
      ],
    );
  }
}
