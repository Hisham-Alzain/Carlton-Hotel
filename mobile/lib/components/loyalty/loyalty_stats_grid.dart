import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The four-up lifetime summary under the balance card: points earned,
/// redeemed, stays counted, and the net movement.
///
/// A 2x2 of dividers rather than four separate cards: these are one reading of
/// a single account, and splitting them into cards would say they are four
/// unrelated facts. The rules between them are gold rather than grey, so the
/// grid reads as part of the card above it rather than a settings panel.
class LoyaltyStatsGrid extends StatelessWidget {
  final LoyaltyAccount account;

  const LoyaltyStatsGrid({required this.account, super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.antiqueGold20),
        boxShadow: const [
          BoxShadow(
            color: AppColors.slateShadow04,
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
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
              value: account.earnedTotal.formatPoints(),
              valueColor: AppColors.forestGreen,
            ),
            end: _Stat(
              label: AppTranslations.loyaltyRedeemed,
              value: account.redeemedTotal.formatPoints(),
              valueColor: AppColors.brickRed,
            ),
          ),
          const Divider(
            height: 1,
            thickness: 1,
            color: AppColors.antiqueGold20,
          ),
          _StatRow(
            start: _Stat(
              label: AppTranslations.loyaltyStaysCounted,
              value: '${account.staysCount}',
              // bronzeGold, not antiqueGold — the lighter gold is a fill and a
              // hairline colour, and falls below readable contrast as text on
              // white.
              valueColor: AppColors.bronzeGold,
            ),
            end: _Stat(
              label: AppTranslations.loyaltyNetChange,
              value: account.netChange.formatSignedPoints(),
              valueColor: account.netChange < 0
                  ? AppColors.brickRed
                  : AppColors.forestGreen,
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
            style: textStyle.labelSmall
                ?.copyWith(
                  fontFamily: 'DM Sans',
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
