import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Returning-guest sign-in — sends a `login` OTP via
/// `POST /auth/guest/request-otp` on the phone or email branch the user chose.
class SignInController extends GetxController {
  /// Reactive: the view's segmented button and the phone/email field ternary
  /// both read this inside their own Obx.
  final Rx<SignInMethod> method = SignInMethod.phone.obs;

  final formKey = GlobalKey<FormState>();
  final phone = PhoneFieldState();
  final emailController = TextEditingController();

  final RxBool isSubmitting = false.obs;

  void switchMethod(SignInMethod value) {
    if (method.value == value) return;
    method.value = value;
  }

  Future<void> submit() async {
    // Only the visible branch of the phone/email ternary is registered with
    // the Form, so this validates exactly the shown field.
    if (!formKey.currentState!.validate()) return;

    final byEmail = method.value == SignInMethod.email;
    final email = emailController.text.trim();
    final channel = byEmail ? 'email' : 'sms';

    isSubmitting.value = true;
    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/auth/guest/request-otp',
      data: {
        'channel': channel,
        if (byEmail) 'email': email else 'phone': phone.controller.text.trim(),
        'purpose': 'login',
      },
    );
    if (isClosed) return;
    isSubmitting.value = false;

    if (response.statusCode != 200 || response.data == null) return;

    final identifier =
        response.data!['identifier'] as String? ??
        (byEmail ? email : phone.controller.text.trim());
    Get.toNamed(
      Routes.otpVerify,
      arguments: OtpVerifyArgs(
        channel: response.data!['channel'] as String? ?? channel,
        purpose: 'login',
        identifier: identifier,
      ),
    );
  }

  @override
  void onClose() {
    phone.dispose();
    emailController.dispose();
    super.onClose();
  }
}
