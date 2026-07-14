import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend wired up. New-guest flow — collects the phone
/// number right after profile creation, then triggers a (simulated) OTP send.
class PhoneEntryController extends GetxController {
  final phoneController = TextEditingController();

  String? phoneError;
  bool isSubmitting = false;

  Future<void> submit() async {
    // Same validation rules as SignInController's phone path.
    final value = phoneController.text.trim();
    if (value.isEmpty) {
      phoneError = AppTranslations.pleaseEnterPhoneNumber;
    } else if (!value.isNumericOnly || value.length < 9) {
      phoneError = AppTranslations.invalidNumber;
    } else {
      phoneError = null;
    }
    update();
    if (phoneError != null) return;

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    if (isClosed) return;
    isSubmitting = false;
    update();

    Get.toNamed(Routes.otpVerify, arguments: value);
  }

  @override
  void onClose() {
    phoneController.dispose();
    super.onClose();
  }
}
