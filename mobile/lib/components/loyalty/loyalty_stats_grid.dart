import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The lifetime summary under the balance card: points earned and redeemed.
///
/// One panel with a rule between the two, rather than two separate cards:
/// these are one reading of a single account. The rule is gold rather than
/// grey, so the grid reads as part of the card above it rather than a settings
/// panel.
class LoyaltyStatsGrid extends StatelessWidget {
  final LoyaltyAccount account;

  const LoyaltyStatsGrid({required this.account, super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: loyaltyPanelDecoration,
      child: Column(
        children: [
          // A 3px foil edge rather than a plain top border — the same gold
          // used on the hero card, so the two surfaces read as one set.
          Container(
            height: 3,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  AppColors.antiqueGold,
                  AppColors.sandGold,
                  AppColors.antiqueGold,
                ],
              ),
            ),
          ),
          _StatRow(
            start: _Stat(
              label: AppTranslations.loyaltyEarned,
              value: account.lifetimeEarnedPoints.formatPoints(),
              valueColor: AppColors.forestGreen,
            ),
            end: _Stat(
              label: AppTranslations.loyaltyRedeemed,
              value: account.lifetimeRedeemedPoints.formatPoints(),
              valueColor: AppColors.brickRed,
            ),
          ),
        ],
      ),
    );
  }
}

/// Two stats sharing a full-height rule. [IntrinsicHeight] is what gives the
/// rule something to measure against — a bare Row leaves it zero-height.
class _StatRow extends StatelessWidget {
  final Widget start;
  final Widget end;

  const _StatRow({required this.start, required this.end});

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: start),
          const VerticalDivider(
            width: 1,
            thickness: 1,
            color: AppColors.antiqueGold20,
          ),
          Expanded(child: end),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;
  final Color valueColor;

  const _Stat({
    required this.label,
    required this.value,
    required this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        spacing: 8,
        children: [
          Text(
            label.toUpperCase(),
            textAlign: TextAlign.center,
            style: textStyle.dmLabelSmall
                ?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.taupeBrown,
                )
                .tracked(context, 1.4),
          ),
          Text(
            value,
            textAlign: TextAlign.center,
            style: textStyle.headlineSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: valueColor,
            ),
          ),
        ],
      ),
    );
  }
}
