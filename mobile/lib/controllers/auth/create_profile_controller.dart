import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend wired up. Empty-field errors match the exact Figma
/// copy for first name; last name mirrors the same pattern.
class CreateProfileController extends GetxController {
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();

  String? firstNameError;
  String? lastNameError;
  bool isSubmitting = false;

  Future<void> submit() async {
    firstNameError = firstNameController.text.trim().isEmpty
        ? AppTranslations.pleaseEnterFirstName
        : null;
    lastNameError = lastNameController.text.trim().isEmpty
        ? AppTranslations.pleaseEnterLastName
        : null;
    update();
    if (firstNameError != null || lastNameError != null) return;

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    isSubmitting = false;
    update();

    Get.toNamed(Routes.phoneEntry);
  }

  @override
  void onClose() {
    firstNameController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
