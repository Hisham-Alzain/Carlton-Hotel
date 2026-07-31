import 'package:carlton/models/guest.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Completes (onboarding, after OTP) or edits the guest profile via
/// `PUT /auth/guest/profile`. Edit mode — signalled by passing `true` as the
/// route argument (from Account) — pre-fills the form and pops back on save;
/// onboarding routes into the app shell.
class CreateProfileController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();

  late final bool isEdit;
  final RxBool isSubmitting = false.obs;

  @override
  void onInit() {
    super.onInit();
    isEdit = Get.arguments == true;
    if (isEdit) {
      final guest = MiddlewareService.find.guest.value;
      firstNameController.text = guest?.firstName ?? '';
      lastNameController.text = guest?.lastName ?? '';
    }
  }

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

    if (response.statusCode != 200 || response.data == null) return;

    // The profile response omits the /me-only entitlement flags — preserve the
    // ones already on the in-memory guest so an edit doesn't wipe them.
    final current = MiddlewareService.find.guest.value;
    MiddlewareService.find.updateGuest(
      Guest.fromJson(response.data!).copyWith(
        hasBooking: current?.hasBooking,
        isCheckedIn: current?.isCheckedIn,
      ),
    );

    if (isEdit) {
      Get.back();
    } else {
      Get.offAllNamed(Routes.main);
    }
  }

  @override
  void onClose() {
    firstNameController.dispose();
    lastNameController.dispose();
    super.onClose();
  }
}
