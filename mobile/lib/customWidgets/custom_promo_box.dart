import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomPromoBox extends StatelessWidget {
  final TextEditingController promoCodeController;
  final VoidCallback onApply;

  const CustomPromoBox({
    required this.promoCodeController,
    required this.onApply,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
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
          Text(
            AppTranslations.promoCode,
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'Plus Jakarta Sans',
              color: AppColors.inkBlack,
            ),
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 8,
            children: [
              Expanded(
                child: CustomTextField(
                  controller: promoCodeController,
                  textInputType: TextInputType.text,
                  hintText: AppTranslations.promoHint,
                  fillColor: AppColors.whisperGrey,
                ),
              ),
              CustomFilledButton(
                height: 44,
                onPressed: onApply,
                child: Text(AppTranslations.apply),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
