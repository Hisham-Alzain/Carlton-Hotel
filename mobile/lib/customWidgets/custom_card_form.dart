import 'package:carlton/components/cards/custom_payment_card_preview.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

const _labelStyle = TextStyle(
  fontFamily: 'Plus Jakarta Sans',
  fontSize: 12,
  fontWeight: FontWeight.w600,
  color: AppColors.inkBlack,
);

class CustomCardForm extends StatelessWidget {
  final TextEditingController numberCtrl;
  final TextEditingController expiryCtrl;
  final TextEditingController cvvCtrl;
  final TextEditingController nameCtrl;
  final VoidCallback onChanged;

  const CustomCardForm({
    required this.numberCtrl,
    required this.expiryCtrl,
    required this.cvvCtrl,
    required this.nameCtrl,
    required this.onChanged,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1.18),
        boxShadow: const [
          BoxShadow(
            color: AppColors.black06,
            blurRadius: 12,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomPaymentCardPreview(
            name: nameCtrl.text,
            expiry: expiryCtrl.text,
            number: numberCtrl.text,
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 12,
              children: [
                _LabeledField(
                  label: 'Card Number',
                  child: CustomTextField(
                    controller: numberCtrl,
                    textInputType: TextInputType.number,
                    hintText: '1234 5678 9012 3456',
                    fillColor: AppColors.whisperGrey,
                    maxLength: 19,
                    onChanged: (_) => onChanged(),
                  ),
                ),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 10,
                  children: [
                    Expanded(
                      child: _LabeledField(
                        label: 'Expiry Date',
                        child: CustomTextField(
                          controller: expiryCtrl,
                          textInputType: TextInputType.datetime,
                          hintText: 'MM/YY',
                          fillColor: AppColors.whisperGrey,
                          maxLength: 5,
                          onChanged: (_) => onChanged(),
                        ),
                      ),
                    ),
                    Expanded(
                      child: _LabeledField(
                        label: 'CVV / CVC',
                        child: CustomTextField(
                          controller: cvvCtrl,
                          textInputType: TextInputType.number,
                          hintText: '•••',
                          fillColor: AppColors.whisperGrey,
                          maxLength: 4,
                          onChanged: (_) => onChanged(),
                        ),
                      ),
                    ),
                  ],
                ),
                _LabeledField(
                  label: 'Name on Card',
                  child: CustomTextField(
                    controller: nameCtrl,
                    textInputType: TextInputType.name,
                    hintText: 'Ahmed Al-Rashid',
                    fillColor: AppColors.whisperGrey,
                    onChanged: (_) => onChanged(),
                  ),
                ),
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
  }
}

class _LabeledField extends StatelessWidget {
  final String label;
  final Widget child;

  const _LabeledField({required this.label, required this.child});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 6,
      children: [
        Text(label, style: _labelStyle),
        child,
      ],
    );
  }
}
