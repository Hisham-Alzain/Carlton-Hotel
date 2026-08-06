# Task 1 review package (COMMIT-FREE — working-tree diff)

## Modified tracked files
```diff
diff --git a/mobile/lib/constants/demo_data.dart b/mobile/lib/constants/demo_data.dart
index 5d68bb3..39da832 100644
--- a/mobile/lib/constants/demo_data.dart
+++ b/mobile/lib/constants/demo_data.dart
@@ -1,4 +1,6 @@
 import 'package:carlton/models/booking_models.dart';
+import 'package:carlton/models/check_in/reservation_summary.dart';
+import 'package:carlton/models/check_in/stay_preferences.dart';
 import 'package:carlton/models/home_models.dart';
 import 'package:carlton/models/preference_option.dart';
 import 'package:carlton/models/service_item.dart';
@@ -402,4 +404,37 @@ abstract class DemoData {
       price: 45,
     ),
   ];
+
+  // ── Pre-arrival / check-in (Figma cpln3bQzXRnpkItKQkJCVs) ──
+  static const preArrivalReservation = ReservationSummary(
+    guestName: 'Ahmed Al-Hassan',
+    suiteName: 'Grand Damascus Suite',
+    roomNumber: '812',
+    floorLabel: '3rd floor',
+    checkInDate: 'Aug 14',
+    checkInTime: 'From 3:00 PM',
+    checkOutDate: 'Aug 16',
+    checkOutTime: 'By 12:00 PM',
+    stayRangeLabel: 'Grand Damascus Suite · Aug 14 – 16, 2026',
+    bookingRef: '#CLT-0082',
+  );
+
+  /// Seeds the Preferences tab. Ids exist in bedOptions/pillowOptions/
+  /// mattressOptions above.
+  static const defaultStayPreferences = StayPreferences(
+    bedTypeId: 'king',
+    pillowId: 'firm',
+    mattressId: 'medium',
+    smokingRoom: false,
+    earlyCheckIn: true,
+    lateCheckOut: false,
+    extraPillows: false,
+    notes: '',
+  );
+
+  /// Passport still shown by the mocked scanner and the verified card.
+  static const demoPassportNumber = 'SY-20480831';
+  static const demoPassportAsset = 'assets/images/demo_passport.png';
+  static const scanDuration = Duration(milliseconds: 1800);
+  static const digitalKeyActivationDuration = Duration(seconds: 2);
 }
```

## New untracked files (full contents)

### mobile/lib/models/check_in/pre_arrival_step.dart
```dart
/// The four rows of the Home pre-arrival checklist (Figma `75:133`).
///
/// `contactDetails` is seeded complete, which is why Home opens at 1/4.
enum PreArrivalStep { contactDetails, identity, arrivalTime, specialRequests }
```

### mobile/lib/models/check_in/check_in_enums.dart
```dart
/// Identity tab state. `captured` is the scanner's success screen before the
/// guest confirms; tapping Continue there promotes it to `verified`. Only
/// `verified` enables the Identity tab CTA.
enum IdentityStatus { notStarted, captured, verified }

/// Digital key button states (Figma `75:1218`).
enum DigitalKeyStatus { idle, activating, activated }

/// Scanner stages (Figma `75:653`, `75:703`, `75:757`).
enum ScanStage { framing, scanning, success }
```

### mobile/lib/models/check_in/reservation_summary.dart
```dart
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

### mobile/lib/models/check_in/stay_preferences.dart
```dart
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

### mobile/test/check_in_models_test.dart
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
