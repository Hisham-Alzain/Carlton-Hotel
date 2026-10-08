import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// The hero balance card at the top of the Loyalty screen, built to read as a
/// physical membership card rather than a stat tile: the gold brand mark over
/// the wordmark, a foil balance, and what the balance is worth.
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
    final worth = account.program.pointsDiscount
        ? account.balanceValueUsd
        : null;

    return TealFoilCard(
      radius: 22,
      shadowAlpha: 0.35,
      shadowBlur: 20,
      shadowOffset: 10,
      glows: const [
        PositionedDirectional(
          top: -50,
          end: -30,
          child: LoyaltyGlowCircle(size: 170),
        ),
        PositionedDirectional(
          bottom: -70,
          end: 60,
          child: LoyaltyGlowCircle(size: 130),
        ),
      ],
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 22,
          children: [
            // The wordmark sits directly beneath the mark, the way it does on
            // the app's own logo lockup (see custom_app_bar's CARLTON / HOTEL
            // pairing).
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
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
                Text(
                  AppTranslations.loyaltyProgramName.toUpperCase(),
                  style: textStyle.dmLabelSmall
                      ?.copyWith(
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
                  style: textStyle.dmLabelSmall
                      ?.copyWith(color: AppColors.white73)
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
                        account.availablePoints.formatPoints(),
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

            // Only when the hotel has set a point value: without one there is
            // no honest figure to show.
            if (worth != null) ...[
              // Gold fading to nothing, not a flat white rule — the rule is
              // part of the card's foil trim rather than a divider between two
              // unrelated blocks.
              Container(
                height: 1,
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.antiqueGold56, AppColors.white00],
                  ),
                ),
              ),
              Text(
                AppTranslations.loyaltyWorth(MoneyFormat.usd(worth)),
                style: textStyle.dmLabelSmall
                    ?.copyWith(color: AppColors.white73)
                    .tracked(context, 0.8),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
