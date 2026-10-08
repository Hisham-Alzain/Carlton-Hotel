import 'package:carlton/controllers/services/airport_transfer_controller.dart';
import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/controllers/account/loyalty_controller.dart';
import 'package:carlton/controllers/account/loyalty_rewards_controller.dart';
import 'package:carlton/controllers/account/loyalty_vouchers_controller.dart';
import 'package:carlton/controllers/account/legal_controller.dart';
import 'package:carlton/controllers/account/preferences_controller.dart';
import 'package:carlton/controllers/account/profile_controller.dart';
import 'package:carlton/controllers/account/support_controller.dart';
import 'package:carlton/controllers/discover/experience_controller.dart';
import 'package:carlton/controllers/dining/restaurant_controller.dart';
import 'package:carlton/controllers/reviews/review_controller.dart';
import 'package:carlton/controllers/auth/create_profile_controller.dart';
import 'package:carlton/controllers/stays/folio_controller.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/controllers/auth/find_booking_controller.dart';
import 'package:carlton/controllers/booking/pre_arrival_documents_controller.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/controllers/auth/reservation_choice_controller.dart';
import 'package:carlton/controllers/auth/otp_verify_controller.dart';
import 'package:carlton/controllers/auth/contact_entry_controller.dart';
import 'package:carlton/controllers/home/ai_concierge_controller.dart';
import 'package:carlton/controllers/home/discover_controller.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/controllers/auth/sign_in_controller.dart';
import 'package:carlton/controllers/splash/splash_screen_controller.dart';
import 'package:carlton/controllers/auth/welcome_back_controller.dart';
import 'package:carlton/models/booking_models.dart';
// import 'package:carlton/models/home_models.dart'; // needed by the restaurant ReviewController
import 'package:carlton/models/review.dart';
import 'package:get/get.dart';

class SplashBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => SplashScreenController());
  }
}

class MainBinding implements Bindings {
  @override
  void dependencies() {
    // Permanent, not fenix: see MainController's doc for why a route-scoped
    // shell controller breaks when sign-in re-pushes /main over itself.
    final startTab = Get.arguments is int ? Get.arguments as int : 0;
    if (Get.isRegistered<MainController>()) {
      Get.find<MainController>().resetTo(startTab);
    } else {
      Get.put(MainController(), permanent: true);
    }
    // The Home + Services tabs live inside the shell, so their controllers are
    // owned here.
    Get.lazyPut(() => HomeController(), fenix: true);
    // Opened from the pre-arrival Home card; fenix so it survives the tab
    // controller being recreated mid-flow.
    Get.lazyPut(() => AirportTransferController(), fenix: true);
    Get.lazyPut(() => ServicesController(), fenix: true);
    Get.lazyPut(() => StaysController(), fenix: true);
    Get.lazyPut(() => AccountController(), fenix: true);
  }
}

class LoyaltyBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => LoyaltyController());
  }
}

/// The rewards screen reads the balance from [LoyaltyController], so it is
/// bound here too: a no-op while the Loyalty screen below it is still open,
/// and the owner when a deep link lands on rewards directly.
class LoyaltyRewardsBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => LoyaltyController());
    Get.lazyPut(() => LoyaltyRewardsController());
  }
}

class LoyaltyVouchersBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => LoyaltyVouchersController());
  }
}

class PreferencesBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => PreferencesController());
  }
}

class RestaurantBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => RestaurantController());
    // Reviews tab switched off for now (see RestaurantDetailView).
    // // Reviews tab (autoLoad) — target derived from the route argument; a
    // // venue with no uuid skips the fetch and renders the empty state. Read the
    // // argument now: the lazy factory runs at first Get.find, when a sheet or
    // // dialog on top may have made Get.arguments something else.
    // final venue = Get.arguments;
    // Get.lazyPut(
    // () => ReviewController(
    // reviewType: ReviewTargetType.diningVenue,
    // targetUuid: venue is RestaurantItem ? venue.uuid : '',
    // ),
    // );
  }
}

class RoomDetailsBinding implements Bindings {
  @override
  void dependencies() {
    // Room Details only submits reviews — the list is never rendered, so
    // autoLoad is off. A room with no uuid can't be reviewed; the view hides
    // the CTA in that case.
    // Read the argument now, not in the factory: the controller is first
    // found from the review sheet, where Get.arguments is no longer the room —
    // the POST went to `/reviews/room_type/` with no uuid (404).
    final room = Get.arguments;
    Get.lazyPut(
      () => ReviewController(
        reviewType: ReviewTargetType.roomType,
        targetUuid: room is RoomOption ? room.uuid : '',
        autoLoad: false,
      ),
    );
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
    Get.lazyPut(() => ContactEntryController());
  }
}

class AiConciergeBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => AiConciergeController());
  }
}

class DiscoverBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => DiscoverController());
  }
}

class FolioBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => FolioController());
  }
}

class ProfileBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => ProfileController());
  }
}

class SupportBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => SupportController());
  }
}

class LegalBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => LegalController());
  }
}

class ExperienceBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => ExperienceController());
  }
}

class PreArrivalDocumentsBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => PreArrivalDocumentsController());
  }
}

class CheckInBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => CheckInController());
  }
}

class ScanIdBinding implements Bindings {
  @override
  void dependencies() {
    Get.lazyPut(() => ScanIdController());
  }
}

// Booking flow — all 5 steps share one BookingFlowController, registered
// once, permanently, at app boot (see main.dart) as a GetxService. No
// per-route binding needed.
