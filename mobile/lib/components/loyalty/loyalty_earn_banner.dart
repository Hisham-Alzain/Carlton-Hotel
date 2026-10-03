import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The earning promise under the balance card: what the guest gets, and when
/// it lands.
///
/// Not a [CustomInfoBanner]. That widget's tones are severity tones — info,
/// warning, success, danger — and this is neither a state nor an alert; it is
/// the programme's offer. Its green `success` tint also imported a Material
/// palette green onto the one screen built entirely from the hotel's own
/// cream/gold, which is exactly the note that made the screen read generic.
class LoyaltyEarnBanner extends StatelessWidget {
  const LoyaltyEarnBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          colors: [AppColors.linenCream, AppColors.ivoryCream],
        ),
        border: Border.all(color: AppColors.antiqueGold20, width: 1),
      ),
      child: Row(
        spacing: 14,
        children: [
          CustomIconChip(
            size: 40,
            radius: 12,
            backgroundColor: AppColors.white,
            border: Border.all(color: AppColors.antiqueGold20),
            child: const Icon(
              // A seal rather than the sparkle glyph every app uses for
              // "magic" — this banner is a guarantee ("credited after
              // checkout"), and a rosette says guaranteed.
              Icons.verified_outlined,
              size: 19,
              color: AppColors.bronzeGold,
            ),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              spacing: 3,
              children: [
                Text(
                  AppTranslations.loyaltyEarnTitle,
                  style: textStyle.labelLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
                Text(
                  AppTranslations.loyaltyEarnBody,
                  style: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
