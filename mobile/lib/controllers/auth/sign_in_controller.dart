import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend is wired up yet. Empty-field errors match the exact
/// Figma copy; a valid submission simulates a network delay then routes into
/// the real OTP screen with the destination (phone/email) it "sent" to.
class SignInController extends GetxController {
  SignInMethod method = SignInMethod.phone;

  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  String? phoneError;
  String? emailError;
  bool isSubmitting = false;

  void switchMethod(SignInMethod value) {
    if (method == value) return;
    method = value;
    phoneError = null;
    emailError = null;
    update();
  }

  Future<void> submit() async {
    method == SignInMethod.phone ? _validatePhone() : _validateEmail();
    update();

    final hasError = method == SignInMethod.phone
        ? phoneError != null
        : emailError != null;
    if (hasError) return;

    // Captured before the await: the user can pop this screen during the
    // delay, which disposes the TextEditingControllers.
    final destination = method == SignInMethod.phone
        ? phoneController.text.trim()
        : emailController.text.trim();

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    if (isClosed) return;
    isSubmitting = false;
    update();

    Get.toNamed(Routes.otpVerify, arguments: destination);
  }

  void _validatePhone() {
    final value = phoneController.text.trim();
    if (value.isEmpty) {
      phoneError = AppTranslations.pleaseEnterPhoneNumber;
    } else if (!value.isNumericOnly || value.length < 9) {
      phoneError = AppTranslations.invalidNumber;
    } else {
      phoneError = null;
    }
  }

  void _validateEmail() {
    final value = emailController.text.trim();
    if (value.isEmpty) {
      emailError = AppTranslations.pleaseEnterEmailAddress;
    } else if (!value.isEmail) {
      emailError = AppTranslations.invalidEmail;
    } else {
      emailError = null;
    }
  }

  @override
  void onClose() {
    phoneController.dispose();
    emailController.dispose();
    super.onClose();
  }
}
