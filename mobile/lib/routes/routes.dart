import 'package:carlton/bindings/binding.dart';
import 'package:carlton/views/check_in/check_in_view.dart';
import 'package:carlton/views/check_in/scan_id_view.dart';
import 'package:carlton/views/auth/create_profile_view.dart';
import 'package:carlton/views/auth/find_booking_view.dart';
import 'package:carlton/views/auth/otp_verify_view.dart';
import 'package:carlton/views/auth/contact_entry_view.dart';
import 'package:carlton/views/auth/reservation_choice_view.dart';
import 'package:carlton/views/book/add_ons_view.dart';
import 'package:carlton/views/book/booking_confirmed_view.dart';
import 'package:carlton/views/book/choose_room_view.dart';
import 'package:carlton/views/book/guest_details_view.dart';
import 'package:carlton/views/book/payment_view.dart';
import 'package:carlton/views/book/review_booking_view.dart';
import 'package:carlton/views/book/room_details_view.dart';
import 'package:carlton/views/booking/pre_arrival_documents_view.dart';
import 'package:carlton/views/discover/discover_view.dart';
import 'package:carlton/views/home/ai_concierge_view.dart';
import 'package:carlton/views/main/main_view.dart';
import 'package:carlton/views/auth/sign_in_view.dart';
import 'package:carlton/views/account/loyalty_view.dart';
import 'package:carlton/views/account/preferences_view.dart';
import 'package:carlton/views/dining/restaurant_detail_view.dart';
import 'package:carlton/views/services/service_category_detail_view.dart';
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
  static const editProfile = '/account/edit-profile';
  static const phoneEntry = '/phone-entry';
  static const reservationChoice = '/reservation-choice';
  static const findBooking = '/find-booking';
  static const aiConcierge = '/ai-concierge';
  static const discover = '/discover';
  static const serviceCategory = '/services/category';
  static const preferences = '/account/preferences';
  static const loyalty = '/account/loyalty';
  static const restaurantDetail = '/dining/restaurant';

  // Booking flow (5 steps, sharing one permanent BookingFlowController).
  static const roomDetails = '/booking/room-details';
  static const chooseRoom = '/booking/rooms';
  static const addOns = '/booking/add-ons';
  static const guestDetails = '/booking/guest';
  static const payment = '/booking/payment';
  static const reviewBooking = '/booking/review';
  static const bookingConfirmed = '/booking/confirmed';
  static const preArrivalDocuments = '/booking/pre-arrival-documents';
  static const checkIn = '/check-in';
  static const scanId = '/check-in/scan-id';
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
      name: Routes.editProfile,
      page: () => const CreateProfileView(),
      binding: CreateProfileBinding(),
    ),
    GetPage(
      name: Routes.phoneEntry,
      page: () => const ContactEntryView(),
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
    GetPage(
      name: Routes.discover,
      page: () => const DiscoverView(),
      binding: DiscoverBinding(),
    ),
    GetPage(
      name: Routes.serviceCategory,
      page: () => const ServiceCategoryDetailView(),
    ),
    GetPage(
      name: Routes.roomDetails,
      page: () => const RoomDetailsView(),
      binding: RoomDetailsBinding(),
    ),
    GetPage(
      name: Routes.preferences,
      page: () => const PreferencesView(),
      binding: PreferencesBinding(),
    ),
    GetPage(
      name: Routes.loyalty,
      page: () => const LoyaltyView(),
      binding: LoyaltyBinding(),
    ),
    GetPage(
      name: Routes.restaurantDetail,
      page: () => const RestaurantDetailView(),
      binding: RestaurantBinding(),
    ),
    GetPage(name: Routes.chooseRoom, page: () => const ChooseRoomView()),
    GetPage(name: Routes.addOns, page: () => const AddOnsView()),
    GetPage(name: Routes.guestDetails, page: () => const GuestDetailsView()),
    GetPage(name: Routes.payment, page: () => const PaymentView()),
    GetPage(name: Routes.reviewBooking, page: () => const ReviewBookingView()),
    GetPage(
      name: Routes.bookingConfirmed,
      page: () => const BookingConfirmedView(),
    ),
    GetPage(
      name: Routes.preArrivalDocuments,
      page: () => const PreArrivalDocumentsView(),
      binding: PreArrivalDocumentsBinding(),
    ),
    GetPage(
      name: Routes.checkIn,
      page: () => const CheckInView(),
      binding: CheckInBinding(),
    ),
    GetPage(
      name: Routes.scanId,
      page: () => const ScanIdView(),
      binding: ScanIdBinding(),
    ),
  ];
}
