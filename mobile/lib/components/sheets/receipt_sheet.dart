import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Body of the past-stay receipt sheet: reservation code, the charge
/// breakdown, and the payment confirmation. The "Download PDF Receipt" button
/// is supplied separately as the sheet's pinned action — see
/// `StaysController.showReceipt`.
class ReceiptSheet extends StatelessWidget {
  final ReceiptData receipt;

  const ReceiptSheet({required this.receipt, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 10,
      children: [
        PillContainer(
          backgroundColor: AppColors.cream,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                AppTranslations.resCode(receipt.resCode),
                style: textStyle.labelLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
              IconButton(
                onPressed: () {},
                icon: Icon(Icons.copy, color: AppColors.taupeBrown),
              ),
            ],
          ),
        ),
        CustomPriceSummary(
          lineItems: receipt.lines
              .map((line) => (line.label, line.amount))
              .toList(),
          totalLabel: AppTranslations.totalCharged,
          totalValue: receipt.total,
        ),
        CustomInfoBanner(
          tone: InfoBannerTone.success,
          message: receipt.paymentInfo,
        ),
      ],
    );
  }
}
