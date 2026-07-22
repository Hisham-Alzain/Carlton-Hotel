import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_copy_pill.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_info_banner.dart';
import 'package:carlton/customWidgets/custom_price_summary.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Receipt bottom sheet (Figma "ReceiptModal") — a thin composition of the
/// copy pill, price summary and success banner foundation widgets.
class ReceiptSheet extends StatelessWidget {
  final ReceiptData receipt;

  const ReceiptSheet({required this.receipt, super.key});

  @override
  Widget build(BuildContext context) {
    return CustomBottomSheet(
      title: 'Receipt',
      subtitle: '${receipt.roomName} · ${receipt.dateLabel}',
      actions: [
        CustomFilledButton(
          backgroundColor: AppColors.teal,
          onPressed: () {
            Get.back<void>();
            CustomSnackbars.showInfo(message: 'Receipt download coming soon');
          },
          child: const Text('Download PDF Receipt'),
        ),
      ],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          CustomCopyPill(value: receipt.resCode),
          const SizedBox(height: 16),
          CustomPriceSummary(
            items: [for (final l in receipt.lines) (l.label, l.amount)],
            totalLabel: 'Total Charged',
            totalValue: receipt.total,
          ),
          const SizedBox(height: 16),
          CustomInfoBanner(
            tone: InfoBannerTone.success,
            message: receipt.paymentInfo,
          ),
        ],
      ),
    );
  }
}
