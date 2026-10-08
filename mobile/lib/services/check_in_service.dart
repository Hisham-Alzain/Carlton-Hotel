import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/l10n/app_translations.dart';
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
/// Device-local: nothing persists across a restart, which returns to the 1/4
/// state and refetches the reservation from `GET /stays/upcoming`.
class CheckInService extends GetxService {
  static CheckInService get find => Get.find<CheckInService>();

  final Rx<ReservationSummary> reservation = Rx<ReservationSummary>(
    ReservationSummary.empty,
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

  /// The key the desk issued (`digital_key` on `GET /stays/upcoming`), shown
  /// once the guest taps the key button. Memory only: the code is
  /// display-only and must never be cached or logged.
  final Rxn<DigitalKey> digitalKey = Rxn<DigitalKey>();

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
    reservation.value = ReservationSummary.empty;
    _stay = null;
    completed
      ..clear()
      ..add(PreArrivalStep.contactDetails);
    identity.value = IdentityStatus.notStarted;
    documentNumber.value = '';
    documentImagePath.value = '';
    preferences.value = PreferenceOptions.defaultStayPreferences;
    key.value = DigitalKeyStatus.idle;
    digitalKey.value = null;
    arrivalSlot.value = null;
  }

  /// True when the guest holds a booking they have not yet checked in to — the
  /// window the pre-arrival checklist covers, and what Home switches its body
  /// on.
  ///
  /// Reads [MiddlewareService.homeState] rather than re-deriving it, so the
  /// check-in flow and Home can never disagree about which state the guest is
  /// in. Reading it inside an `Obx` subscribes
  /// to `MiddlewareService.guest`, which is what flips it after check-in.
  /// Widget tests construct this service without the session singleton, so an
  /// unregistered MiddlewareService reads as "no booking" instead of throwing.
  bool get isPreArrival {
    if (!Get.isRegistered<MiddlewareService>()) return false;
    return MiddlewareService.find.homeState == HomeViewState.preCheckIn;
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

  /// Records the ETA and sends it to the hotel
  /// (`POST /stays/{reservation}/online-check-in`, `arrival_time` as `HH:mm`
  /// hotel time), so reception sees it and the server's checklist step
  /// completes. Applied at once; put back if the server refuses, so an ETA the
  /// desk never received does not look saved. With no loaded booking there is
  /// nothing to send it to and it stays on the device.
  Future<void> submitArrivalTime(ArrivalSlot slot) async {
    final previous = arrivalSlot.value;
    final hadStep = completed.contains(PreArrivalStep.arrivalTime);
    markArrivalTime(slot);

    final stay = _stay;
    if (stay == null ||
        !Get.isRegistered<ApiService>() ||
        !Get.isRegistered<MiddlewareService>() ||
        !MiddlewareService.find.isAuthenticated) {
      return;
    }
    final hour = slot.hour.toString().padLeft(2, '0');
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/stays/${stay.uuid}/online-check-in',
      data: {'arrival_time': '$hour:00'},
      showErrorDialog: false,
    );
    if (isClosed || res.ok) return;

    arrivalSlot.value = previous;
    if (!hadStep) completed.remove(PreArrivalStep.arrivalTime);
    switch (res.error?.errorCode) {
      case ErrorCodes.reservationState:
        CustomSnackbars.showInfo(
          message: AppTranslations.onlineCheckInNotConfirmed,
        );
      case ErrorCodes.onlineCheckInClosed:
        CustomSnackbars.showError(message: AppTranslations.onlineCheckInClosed);
      default:
        if (res.error != null) ApiService.find.dialogs.showError(res.error!);
    }
  }

  void markSpecialRequests() => completed.add(PreArrivalStep.specialRequests);

  void savePreferences(StayPreferences next) {
    preferences.value = next;
    markSpecialRequests();
  }

  /// Re-reads the booking for the key the desk issued. The desk issues it when
  /// it approves the check-in, so before that the button goes back to idle and
  /// says so instead of pretending a key exists.
  Future<void> activateDigitalKey() async {
    if (key.value != DigitalKeyStatus.idle) return;
    key.value = DigitalKeyStatus.activating;
    await loadReservation();
    // The guest can leave the tab mid-activation.
    if (isClosed) return;
    if (digitalKey.value != null) {
      key.value = DigitalKeyStatus.activated;
      return;
    }
    key.value = DigitalKeyStatus.idle;
    CustomSnackbars.showInfo(message: AppTranslations.digitalKeyNotIssued);
  }

