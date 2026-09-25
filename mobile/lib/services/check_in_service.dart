import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/arrival_slot.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:get/get.dart';

/// Single source of truth for pre-arrival and check-in state.
///
/// Registered permanently in `main.dart` beside `BookingFlowController`, so
/// the Home tab and the check-in wizard read the same instance. Home is the
/// consumer (progress bar, checklist ticks); the wizard is the producer.
///
/// Demo-only: nothing persists. A restart returns to the seeded 1/4 state.
/// How long the digital-key activation animation runs before the key
/// reports itself active.
const _digitalKeyActivationDuration = Duration(seconds: 2);

class CheckInService extends GetxService {
  static CheckInService get find => Get.find<CheckInService>();

  final Rx<ReservationSummary> reservation = Rx<ReservationSummary>(
    DemoData.preArrivalReservation,
  );

  /// Seeded with `contactDetails` so Home opens at 1/4, matching Figma 2237:4237.
  final RxSet<PreArrivalStep> completed = <PreArrivalStep>{
    PreArrivalStep.contactDetails,
  }.obs;

  final Rx<IdentityStatus> identity = IdentityStatus.notStarted.obs;
  final RxString documentNumber = ''.obs;

  /// Absolute path to the ID photo the guest captured, or empty when they have
  /// not scanned yet. Written by the scanner and read by the Identity tab, so
  /// the verified card shows the real document rather than a placeholder.
  final RxString documentImagePath = ''.obs;
  final Rx<StayPreferences> preferences = Rx<StayPreferences>(
    PreferenceOptions.defaultStayPreferences,
  );
  final Rx<DigitalKeyStatus> key = DigitalKeyStatus.idle.obs;

  /// Null until the guest picks one. Stored as the slot rather than its label
  /// so the text re-renders in the active locale — see [ArrivalSlot].
  final Rx<ArrivalSlot?> arrivalSlot = Rx<ArrivalSlot?>(null);

  /// Wipes everything tied to the signed-in guest.
  ///
  /// This service is `permanent: true`, so without an explicit reset the next
  /// guest to sign in on the same device inherited the previous one's state:
  /// a completed checklist (making `isReadyToCheckIn` true immediately), their
  /// stay preferences, and — the reason this is a privacy fix, not a staleness
  /// one — their scanned ID number and the on-disk path to the photo of their
  /// passport. Called from [MiddlewareService.signOut], the single place a
  /// session ends.
  void reset() {
    reservation.value = DemoData.preArrivalReservation;
    completed
      ..clear()
      ..add(PreArrivalStep.contactDetails);
    identity.value = IdentityStatus.notStarted;
    documentNumber.value = '';
    documentImagePath.value = '';
    preferences.value = PreferenceOptions.defaultStayPreferences;
    key.value = DigitalKeyStatus.idle;
    arrivalSlot.value = null;
  }

  int get totalSteps => PreArrivalStep.values.length;
  int get completedCount => completed.length;
  double get progress => completedCount / totalSteps;
  bool isStepComplete(PreArrivalStep step) => completed.contains(step);

  /// [imagePath] is the scanner's captured photo. It stays optional so the
  /// upload route — which hands documents straight to the API and never holds a
  /// local file — can mark identity verified without one.
  void markIdentityVerified(String number, {String imagePath = ''}) {
    identity.value = IdentityStatus.verified;
    documentNumber.value = number;
    if (imagePath.isNotEmpty) documentImagePath.value = imagePath;
    completed.add(PreArrivalStep.identity);
  }

  void markArrivalTime(ArrivalSlot slot) {
    arrivalSlot.value = slot;
    completed.add(PreArrivalStep.arrivalTime);
  }

  void markSpecialRequests() => completed.add(PreArrivalStep.specialRequests);

  void savePreferences(StayPreferences next) {
    preferences.value = next;
    markSpecialRequests();
  }

