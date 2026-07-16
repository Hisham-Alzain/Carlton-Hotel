import 'package:carlton/bindings/binding.dart';
import 'package:carlton/views/auth/create_profile_view.dart';
import 'package:carlton/views/auth/find_booking_view.dart';
import 'package:carlton/views/auth/otp_verify_view.dart';
import 'package:carlton/views/auth/phone_entry_view.dart';
import 'package:carlton/views/auth/reservation_choice_view.dart';
import 'package:carlton/views/home/ai_concierge_view.dart';
import 'package:carlton/views/main/main_view.dart';
import 'package:carlton/views/auth/sign_in_view.dart';
import 'package:carlton/views/splash_screen/splash_screen_view.dart';
import 'package:carlton/views/auth/welcome_back_view.dart';
import 'package:get/get.dart';

abstract class Routes {
  static const splashScreen = '/splash-screen';
  static const main = '/main';
  static const signIn = '/sign-in';
  static const otpVerify = '/otp-verify';
  static const welcomeBack = '/welcome-back';
  static const createProfile = '/create-profile';
  static const phoneEntry = '/phone-entry';
  static const reservationChoice = '/reservation-choice';
  static const findBooking = '/find-booking';
  static const aiConcierge = '/ai-concierge';
}

abstract class Pages {
  static final List<GetPage<dynamic>> getPages = [
    GetPage(
      name: Routes.splashScreen,
      page: () => const SplashScreenView(),
      binding: SplashBinding(),
    ),
    GetPage(
      name: Routes.main,
      page: () => const MainView(),
      binding: MainBinding(),
    ),
    GetPage(
      name: Routes.signIn,
      page: () => const SignInView(),
      binding: SignInBinding(),
    ),
    GetPage(
      name: Routes.otpVerify,
      page: () => const OtpVerifyView(),
      binding: OtpVerifyBinding(),
    ),
    GetPage(
      name: Routes.welcomeBack,
      page: () => const WelcomeBackView(),
      binding: WelcomeBackBinding(),
    ),
    GetPage(
      name: Routes.createProfile,
      page: () => const CreateProfileView(),
      binding: CreateProfileBinding(),
    ),
    GetPage(
      name: Routes.phoneEntry,
      page: () => const PhoneEntryView(),
      binding: PhoneEntryBinding(),
    ),
    GetPage(
      name: Routes.reservationChoice,
      page: () => const ReservationChoiceView(),
      binding: ReservationChoiceBinding(),
    ),
    GetPage(
      name: Routes.findBooking,
      page: () => const FindBookingView(),
      binding: FindBookingBinding(),
    ),
    GetPage(
      name: Routes.aiConcierge,
      page: () => const AiConciergeView(),
      binding: AiConciergeBinding(),
    ),
  ];
}
