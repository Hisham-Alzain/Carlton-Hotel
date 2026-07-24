import 'package:carlton/components/booking_summary_header.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_card_form.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_pay_at_hotel_panel.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_selectable_card.dart';
import 'package:carlton/customWidgets/custom_wallet_panel.dart';
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

  static const _icons = {
    PaymentMethod.card: 'assets/icons/pay_card.svg',
    PaymentMethod.applePay: 'assets/icons/pay_apple.svg',
    PaymentMethod.googlePay: 'assets/icons/pay_google.svg',
    PaymentMethod.payAtHotel: 'assets/icons/pay_hotel.svg',
  };

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      appBar: AppBar(
        title: Text('Payment'),
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
        builder: (c) {
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
                BookingSummaryHeader(controller: c),
                // CustomPromoBox(
                //   controller: c.promoCtrl,
                //   onApply: c.applyPromo,
                // ),
                Text(
                  'Payment Method',
                  style: textStyle.labelLarge?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkBlack,
                  ),
                ),
                for (final m in PaymentMethod.values)
                  CustomSelectableCard(
                    title: m.label,
                    subtitle: m.subtitle,
                    control: SelectableControl.radio,
                    selected: c.paymentMethod == m,
                    onTap: () => c.selectPaymentMethod(m),
                    leading: _leadingIcon(_icons[m]!, c.paymentMethod == m),
                  ),
                _methodBody(c),
                CustomFilledButton(
                  backgroundColor: c.canReviewBooking
                      ? AppColors.lagoonTeal
                      : AppColors.pearlGrey,
                  onPressed: c.canReviewBooking ? c.reviewBooking : null,
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

  static Widget _methodBody(BookingFlowController c) {
    switch (c.paymentMethod) {
      case PaymentMethod.card:
        return CustomCardForm(
          numberCtrl: c.cardNumberCtrl,
          expiryCtrl: c.cardExpiryCtrl,
          cvvCtrl: c.cardCvvCtrl,
          nameCtrl: c.cardNameCtrl,
          onChanged: c.onPaymentFieldChanged,
        );
      case PaymentMethod.applePay:
        return const CustomWalletPanel(
          glyphPath: 'assets/icons/pay_apple.svg',
          badgeColor: AppColors.white10,
          tintGlyphWhite: true,
          title: 'Apple Pay',
          subtitle: 'One tap, secure, instant',
          bullets: [
            'Uses your saved card from Wallet',
            'Authorized with Face ID or Touch ID',
            'No card details shared with Apple Pay',
          ],
          footer:
              "You'll be redirected to Apple Pay to complete authorization.",
        );
      //TODO: add correct google  icon
      case PaymentMethod.googlePay:
        return const CustomWalletPanel(
          glyphPath: 'assets/icons/pay_google.svg',
          badgeColor: AppColors.white,
          tintGlyphWhite: false,
          title: 'Google Pay',
          subtitle: 'Fast and secure checkout',
          bullets: [
            'Uses your saved Google payment method',
            "Protected by Google's security systems",
            'Instant payment confirmation',
          ],
          footer:
              "You'll be redirected to Google Pay to complete authorization.",
        );
      case PaymentMethod.payAtHotel:
        return const CustomPayAtHotelPanel();
    }
  }
}
