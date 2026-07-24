import 'package:carlton/components/cards/custom_payment_card_preview.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

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
    final TextTheme textStyle = Get.textTheme;
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.black07, width: 1),
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
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
              children: [
                CustomTextField(
                  controller: numberCtrl,
                  textInputType: TextInputType.number,
                  label: 'Card Number',
                  labelColor: AppColors.inkBlack,
                  hintText: '1234 5678 9012 3456',
                  fillColor: AppColors.whisperGrey,
                  maxLength: 16,
                  onChanged: (_) => onChanged(),
                ),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 10,
                  children: [
                    Expanded(
                      child: CustomTextField(
                        controller: expiryCtrl,
                        textInputType: TextInputType.datetime,
                        label: 'Expiry Date',
                        labelColor: AppColors.inkBlack,
                        hintText: 'MM/YY',
                        fillColor: AppColors.whisperGrey,
                        maxLength: 4,
                        onChanged: (_) => onChanged(),
                      ),
                    ),
                    Expanded(
                      child: CustomTextField(
                        controller: cvvCtrl,
                        textInputType: TextInputType.number,
                        label: 'CVV / CVC',
                        labelColor: AppColors.inkBlack,
                        hintText: '•••',
                        fillColor: AppColors.whisperGrey,
                        maxLength: 3,
                        onChanged: (_) => onChanged(),
                      ),
                    ),
                  ],
                ),
                CustomTextField(
                  controller: nameCtrl,
                  textInputType: TextInputType.name,
                  label: 'Name on Card',
                  labelColor: AppColors.inkBlack,
                  hintText: 'Ahmed Al-Rashid',
                  fillColor: AppColors.whisperGrey,
                  onChanged: (_) => onChanged(),
                ),
                Row(
                  spacing: 10,
                  children: [
                    SvgPicture.asset(
                      'assets/icons/lock.svg',
                      colorFilter: const ColorFilter.mode(
                        AppColors.taupeBrown,
                        BlendMode.srcIn,
                      ),
                    ),
                    Text(
                      '256-bit SSL encrypted · PCI DSS compliant',
                      style: textStyle.labelSmall?.copyWith(
                        fontFamily: 'DM Sans',
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
