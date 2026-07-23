import 'package:carlton/components/cards/custom_payment_card_preview.dart';
import 'package:carlton/components/custom_booking_app_bar.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/customWidgets/custom_selectable_card.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

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
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text('Payment Method', style: _sectionLabelStyle),
                  const SizedBox(height: 14),
                  for (final m in PaymentMethod.values) ...[
                    CustomSelectableCard(
                      title: m.label,
                      subtitle: m.subtitle,
                      control: SelectableControl.radio,
                      selected: c.paymentMethod == m,
                      onTap: () => c.selectPaymentMethod(m),
                      leading: _leadingIcon(_icons[m]!, c.paymentMethod == m),
                    ),
                    const SizedBox(height: 8),
                  ],
                  if (c.paymentMethod == PaymentMethod.card) ...[
                    const SizedBox(height: 6),
                    _cardForm(c),
                  ],
                  const SizedBox(height: 14),
                  _promoBox(c),
                ],
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
                  child: const Text('Review Booking'),
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
}

const _sectionLabelStyle = TextStyle(
  fontFamily: 'Plus Jakarta Sans',
  fontSize: 14,
  fontWeight: FontWeight.w600,
  color: AppColors.inkBlack,
);

const _fieldLabelStyle = TextStyle(
  fontFamily: 'Plus Jakarta Sans',
  fontSize: 12,
  fontWeight: FontWeight.w600,
  color: AppColors.inkBlack,
);

Widget _fieldLabel(String text) => Padding(
  padding: const EdgeInsets.only(bottom: 6),
  child: Text(text, style: _fieldLabelStyle),
);

Widget _cardForm(BookingFlowController c) => Container(
  clipBehavior: Clip.antiAlias,
  decoration: BoxDecoration(
    color: AppColors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: AppColors.black07, width: 1.18),
    boxShadow: const [
      BoxShadow(color: AppColors.black06, blurRadius: 12, offset: Offset(0, 2)),
    ],
  ),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      CustomPaymentCardPreview(
        name: c.cardNameCtrl.text,
        expiry: c.cardExpiryCtrl.text,
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _fieldLabel('Card Number'),
            CustomTextField(
              controller: c.cardNumberCtrl,
              textInputType: TextInputType.number,
              hintText: '1234 5678 9012 3456',
              fillColor: AppColors.whisperGrey,
              maxLength: 19,
              onChanged: (_) => c.onPaymentFieldChanged(),
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _fieldLabel('Expiry Date'),
                      CustomTextField(
                        controller: c.cardExpiryCtrl,
                        textInputType: TextInputType.datetime,
                        hintText: 'MM/YY',
                        fillColor: AppColors.whisperGrey,
                        maxLength: 5,
                        onChanged: (_) => c.onPaymentFieldChanged(),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _fieldLabel('CVV / CVC'),
                      CustomTextField(
                        controller: c.cardCvvCtrl,
                        textInputType: TextInputType.number,
                        hintText: '•••',
                        fillColor: AppColors.whisperGrey,
                        maxLength: 4,
                        onChanged: (_) => c.onPaymentFieldChanged(),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _fieldLabel('Name on Card'),
            CustomTextField(
              controller: c.cardNameCtrl,
              textInputType: TextInputType.name,
              hintText: 'Ahmed Al-Rashid',
              fillColor: AppColors.whisperGrey,
              onChanged: (_) => c.onPaymentFieldChanged(),
            ),
            const SizedBox(height: 10),
            Row(
              spacing: 6,
              children: [
                SvgPicture.asset(
                  'assets/icons/lock.svg',
                  width: 12,
                  height: 12,
                  colorFilter: const ColorFilter.mode(
                    AppColors.taupeBrown,
                    BlendMode.srcIn,
                  ),
                ),
                const Text(
                  '256-bit SSL encrypted · PCI DSS compliant',
                  style: TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 11,
                    color: AppColors.taupeBrown,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ],
  ),
);

Widget _promoBox(BookingFlowController c) => Container(
  padding: const EdgeInsets.all(15.18),
  decoration: BoxDecoration(
    color: AppColors.white,
    borderRadius: BorderRadius.circular(12),
    border: Border.all(color: AppColors.black06, width: 1.18),
  ),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    spacing: 10,
    children: [
      const Text(
        'Promo Code',
        style: TextStyle(
          fontFamily: 'Plus Jakarta Sans',
          fontSize: 13,
          fontWeight: FontWeight.w500,
          color: AppColors.inkBlack,
        ),
      ),
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 8,
        children: [
          Expanded(
            child: CustomTextField(
              controller: c.promoCtrl,
              textInputType: TextInputType.text,
              hintText: 'Enter promo code',
              fillColor: AppColors.whisperGrey,
            ),
          ),
          CustomFilledButton(
            height: 44,
            onPressed: c.applyPromo,
            child: const Text('Apply'),
          ),
        ],
      ),
    ],
  ),
);
