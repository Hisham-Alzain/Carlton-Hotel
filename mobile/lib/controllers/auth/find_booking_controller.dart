import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/models/pending_booking_link.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// "I already have a reservation" — links a hotel booking to the app via
/// `POST /auth/guest/link-booking-code {booking_code, phone}`, then routes to
/// OTP with `purpose: booking_link`.
///
/// The second factor is the **phone**, not the last name: the server texts the
/// code to the reservation's phone and returns only a masked copy of it, while
/// `verify-otp` must be told which phone the code belongs to. Asking the guest
/// for it means the app holds the real number to verify against — and the
/// server only matches a booking whose phone is that number.
class FindBookingController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final codeController = TextEditingController();
  final phone = PhoneFieldState();

  final RxBool isSubmitting = false.obs;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    final bookingCode = codeController.text.trim();
    final phoneNumber = phone.controller.text.trim();

    isSubmitting.value = true;
    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/auth/guest/link-booking-code',
      data: {'booking_code': bookingCode, 'phone': phoneNumber},
    );
    if (isClosed) return;
    isSubmitting.value = false;

    if (!response.hasData) return;

    // Stash so the OTP screen can re-trigger link-booking-code on resend.
    await SessionService.setPendingBookingLink(
      PendingBookingLink(bookingCode: bookingCode, phone: phoneNumber),
    );

    // The server masks the number it texted; show that, but verify against
    // the number the guest typed — the same one, since the lookup matched it.
    final masked =
        response.data!['identifier_masked'] as String? ?? phoneNumber;
    Get.toNamed(
      Routes.otpVerify,
      arguments: OtpVerifyArgs(
        channel: 'sms',
        purpose: 'booking_link',
        identifier: phoneNumber,
        display: masked,
      ),
    );
  }

  @override
  void onClose() {
    codeController.dispose();
    phone.dispose();
    super.onClose();
  }
}
