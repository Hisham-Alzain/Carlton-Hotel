import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Sign-out confirmation as a bottom sheet (Figma Account "Sign Out" state):
/// red logout badge, title, message, and a Cancel / Sign Out button pair.
Future<void> showSignOutSheet({required VoidCallback onConfirm}) {
  return CustomBottomSheet.show<void>(
    showClose: false,
    child: CustomEmptyPlaceholder(
      iconWidget: SvgPicture.asset(
        'assets/icons/acc_signout.svg',
        width: 28,
        height: 28,
        colorFilter: const ColorFilter.mode(
          AppColors.brickRed,
          BlendMode.srcIn,
        ),
      ),
      iconContainerColor: AppColors.crimsonRed10,
      title: AppTranslations.signOutTitle,
      titleColor: AppColors.inkBlack,
      subtitle: AppTranslations.signOutBody,
    ),
    actions: Row(
      spacing: 10,
      children: [
        Expanded(
          child: CustomFilledButton(
            height: 50,
            backgroundColor: AppColors.whisperGrey,
            foregroundColor: AppColors.inkBlack,
            onPressed: () => Get.back(),
            child: Text(AppTranslations.cancel),
          ),
        ),
        Expanded(
          child: CustomFilledButton(
            height: 50,
            backgroundColor: AppColors.brickRed,
            onPressed: () {
              Get.back();
              onConfirm();
            },
            child: Text(AppTranslations.signOut),
          ),
        ),
      ],
    ),
  );
}
