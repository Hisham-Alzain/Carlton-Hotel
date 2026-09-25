import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/routes/routes.dart';
import 'package:get/get.dart';

class ReservationChoiceController extends GetxController {
  void findBooking() => Get.toNamed(Routes.findBooking);

  /// "I don't have a reservation" — no auth, no lookup. Routed through the
  /// same resolver as every other entry point rather than hardcoding Home:
  /// a null reservation *is* the defaultHome case, and going through
  /// [HomeController.goHome] keeps that one decision in one place.
  void continueAsGuest() => HomeController.goHome();
}
