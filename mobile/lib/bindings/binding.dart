import 'package:carlton/controllers/auth/create_profile_controller.dart';
import 'package:carlton/controllers/booking/find_booking_controller.dart';
import 'package:carlton/controllers/booking/reservation_choice_controller.dart';
import 'package:carlton/controllers/auth/otp_verify_controller.dart';
import 'package:carlton/controllers/auth/phone_entry_controller.dart';
import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/controllers/auth/sign_in_controller.dart';
import 'package:carlton/controllers/splash/splash_controller.dart';
import 'package:carlton/controllers/auth/welcome_back_controller.dart';
import 'package:get/get.dart';

class SplashBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => SplashController());
  }
}

class MainBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => MainController(), fenix: true);
    // The Home + Services tabs live inside the shell, so their controllers are
    // owned here.
    Get.lazyPut(() => HomeController(), fenix: true);
    Get.lazyPut(() => ServicesController(), fenix: true);
  }
}

class SignInBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => SignInController());
  }
}

class OtpVerifyBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => OtpVerifyController());
  }
}

class WelcomeBackBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => WelcomeBackController());
  }
}

class CreateProfileBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => CreateProfileController());
  }
}

class FindBookingBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => FindBookingController());
  }
}

class ReservationChoiceBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => ReservationChoiceController());
  }
}

class PhoneEntryBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => PhoneEntryController());
  }
}

class AiConciergeBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => AiConciergeController());
  }
}
