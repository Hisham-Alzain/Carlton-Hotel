import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/models/pending_booking_link.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// "I already have a reservation" — links a hotel booking to the app via
/// `POST /auth/guest/link-booking-code {booking_code, last_name}`, which sends
/// an OTP to the reservation contact, then routes to OTP with
/// `purpose: booking_link`.
class FindBookingController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final codeController = TextEditingController();
  final lastNameController = TextEditingController();

  final RxBool isSubmitting = false.obs;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    final bookingCode = codeController.text.trim();
    final lastName = lastNameController.text.trim();

    isSubmitting.value = true;
    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/auth/guest/link-booking-code',
      data: {'booking_code': bookingCode, 'last_name': lastName},
    );
    if (isClosed) return;
    isSubmitting.value = false;

    if (response.statusCode != 200 || response.data == null) return;

    // Stash so the OTP screen can re-trigger link-booking-code on resend.
    await SessionService.setPendingBookingLink(
      PendingBookingLink(bookingCode: bookingCode, lastName: lastName),
    );

    final masked = response.data!['identifier_masked'] as String? ?? '';
    Get.toNamed(
      Routes.otpVerify,
      // NOTE: for booking_link the verify-otp identifier is keyed by the
      // reservation contact server-side; the app only has the masked value to
      // display. Confirm the exact verify-otp payload for this path on-device.
      arguments: OtpVerifyArgs(
        channel: 'sms',
        purpose: 'booking_link',
        identifier: masked,
        display: masked,
      ),
    );
  }

  @override
  void onClose() {
    codeController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
