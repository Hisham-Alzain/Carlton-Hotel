import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// My Profile: shows the signed-in guest and edits them in place — Edit turns
/// the name and email rows into fields, Save sends `PUT /auth/guest/profile`.
/// Holds no copy of the guest: it reads [MiddlewareService.guest], which a save
/// updates, so the page and the Account card change together.
///
/// The phone is shown but not editable: it is the verified sign-in identity,
/// and changing it needs an OTP round the profile endpoint does not do.
class ProfileController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final emailController = TextEditingController();

  final RxBool isEditing = false.obs;
  final RxBool isSaving = false.obs;

  Guest? get guest => MiddlewareService.find.guest.value;

  /// Sign-in works by phone or by email OTP, so a guest with no phone on file
  /// signs in with the email — clearing it would lock them out.
  bool get emailRequired => (guest?.phone ?? '').isEmpty;

  /// Fills the fields from what is on file, then switches the rows to inputs.
  void startEditing() {
    final current = guest;
    firstNameController.text = current?.firstName ?? '';
    lastNameController.text = current?.lastName ?? '';
    emailController.text = current?.email ?? '';
    isEditing.value = true;
  }

  void cancelEditing() {
    if (isSaving.value) return;
    isEditing.value = false;
  }

  /// Sends only what changed — an untouched email is not re-sent, so its
  /// verified flag is not put at risk by a name edit.
  Future<void> save() async {
    if (isSaving.value || !formKey.currentState!.validate()) return;
    final current = guest;
    if (current == null) return;

    final firstName = firstNameController.text.trim();
    final lastName = lastNameController.text.trim();
    final email = emailController.text.trim();
    final changes = <String, dynamic>{
      if (firstName != (current.firstName ?? '')) 'first_name': firstName,
      if (lastName != (current.lastName ?? '')) 'last_name': lastName,
      if (email != (current.email ?? '')) 'email': email.isEmpty ? null : email,
    };
    if (changes.isEmpty) {
      isEditing.value = false;
      return;
    }

    isSaving.value = true;
    final response = await ApiService.find.put<Map<String, dynamic>>(
      path: '/auth/guest/profile',
      data: changes,
    );
    if (isClosed) return;
    isSaving.value = false;
    if (!response.hasData) return;

    // The profile response omits the /me-only entitlement flags — keep the
    // ones already on the session guest so an edit does not wipe them.
    MiddlewareService.find.updateGuest(
      Guest.fromJson(response.data!).copyWith(
        hasBooking: current.hasBooking,
        isCheckedIn: current.isCheckedIn,
      ),
    );
    isEditing.value = false;
    CustomSnackbars.showSuccess(message: AppTranslations.profileUpdated);
  }

  @override
  void onClose() {
    firstNameController.dispose();
    lastNameController.dispose();
    emailController.dispose();
    super.onClose();
  }
}
