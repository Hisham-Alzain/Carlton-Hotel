import 'package:carlton/constants/error_codes.dart';
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
/// The phone is editable too. The server refuses to overwrite a phone the
/// guest already verified (`verified_contact_immutable`) until it offers an
/// OTP change-number flow; that refusal is explained, not shown raw.
class ProfileController extends GetxController {
  final formKey = GlobalKey<FormState>();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final emailController = TextEditingController();
  final phoneController = TextEditingController();

  final RxBool isEditing = false.obs;
  final RxBool isSaving = false.obs;

  Guest? get guest => MiddlewareService.find.guest.value;

  /// Sign-in works by phone or by email OTP, so a guest with no phone on file
  /// signs in with the email — clearing it would lock them out.
  bool get emailRequired => (guest?.phone ?? '').isEmpty;

  /// The mirror rule: with no email on file the phone is the only sign-in.
  bool get phoneRequired => (guest?.email ?? '').isEmpty;

  /// Fills the fields from what is on file, then switches the rows to inputs.
  void startEditing() {
    final current = guest;
    firstNameController.text = current?.firstName ?? '';
    lastNameController.text = current?.lastName ?? '';
    emailController.text = current?.email ?? '';
    phoneController.text = current?.phone ?? '';
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
    final phone = phoneController.text.replaceAll(' ', '').trim();
    final changes = <String, dynamic>{
      if (firstName != (current.firstName ?? '')) 'first_name': firstName,
      if (lastName != (current.lastName ?? '')) 'last_name': lastName,
      if (email != (current.email ?? '')) 'email': email.isEmpty ? null : email,
      if (phone != (current.phone ?? '')) 'phone': phone.isEmpty ? null : phone,
    };
    if (changes.isEmpty) {
      isEditing.value = false;
      return;
    }

    isSaving.value = true;
    final response = await ApiService.find.put<Map<String, dynamic>>(
      path: '/auth/guest/profile',
      data: changes,
      showErrorDialog: false,
    );
    if (isClosed) return;
    isSaving.value = false;
    if (!response.hasData) {
      final error = response.error;
      // A verified phone/email can only change through a code sent to the
      // new contact, which the server does not offer yet.
      if (error?.errorCode == ErrorCodes.verifiedContactImmutable) {
        CustomSnackbars.showInfo(
          message: AppTranslations.verifiedContactLocked,
        );
      } else if (error != null) {
        ApiService.find.dialogs.showError(error);
      }
      return;
    }

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
    phoneController.dispose();
    super.onClose();
  }
}
