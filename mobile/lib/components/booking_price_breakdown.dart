import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/custom_price_summary.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The server-priced charge breakdown (room · promo · total) shown both inside
/// the summary header's "View price details" expansion and as the always-visible
/// card on Review Booking (Figma "Booking / Step 12"). Values come from the
/// `GET /public/quote` result the controller holds (the API returns no separate
/// tax line — the total already nets the discount). Add-ons are UI-only (no
/// backend concept) so they no longer appear in the charged breakdown.
class BookingPriceBreakdown extends StatelessWidget {
  final BookingFlowController controller;

  const BookingPriceBreakdown({required this.controller, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final room = controller.selectedRoom.value!;

    return Padding(
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 10,
        children: [
          CustomPriceSummaryRow(
            title: '${room.name} · ${controller.displayNights} nights',
            value: controller.subtotalDisplay,
          ),
          if (controller.hasDiscount)
            CustomPriceSummaryRow(
              title: 'Promo ${controller.promoCtrl.text}',
              value: controller.discountDisplay,
              titleColor: AppColors.successGreen,
              valueColor: AppColors.successGreen,
            ),
          const Divider(),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    AppTranslations.total,
                    style: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.inkBlack,
                    ),
                  ),
                  Text(
                    'Final total for your stay',
                    style: textStyle.labelSmall?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
              Text(
                controller.totalDisplay,
                style: textStyle.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
