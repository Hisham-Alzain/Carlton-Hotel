import 'package:carlton/components/booking_summary_header.dart';
import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_card_form.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_pay_at_hotel_panel.dart';
import 'package:carlton/customWidgets/custom_promo_box.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_selectable_card.dart';
import 'package:carlton/customWidgets/custom_wallet_panel.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

const _sectionLabelStyle = TextStyle(
  fontFamily: 'Plus Jakarta Sans',
  fontSize: 14,
  fontWeight: FontWeight.w600,
  color: AppColors.inkBlack,
);

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
      appBar: const CustomBookingAppBar(title: 'Payment', currentStep: 5),
      body: GetBuilder<BookingFlowController>(
        builder: (c) => Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  spacing: 14,
                  children: [
                    BookingSummaryHeader(controller: c),
                    CustomPromoBox(
                      controller: c.promoCtrl,
                      onApply: c.applyPromo,
                    ),
                    const Text('Payment Method', style: _sectionLabelStyle),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      spacing: 8,
                      children: [
                        for (final m in PaymentMethod.values)
                          CustomSelectableCard(
                            title: m.label,
                            subtitle: m.subtitle,
                            control: SelectableControl.radio,
                            selected: c.paymentMethod == m,
                            onTap: () => c.selectPaymentMethod(m),
                            leading: _leadingIcon(
                              _icons[m]!,
                              c.paymentMethod == m,
                            ),
                          ),
                      ],
                    ),
                    _methodBody(c),
                  ],
                ),
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                child: CustomFilledButton(
                  width: double.infinity,
                  backgroundColor: c.canReviewBooking
                      ? AppColors.lagoonTeal
                      : AppColors.pearlGrey,
                  onPressed: c.canReviewBooking ? c.reviewBooking : null,
                  child: const Text('Continue'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static Widget _leadingIcon(String assetPath, bool selected) => Container(
    width: 38,
    height: 38,
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
