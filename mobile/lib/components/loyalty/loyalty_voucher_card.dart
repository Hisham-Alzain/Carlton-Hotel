import 'package:carlton/components/loyalty/loyalty_card_texture.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/extensions/text_style_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A voucher the guest holds: its code (tap to copy), what it is worth, and
/// whether it can still be used. A used or expired voucher stays in the list
/// but is dimmed, so the guest can see what happened to it.
class LoyaltyVoucherCard extends StatelessWidget {
  final LoyaltyVoucher voucher;
  final VoidCallback onCopy;

  const LoyaltyVoucherCard({
    required this.voucher,
    required this.onCopy,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final usable = voucher.isUsable;
    final expires = voucher.expiresAt;
    final (statusLabel, statusColor) = switch (voucher) {
      LoyaltyVoucher(isUsed: true) => (
        AppTranslations.loyaltyVoucherUsed,
        AppColors.taupeBrown,
      ),
      LoyaltyVoucher(isExpired: true) => (
        AppTranslations.loyaltyVoucherExpired,
        AppColors.brickRed,
      ),
      LoyaltyVoucher(isUsable: true) => (
        AppTranslations.loyaltyVoucherActive,
        AppColors.forestGreen,
      ),
      _ => (AppTranslations.loyaltyVoucherUnavailable, AppColors.taupeBrown),
    };

    return Opacity(
      opacity: usable ? 1 : 0.6,
      child: Container(
        padding: const EdgeInsets.all(15),
        decoration: loyaltyPanelDecoration,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 12,
          children: [
            Row(
              spacing: 10,
              children: [
                Expanded(
                  child: Text(
                    voucher.rewardName.value,
                    style: textStyle.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                PillContainer(
                  backgroundColor: AppColors.antiqueGold09,
                  radius: 20,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  child: Text(
                    statusLabel,
                    style: textStyle.dmLabelSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: statusColor,
                    ),
                  ),
                ),
              ],
            ),
            // The code is what the guest types at booking, so it gets the card
            // number treatment and copies on tap.
            InkWell(
              onTap: usable ? onCopy : null,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: AppColors.antiqueGold09,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.antiqueGold20),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        voucher.code,
                        style: textStyle.dmTitleMedium
                            ?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            )
                            .tracked(context, 2),
                      ),
                    ),
                    if (usable)
                      const Icon(
                        Icons.copy_rounded,
                        size: 18,
                        color: AppColors.bronzeGold,
                      ),
                  ],
                ),
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: Text(
                    voucher.valueUsd != null
                        ? AppTranslations.loyaltyValueOff(
                            MoneyFormat.usdString(voucher.valueUsd),
                          )
                        : voucher.typeLabel,
                    style: textStyle.dmLabelMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.forestGreen,
                    ),
                  ),
                ),
                if (expires != null)
                  Text(
                    AppTranslations.loyaltyVoucherExpires(
                      expires.formatDatePicker(),
                    ),
                    style: textStyle.dmLabelSmall?.copyWith(
                      color: AppColors.taupeBrown,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
