import 'package:carlton/components/booking_loyalty_panel.dart';
import 'package:carlton/theme/theme.dart';
import 'package:carlton/components/booking_step_header.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/booking_price_breakdown.dart';
import 'package:carlton/components/booking_summary_header.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Step 6 — final review before confirming (Figma "Booking / Step 12").
class ReviewBookingView extends StatelessWidget {
  const ReviewBookingView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<BookingFlowController>();

    return CustomScaffold(
      appBar: BookingStepAppBar(
        title: AppTranslations.reviewBooking,
        onClose: Get.back,
      ),
      body: Obx(() {
        final TextTheme textStyle = Get.textTheme;
        return Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            spacing: 10,
            children: [
              BookingStepIndicator(step: 5),
              BookingSummaryHeader(
                roomName: controller.selectedRoom.value!.name,
                roomImage: controller.selectedRoom.value!.images.first,
                dateRange: controller.dateRange,
                nights: controller.nights,
                totalDisplay: controller.totalDisplay,
              ),

              Container(
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.black06, width: 1),
                ),
                child: BookingPriceBreakdown(summary: controller.priceSummary),
              ),

              Obx(() {
                final account = controller.loyaltyAccount.value;
                if (account == null) return const SizedBox.shrink();
                return BookingLoyaltyPanel(
                  account: account,
                  mode: controller.loyaltyMode.value,
                  pointsController: controller.pointsCtrl,
                  voucherController: controller.voucherCtrl,
                  preview: controller.loyaltyPreview.value,
                  error: controller.loyaltyError.value,
                  pricing: controller.loyaltyPricing.value,
                  onModeChanged: controller.setLoyaltyMode,
                  onInputChanged: controller.onLoyaltyInputChanged,
                );
              }),

              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.whisperGrey,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 12,
                  children: [
                    Text(
                      AppTranslations.guestAndPayment,
                      style: textStyle.labelLarge?.copyWith(
                        fontFamily: 'Plus Jakarta Sans',
                        fontWeight: FontWeight.w600,
                        color: AppColors.inkBlack,
                      ),
                    ),
                    _infoRow(
                      AppTranslations.guestLabel,
                      '${controller.firstNameCtrl.text} ${controller.lastNameCtrl.text}',
                    ),
                    _infoRow(AppTranslations.email, controller.emailCtrl.text),
                    _infoRow(
                      AppTranslations.payment,
                      controller.paymentMethodDisplay,
                    ),
                  ],
                ),
              ),

              CustomFilledButton(
                width: double.infinity,
                backgroundColor:
                    controller.paymentMethod.value == PaymentMethod.applePay ||
                        controller.paymentMethod.value ==
                            PaymentMethod.googlePay
                    ? AppColors.inkBlack
                    : AppColors.lagoonTeal,
                onPressed: controller.confirmBooking,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  spacing: 10,
                  children: [
                    if (controller.paymentMethod.value.iconPath
                        case final iconPath?)
                      SvgPicture.asset(
                        iconPath,
                        width: 16,
                        height: 16,
                        // Apple mark is white; the Google "G" keeps its colours.
                        colorFilter:
                            controller.paymentMethod.value ==
                                PaymentMethod.applePay
                            ? const ColorFilter.mode(
                                AppColors.white,
                                BlendMode.srcIn,
                              )
                            : null,
                      ),
                    Text(controller.confirmCtaLabel),
                  ],
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _infoRow(String label, String value) {
    final TextTheme textStyle = Get.textTheme;
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: textStyle.dmLabelMedium?.copyWith(color: AppColors.dimGrey),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: textStyle.labelMedium?.copyWith(color: AppColors.inkBlack),
          ),
        ),
      ],
    );
  }
}
