import 'package:carlton/routes/routes.dart';
import 'package:get/get.dart';

/// Figma node 2056:448 — a brief success beat with no button; it auto-
/// advances back into Services, clearing the whole auth sub-stack (Sign In /
/// Create Profile / Phone Entry / OTP) since that flow is now an on-demand
/// action launched from Services, not the app's entry point.
class WelcomeBackController extends GetxController {
  static const _displayDuration = Duration(milliseconds: 1600);

  @override
  void onReady() {
    super.onReady();
    // Future.delayed(_displayDuration, () {
    //   if (isClosed) return;
    //   Get.offAllNamed(Routes.main);
    // });
  }
}
