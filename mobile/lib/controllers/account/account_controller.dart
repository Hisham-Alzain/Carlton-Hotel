import 'package:carlton/l10n/app_translations.dart';
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

  void openLoyalty() => Get.toNamed(Routes.loyalty);

  /// Edit the profile (reuses the Create Profile form, pre-filled).
  void editProfile() => Get.toNamed(Routes.editProfile, arguments: true);

  /// Published FAQs (`GET /public/faqs`) plus a route into the staff chat.
  void openSupport() => Get.toNamed(Routes.support);

  /// Terms, privacy and about, from `GET /public/pages/{slug}`.
  void openLegal() => Get.toNamed(Routes.legal);

  /// Rows without a destination yet fall back to the coming-soon snackbar,
  /// matching how the Services hub handles not-yet-built categories.
  /// Notifications, Saved Payments and Security stay here on purpose: the API
  /// exposes no preference, card-vault or password endpoints to back them.
  void comingSoon(String label) => CustomSnackbars.showInfo(
    message: AppTranslations.sectionComingSoon(label),
  );

  /// Sign-out calls `POST /auth/guest/logout` (MiddlewareService.signOut) to
  /// revoke the token, then clears the local session whatever the answer — a
  /// guest must always be able to sign out, even offline.
  void confirmSignOut() {
    showSignOutSheet(
      onConfirm: () async {
        await MiddlewareService.find.signOut();
        // Back to the entry fork ("do you have a reservation?") rather than
        // straight to Sign In — a signed-out guest may want to browse or link a
        // booking, and Sign In is only one of those three paths.
        Get.offAllNamed(Routes.reservationChoice);
      },
    );
  }

}
