# Task 3: CheckInService

**Files:**
- Create: `lib/services/check_in_service.dart`
- Modify: `lib/main.dart` (register beside `BookingFlowController`)
- Test: `test/check_in_service_test.dart`

**Interfaces:**
- Consumes: Task 1's models and `DemoData.preArrivalReservation`, `DemoData.defaultStayPreferences`, `DemoData.demoPassportNumber`.
- Produces: `CheckInService` with `static CheckInService get find`, fields `reservation`, `completed`, `identity`, `preferences`, `key`, `documentNumber`, `isPreArrival`; methods `markIdentityVerified(String documentNumber)`, `markArrivalTime(String label)`, `markSpecialRequests()`, `savePreferences(StayPreferences)`, `activateDigitalKey()`, `completeCheckIn()`; getters `completedCount`, `totalSteps`, `progress`, `isStepComplete(PreArrivalStep)`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_service_test.dart
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  test('opens at 1/4 with only contact details complete', () {
    final service = CheckInService.find;
    expect(service.completedCount, 1);
    expect(service.totalSteps, 4);
    expect(service.isStepComplete(PreArrivalStep.contactDetails), isTrue);
    expect(service.isStepComplete(PreArrivalStep.identity), isFalse);
    expect(service.progress, closeTo(0.25, 0.001));
  });

  test('verifying identity advances progress from 1/4 to 2/4', () {
    final service = CheckInService.find;
    service.markIdentityVerified('SY-20480831');

    expect(service.identity.value, IdentityStatus.verified);
    expect(service.documentNumber.value, 'SY-20480831');
    expect(service.isStepComplete(PreArrivalStep.identity), isTrue);
    expect(service.completedCount, 2);
    expect(service.progress, closeTo(0.5, 0.001));
  });

  test('marking a step twice does not double-count it', () {
    final service = CheckInService.find;
    service.markIdentityVerified('SY-20480831');
    service.markIdentityVerified('SY-20480831');
    expect(service.completedCount, 2);
  });

  test('completeCheckIn ends the pre-arrival state', () {
    final service = CheckInService.find;
    expect(service.isPreArrival.value, isTrue);
    service.completeCheckIn();
    expect(service.isPreArrival.value, isFalse);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_service_test.dart`
Expected: FAIL — `Target of URI doesn't exist: 'package:carlton/services/check_in_service.dart'`

- [ ] **Step 3: Write the service**

```dart
// lib/services/check_in_service.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:get/get.dart';

/// Single source of truth for pre-arrival and check-in state.
///
/// Registered permanently in `main.dart` beside `BookingFlowController`, so
/// both the Home tab and the check-in wizard read the same instance. Home is
/// the consumer (progress bar, checklist ticks); the wizard is the producer.
///
/// Demo-only: nothing persists. A restart returns to the seeded 1/4 state.
class CheckInService extends GetxService {
  static CheckInService get find => Get.find<CheckInService>();

  final Rx<ReservationSummary> reservation =
      Rx<ReservationSummary>(DemoData.preArrivalReservation);

  /// Seeded with `contactDetails` so Home opens at 1/4, matching Figma 75:133.
  final RxSet<PreArrivalStep> completed = <PreArrivalStep>{
    PreArrivalStep.contactDetails,
  }.obs;

  final Rx<IdentityStatus> identity = IdentityStatus.notStarted.obs;
  final RxString documentNumber = ''.obs;
  final Rx<StayPreferences> preferences =
      Rx<StayPreferences>(DemoData.defaultStayPreferences);
  final Rx<DigitalKeyStatus> key = DigitalKeyStatus.idle.obs;
  final RxString arrivalTimeLabel = ''.obs;

  /// True while the guest has a reservation they have not checked into.
  /// Home reads this to pick its pre-arrival section list.
  final RxBool isPreArrival = true.obs;

  int get totalSteps => PreArrivalStep.values.length;
  int get completedCount => completed.length;
  double get progress => completedCount / totalSteps;
  bool isStepComplete(PreArrivalStep step) => completed.contains(step);

  void markIdentityVerified(String number) {
    identity.value = IdentityStatus.verified;
    documentNumber.value = number;
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

  /// Simulates provisioning. The 2s delay is the only thing the real
  /// integration would replace.
  Future<void> activateDigitalKey() async {
    if (key.value != DigitalKeyStatus.idle) return;
    key.value = DigitalKeyStatus.activating;
    await Future<void>.delayed(DemoData.digitalKeyActivationDuration);
    if (isClosed) return;
    key.value = DigitalKeyStatus.activated;
  }

  /// Ends pre-arrival: Home falls back to its existing reservation state.
  void completeCheckIn() {
    completed.addAll(PreArrivalStep.values);
    isPreArrival.value = false;
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `flutter test test/check_in_service_test.dart`
Expected: PASS — 4 tests

- [ ] **Step 5: Register in main.dart**

In `lib/main.dart`, add the import and one line immediately after `Get.put(BookingFlowController(), permanent: true);` (currently line 28):

```dart
  Get.put(CheckInService(), permanent: true);
```

- [ ] **Step 6: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 7: Commit** *(only with authorization)*

```bash
git add lib/services/check_in_service.dart lib/main.dart test/check_in_service_test.dart
git commit -m "feat(check-in): add CheckInService as pre-arrival source of truth"
```

---

