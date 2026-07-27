import 'package:carlton/components/sheets/sign_out_sheet.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/session_service.dart';
import 'package:get/get.dart';

class AccountController extends GetxController {
  // Demo profile (see constants/demo_data.dart) until a real account API exists.
  final String name = DemoData.userName;
  final String email = DemoData.userEmail;

  void openPreferences() => Get.toNamed(Routes.preferences);

  /// Rows without a destination yet fall back to the coming-soon snackbar,
  /// matching how the Services hub handles not-yet-built categories.
  void comingSoon(String label) =>
      CustomSnackbars.showInfo(message: '$label coming soon');

  void confirmSignOut() {
    showSignOutSheet(
      onConfirm: () async {
        await SessionService.signOut();
        Get.offAllNamed(Routes.signIn);
      },
    );
  }
}
