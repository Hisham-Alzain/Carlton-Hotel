import 'package:carlton/components/home/glass_card_style.dart';
import 'package:carlton/components/sheets/airport_transfer_sheet.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Airport transfer promo (Figma `2237:4237`). Routes into the existing
/// Services tab rather than inventing a second request path.
class AirportTransferSection extends GetView<HomeController> {
  const AirportTransferSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(glassCardRadius),
        border: Border.fromBorderSide(glassCardBorder),
        boxShadow: const [glassCardShadow],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 14,
        children: [
          Row(
            spacing: 12,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 8,
                  children: [
                    Text(
                      AppTranslations.airportTransfer,
                      style: Get.textTheme.titleSmall?.copyWith(
                        fontSize: 15,
                        color: AppColors.nearBlack,
                      ),
                    ),
                    Text(
                      AppTranslations.airportTransferBody,
                      style: Get.textTheme.bodySmall?.copyWith(
                        fontSize: 11,
                        color: AppColors.steelGrey,
                      ),
                    ),
                  ],
                ),
              ),
              const CustomImage(
                source: 'assets/images/airport_transfer.png',
                width: 90,
                height: 75,
                fit: BoxFit.contain,
              ),
            ],
          ),
          CustomFilledButton(
            height: 44,
            width: double.infinity,
            onPressed: showAirportTransferSheet,
            backgroundColor: AppColors.lagoonTeal,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(6),
            ),
            textStyle: Get.textTheme.labelLarge?.copyWith(
              fontSize: 13,
              fontFamily: 'DM Sans',
            ),
            child: Text(AppTranslations.requestAirportTransfer),
          ),
        ],
      ),
    );
  }
}
