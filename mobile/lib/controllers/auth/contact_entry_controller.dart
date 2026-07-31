import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// New-guest registration entry — sends an OTP via
/// `POST /auth/guest/request-otp {channel, phone|email, purpose: register}`,
/// then routes to the OTP screen with the E.164 identifier the server echoes
/// back. Field validation is handled by [formKey].
class ContactEntryController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final emailController = TextEditingController();
  final phone = PhoneFieldState();

  final RxBool isSubmitting = false.obs;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    final hasPhone = phone.nationalNumber.isNotEmpty;
    final email = emailController.text.trim();
    final useEmail = !hasPhone && email.isNotEmpty;
    final channel = useEmail ? 'email' : 'sms';

    isSubmitting.value = true;
    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/auth/guest/request-otp',
      data: {
        'channel': channel,
        if (useEmail) 'email': email else 'phone': phone.controller.text.trim(),
        'purpose': 'register',
      },
    );
    if (isClosed) return;
    isSubmitting.value = false;

    if (response.statusCode != 200 || response.data == null) return;

    final identifier =
        response.data!['identifier'] as String? ??
        (useEmail ? email : phone.controller.text.trim());
    Get.toNamed(
      Routes.otpVerify,
      arguments: OtpVerifyArgs(
        channel: response.data!['channel'] as String? ?? channel,
        purpose: 'register',
        identifier: identifier,
      ),
    );
  }

  @override
  void onClose() {
    emailController.dispose();
    phone.dispose();
    super.onClose();
  }
}
