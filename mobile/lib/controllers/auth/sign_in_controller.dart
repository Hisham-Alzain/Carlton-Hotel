import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend is wired up yet. Empty-field errors match the exact
/// Figma copy; a valid submission simulates a network delay then routes into
/// the real OTP screen with the destination (phone/email) it "sent" to.
class SignInController extends GetxController {
  SignInMethod method = SignInMethod.phone;

  final formKey = GlobalKey<FormState>();
  final phone = PhoneFieldState();
  final emailController = TextEditingController();

  bool isSubmitting = false;

  void switchMethod(SignInMethod value) {
    if (method == value) return;
    method = value;
    update();
  }

  Future<void> submit() async {
    // Only the currently mounted branch of the phone/email ternary is
    // registered with the Form, so this validates exactly the visible field.
    if (!formKey.currentState!.validate()) return;

    // Captured before the await: the user can pop this screen during the
    // delay, which disposes the TextEditingControllers.
    final destination = method == SignInMethod.phone
        ? phone.controller.text.trim()
        : emailController.text.trim();

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    if (isClosed) return;
    isSubmitting = false;
    update();

    Get.toNamed(Routes.otpVerify, arguments: destination);
  }

  @override
  void onClose() {
    phone.dispose();
    emailController.dispose();
    super.onClose();
  }
}
