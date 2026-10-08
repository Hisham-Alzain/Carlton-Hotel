import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

/// "Use your rewards" on the Review screen: pay part of the booking with points
/// or take a voucher off it, and see what it comes to before confirming.
///
/// Points and a voucher are mutually exclusive, so [mode] is one of `none`,
/// `points`, `voucher`. Everything shown comes from the server's preview; the
/// app never works out a discount itself.
class BookingLoyaltyPanel extends StatelessWidget {
  final LoyaltyAccount account;
  final String mode;
  final TextEditingController pointsController;
  final TextEditingController voucherController;
  final LoyaltyPreview? preview;
  final String? error;
  final bool pricing;
  final ValueChanged<String> onModeChanged;
  final ValueChanged<String> onInputChanged;

  const BookingLoyaltyPanel({
    required this.account,
    required this.mode,
    required this.pointsController,
    required this.voucherController,
    required this.preview,
    required this.error,
    required this.pricing,
    required this.onModeChanged,
    required this.onInputChanged,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    // Points need the hotel's value and cap to be set, and a balance to spend.
    final canUsePoints =
        account.program.pointsDiscount && account.availablePoints > 0;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.whisperGrey,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 12,
        children: [
          Text(
            AppTranslations.loyaltyUseRewards,
            style: textStyle.labelLarge?.copyWith(
              fontWeight: FontWeight.w600,
              color: AppColors.inkBlack,
            ),
          ),
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: [
              ButtonSegment(
                value: 'none',
                label: Text(AppTranslations.loyaltyUseNone),
              ),
              if (canUsePoints)
                ButtonSegment(
                  value: 'points',
                  label: Text(AppTranslations.loyaltyUsePoints),
                ),
              ButtonSegment(
                value: 'voucher',
                label: Text(AppTranslations.loyaltyUseVoucher),
              ),
            ],
            selected: {mode},
            onSelectionChanged: (selection) => onModeChanged(selection.first),
          ),
          if (mode == 'points') ...[
            CustomTextField(
              controller: pointsController,
              textInputType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              hintText: AppTranslations.loyaltyPointsToUse,
              fillColor: AppColors.white,
              onChanged: onInputChanged,
            ),
            Text(
              AppTranslations.loyaltyAvailableLine(
                account.availablePoints.formatPoints(),
              ),
              style: textStyle.dmLabelSmall?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
          ],
          if (mode == 'voucher')
            CustomTextField(
              controller: voucherController,
              textInputType: TextInputType.text,
              hintText: AppTranslations.loyaltyVoucherCodeHint,
              fillColor: AppColors.white,
              onChanged: onInputChanged,
            ),
          if (pricing)
            const Center(child: SpinningIconIndicator(size: 24))
          else if (error != null)
            Text(
              error!,
              style: textStyle.dmLabelMedium?.copyWith(
                color: AppColors.brickRed,
              ),
            )
          else if (preview != null)
            _Summary(preview: preview!),
        ],
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  final LoyaltyPreview preview;

  const _Summary({required this.preview});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    Widget line(String label, String value, {bool bold = false}) => Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: textStyle.dmLabelMedium?.copyWith(
              fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
              color: AppColors.inkBlack,
            ),
          ),
        ),
        Text(
          value,
          style: textStyle.dmLabelMedium?.copyWith(
            fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
            color: bold ? AppColors.primary : AppColors.forestGreen,
          ),
        ),
      ],
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 6,
      children: [
        if (preview.hasPointsDiscount)
          line(
            AppTranslations.loyaltyPointsDiscount,
            '-${MoneyFormat.usdString(preview.pointsDiscountUsd)}',
          ),
        if (preview.hasVoucher && !preview.upgradeRequested)
          line(
            AppTranslations.loyaltyVoucherDiscount,
            '-${MoneyFormat.usdString(preview.voucherDiscountUsd)}',
          ),
        // An upgrade voucher takes nothing off: the desk upgrades the room.
        if (preview.upgradeRequested)
          Text(
            AppTranslations.loyaltyUpgradeRequested,
            style: textStyle.dmLabelMedium?.copyWith(
              color: AppColors.forestGreen,
            ),
          ),
        line(
          AppTranslations.loyaltyNetTotal,
          MoneyFormat.usdString(preview.netTotalUsd),
          bold: true,
        ),
        if (preview.pointsEarnableEstimate != null)
          Text(
            AppTranslations.loyaltyEarnEstimate(
              preview.pointsEarnableEstimate!.formatPoints(),
            ),
            style: textStyle.dmLabelSmall?.copyWith(
              color: AppColors.taupeBrown,
            ),
          ),
      ],
    );
  }
}
