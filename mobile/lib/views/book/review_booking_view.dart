import 'package:carlton/components/booking_summary_header.dart';
import 'package:carlton/components/custom_price_summary.dart';
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

  static const _icons = {
    PaymentMethod.applePay: 'assets/icons/pay_apple.svg',
    PaymentMethod.googlePay: 'assets/icons/pay_google.svg',
  };

  Widget _breakdown(BookingFlowController c) {
    final room = c.selectedRoom!;
    return Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 10,
        children: [
          CustomPriceSummaryRow(
            title: '${room.name} · ${c.nights} nights',
            value: '\$${c.roomTotal}',
          ),
          for (final id in c.selectedAddOnIds)
            CustomPriceSummaryRow(
              title: c.addOns.firstWhere((a) => a.id == id).title,
              value: '\$${c.addOns.firstWhere((a) => a.id == id).price}',
            ),
          CustomPriceSummaryRow(
            title: 'Taxes & fees (15%)',
            value: '\$${c.taxes}',
          ),
          if (c.promoApplied)
            CustomPriceSummaryRow(
              title: 'Promo ${c.promoCtrl.text} (-10%)',
              value: '-\$${c.promoDiscount}',
              titleColor: AppColors.successGreen,
              valueColor: AppColors.successGreen,
            ),
          const Divider(),
          CustomPriceSummaryRow(
            title: 'Total',
            value: '\$${c.grandTotal}',
            isTotal: true,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      // appBar: const CustomBookingAppBar(
      //   title: 'Review Booking',
      //   currentStep: 6,
      // ),
      body: GetBuilder<BookingFlowController>(
        builder: (c) {
          final TextTheme textStyle = Get.textTheme;
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    BookingSummaryHeader(controller: c),
                    const SizedBox(height: 14),
                    Container(
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppColors.black06,
                          width: 1.18,
                        ),
                      ),
                      child: _breakdown(c),
                    ),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppColors.black06,
                          width: 1.18,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        spacing: 12,
                        children: [
                          Text(
                            'Guest & Payment',
                            style: textStyle.labelLarge?.copyWith(
                              fontFamily: 'Plus Jakarta Sans',
                              fontWeight: FontWeight.w600,
                              color: AppColors.inkBlack,
                            ),
                          ),
                          _infoRow(
                            'Guest',
                            '${c.firstNameCtrl.text} ${c.lastNameCtrl.text}',
                          ),
                          _infoRow('Email', c.emailCtrl.text),
                          _infoRow('Payment', c.paymentMethodDisplay),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                  child: CustomFilledButton(
                    width: double.infinity,
                    backgroundColor:
                        c.paymentMethod == PaymentMethod.applePay ||
                            c.paymentMethod == PaymentMethod.googlePay
                        ? AppColors.inkBlack
                        : AppColors.lagoonTeal,
                    onPressed: c.confirmBooking,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      spacing: 8,
                      children: [
                        if (_icons[c.paymentMethod] case final glyph?)
                          SvgPicture.asset(
                            glyph,
                            width: 16,
                            height: 16,
                            // Apple mark is white; the Google "G" keeps its colours.
                            colorFilter:
                                c.paymentMethod == PaymentMethod.applePay
                                ? const ColorFilter.mode(
                                    AppColors.white,
                                    BlendMode.srcIn,
                                  )
                                : null,
                          ),
                        Text(c.confirmCtaLabel),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
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
          style: textStyle.labelMedium?.copyWith(
            fontFamily: 'DM Sans',
            color: AppColors.dimGrey,
          ),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'Plus Jakarta Sans',
              color: AppColors.inkBlack,
            ),
          ),
        ),
      ],
    );
  }
}
