import 'package:carlton/controllers/auth/welcome_back_controller.dart';
import 'package:carlton/components/custom_auth_background.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class WelcomeBackView extends GetView<WelcomeBackController> {
  const WelcomeBackView({super.key});

  @override
  Widget build(BuildContext context) {
    final _ = controller;
    return CustomAuthBackground(
      child: CustomEmptyPlaceholder(
        iconWidget: Container(
          width: 64,
          height: 64,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.antiqueGold,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.slateTeal, width: 1.5),
          ),
          child: const Icon(Icons.check, color: Colors.white, size: 28),
        ),
        title: AppTranslations.welcomeBackTitle,
        subtitle: AppTranslations.welcomeBackSubtitle,
        titleColor: Colors.white,
        subtitleColor: AppColors.snowGrey75,
      ),
    );
  }
}
