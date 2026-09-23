import 'package:carlton/components/cards/custom_payment_card_preview.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/utils/input_formatters.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomCardForm extends StatelessWidget {
  final TextEditingController cardNumberController;
  final TextEditingController expiryController;
  final TextEditingController cvvController;
  final TextEditingController cardholderNameController;
  final VoidCallback onChanged;

  const CustomCardForm({
    required this.cardNumberController,
    required this.expiryController,
    required this.cvvController,
    required this.cardholderNameController,
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
            cardholderName: cardholderNameController.text,
            cardExpiry: expiryController.text,
            cardNumber: cardNumberController.text,
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 10,
              children: [
                CustomTextField(
                  controller: cardNumberController,
                  textInputType: TextInputType.number,
                  captionLabel: 'Card Number',
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
                        controller: expiryController,
                        textInputType: TextInputType.datetime,
                        captionLabel: 'Expiry Date',
                        labelColor: AppColors.inkBlack,
                        hintText: 'MM/YY',
                        fillColor: AppColors.whisperGrey,
                        maxLength: 5,
                        inputFormatters: [ExpiryDateInputFormatter()],
                        onChanged: (_) => onChanged(),
                      ),
                    ),
                    Expanded(
                      child: CustomTextField(
                        controller: cvvController,
                        textInputType: TextInputType.number,
                        captionLabel: 'CVV / CVC',
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
                  controller: cardholderNameController,
                  textInputType: TextInputType.name,
                  captionLabel: 'Name on Card',
                  labelColor: AppColors.inkBlack,
                  hintText: 'Ahmed Al-Rashid',
                  fillColor: AppColors.whisperGrey,
                  onChanged: (_) => onChanged(),
                ),
                // iconSize 14 matches lock.svg's own 13.997 viewBox, which is
                // what the unsized SvgPicture rendered at before.
                RowTextComponent(
                  text: '256-bit SSL encrypted · PCI DSS compliant',
                  iconPath: 'assets/icons/lock.svg',
                  iconSize: 14,
                  iconColor: AppColors.taupeBrown,
                  spacing: 10,
                  textStyle: textStyle.labelSmall?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
