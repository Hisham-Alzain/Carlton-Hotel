import 'package:carlton/components/booking_summary_header.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/components/custom_card_form.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/components/custom_pay_at_hotel_panel.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/components/custom_selectable_card.dart';
import 'package:carlton/components/custom_wallet_panel.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';

/// Step 5 — payment method + card form (Figma "Booking / Step 8"). Review
/// Booking stays disabled until the selected method's details are complete.
class PaymentView extends StatelessWidget {
  const PaymentView({super.key});

  static const _methodIconPaths = {
    PaymentMethod.card: 'assets/icons/pay_card.svg',
    PaymentMethod.applePay: 'assets/icons/pay_apple.svg',
    PaymentMethod.googlePay: 'assets/icons/pay_google.svg',
    PaymentMethod.payAtHotel: 'assets/icons/pay_hotel.svg',
  };

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        title: Text('Review Booking'),
        iconTheme: IconThemeData(color: Colors.black),
        actions: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.whisperGrey,
            ),
            child: IconButton(
              onPressed: () {},
              icon: const Icon(Icons.close, color: AppColors.inkBlack),
            ),
          ),
        ],
      ),
      body: GetBuilder<BookingFlowController>(
        builder: (controller) {
          final TextTheme textStyle = Get.textTheme;
          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              spacing: 10,
              children: [
                AnimatedSmoothIndicator(
                  activeIndex: 4,
                  count: 6,
                  effect: SlideEffect(
                    dotHeight: 5,
                    dotWidth: 50,
                    spacing: 20,
                    activeDotColor: AppColors.primary,
                    dotColor: AppColors.iceBlue,
                  ),
                ),
                BookingSummaryHeader(controller: controller),
                // CustomPromoBox(
                //   promoCodeController: controller.promoCtrl,
                //   onApply: controller.applyPromo,
                // ),
                Text(
                  'Payment Method',
                  style: textStyle.labelLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkBlack,
                  ),
                ),
                ...PaymentMethod.values.map(
                  (method) => CustomSelectableCard(
                    title: method.label,
                    subtitle: method.subtitle,
                    controlType: SelectableControl.radio,
                    selected: controller.paymentMethod == method,
                    onTap: () => controller.selectPaymentMethod(method),
                    leading: _leadingIcon(
                      _methodIconPaths[method]!,
                      controller.paymentMethod == method,
                    ),
                  ),
                ),
                _methodBody(controller),
                CustomFilledButton(
                  backgroundColor: controller.canReviewBooking
                      ? AppColors.lagoonTeal
                      : AppColors.pearlGrey,
                  onPressed: controller.canReviewBooking
                      ? controller.reviewBooking
                      : null,
                  child: const Text('Continue'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  static Widget _leadingIcon(String assetPath, bool selected) => Container(
    width: 40,
    height: 40,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: selected ? AppColors.primary07 : AppColors.pearlCream,
      borderRadius: BorderRadius.circular(10),
    ),
    child: SvgPicture.asset(assetPath, width: 20, height: 20),
  );

  static Widget _methodBody(BookingFlowController controller) {
    switch (controller.paymentMethod) {
      case PaymentMethod.card:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            CustomCardForm(
              cardNumberController: controller.cardNumberCtrl,
              expiryController: controller.cardExpiryCtrl,
              cvvController: controller.cardCvvCtrl,
              cardholderNameController: controller.cardNameCtrl,
              onChanged: controller.onPaymentFieldChanged,
            ),
            const _ComingSoonNote(),
          ],
        );
      case PaymentMethod.applePay:
        return const CustomWalletPanel(
          iconPath: 'assets/icons/pay_apple.svg',
          badgeColor: AppColors.white10,
          tintGlyphWhite: true,
          title: 'Apple Pay',
          subtitle: 'One tap, secure, instant',
          bullets: [
            'Uses your saved card from Wallet',
            'Authorized with Face ID or Touch ID',
            'No card details shared with Apple Pay',
          ],
          footerNote:
              "Apple Pay isn't available yet — choose Pay at Hotel to confirm.",
        );
      case PaymentMethod.googlePay:
        return const CustomWalletPanel(
          iconPath: 'assets/icons/google.svg',
          badgeColor: AppColors.white,
          tintGlyphWhite: false,
          title: 'Google Pay',
          subtitle: 'Fast and secure checkout',
          bullets: [
            'Uses your saved Google payment method',
            "Protected by Google's security systems",
            'Instant payment confirmation',
          ],
          footerNote:
              "Google Pay isn't available yet — choose Pay at Hotel to confirm.",
        );
      case PaymentMethod.payAtHotel:
        return const CustomPayAtHotelPanel();
    }
  }
}

/// Shown under the card form: card payments have no gateway yet, so the booking
/// can only be confirmed with Pay at Hotel (see decision #1).
class _ComingSoonNote extends StatelessWidget {
  const _ComingSoonNote();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.whisperGrey,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        spacing: 8,
        children: [
          const Icon(Icons.info_outline, size: 18, color: AppColors.dimGrey),
          Expanded(
            child: Text(
              "Card payments aren't available yet — choose Pay at Hotel to "
              'confirm your booking.',
              style: Get.textTheme.labelMedium?.copyWith(
                fontFamily: 'DM Sans',
                color: AppColors.dimGrey,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
