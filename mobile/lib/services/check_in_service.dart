import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:get/get.dart';

/// Single source of truth for pre-arrival and check-in state.
///
/// Registered permanently in `main.dart` beside `BookingFlowController`, so
/// the Home tab and the check-in wizard read the same instance. Home is the
/// consumer (progress bar, checklist ticks); the wizard is the producer.
///
/// Demo-only: nothing persists. A restart returns to the seeded 1/4 state.
class CheckInService extends GetxService {
  static CheckInService get find => Get.find<CheckInService>();

  final Rx<ReservationSummary> reservation = Rx<ReservationSummary>(
    DemoData.preArrivalReservation,
  );

  /// Seeded with `contactDetails` so Home opens at 1/4, matching Figma 75:133.
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
    DemoData.defaultStayPreferences,
  );
  final Rx<DigitalKeyStatus> key = DigitalKeyStatus.idle.obs;
  final RxString arrivalTimeLabel = ''.obs;

  /// True while the guest has a reservation they have not checked into.
  /// Home reads this to pick its pre-arrival section list.
  final RxBool isPreArrival = true.obs;

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

  void markArrivalTime(String label) {
    arrivalTimeLabel.value = label;
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
    await Future<void>.delayed(DemoData.digitalKeyActivationDuration);
    // The guest can leave the tab mid-activation.
    if (isClosed) return;
    key.value = DigitalKeyStatus.activated;
  }

  /// Ends pre-arrival: Home falls back to its existing reservation state.
  void completeCheckIn() {
    completed.addAll(PreArrivalStep.values);
    isPreArrival.value = false;
  }
}
