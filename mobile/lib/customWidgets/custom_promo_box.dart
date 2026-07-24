import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class CustomPromoBox extends StatelessWidget {
  final TextEditingController controller;
  final VoidCallback onApply;

  const CustomPromoBox({
    required this.controller,
    required this.onApply,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
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
                  controller: controller,
                  textInputType: TextInputType.text,
                  hintText: 'Enter promo code',
                  fillColor: AppColors.whisperGrey,
                ),
              ),
              CustomFilledButton(
                height: 44,
                onPressed: onApply,
                child: const Text('Apply'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