  /// Simulates provisioning. The delay is the only thing a real integration
  /// would replace.
  Future<void> activateDigitalKey() async {
    if (key.value != DigitalKeyStatus.idle) return;
    key.value = DigitalKeyStatus.activating;
    await Future<void>.delayed(_digitalKeyActivationDuration);
    // The guest can leave the tab mid-activation.
    if (isClosed) return;
    key.value = DigitalKeyStatus.activated;
  }

  /// The steps that actually gate check-in.
  ///
  /// Deliberately NOT `PreArrivalStep.values`. [PreArrivalStep.arrivalTime] is
  /// settable from exactly one place — Home's checklist sheet — and no wizard
  /// tab marks it, so requiring it made "Complete Check-In" permanently
  /// unreachable for a guest who went straight through the wizard. Arrival time
  /// is a courtesy the hotel likes to have, not a precondition for occupying a
  /// room that is already paid for.
  ///
  /// Home still shows progress out of all four rows; this set is only about
  /// what blocks the transition.
  static const Set<PreArrivalStep> requiredSteps = {
    PreArrivalStep.contactDetails,
    PreArrivalStep.identity,
    PreArrivalStep.specialRequests,
  };

  /// Every *required* checklist row done — the rule for being allowed to check
  /// in. See [requiredSteps] for why this is not all four.
  bool get isReadyToCheckIn => requiredSteps.every(completed.contains);

  /// The required steps still outstanding, in checklist order. Drives the
  /// message the wizard shows when the guest is turned away, so it names the
  /// step instead of failing silently.
  List<PreArrivalStep> get missingSteps => PreArrivalStep.values
      .where(requiredSteps.contains)
      .where((s) => !completed.contains(s))
      .toList();

  /// Loads the guest's own reservation for the wizard's booking panel.
  ///
  /// Best-effort: on failure the seeded placeholder stays, which is the same
  /// state the wizard opened in. Never surfaces a dialog — the guest came here
  /// to check in, and a fetch failure should not block that.
  Future<void> loadReservation() async {
    // Both are permanent singletons from main.dart, but this service is also
    // constructed directly in widget tests, where neither exists.
    if (!Get.isRegistered<MiddlewareService>() ||
        !Get.isRegistered<ApiService>()) {
      return;
    }
    if (!MiddlewareService.find.isAuthenticated) return;
    final response = await ApiService.find.get<List<dynamic>>(
      path: '/stays/upcoming',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (response.statusCode != 200 || response.data == null) return;
    final upcoming = UpcomingStay.listFromJson(response.data);
    if (upcoming.isEmpty) return;
    reservation.value = ReservationSummary.fromUpcomingStay(
      upcoming.first,
      // Locale resolution belongs here, not in the model — see the factory.
      suiteName: upcoming.first.roomName.value,
      guest: MiddlewareService.find.guest.value,
    );
  }

  /// Checks the guest in for real (`POST /stays/check-in`), which flips their
  /// reservation to `checked_in` and is what makes Home resolve to
  /// [HomeViewState.activeBooking].
  ///
  /// The completion rule is asserted here rather than trusted from the caller:
  /// this is the one place the transition happens, so it is the one place worth
  /// guarding. Any non-[CheckInOutcome.success] leaves the guest on the wizard
  /// rather than pretending they are in-house.
  ///
  /// The outcome is deliberately three-valued: [CheckInOutcome.incomplete]
  /// never reaches the network, so the caller — not ApiService — owes the guest
  /// an explanation.
  Future<CheckInOutcome> completeCheckIn() async {
    if (!isReadyToCheckIn) return CheckInOutcome.incomplete;

    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/stays/check-in',
    );
    if (response.statusCode != 200) return CheckInOutcome.failed;

    // has_booking / is_checked_in gate the in-stay sections Home is about to
    // render, so refresh them from /me before Home re-resolves — otherwise it
    // shows activeBooking while the folio and requests still read as absent.
    await MiddlewareService.find.checkToken();
    return CheckInOutcome.success;
  }
}