  /// The steps that actually gate check-in.
  ///
  /// Every checklist row, arrival time included: the hotel plans the room and
  /// the welcome around the guest's ETA. It stays reachable from the wizard —
  /// "Complete Check-In" opens the arrival-time sheet when it is missing
  /// (CheckInController.completeAndExit) — as well as from Home's checklist.
  static const Set<PreArrivalStep> requiredSteps = {
    PreArrivalStep.contactDetails,
    PreArrivalStep.identity,
    PreArrivalStep.arrivalTime,
    PreArrivalStep.specialRequests,
  };

  /// Every *required* checklist row done — the rule for being allowed to check
  /// in. See [requiredSteps].
  bool get isReadyToCheckIn => requiredSteps.every(completed.contains);

  /// The required steps still outstanding, in checklist order. Drives the
  /// message the wizard shows when the guest is turned away, so it names the
  /// step instead of failing silently.
  List<PreArrivalStep> get missingSteps => PreArrivalStep.values
      .where(requiredSteps.contains)
      .where((s) => !completed.contains(s))
      .toList();

  /// The stay [loadReservation] last fetched — what check-in would act on.
  UpcomingStay? _stay;

  /// Why check-in cannot be completed yet, or null when it can (or when the
  /// stay is unknown — the server then decides). Only the rule that always
  /// holds on `POST /stays/check-in` is checked here: a booking still awaiting
  /// the hotel cannot be checked in. The arrival-day rule is enforced by the
  /// server (`POST /stays/check-in` only finds stays whose check-in is today
  /// or earlier) and answered as `reservation_state`.
  String? get notOpenReason {
    final stay = _stay;
    if (stay == null) return null;
    if (stay.isAwaitingHotel) {
      return AppTranslations.checkInAwaitingConfirmation;
    }
    return null;
  }

  /// Loads the guest's own reservation for the wizard's booking panel.
  ///
  /// Best-effort: on failure [ReservationSummary.empty] stays, which is the
  /// same state the wizard opened in. Never surfaces a dialog — the guest came
  /// here to check in, and a fetch failure should not block that.
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
    if (!response.hasData) return;
    final stay = UpcomingStay.primary(UpcomingStay.listFromJson(response.data));
    if (stay == null) return;
    _stay = stay;
    digitalKey.value = stay.digitalKey;
    _seedFromServer(stay);
    reservation.value = ReservationSummary.fromUpcomingStay(
      stay,
      // Locale resolution belongs here, not in the model — see the factory.
      suiteName: stay.roomName.value,
      guest: MiddlewareService.find.guest.value,
    );
  }

  /// Marks the steps the server already recorded for this booking as done, so
  /// an app restart or a second phone does not send the guest back through
  /// them. Only adds: a step done on this device is never undone here.
  void _seedFromServer(UpcomingStay stay) {
    final done = stay.checklistDone;
    if (done.contains('documents_uploaded')) {
      identity.value = IdentityStatus.verified;
      completed.add(PreArrivalStep.identity);
    }
    if (done.contains('preferences_set')) {
      completed.add(PreArrivalStep.specialRequests);
    }
    // The server takes any HH:mm; only a time matching one of the app's slots
    // can be shown, so anything else leaves the step for the guest to pick.
    final hour = int.tryParse((stay.arrivalTime ?? '').split(':').first);
    final slot = ArrivalSlot.tryFromHour(hour);
    if (slot != null) {
      arrivalSlot.value ??= slot;
      completed.add(PreArrivalStep.arrivalTime);
    }
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
  /// The server's own words for the last [CheckInOutcome.notOpenOnServer]
  /// ("Check-in opens on your arrival day, …"), localized by Accept-Language.
  String? notOpenMessage;

  Future<CheckInOutcome> completeCheckIn() async {
    if (notOpenReason != null) return CheckInOutcome.notOpenYet;
    if (!isReadyToCheckIn) return CheckInOutcome.incomplete;

    final response = await ApiService.find.post<Map<String, dynamic>>(
      path: '/stays/check-in',
      showErrorDialog: false,
    );
    if (!response.ok) {
      final error = response.error;
      switch (error?.errorCode) {
        case ErrorCodes.reservationState:
          final message = error?.message ?? '';
          notOpenMessage = message.isEmpty ? null : message;
          return CheckInOutcome.notOpenOnServer;
        // No clean room of the booked type is free right now: the booking is
        // fine, the room is not ready — "no rooms for your dates" would scare.
        case ErrorCodes.noAvailability || ErrorCodes.roomOutOfOrder:
          CustomSnackbars.showInfo(
            message: AppTranslations.checkInRoomNotReady,
          );
        default:
          if (error != null) ApiService.find.dialogs.showError(error);
      }
      return CheckInOutcome.failed;
    }

    // has_booking / is_checked_in gate the in-stay sections Home is about to
    // render, so refresh them from /me before Home re-resolves — otherwise it
    // shows activeBooking while the folio and requests still read as absent.
    await MiddlewareService.find.checkToken();
    return CheckInOutcome.success;
  }
}
