# Task 1: Models and demo data

**Files:**
- Create: `lib/models/check_in/pre_arrival_step.dart`
- Create: `lib/models/check_in/check_in_enums.dart`
- Create: `lib/models/check_in/reservation_summary.dart`
- Create: `lib/models/check_in/stay_preferences.dart`
- Modify: `lib/constants/demo_data.dart`
- Test: `test/check_in_models_test.dart`

**Interfaces:**
- Consumes: `PreferenceOption` from `lib/models/preference_option.dart` (fields `id`, `label`, `iconAsset`, `icon`).
- Produces: `PreArrivalStep` (enum, 4 values), `IdentityStatus`, `DigitalKeyStatus`, `ScanStage`, `ReservationSummary`, `StayPreferences` with `copyWith`, `DemoData.preArrivalReservation`, `DemoData.defaultStayPreferences`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_models_test.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('PreArrivalStep has exactly the four home-checklist steps', () {
    expect(PreArrivalStep.values, hasLength(4));
    expect(PreArrivalStep.values, contains(PreArrivalStep.contactDetails));
    expect(PreArrivalStep.values, contains(PreArrivalStep.identity));
    expect(PreArrivalStep.values, contains(PreArrivalStep.arrivalTime));
    expect(PreArrivalStep.values, contains(PreArrivalStep.specialRequests));
  });

  test('demo reservation carries the Figma booking values', () {
    final r = DemoData.preArrivalReservation;
    expect(r.guestName, 'Ahmed Al-Hassan');
    expect(r.roomNumber, '812');
    expect(r.suiteName, 'Grand Damascus Suite');
    expect(r.bookingRef, '#CLT-0082');
    expect(r.floorLabel, '3rd floor');
  });

  test('StayPreferences.copyWith replaces only the named field', () {
    const base = StayPreferences(
      bedTypeId: 'king',
      pillowId: 'firm',
      mattressId: 'medium',
      smokingRoom: false,
      earlyCheckIn: true,
      lateCheckOut: false,
      extraPillows: false,
      notes: '',
    );
    final next = base.copyWith(smokingRoom: true);
    expect(next.smokingRoom, isTrue);
    expect(next.earlyCheckIn, isTrue);
    expect(next.bedTypeId, 'king');
    expect(next.notes, '');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_models_test.dart`
Expected: FAIL — `Target of URI doesn't exist: 'package:carlton/models/check_in/pre_arrival_step.dart'`

- [ ] **Step 3: Create the enums**

```dart
// lib/models/check_in/pre_arrival_step.dart

/// The four rows of the Home pre-arrival checklist (Figma `75:133`).
///
/// `contactDetails` is seeded complete, which is why Home opens at 1/4.
enum PreArrivalStep { contactDetails, identity, arrivalTime, specialRequests }
```

```dart
// lib/models/check_in/check_in_enums.dart

/// Identity tab state. `captured` is the scanner's success screen before the
/// guest confirms; tapping Continue there promotes it to `verified`. Only
/// `verified` enables the Identity tab CTA.
enum IdentityStatus { notStarted, captured, verified }

/// Digital key button states (Figma `75:1218`).
enum DigitalKeyStatus { idle, activating, activated }

/// Scanner stages (Figma `75:653`, `75:703`, `75:757`).
enum ScanStage { framing, scanning, success }
```

- [ ] **Step 4: Create the DTOs**

```dart
// lib/models/check_in/reservation_summary.dart

/// Immutable snapshot of the reservation the check-in flow operates on.
/// Demo-only for now; a real integration replaces the DemoData constant with
/// a JSON factory and nothing else changes.
class ReservationSummary {
  final String guestName;
  final String suiteName;
  final String roomNumber;
  final String floorLabel;
  final String checkInDate;
  final String checkInTime;
  final String checkOutDate;
  final String checkOutTime;
  final String stayRangeLabel;
  final String bookingRef;

  const ReservationSummary({
    required this.guestName,
    required this.suiteName,
    required this.roomNumber,
    required this.floorLabel,
    required this.checkInDate,
    required this.checkInTime,
    required this.checkOutDate,
    required this.checkOutTime,
    required this.stayRangeLabel,
    required this.bookingRef,
  });
}
```

```dart
// lib/models/check_in/stay_preferences.dart

/// Preferences tab payload (Figma `75:808`). Ids match the `PreferenceOption`
/// ids already in DemoData.bedOptions / pillowOptions / mattressOptions.
class StayPreferences {
  final String bedTypeId;
  final String pillowId;
  final String mattressId;
  final bool smokingRoom;
  final bool earlyCheckIn;
  final bool lateCheckOut;
  final bool extraPillows;
  final String notes;

  const StayPreferences({
    required this.bedTypeId,
    required this.pillowId,
    required this.mattressId,
    required this.smokingRoom,
    required this.earlyCheckIn,
    required this.lateCheckOut,
    required this.extraPillows,
    required this.notes,
  });

  StayPreferences copyWith({
    String? bedTypeId,
    String? pillowId,
    String? mattressId,
    bool? smokingRoom,
    bool? earlyCheckIn,
    bool? lateCheckOut,
    bool? extraPillows,
    String? notes,
  }) {
    return StayPreferences(
      bedTypeId: bedTypeId ?? this.bedTypeId,
      pillowId: pillowId ?? this.pillowId,
      mattressId: mattressId ?? this.mattressId,
      smokingRoom: smokingRoom ?? this.smokingRoom,
      earlyCheckIn: earlyCheckIn ?? this.earlyCheckIn,
      lateCheckOut: lateCheckOut ?? this.lateCheckOut,
      extraPillows: extraPillows ?? this.extraPillows,
      notes: notes ?? this.notes,
    );
  }
}
```

- [ ] **Step 5: Add the demo constants**

Append inside `abstract class DemoData` in `lib/constants/demo_data.dart`, and add the two imports at the top of that file.

```dart
  // ── Pre-arrival / check-in (Figma cpln3bQzXRnpkItKQkJCVs) ──
  static const preArrivalReservation = ReservationSummary(
    guestName: 'Ahmed Al-Hassan',
    suiteName: 'Grand Damascus Suite',
    roomNumber: '812',
    floorLabel: '3rd floor',
    checkInDate: 'Aug 14',
    checkInTime: 'From 3:00 PM',
    checkOutDate: 'Aug 16',
    checkOutTime: 'By 12:00 PM',
    stayRangeLabel: 'Grand Damascus Suite · Aug 14 – 16, 2026',
    bookingRef: '#CLT-0082',
  );

  /// Seeds the Preferences tab. Ids exist in bedOptions/pillowOptions/
  /// mattressOptions above.
  static const defaultStayPreferences = StayPreferences(
    bedTypeId: 'king',
    pillowId: 'firm',
    mattressId: 'medium',
    smokingRoom: false,
    earlyCheckIn: true,
    lateCheckOut: false,
    extraPillows: false,
    notes: '',
  );

  /// Passport still shown by the mocked scanner and the verified card.
  static const demoPassportNumber = 'SY-20480831';
  static const demoPassportAsset = 'assets/images/demo_passport.png';
  static const scanDuration = Duration(milliseconds: 1800);
  static const digitalKeyActivationDuration = Duration(seconds: 2);
```

Verify the seeded ids exist:
Run: `grep -nE "id: '(king|firm|medium)'" lib/constants/demo_data.dart`
If any is absent, use the first entry's id from that option list instead and update the test in Step 1 to match.

- [ ] **Step 6: Run test to verify it passes**

Run: `flutter test test/check_in_models_test.dart`
Expected: PASS — 3 tests

- [ ] **Step 7: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 8: Commit** *(only with authorization — see Global Constraints)*

```bash
git add lib/models/check_in lib/constants/demo_data.dart test/check_in_models_test.dart
git commit -m "feat(check-in): add pre-arrival models and demo reservation"
```

---

