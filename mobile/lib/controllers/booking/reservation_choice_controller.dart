import 'package:carlton/routes/routes.dart';
import 'package:get/get.dart';

class ReservationChoiceController extends GetxController {
  void findBooking() => Get.toNamed(Routes.findBooking);
  void continueAsGuest() => Get.offAllNamed(Routes.main);
}
