import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/session_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Demo-only: no backend wired up. Figma didn't show an error state for this
/// screen, so it uses the generic required-field message.
class FindBookingController extends GetxController {
  final codeController = TextEditingController();
  final lastNameController = TextEditingController();

  String? codeError;
  String? lastNameError;
  bool isSubmitting = false;

  Future<void> submit() async {
    codeError = codeController.text.trim().isEmpty
        ? AppTranslations.requiredField
        : null;
    lastNameError = lastNameController.text.trim().isEmpty
        ? AppTranslations.requiredField
        : null;
    update();
    if (codeError != null || lastNameError != null) return;

    // Captured before the await: popping the screen mid-delay disposes the
    // TextEditingControllers.
    final reservation = Reservation(
      code: codeController.text.trim(),
      lastName: lastNameController.text.trim(),
    );

    isSubmitting = true;
    update();
    await Future.delayed(DemoData.networkDelay);
    if (isClosed) return;
    isSubmitting = false;
    update();

    // Every reservation belongs to an account, so the guest is really an
    // account holder — verify the phone on file (OTP) before attaching the
    // booking. The reservation is stashed and picked up by ServicesController
    // once OTP signs them in; no one ends up as a "guest with a reservation".
    await SessionService.setPendingReservation(reservation);
    Get.toNamed(
      Routes.otpVerify,
      arguments: AppTranslations.reservationPhoneDestination,
    );
  }

  @override
  void onClose() {
    codeController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
