import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Sign-out confirmation as a bottom sheet (Figma Account "Sign Out" state):
/// red logout badge, title, message, and a Cancel / Sign Out button pair.
Future<void> showSignOutSheet({required VoidCallback onConfirm}) {
  return CustomBottomSheet.show<void>(
    showClose: false,
    child: const CustomEmptyPlaceholder(
      iconWidget: Icon(Icons.logout, size: 28, color: AppColors.brickRed),
      iconContainerColor: AppColors.crimsonRed10,
      title: 'Sign Out?',
      titleColor: AppColors.inkBlack,
      subtitle:
          "You'll need to sign in again to access your account and active "
          'stays.',
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
            child: const Text('Cancel'),
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
            child: const Text('Sign Out'),
          ),
        ),
      ],
    ),
  );
}
