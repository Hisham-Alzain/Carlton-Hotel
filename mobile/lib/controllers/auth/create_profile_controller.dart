import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Completes the guest profile during onboarding (after OTP) via
/// `PUT /auth/guest/profile`, then routes into the app shell. Editing an
/// existing profile happens in place on My Profile (ProfileController).
class CreateProfileController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();

  final RxBool isSubmitting = false.obs;

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;

    isSubmitting.value = true;
    final response = await ApiService.find.put<Map<String, dynamic>>(
      path: '/auth/guest/profile',
      data: {
        'first_name': firstNameController.text.trim(),
        'last_name': lastNameController.text.trim(),
      },
    );
    if (isClosed) return;
    isSubmitting.value = false;

    if (!response.hasData) return;

    // The profile response omits the /me-only entitlement flags — preserve the
    // ones already on the in-memory guest so an edit doesn't wipe them.
    final current = MiddlewareService.find.guest.value;
    MiddlewareService.find.updateGuest(
      Guest.fromJson(response.data!).copyWith(
        hasBooking: current?.hasBooking,
        isCheckedIn: current?.isCheckedIn,
      ),
    );

    // First-time profile completion is the other tail of the auth flow —
    // resolve Home from the reservation, same as the returning-guest path.
    await HomeController.restoreAndGoHome();
  }

  @override
  void onClose() {
    firstNameController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
