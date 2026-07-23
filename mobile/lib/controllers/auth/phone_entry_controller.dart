import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/routes/routes.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend wired up. New-guest flow — collects the phone
/// number right after profile creation, then triggers a (simulated) OTP send.
/// Field validation is handled by [formKey] via the CustomTextField validator.
class PhoneEntryController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final emailController = TextEditingController();
  final phone = PhoneFieldState();

  bool isSubmitting = false;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    // Carries the dial code — the OTP screen shows this verbatim.
    final value = phone.controller.text.trim();

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
    emailController.dispose();
    phone.dispose();
    super.onClose();
  }
}
