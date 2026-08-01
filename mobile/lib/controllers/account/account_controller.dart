import 'package:carlton/components/sheets/sign_out_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:get/get.dart';

class AccountController extends GetxController {
  Guest? get _guest => MiddlewareService.find.guest.value;

  String get name => _guest?.fullName ?? '';
  String get email => _guest?.email ?? '';

  void openPreferences() => Get.toNamed(Routes.preferences);

  /// Edit the profile (reuses the Create Profile form, pre-filled).
  void editProfile() => Get.toNamed(Routes.editProfile, arguments: true);

  /// Rows without a destination yet fall back to the coming-soon snackbar,
  /// matching how the Services hub handles not-yet-built categories.
  void comingSoon(String label) =>
      CustomSnackbars.showInfo(message: '$label coming soon');

  void confirmSignOut() {
    showSignOutSheet(
      onConfirm: () async {
        await MiddlewareService.find.signOut();
        Get.offAllNamed(Routes.signIn);
      },
    );
  }
}
