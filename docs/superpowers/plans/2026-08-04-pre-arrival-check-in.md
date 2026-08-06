# Pre-Arrival & Digital Check-In Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build all nine frames of Figma file `cpln3bQzXRnpkItKQkJCVs` — a pre-arrival home state and a three-tab digital check-in wizard — against demo data, with no backend work and no new dependencies.

**Architecture:** Nine Figma frames collapse to two routes plus one home state, because five of them are re-renders of two views. A `CheckInService` (permanent `GetxService`) owns reservation and step state; the wizard writes to it and the home screen observes it, so the pre-arrival progress bar is driven by real state rather than a hardcoded fraction. The ID scanner is a timed state machine over a static asset — no camera, no permissions.

**Tech Stack:** Flutter, GetX (`get ^4.7.3`), `flutter_svg ^2.3.0`, `get_storage ^2.1.1` (not used here), existing `AppColors` / `CustomDropdownField` / `CustomFilledButton` / `CustomBottomSheet`.

**Spec:** `docs/superpowers/specs/2026-08-04-pre-arrival-check-in-design.md`

## Global Constraints

Every task's requirements implicitly include this section.

- **Working directory is `mobile/`.** All commands run from there.
- **DO NOT COMMIT OR PUSH** without explicit user authorization. Commit steps are written for when that authorization is given; until then, stop at the step before and report.
- **Reactive style: `Rx` + `Obx`, not `GetBuilder` + `update()`.** `mobile/CLAUDE.md` still says this codebase uses `GetBuilder`; that line is stale. `HomeController` (428 lines) and `home_view.dart` (377 lines) — the most recently refactored code, and the code this feature integrates with — use `RxList`, `RxBool`, `Rx<T>` and `Obx`. Follow the code, not the doc.
- **`CheckInService` registers in `main.dart`, not a binding.** The spec said "InitialBinding"; the actual project pattern for a cross-screen service is `Get.put(..., permanent: true)` in `main.dart`, exactly as `BookingFlowController` does (`main.dart:28`). Follow `main.dart`.
- **All user-facing strings go through l10n.** Add the key to both `lib/l10n/local.dart` (`en` **and** `ar` maps) and `lib/l10n/app_translations.dart` (typed getter), then reference `AppTranslations.xxx` in views. Never write a raw `'key'.tr` or a bare literal in a widget.
- **Zero new colours.** Every colour comes from `lib/theme/app_colors.dart`. The six this feature needs: `primary` `#08414D`, `lagoonTeal` `#2F7D8E`, `antiqueGold` `#B8975A`, `cream` `#F0EBE2`, `successGreen` `#4CAF50`, `forestGreen` `#19541C`.
- **Zero new pub dependencies.** No camera, no OCR, no permission packages.
- **Typography: `Get.textTheme`.** Building a `TextStyle` from scratch is the violation; `Get.textTheme.titleMedium?.copyWith(fontFamily: 'DM Sans')` is house style.
- **Spacing: multiples of 5, default 10.** Use the `spacing:` parameter on `Row`/`Column` rather than `SizedBox` separators.
- **No `ApiService` calls in any new code.** The one exception is the pre-existing `/booking/pre-arrival-documents` route, reached unchanged from the scanner's Upload control.
- **Copy fixes (do not reproduce the Figma typos):** `Complete Check-In` (not "Ceck"), `Mattress Type` (not "Matress"), `Experiences` (not "Eperiences").
- **Verification gates:** `flutter analyze` must report **0 issues** (project baseline) and `flutter test` must pass. Report actual counts, never "should work".

---

## File Structure

| File | Responsibility |
|---|---|
| `lib/models/check_in/pre_arrival_step.dart` | The 4-step enum + labels |
| `lib/models/check_in/check_in_enums.dart` | `IdentityStatus`, `DigitalKeyStatus`, `ScanStage` |
| `lib/models/check_in/reservation_summary.dart` | Immutable reservation DTO |
| `lib/models/check_in/stay_preferences.dart` | Immutable preferences DTO + `copyWith` |
| `lib/services/check_in_service.dart` | Single source of truth for all check-in state |
| `lib/controllers/check_in/check_in_controller.dart` | Wizard step index + per-tab form state |
| `lib/controllers/check_in/scan_id_controller.dart` | Scanner state machine |
| `lib/views/check_in/check_in_view.dart` | 3-tab shell (PageView) |
| `lib/views/check_in/scan_id_view.dart` | Mocked scanner, 3 stages |
| `lib/components/check_in/check_in_tab_bar.dart` | Tab strip with lock rules |
| `lib/components/check_in/check_in_booking_panel.dart` | Cream 4-row booking card |
| `lib/components/check_in/digital_key_button.dart` | 3-state key button |
| `lib/components/check_in/arrival_time_sheet.dart` | Time-picker sheet (gap G1) |
| `lib/components/custom_toggle_tile.dart` | App-generic title/subtitle/Switch tile |
| `lib/views/home/home_view.dart` | +3 sections, +third const list |

---

### Task 1: Models and demo data

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

### Task 2: Localization strings

**Files:**
- Modify: `lib/l10n/local.dart`
- Modify: `lib/l10n/app_translations.dart`
- Test: `test/check_in_l10n_test.dart`

**Interfaces:**
- Produces: `AppTranslations.checkInTitle`, `.checkInTabIdentity`, `.checkInTabPreferences`, `.checkInTabRoomKey`, `.identityVerificationTitle`, `.identityVerificationSubtitle`, `.yourBooking`, `.guest`, `.room`, `.checkInLabel`, `.checkOutLabel`, `.tapToScanId`, `.passportOrNationalId`, `.scanId`, `.identitySecurityNote`, `.continueToPreferences`, `.identityVerified`, `.scanYourId`, `.positionIdInFrame`, `.scanning`, `.dontMoveId`, `.scanSuccessful`, `.capturedIdDetails`, `.frontOfId`, `.edit`, `.makeSureDetailsClear`, `.continueLabel`, `.scanAgain`, `.flash`, `.scan`, `.upload`, `.bedType`, `.pillow`, `.mattressType`, `.roomType`, `.smokingRoom`, `.smokingRoomSubtitle`, `.earlyCheckIn`, `.earlyCheckInSubtitle`, `.lateCheckOut`, `.lateCheckOutSubtitle`, `.extraPillows`, `.extraPillowsSubtitle`, `.specialRequests`, `.specialRequestsHint`, `.yourRoomKey`, `.roomKeySubtitle`, `.keyCardWaitingTitle`, `.keyCardWaitingBody`, `.addDigitalKey`, `.activatingDigitalKey`, `.activatedDigitalKey`, `.digitalKeyHint`, `.completeCheckIn`, `.preCheckInAvailable`, `.roomChip`, `.bookingRef`, `.preArrivalProgress`, `.completeFraction`, `.checkInNow`, `.preArrivalChecklist`, `.confirmContactDetails`, `.completed`, `.uploadIdOrPassport`, `.requiredForCheckIn`, `.setArrivalTime`, `.tapToAddEta`, `.optionalPreferences`, `.airportTransfer`, `.airportTransferBody`, `.requestAirportTransfer`, `.selectArrivalTime`, `.save`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_l10n_test.dart
import 'package:carlton/l10n/local.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the l10n rule: every check-in key must exist in BOTH locales.
/// A key added to `en` only would silently render its raw id in Arabic.
void main() {
  test('every check-in key exists in en and ar', () {
    final translations = Local().keys;
    final en = translations['en_US'] ?? translations['en']!;
    final ar = translations['ar_SA'] ?? translations['ar']!;

    const required = <String>[
      'checkIn.title',
      'checkIn.tabIdentity',
      'checkIn.tabPreferences',
      'checkIn.tabRoomKey',
      'checkIn.completeCheckIn',
      'checkIn.addDigitalKey',
      'checkIn.scanYourId',
      'preArrival.checklist',
      'preArrival.checkInNow',
    ];

    for (final key in required) {
      expect(en.containsKey(key), isTrue, reason: 'missing in en: $key');
      expect(ar.containsKey(key), isTrue, reason: 'missing in ar: $key');
    }
  });
}
```

- [ ] **Step 2: Confirm the locale map keys before running**

Run: `grep -nE "'(en|ar)[_A-Z]*':" lib/l10n/local.dart`
Note the exact locale strings (e.g. `'en_US'` vs `'en'`) and, if they differ from the test, edit the two lookups in Step 1 to match. Also confirm the class name is `Local`:
Run: `grep -n "class .*Translations" lib/l10n/local.dart`

- [ ] **Step 3: Run test to verify it fails**

Run: `flutter test test/check_in_l10n_test.dart`
Expected: FAIL — `missing in en: checkIn.title`

- [ ] **Step 4: Add keys to both locale maps**

In `lib/l10n/local.dart`, add to the `en` map:

```dart
      // ── Check-in wizard ──
      'checkIn.title': 'Check-In',
      'checkIn.tabIdentity': 'Identity',
      'checkIn.tabPreferences': 'Preferences',
      'checkIn.tabRoomKey': 'Room Key',
      'checkIn.identityTitle': 'Identity Verification',
      'checkIn.identitySubtitle':
          'We need to verify your identity to complete check-in. Please have your passport or national ID ready.',
      'checkIn.yourBooking': 'YOUR BOOKING',
      'checkIn.guest': 'Guest',
      'checkIn.room': 'Room',
      'checkIn.checkInLabel': 'Check-in',
      'checkIn.checkOutLabel': 'Check-out',
      'checkIn.tapToScanId': 'Tap to scan your ID',
      'checkIn.passportOrNationalId': 'Passport or National ID card',
      'checkIn.scanId': 'Scan ID',
      'checkIn.securityNote':
          'Your identity is verified securely. We do not store copies of your documents beyond check-in.',
      'checkIn.continueToPreferences': 'Continue to Preferences',
      'checkIn.identityVerified': 'Identity Verified',
      'checkIn.scanYourId': 'Scan Your ID',
      'checkIn.positionIdInFrame': 'Position the front of your ID in the frame',
      'checkIn.scanning': 'Scanning…',
      'checkIn.dontMoveId': "Please don't move your ID",
      'checkIn.scanSuccessful': 'Scan successful!',
      'checkIn.capturedIdDetails': "We've captured your ID details.",
      'checkIn.frontOfId': 'Front of ID',
      'checkIn.edit': 'Edit',
      'checkIn.makeSureDetailsClear':
          'Make sure the details are clear and easy to read.',
      'checkIn.continueLabel': 'Continue',
      'checkIn.scanAgain': 'Scan again',
      'checkIn.flash': 'Flash',
      'checkIn.scan': 'Scan',
      'checkIn.upload': 'Upload',
      'checkIn.bedType': 'Bed Type',
      'checkIn.pillow': 'Pillow',
      'checkIn.mattressType': 'Mattress Type',
      'checkIn.roomType': 'Room Type',
      'checkIn.smokingRoom': 'Smoking Room',
      'checkIn.smokingRoomSubtitle': 'Request smoking-permitted room',
      'checkIn.earlyCheckIn': 'Early Check-in',
      'checkIn.earlyCheckInSubtitle': 'Request early check-in when available',
      'checkIn.lateCheckOut': 'Late Check-out',
      'checkIn.lateCheckOutSubtitle': 'Request late check-out when available',
      'checkIn.extraPillows': 'Extra Pillows',
      'checkIn.extraPillowsSubtitle': 'Request Extra Pillows',
      'checkIn.specialRequests': 'Special Requests',
      'checkIn.specialRequestsHint': 'Any other requests or notes for our team…',
      'checkIn.yourRoomKey': 'Your Room Key',
      'checkIn.roomKeySubtitle':
          'Everything is settled. Add your key to this phone and you can go straight up when you arrive',
      'checkIn.keyCardWaitingTitle': 'A key Card will be waiting for you',
      'checkIn.keyCardWaitingBody':
          "At Reception, or brought to your suite. You don't need to ask.",
      'checkIn.addDigitalKey': 'Add Digital Key to phone',
      'checkIn.activatingDigitalKey': 'Activating Digital Key…',
      'checkIn.activatedDigitalKey': 'Activated Digital Key',
      'checkIn.digitalKeyHint': 'Works with phone locked · tap and hold',
      'checkIn.completeCheckIn': 'Complete Check-In',
      'checkIn.selectArrivalTime': 'Select your arrival time',
      'checkIn.save': 'Save',
      // ── Home pre-arrival ──
      'preArrival.available': 'PRE-CHECK-IN AVAILABLE',
      'preArrival.roomChip': 'ROOM @number',
      'preArrival.bookingRef': 'BOOKING REF',
      'preArrival.progress': 'Pre-arrival progress',
      'preArrival.completeFraction': '@done/@total complete',
      'preArrival.checkInNow': 'Check In Now',
      'preArrival.checklist': 'Pre-Arrival Checklist',
      'preArrival.confirmContactDetails': 'Confirm contact details',
      'preArrival.completed': 'Completed',
      'preArrival.uploadIdOrPassport': 'Upload ID or Passport',
      'preArrival.requiredForCheckIn': 'Required for check-in',
      'preArrival.setArrivalTime': 'Set arrival time',
      'preArrival.tapToAddEta': 'Tap to add your ETA',
      'preArrival.specialRequests': 'Special requests',
      'preArrival.optionalPreferences': 'Optional preferences & notes',
      'preArrival.airportTransfer': 'Airport Transfer',
      'preArrival.airportTransferBody':
          'Request pickup from Damascus International Airport',
      'preArrival.requestAirportTransfer': 'Request Airport Transfer',
```

Add the matching `ar` entries:

```dart
      // ── Check-in wizard ──
      'checkIn.title': 'تسجيل الوصول',
      'checkIn.tabIdentity': 'الهوية',
      'checkIn.tabPreferences': 'التفضيلات',
      'checkIn.tabRoomKey': 'مفتاح الغرفة',
      'checkIn.identityTitle': 'التحقق من الهوية',
      'checkIn.identitySubtitle':
          'نحتاج إلى التحقق من هويتك لإتمام تسجيل الوصول. يرجى تجهيز جواز سفرك أو هويتك الوطنية.',
      'checkIn.yourBooking': 'حجزك',
      'checkIn.guest': 'الضيف',
      'checkIn.room': 'الغرفة',
      'checkIn.checkInLabel': 'الوصول',
      'checkIn.checkOutLabel': 'المغادرة',
      'checkIn.tapToScanId': 'اضغط لمسح هويتك',
      'checkIn.passportOrNationalId': 'جواز السفر أو بطاقة الهوية الوطنية',
      'checkIn.scanId': 'مسح الهوية',
      'checkIn.securityNote':
          'يتم التحقق من هويتك بشكل آمن. لا نحتفظ بنسخ من مستنداتك بعد تسجيل الوصول.',
      'checkIn.continueToPreferences': 'المتابعة إلى التفضيلات',
      'checkIn.identityVerified': 'تم التحقق من الهوية',
      'checkIn.scanYourId': 'امسح هويتك',
      'checkIn.positionIdInFrame': 'ضع وجه الهوية داخل الإطار',
      'checkIn.scanning': 'جارٍ المسح…',
      'checkIn.dontMoveId': 'يرجى عدم تحريك الهوية',
      'checkIn.scanSuccessful': 'تم المسح بنجاح!',
      'checkIn.capturedIdDetails': 'لقد التقطنا بيانات هويتك.',
      'checkIn.frontOfId': 'وجه الهوية',
      'checkIn.edit': 'تعديل',
      'checkIn.makeSureDetailsClear': 'تأكد من أن البيانات واضحة وسهلة القراءة.',
      'checkIn.continueLabel': 'متابعة',
      'checkIn.scanAgain': 'إعادة المسح',
      'checkIn.flash': 'الفلاش',
      'checkIn.scan': 'مسح',
      'checkIn.upload': 'رفع',
      'checkIn.bedType': 'نوع السرير',
      'checkIn.pillow': 'الوسادة',
      'checkIn.mattressType': 'نوع المرتبة',
      'checkIn.roomType': 'نوع الغرفة',
      'checkIn.smokingRoom': 'غرفة للمدخنين',
      'checkIn.smokingRoomSubtitle': 'طلب غرفة يُسمح فيها بالتدخين',
      'checkIn.earlyCheckIn': 'وصول مبكر',
      'checkIn.earlyCheckInSubtitle': 'طلب وصول مبكر عند توفره',
      'checkIn.lateCheckOut': 'مغادرة متأخرة',
      'checkIn.lateCheckOutSubtitle': 'طلب مغادرة متأخرة عند توفرها',
      'checkIn.extraPillows': 'وسائد إضافية',
      'checkIn.extraPillowsSubtitle': 'طلب وسائد إضافية',
      'checkIn.specialRequests': 'طلبات خاصة',
      'checkIn.specialRequestsHint': 'أي طلبات أو ملاحظات أخرى لفريقنا…',
      'checkIn.yourRoomKey': 'مفتاح غرفتك',
      'checkIn.roomKeySubtitle':
          'كل شيء جاهز. أضف مفتاحك إلى هذا الهاتف لتصعد مباشرة عند وصولك',
      'checkIn.keyCardWaitingTitle': 'بطاقة المفتاح بانتظارك',
      'checkIn.keyCardWaitingBody':
          'في الاستقبال، أو تُسلَّم إلى جناحك. لا داعي للطلب.',
      'checkIn.addDigitalKey': 'أضف المفتاح الرقمي إلى الهاتف',
      'checkIn.activatingDigitalKey': 'جارٍ تفعيل المفتاح الرقمي…',
      'checkIn.activatedDigitalKey': 'تم تفعيل المفتاح الرقمي',
      'checkIn.digitalKeyHint': 'يعمل والهاتف مقفل · اضغط مع الاستمرار',
      'checkIn.completeCheckIn': 'إتمام تسجيل الوصول',
      'checkIn.selectArrivalTime': 'اختر وقت وصولك',
      'checkIn.save': 'حفظ',
      // ── Home pre-arrival ──
      'preArrival.available': 'تسجيل الوصول المسبق متاح',
      'preArrival.roomChip': 'غرفة @number',
      'preArrival.bookingRef': 'رقم الحجز',
      'preArrival.progress': 'تقدّم ما قبل الوصول',
      'preArrival.completeFraction': 'اكتمل @done من @total',
      'preArrival.checkInNow': 'سجّل وصولك الآن',
      'preArrival.checklist': 'قائمة ما قبل الوصول',
      'preArrival.confirmContactDetails': 'تأكيد بيانات التواصل',
      'preArrival.completed': 'مكتمل',
      'preArrival.uploadIdOrPassport': 'رفع الهوية أو جواز السفر',
      'preArrival.requiredForCheckIn': 'مطلوب لتسجيل الوصول',
      'preArrival.setArrivalTime': 'تحديد وقت الوصول',
      'preArrival.tapToAddEta': 'اضغط لإضافة وقت وصولك',
      'preArrival.specialRequests': 'طلبات خاصة',
      'preArrival.optionalPreferences': 'تفضيلات وملاحظات اختيارية',
      'preArrival.airportTransfer': 'التوصيل من المطار',
      'preArrival.airportTransferBody': 'اطلب توصيلة من مطار دمشق الدولي',
      'preArrival.requestAirportTransfer': 'اطلب التوصيل من المطار',
```

- [ ] **Step 5: Add the typed getters**

In `lib/l10n/app_translations.dart`, matching the file's existing getter style:

```dart
  // ── Check-in wizard ──
  static String get checkInTitle => 'checkIn.title'.tr;
  static String get checkInTabIdentity => 'checkIn.tabIdentity'.tr;
  static String get checkInTabPreferences => 'checkIn.tabPreferences'.tr;
  static String get checkInTabRoomKey => 'checkIn.tabRoomKey'.tr;
  static String get identityTitle => 'checkIn.identityTitle'.tr;
  static String get identitySubtitle => 'checkIn.identitySubtitle'.tr;
  static String get yourBooking => 'checkIn.yourBooking'.tr;
  static String get guestLabel => 'checkIn.guest'.tr;
  static String get roomLabel => 'checkIn.room'.tr;
  static String get checkInLabel => 'checkIn.checkInLabel'.tr;
  static String get checkOutLabel => 'checkIn.checkOutLabel'.tr;
  static String get tapToScanId => 'checkIn.tapToScanId'.tr;
  static String get passportOrNationalId => 'checkIn.passportOrNationalId'.tr;
  static String get scanId => 'checkIn.scanId'.tr;
  static String get identitySecurityNote => 'checkIn.securityNote'.tr;
  static String get continueToPreferences => 'checkIn.continueToPreferences'.tr;
  static String get identityVerified => 'checkIn.identityVerified'.tr;
  static String get scanYourId => 'checkIn.scanYourId'.tr;
  static String get positionIdInFrame => 'checkIn.positionIdInFrame'.tr;
  static String get scanningLabel => 'checkIn.scanning'.tr;
  static String get dontMoveId => 'checkIn.dontMoveId'.tr;
  static String get scanSuccessful => 'checkIn.scanSuccessful'.tr;
  static String get capturedIdDetails => 'checkIn.capturedIdDetails'.tr;
  static String get frontOfId => 'checkIn.frontOfId'.tr;
  static String get editLabel => 'checkIn.edit'.tr;
  static String get makeSureDetailsClear => 'checkIn.makeSureDetailsClear'.tr;
  static String get continueLabel => 'checkIn.continueLabel'.tr;
  static String get scanAgain => 'checkIn.scanAgain'.tr;
  static String get flashLabel => 'checkIn.flash'.tr;
  static String get scanLabel => 'checkIn.scan'.tr;
  static String get uploadLabel => 'checkIn.upload'.tr;
  static String get bedType => 'checkIn.bedType'.tr;
  static String get pillowLabel => 'checkIn.pillow'.tr;
  static String get mattressType => 'checkIn.mattressType'.tr;
  static String get roomType => 'checkIn.roomType'.tr;
  static String get smokingRoom => 'checkIn.smokingRoom'.tr;
  static String get smokingRoomSubtitle => 'checkIn.smokingRoomSubtitle'.tr;
  static String get earlyCheckIn => 'checkIn.earlyCheckIn'.tr;
  static String get earlyCheckInSubtitle => 'checkIn.earlyCheckInSubtitle'.tr;
  static String get lateCheckOut => 'checkIn.lateCheckOut'.tr;
  static String get lateCheckOutSubtitle => 'checkIn.lateCheckOutSubtitle'.tr;
  static String get extraPillows => 'checkIn.extraPillows'.tr;
  static String get extraPillowsSubtitle => 'checkIn.extraPillowsSubtitle'.tr;
  static String get specialRequestsTitle => 'checkIn.specialRequests'.tr;
  static String get specialRequestsHint => 'checkIn.specialRequestsHint'.tr;
  static String get yourRoomKey => 'checkIn.yourRoomKey'.tr;
  static String get roomKeySubtitle => 'checkIn.roomKeySubtitle'.tr;
  static String get keyCardWaitingTitle => 'checkIn.keyCardWaitingTitle'.tr;
  static String get keyCardWaitingBody => 'checkIn.keyCardWaitingBody'.tr;
  static String get addDigitalKey => 'checkIn.addDigitalKey'.tr;
  static String get activatingDigitalKey => 'checkIn.activatingDigitalKey'.tr;
  static String get activatedDigitalKey => 'checkIn.activatedDigitalKey'.tr;
  static String get digitalKeyHint => 'checkIn.digitalKeyHint'.tr;
  static String get completeCheckIn => 'checkIn.completeCheckIn'.tr;
  static String get selectArrivalTime => 'checkIn.selectArrivalTime'.tr;
  static String get saveLabel => 'checkIn.save'.tr;

  // ── Home pre-arrival ──
  static String get preCheckInAvailable => 'preArrival.available'.tr;
  static String roomChip(String number) =>
      'preArrival.roomChip'.trParams({'number': number});
  static String get bookingRefLabel => 'preArrival.bookingRef'.tr;
  static String get preArrivalProgress => 'preArrival.progress'.tr;
  static String completeFraction(int done, int total) =>
      'preArrival.completeFraction'
          .trParams({'done': '$done', 'total': '$total'});
  static String get checkInNow => 'preArrival.checkInNow'.tr;
  static String get preArrivalChecklist => 'preArrival.checklist'.tr;
  static String get confirmContactDetails =>
      'preArrival.confirmContactDetails'.tr;
  static String get completedLabel => 'preArrival.completed'.tr;
  static String get uploadIdOrPassport => 'preArrival.uploadIdOrPassport'.tr;
  static String get requiredForCheckIn => 'preArrival.requiredForCheckIn'.tr;
  static String get setArrivalTime => 'preArrival.setArrivalTime'.tr;
  static String get tapToAddEta => 'preArrival.tapToAddEta'.tr;
  static String get preArrivalSpecialRequests =>
      'preArrival.specialRequests'.tr;
  static String get optionalPreferences => 'preArrival.optionalPreferences'.tr;
  static String get airportTransfer => 'preArrival.airportTransfer'.tr;
  static String get airportTransferBody => 'preArrival.airportTransferBody'.tr;
  static String get requestAirportTransfer =>
      'preArrival.requestAirportTransfer'.tr;
```

- [ ] **Step 6: Run test to verify it passes**

Run: `flutter test test/check_in_l10n_test.dart`
Expected: PASS — 1 test

- [ ] **Step 7: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 8: Commit** *(only with authorization)*

```bash
git add lib/l10n test/check_in_l10n_test.dart
git commit -m "feat(check-in): add en/ar strings for pre-arrival and check-in"
```

---

### Task 3: CheckInService

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

### Task 4: Icon audit and extraction

**Files:**
- Create: `assets/icons/chk_*.svg` (only the gaps)
- Create: `assets/images/demo_passport.png`
- Modify: none — `assets/icons/` is already a directory glob in `pubspec.yaml:52`

**Interfaces:**
- Produces: asset paths referenced by Tasks 6–14.

**Audit result.** The existing 96 icons already cover most of this feature. Reuse these directly:

| Need | Existing asset |
|---|---|
| Bed type options | `king_bed.svg`, `kingbed.svg`, `queenbed.svg`, `singlebed.svg`, `doublebed.svg`, `twinbeds.svg`, `extrabed.svg` |
| Pillow options | `firmpillow.svg`, `softpillow.svg`, `featherpillow.svg` |
| Mattress options | `firmmattress.svg`, `mediummattress.svg`, `softmattress.svg`, `hotelstandardmattress.svg`, `memoryfoammattress.svg`, `orthopedicmattress.svg` |
| Checklist: completed tick | `check.svg` |
| Checklist: arrival time | `clock.svg` |
| Checklist: special requests | `act_request.svg` |
| Security note, info rows | `info.svg` |
| Booking ref / room tiles | `calendar.svg`, `bed.svg` |
| Concierge card | `concierge_spark.svg` |
| Dining / experiences cards | `clock.svg`, `location.svg`, `star.svg`, `rating.svg`, `cuisine.svg` |
| Dropdown chevron | `date_chevron.svg` |

**Gaps to extract** — these have no existing equivalent:

| New asset | Used by |
|---|---|
| `chk_id_card.svg` | Identity tab header chip, scanner Scan control |
| `chk_scan_frame.svg` | "Tap to scan your ID" card |
| `chk_flash.svg` | Scanner Flash control |
| `chk_upload.svg` | Scanner Upload control, checklist ID row |
| `chk_key.svg` | Room Key tab header chip |
| `chk_phone.svg` | Digital key button |
| `chk_hamburger.svg` | Home header (only if `home_view.dart` has no existing one) |
| `chk_airport_transfer.svg` | Airport Transfer card (only if the design uses a vector, not the raster) |

- [ ] **Step 1: Confirm which gaps are real**

Run: `ls assets/icons | grep -iE "key|flash|phone|upload|scan|id_|menu|burger"`
Any hit means that row is not a gap — reuse the existing file and drop it from the extraction list. `download.svg` is a *download* arrow; do not reuse it for upload unless it is direction-agnostic when opened.

- [ ] **Step 2: Extract the remaining gaps**

Use the Figma MCP `download_assets` tool against file `cpln3bQzXRnpkItKQkJCVs` for the node containing each glyph, taking the `svgAssets` entries. Node references: Identity header chip is inside `75:463`; the scanner control row is inside `75:653`; the Room Key header chip is inside `75:928`; the digital key button is `75:1218`.

- [ ] **Step 3: Inline the `var()` fallbacks — this step is mandatory**

Figma returns these SVGs with fills like `fill="var(--stroke-0, #08414D)"`. **`flutter_svg` renders nothing for a `var()`** — the icon will silently draw as empty space. Substitute the fallback hex in every downloaded file:

```bash
cd assets/icons
sed -i -E 's/var\(--[a-zA-Z0-9_-]+, *(#[0-9a-fA-F]{3,8})\)/\1/g' chk_*.svg
grep -l "var(--" chk_*.svg   # must print nothing
```

- [ ] **Step 4: Add the demo passport image**

Export node `75:757`'s captured-ID card image (or the passport inside `75:653`) as PNG at scale 2 via `download_assets`, and save it to `assets/images/demo_passport.png`. This single asset backs all three scanner stages.

- [ ] **Step 5: Verify every asset loads**

Run: `flutter analyze`
Expected: 0 issues

Then confirm each new file is non-empty and well-formed:

```bash
for f in assets/icons/chk_*.svg; do echo "$f $(wc -c < "$f")"; done
```

Expected: every file > 100 bytes.

- [ ] **Step 6: Commit** *(only with authorization)*

```bash
git add assets/icons assets/images/demo_passport.png
git commit -m "feat(check-in): extract check-in icons and demo passport asset"
```

---

### Task 5: Routes, bindings and view shells

**Files:**
- Create: `lib/controllers/check_in/check_in_controller.dart` (minimal)
- Create: `lib/views/check_in/check_in_view.dart` (minimal)
- Create: `lib/views/check_in/scan_id_view.dart` (minimal)
- Modify: `lib/routes/routes.dart`
- Modify: `lib/bindings/binding.dart`
- Test: `test/check_in_routes_test.dart`

**Interfaces:**
- Consumes: `CheckInService` from Task 3.
- Produces: `Routes.checkIn` = `'/check-in'`, `Routes.scanId` = `'/check-in/scan-id'`, `CheckInBinding`, `ScanIdBinding`, `CheckInController` with `RxInt activeTab` and `void goToTab(int)`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_routes_test.dart
import 'package:carlton/routes/routes.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('check-in routes are registered exactly once', () {
    final names = Pages.getPages.map((p) => p.name).toList();
    expect(names, contains(Routes.checkIn));
    expect(names, contains(Routes.scanId));
    expect(names.where((n) => n == Routes.checkIn), hasLength(1));
    expect(names.where((n) => n == Routes.scanId), hasLength(1));
  });

  test('check-in route paths match the spec', () {
    expect(Routes.checkIn, '/check-in');
    expect(Routes.scanId, '/check-in/scan-id');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_routes_test.dart`
Expected: FAIL — `The getter 'checkIn' isn't defined for the class 'Routes'`

- [ ] **Step 3: Write the minimal controller**

```dart
// lib/controllers/check_in/check_in_controller.dart
import 'package:carlton/services/check_in_service.dart';
import 'package:get/get.dart';

/// Drives the three-tab wizard.
///
/// Deliberately NOT a TabController: a TabController owned by a type-keyed
/// singleton outlives the widget that created its ticker and crashes on
/// re-entry. The tabs here are a progress indicator driven by the Continue
/// buttons, so a plain RxInt + PageView is both safer and closer to the design.
class CheckInController extends GetxController {
  final CheckInService service = CheckInService.find;
  final PageController pageController = PageController();

  final RxInt activeTab = 0.obs;

  /// Highest tab the guest has reached. Completed tabs are tappable to go
  /// back; unvisited tabs are locked.
  final RxInt furthestTab = 0.obs;

  bool isTabUnlocked(int index) => index <= furthestTab.value;

  void goToTab(int index) {
    if (!isTabUnlocked(index)) return;
    activeTab.value = index;
    pageController.jumpToPage(index);
  }

  void advanceTo(int index) {
    if (index > furthestTab.value) furthestTab.value = index;
    goToTab(index);
  }

  @override
  void onClose() {
    pageController.dispose();
    super.onClose();
  }
}
```

Add `import 'package:flutter/material.dart';` at the top for `PageController`.

- [ ] **Step 4: Write the minimal views**

```dart
// lib/views/check_in/check_in_view.dart
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CheckInView extends GetView<CheckInController> {
  const CheckInView({super.key});

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: SizedBox.shrink());
}
```

```dart
// lib/views/check_in/scan_id_view.dart
import 'package:flutter/material.dart';

class ScanIdView extends StatelessWidget {
  const ScanIdView({super.key});

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: SizedBox.shrink());
}
```

- [ ] **Step 5: Register routes and bindings**

In `lib/routes/routes.dart`, add to `abstract class Routes` after `preArrivalDocuments`:

```dart
  static const checkIn = '/check-in';
  static const scanId = '/check-in/scan-id';
```

and to `Pages.getPages` before the closing `];`:

```dart
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
```

In `lib/bindings/binding.dart`, append:

```dart
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
```

`ScanIdController` does not exist until Task 9. Until then, comment out the `ScanIdBinding` body's single line and the `scanId` `GetPage`'s `binding:` argument, or create `ScanIdController` as an empty `GetxController` now and fill it in Task 9. **Prefer the latter** — an empty class keeps the route wired and the build green.

```dart
// lib/controllers/check_in/scan_id_controller.dart — filled in Task 9
import 'package:get/get.dart';

class ScanIdController extends GetxController {}
```

Add all four imports to `routes.dart` and `binding.dart`.

- [ ] **Step 6: Run test to verify it passes**

Run: `flutter test test/check_in_routes_test.dart`
Expected: PASS — 2 tests

- [ ] **Step 7: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 8: Commit** *(only with authorization)*

```bash
git add lib/routes lib/bindings lib/views/check_in lib/controllers/check_in test/check_in_routes_test.dart
git commit -m "feat(check-in): wire /check-in and /check-in/scan-id routes"
```

---

### Task 6: Wizard chrome — tab bar and booking panel

**Files:**
- Create: `lib/components/check_in/check_in_tab_bar.dart`
- Create: `lib/components/check_in/check_in_booking_panel.dart`
- Test: `test/check_in_tab_bar_test.dart`

**Interfaces:**
- Consumes: `CheckInController.activeTab`, `.furthestTab`, `.isTabUnlocked`, `.goToTab`; `ReservationSummary`; `AppTranslations` getters from Task 2.
- Produces: `CheckInTabBar({required int activeIndex, required bool Function(int) isUnlocked, required ValueChanged<int> onTap})`, `CheckInBookingPanel({required ReservationSummary reservation})`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_tab_bar_test.dart
import 'package:carlton/components/check_in/check_in_tab_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  testWidgets('locked tabs do not fire onTap', (tester) async {
    var tapped = -1;
    await tester.pumpWidget(
      GetMaterialApp(
        home: Scaffold(
          body: CheckInTabBar(
            activeIndex: 0,
            isUnlocked: (i) => i == 0,
            onTap: (i) => tapped = i,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Room Key'));
    await tester.pump();
    expect(tapped, -1, reason: 'locked tab must be inert');

    await tester.tap(find.text('Identity'));
    await tester.pump();
    expect(tapped, 0);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_tab_bar_test.dart`
Expected: FAIL — `Target of URI doesn't exist: '.../check_in_tab_bar.dart'`

- [ ] **Step 3: Write the tab bar**

```dart
// lib/components/check_in/check_in_tab_bar.dart
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Wizard progress strip (Figma 75:463 / 75:808 / 75:928).
///
/// Not a TabBar — see CheckInController for why. Locked tabs render muted and
/// swallow taps rather than being removed, so the guest can see what is coming.
class CheckInTabBar extends StatelessWidget {
  final int activeIndex;
  final bool Function(int) isUnlocked;
  final ValueChanged<int> onTap;

  const CheckInTabBar({
    required this.activeIndex,
    required this.isUnlocked,
    required this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final labels = <String>[
      AppTranslations.checkInTabIdentity,
      AppTranslations.checkInTabPreferences,
      AppTranslations.checkInTabRoomKey,
    ];

    return Row(
      children: List<Widget>.generate(labels.length, (index) {
        final active = index == activeIndex;
        final unlocked = isUnlocked(index);
        return Expanded(
          child: InkWell(
            onTap: unlocked ? () => onTap(index) : null,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Column(
                spacing: 10,
                children: [
                  Text(
                    labels[index],
                    textAlign: TextAlign.center,
                    style: Get.textTheme.titleSmall?.copyWith(
                      color: active
                          ? AppColors.primary
                          : unlocked
                              ? AppColors.taupeBrown
                              : AppColors.stoneTaupe,
                      fontWeight:
                          active ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                  Container(
                    height: 2,
                    color: active
                        ? AppColors.primary
                        : AppColors.primary00,
                  ),
                ],
              ),
            ),
          ),
        );
      }),
    );
  }
}
```

- [ ] **Step 4: Write the booking panel**

```dart
// lib/components/check_in/check_in_booking_panel.dart
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The cream "YOUR BOOKING" card shared by both Identity states
/// (Figma 75:463 and 75:565).
class CheckInBookingPanel extends StatelessWidget {
  final ReservationSummary reservation;

  const CheckInBookingPanel({required this.reservation, super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.pearlCream,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 10,
          children: [
            Text(
              AppTranslations.yourBooking,
              style: Get.textTheme.labelMedium?.copyWith(
                color: AppColors.lagoonTeal,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.5,
              ),
            ),
            _Row(AppTranslations.guestLabel, reservation.guestName),
            _Row(
              AppTranslations.roomLabel,
              '${reservation.suiteName} · ${reservation.roomNumber}',
            ),
            _Row(
              AppTranslations.checkInLabel,
              '${reservation.checkInDate}, 2026 · 3:00 PM',
            ),
            _Row(
              AppTranslations.checkOutLabel,
              '${reservation.checkOutDate}, 2026 · 12:00 PM',
            ),
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  final String label;
  final String value;

  const _Row(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        Text(
          label,
          style: Get.textTheme.bodyMedium
              ?.copyWith(color: AppColors.taupeBrown),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: Get.textTheme.bodyMedium?.copyWith(
              color: AppColors.primary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `flutter test test/check_in_tab_bar_test.dart`
Expected: PASS — 1 test

- [ ] **Step 6: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 7: Commit** *(only with authorization)*

```bash
git add lib/components/check_in test/check_in_tab_bar_test.dart
git commit -m "feat(check-in): add wizard tab bar and booking panel"
```

---

### Task 7: Wizard shell

**Files:**
- Modify: `lib/views/check_in/check_in_view.dart`
- Test: none new (covered by Task 8's widget test)

**Interfaces:**
- Consumes: `CheckInTabBar`, `CheckInController`.
- Produces: `CheckInView` rendering an app bar, the tab bar, and a non-swipeable `PageView` with three placeholder children replaced in Tasks 8, 11 and 12.

- [ ] **Step 1: Replace the shell**

```dart
// lib/views/check_in/check_in_view.dart
import 'package:carlton/components/check_in/check_in_tab_bar.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Three-tab check-in wizard (Figma 75:463, 75:808, 75:928).
class CheckInView extends GetView<CheckInController> {
  const CheckInView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pearlCream,
      appBar: AppBar(
        backgroundColor: AppColors.pearlCream,
        elevation: 0,
        leading: const BackButton(color: AppColors.primary),
        title: Text(
          AppTranslations.checkInTitle,
          style: Get.textTheme.titleLarge
              ?.copyWith(color: AppColors.primary),
        ),
      ),
      body: Column(
        children: [
          Obx(
            () => CheckInTabBar(
              activeIndex: controller.activeTab.value,
              isUnlocked: controller.isTabUnlocked,
              onTap: controller.goToTab,
            ),
          ),
          Expanded(
            child: PageView(
              controller: controller.pageController,
              physics: const NeverScrollableScrollPhysics(),
              onPageChanged: (i) => controller.activeTab.value = i,
              children: const [
                SizedBox.shrink(), // Task 8  — Identity
                SizedBox.shrink(), // Task 11 — Preferences
                SizedBox.shrink(), // Task 12 — Room Key
              ],
            ),
          ),
        ],
      ),
    );
  }
}
```

- [ ] **Step 2: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 3: Run the full suite**

Run: `flutter test`
Expected: all pass

- [ ] **Step 4: Commit** *(only with authorization)*

```bash
git add lib/views/check_in/check_in_view.dart
git commit -m "feat(check-in): add three-tab wizard shell"
```

---

### Task 8: Identity tab

**Files:**
- Create: `lib/views/check_in/tabs/identity_tab.dart`
- Modify: `lib/views/check_in/check_in_view.dart` (swap first `SizedBox.shrink()`)
- Test: `test/check_in_identity_tab_test.dart`

**Interfaces:**
- Consumes: `CheckInService.identity`, `.documentNumber`, `.reservation`; `CheckInBookingPanel`; `CheckInController.advanceTo`.
- Produces: `IdentityTab` widget.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_identity_tab_test.dart
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/views/check_in/tabs/identity_tab.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
    Get.put(CheckInController());
  });

  tearDown(Get.reset);

  testWidgets('Continue is disabled until identity is verified',
      (tester) async {
    await tester.pumpWidget(
      const GetMaterialApp(home: Scaffold(body: IdentityTab())),
    );
    await tester.pump();

    final button = tester.widget<AbsorbPointer>(
      find.byKey(const Key('identity-continue-gate')),
    );
    expect(button.absorbing, isTrue,
        reason: 'CTA must be inert while unverified');

    CheckInService.find.markIdentityVerified('SY-20480831');
    await tester.pump();

    final enabled = tester.widget<AbsorbPointer>(
      find.byKey(const Key('identity-continue-gate')),
    );
    expect(enabled.absorbing, isFalse);
    expect(find.textContaining('SY-20480831'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_identity_tab_test.dart`
Expected: FAIL — `Target of URI doesn't exist: '.../identity_tab.dart'`

- [ ] **Step 3: Write the tab**

```dart
// lib/views/check_in/tabs/identity_tab.dart
import 'package:carlton/components/check_in/check_in_booking_panel.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Identity tab. Figma 75:463 (notStarted) and 75:565 (verified) are ONE view
/// in two states — everything above the state block is identical in both.
class IdentityTab extends GetView<CheckInController> {
  const IdentityTab({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      // Scrollable, not a ListView: the content is a fixed short column, so
      // lazy building would buy nothing.
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final verified =
            controller.service.identity.value == IdentityStatus.verified;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
          children: [
            if (!verified) const _Header(),
            CheckInBookingPanel(
              reservation: controller.service.reservation.value,
            ),
            if (verified) const _VerifiedCard() else const _ScanPrompt(),
            if (!verified) const _SecurityNote(),
            AbsorbPointer(
              key: const Key('identity-continue-gate'),
              absorbing: !verified,
              child: Opacity(
                opacity: verified ? 1 : 0.4,
                child: CustomFilledButton(
                  height: 50,
                  onPressed: () => controller.advanceTo(1),
                  backgroundColor: AppColors.lagoonTeal,
                  child: Text(AppTranslations.continueToPreferences),
                ),
              ),
            ),
          ],
        );
      }),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header();

  @override
  Widget build(BuildContext context) {
    return Column(
      spacing: 10,
      children: [
        CircleAvatar(
          radius: 25,
          backgroundColor: AppColors.primary08,
          child: SvgPicture.asset(
            'assets/icons/chk_id_card.svg',
            width: 24,
          ),
        ),
        Text(
          AppTranslations.identityTitle,
          style: Get.textTheme.headlineSmall
              ?.copyWith(color: AppColors.primary),
        ),
        Text(
          AppTranslations.identitySubtitle,
          textAlign: TextAlign.center,
          style: Get.textTheme.bodyMedium
              ?.copyWith(color: AppColors.taupeBrown),
        ),
      ],
    );
  }
}

class _ScanPrompt extends GetView<CheckInController> {
  const _ScanPrompt();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.linenTaupe30),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          spacing: 15,
          children: [
            SvgPicture.asset('assets/icons/chk_scan_frame.svg', width: 30),
            Text(
              AppTranslations.tapToScanId,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.primary),
            ),
            Text(
              AppTranslations.passportOrNationalId,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.taupeBrown),
            ),
            CustomFilledButton(
              height: 50,
              width: double.infinity,
              onPressed: () => Get.toNamed(Routes.scanId),
              backgroundColor: AppColors.nearBlack,
              child: Text(AppTranslations.scanId),
            ),
          ],
        ),
      ),
    );
  }
}

class _VerifiedCard extends GetView<CheckInController> {
  const _VerifiedCard();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.successGreen08,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.successGreen),
      ),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Row(
          spacing: 10,
          children: [
            const Icon(Icons.check_circle,
                color: AppColors.successGreen, size: 30),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 5,
                children: [
                  Text(
                    AppTranslations.identityVerified,
                    style: Get.textTheme.titleSmall
                        ?.copyWith(color: AppColors.primary),
                  ),
                  Text(
                    'Passport #${controller.service.documentNumber.value} · '
                    '${controller.service.reservation.value.guestName}',
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.taupeBrown),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SecurityNote extends StatelessWidget {
  const _SecurityNote();

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        SvgPicture.asset('assets/icons/info.svg', width: 16),
        Expanded(
          child: Text(
            AppTranslations.identitySecurityNote,
            style: Get.textTheme.bodySmall
                ?.copyWith(color: AppColors.taupeBrown),
          ),
        ),
      ],
    );
  }
}
```

- [ ] **Step 4: Wire it into the shell**

In `check_in_view.dart`, replace the first `SizedBox.shrink()` with `IdentityTab()` and add its import. The `children:` list can no longer be `const`; drop the `const` keyword on the list.

- [ ] **Step 5: Run test to verify it passes**

Run: `flutter test test/check_in_identity_tab_test.dart`
Expected: PASS — 1 test

- [ ] **Step 6: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 7: Commit** *(only with authorization)*

```bash
git add lib/views/check_in test/check_in_identity_tab_test.dart
git commit -m "feat(check-in): add Identity tab with verified/unverified states"
```

---

### Task 9: Scanner state machine

**Files:**
- Modify: `lib/controllers/check_in/scan_id_controller.dart`
- Test: `test/scan_id_controller_test.dart`

**Interfaces:**
- Consumes: `ScanStage`, `DemoData.scanDuration`, `DemoData.demoPassportNumber`, `CheckInService.markIdentityVerified`.
- Produces: `ScanIdController` with `Rx<ScanStage> stage`, `Future<void> startScan()`, `void scanAgain()`, `void confirm()`, `void openUpload()`.

- [ ] **Step 1: Write the failing test**

```dart
// test/scan_id_controller_test.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
    Get.put(ScanIdController());
  });

  tearDown(Get.reset);

  testWidgets('framing -> scanning -> success', (tester) async {
    final controller = Get.find<ScanIdController>();
    expect(controller.stage.value, ScanStage.framing);

    final pending = controller.startScan();
    await tester.pump();
    expect(controller.stage.value, ScanStage.scanning);

    await tester.pump(DemoData.scanDuration);
    await pending;
    expect(controller.stage.value, ScanStage.success);
  });

  testWidgets('scanAgain returns to framing', (tester) async {
    final controller = Get.find<ScanIdController>();
    final pending = controller.startScan();
    await tester.pump(DemoData.scanDuration);
    await pending;
    expect(controller.stage.value, ScanStage.success);

    controller.scanAgain();
    expect(controller.stage.value, ScanStage.framing);
  });

  test('confirm promotes the service to verified', () {
    final controller = Get.find<ScanIdController>();
    controller.stage.value = ScanStage.success;
    controller.confirm();

    expect(CheckInService.find.identity.value, IdentityStatus.verified);
    expect(CheckInService.find.documentNumber.value,
        DemoData.demoPassportNumber);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/scan_id_controller_test.dart`
Expected: FAIL — `The getter 'stage' isn't defined for the class 'ScanIdController'`

- [ ] **Step 3: Write the controller**

```dart
// lib/controllers/check_in/scan_id_controller.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:get/get.dart';

/// Mocked ID scanner (Figma 75:653 / 75:703 / 75:757).
///
/// There is no camera: a static passport asset sits behind the bracket overlay
/// in every stage and `startScan` just runs a timer. Swapping in a real camera
/// later means replacing `startScan` and nothing else.
class ScanIdController extends GetxController {
  final CheckInService service = CheckInService.find;

  final Rx<ScanStage> stage = ScanStage.framing.obs;

  Future<void> startScan() async {
    if (stage.value == ScanStage.scanning) return;
    stage.value = ScanStage.scanning;
    await Future<void>.delayed(DemoData.scanDuration);
    // Guard: the guest can pop the route mid-scan.
    if (isClosed) return;
    stage.value = ScanStage.success;
  }

  void scanAgain() => stage.value = ScanStage.framing;

  /// Promotes the captured document to verified and returns to the Identity
  /// tab, which re-renders in its verified state.
  void confirm() {
    service.markIdentityVerified(DemoData.demoPassportNumber);
    if (Get.currentRoute == Routes.scanId) Get.back<void>();
  }

  /// Gap G2: the Upload control reuses the existing, API-wired document
  /// upload route rather than duplicating it.
  void openUpload() => Get.toNamed(Routes.preArrivalDocuments);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `flutter test test/scan_id_controller_test.dart`
Expected: PASS — 3 tests

- [ ] **Step 5: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 6: Commit** *(only with authorization)*

```bash
git add lib/controllers/check_in/scan_id_controller.dart test/scan_id_controller_test.dart
git commit -m "feat(check-in): add mocked ID scanner state machine"
```

---

### Task 10: Scanner view

**Files:**
- Modify: `lib/views/check_in/scan_id_view.dart`
- Test: none new (Task 9 covers the machine; this is presentation)

**Interfaces:**
- Consumes: `ScanIdController`, `DemoData.demoPassportAsset`.

- [ ] **Step 1: Write the view**

```dart
// lib/views/check_in/scan_id_view.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// One view, three stages (Figma 75:653, 75:703, 75:757).
class ScanIdView extends GetView<ScanIdController> {
  const ScanIdView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pearlCream,
      appBar: AppBar(
        backgroundColor: AppColors.pearlCream,
        elevation: 0,
        leading: const BackButton(color: AppColors.primary),
        title: Text(
          AppTranslations.scanYourId,
          style: Get.textTheme.titleLarge
              ?.copyWith(color: AppColors.primary),
        ),
      ),
      body: Obx(() {
        final stage = controller.stage.value;
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                AppTranslations.positionIdInFrame,
                style: Get.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.taupeBrown),
              ),
            ),
            Expanded(
              child: stage == ScanStage.success
                  ? const _SuccessBody()
                  : _CaptureBody(scanning: stage == ScanStage.scanning),
            ),
            if (stage != ScanStage.success) const _ControlRow(),
          ],
        );
      }),
    );
  }
}

class _CaptureBody extends StatelessWidget {
  final bool scanning;

  const _CaptureBody({required this.scanning});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.abyssTeal,
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        spacing: 20,
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              Image.asset(DemoData.demoPassportAsset),
              // Bracket overlay: four green corners drawn as a border.
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: AppColors.successGreen,
                      width: 3,
                    ),
                  ),
                ),
              ),
              if (scanning)
                Container(height: 2, color: AppColors.successGreen),
            ],
          ),
          if (scanning) ...[
            const LinearProgressIndicator(
              color: AppColors.successGreen,
              backgroundColor: AppColors.cream20,
            ),
            Text(
              AppTranslations.scanningLabel,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.cream),
            ),
            Text(
              AppTranslations.dontMoveId,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.cream60),
            ),
          ],
        ],
      ),
    );
  }
}

class _SuccessBody extends GetView<ScanIdController> {
  const _SuccessBody();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        spacing: 20,
        children: [
          const CircleAvatar(
            radius: 25,
            backgroundColor: AppColors.lagoonTeal,
            child: Icon(Icons.check, color: Colors.white),
          ),
          Text(
            AppTranslations.scanSuccessful,
            textAlign: TextAlign.center,
            style: Get.textTheme.headlineSmall
                ?.copyWith(color: AppColors.primary),
          ),
          Text(
            AppTranslations.capturedIdDetails,
            textAlign: TextAlign.center,
            style: Get.textTheme.bodyMedium
                ?.copyWith(color: AppColors.taupeBrown),
          ),
          Card(
            color: Colors.white,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            child: Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                spacing: 10,
                children: [
                  Row(
                    children: [
                      Text(
                        AppTranslations.frontOfId,
                        style: Get.textTheme.titleSmall
                            ?.copyWith(color: AppColors.primary),
                      ),
                      const Spacer(),
                      Text(
                        AppTranslations.editLabel,
                        style: Get.textTheme.labelLarge
                            ?.copyWith(color: AppColors.lagoonTeal),
                      ),
                    ],
                  ),
                  Image.asset(DemoData.demoPassportAsset),
                  Text(
                    AppTranslations.makeSureDetailsClear,
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.taupeBrown),
                  ),
                ],
              ),
            ),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.confirm,
            backgroundColor: AppColors.lagoonTeal,
            child: Text(AppTranslations.continueLabel),
          ),
          CustomFilledButton(
            height: 50,
            onPressed: controller.scanAgain,
            backgroundColor: AppColors.primary08,
            foregroundColor: AppColors.primary,
            child: Text(AppTranslations.scanAgain),
          ),
        ],
      ),
    );
  }
}

class _ControlRow extends GetView<ScanIdController> {
  const _ControlRow();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          // Gap G3: Flash is decorative — there is no camera to light.
          _Control(
            asset: 'assets/icons/chk_flash.svg',
            label: AppTranslations.flashLabel,
            onTap: null,
          ),
          _Control(
            asset: 'assets/icons/chk_id_card.svg',
            label: AppTranslations.scanLabel,
            onTap: controller.startScan,
            highlighted: true,
          ),
          _Control(
            asset: 'assets/icons/chk_upload.svg',
            label: AppTranslations.uploadLabel,
            onTap: controller.openUpload,
          ),
        ],
      ),
    );
  }
}

class _Control extends StatelessWidget {
  final String asset;
  final String label;
  final VoidCallback? onTap;
  final bool highlighted;

  const _Control({
    required this.asset,
    required this.label,
    required this.onTap,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Column(
        spacing: 10,
        children: [
          CircleAvatar(
            radius: 25,
            backgroundColor:
                highlighted ? AppColors.lagoonTeal : AppColors.primary08,
            child: SvgPicture.asset(asset, width: 22),
          ),
          Text(
            label,
            style: Get.textTheme.bodySmall?.copyWith(
              color: highlighted
                  ? AppColors.lagoonTeal
                  : AppColors.taupeBrown,
            ),
          ),
        ],
      ),
    );
  }
}
```

- [ ] **Step 2: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 3: Run the full suite**

Run: `flutter test`
Expected: all pass

- [ ] **Step 4: Commit** *(only with authorization)*

```bash
git add lib/views/check_in/scan_id_view.dart
git commit -m "feat(check-in): add mocked scanner view with three stages"
```

---

### Task 11: Toggle tile and Preferences tab

**Files:**
- Create: `lib/components/custom_toggle_tile.dart`
- Create: `lib/views/check_in/tabs/preferences_tab.dart`
- Modify: `lib/views/check_in/check_in_view.dart` (swap second child)
- Test: `test/check_in_preferences_tab_test.dart`

**Interfaces:**
- Consumes: `CustomDropdownField({required String label, required String value, required List<PreferenceOption> options, required String selectedId, required ValueChanged<PreferenceOption> onSelected})`; `DemoData.bedOptions` / `.pillowOptions` / `.mattressOptions`; `CheckInService.preferences`, `.savePreferences`.
- Produces: `CustomToggleTile({required String title, required String subtitle, required bool value, required ValueChanged<bool> onChanged})`, `PreferencesTab`.

- [ ] **Step 1: Write the failing test**

```dart
// test/check_in_preferences_tab_test.dart
import 'package:carlton/components/custom_toggle_tile.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  testWidgets('CustomToggleTile reports changes and shows both lines',
      (tester) async {
    bool? received;
    await tester.pumpWidget(
      GetMaterialApp(
        home: Scaffold(
          body: CustomToggleTile(
            title: 'Early Check-in',
            subtitle: 'Request early check-in when available',
            value: false,
            onChanged: (v) => received = v,
          ),
        ),
      ),
    );

    expect(find.text('Early Check-in'), findsOneWidget);
    expect(find.text('Request early check-in when available'), findsOneWidget);

    await tester.tap(find.byType(Switch));
    await tester.pump();
    expect(received, isTrue);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/check_in_preferences_tab_test.dart`
Expected: FAIL — `Target of URI doesn't exist: '.../custom_toggle_tile.dart'`

- [ ] **Step 3: Write the toggle tile**

```dart
// lib/components/custom_toggle_tile.dart
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Title + subtitle + Switch in a grey card.
///
/// App-generic, so it lives in components/ rather than components/check_in/.
/// `views/account/preferences_view.dart` inlines a private `_switch` helper for
/// the same shape; adopting this tile there is deliberately a separate change.
class CustomToggleTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  const CustomToggleTile({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.primary06,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
        child: Row(
          spacing: 10,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 5,
                children: [
                  Text(
                    title,
                    style: Get.textTheme.titleSmall
                        ?.copyWith(color: AppColors.primary),
                  ),
                  Text(
                    subtitle,
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.taupeBrown),
                  ),
                ],
              ),
            ),
            Switch(
              value: value,
              onChanged: onChanged,
              activeTrackColor: AppColors.lagoonTeal,
            ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Write the Preferences tab**

```dart
// lib/views/check_in/tabs/preferences_tab.dart
import 'package:carlton/components/account/custom_dropdown_field.dart';
import 'package:carlton/components/custom_toggle_tile.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Preferences tab (Figma 75:808). Three dropdowns reuse the existing account
/// dropdown and the existing DemoData option lists; only the toggle tile is new.
class PreferencesTab extends GetView<CheckInController> {
  const PreferencesTab({super.key});

  String _labelFor(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first).label;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final prefs = controller.service.preferences.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 15,
          children: [
            CustomDropdownField(
              label: AppTranslations.bedType,
              value: _labelFor(DemoData.bedOptions, prefs.bedTypeId),
              options: DemoData.bedOptions,
              selectedId: prefs.bedTypeId,
              onSelected: (o) => controller.service.preferences.value =
                  prefs.copyWith(bedTypeId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.pillowLabel,
              value: _labelFor(DemoData.pillowOptions, prefs.pillowId),
              options: DemoData.pillowOptions,
              selectedId: prefs.pillowId,
              onSelected: (o) => controller.service.preferences.value =
                  prefs.copyWith(pillowId: o.id),
            ),
            CustomDropdownField(
              label: AppTranslations.mattressType,
              value: _labelFor(DemoData.mattressOptions, prefs.mattressId),
              options: DemoData.mattressOptions,
              selectedId: prefs.mattressId,
              onSelected: (o) => controller.service.preferences.value =
                  prefs.copyWith(mattressId: o.id),
            ),
            Text(
              AppTranslations.roomType,
              style: Get.textTheme.titleSmall
                  ?.copyWith(color: AppColors.primary),
            ),
            CustomToggleTile(
              title: AppTranslations.smokingRoom,
              subtitle: AppTranslations.smokingRoomSubtitle,
              value: prefs.smokingRoom,
              onChanged: (v) => controller.service.preferences.value =
                  prefs.copyWith(smokingRoom: v),
            ),
            CustomToggleTile(
              title: AppTranslations.earlyCheckIn,
              subtitle: AppTranslations.earlyCheckInSubtitle,
              value: prefs.earlyCheckIn,
              onChanged: (v) => controller.service.preferences.value =
                  prefs.copyWith(earlyCheckIn: v),
            ),
            CustomToggleTile(
              title: AppTranslations.lateCheckOut,
              subtitle: AppTranslations.lateCheckOutSubtitle,
              value: prefs.lateCheckOut,
              onChanged: (v) => controller.service.preferences.value =
                  prefs.copyWith(lateCheckOut: v),
            ),
            CustomToggleTile(
              title: AppTranslations.extraPillows,
              subtitle: AppTranslations.extraPillowsSubtitle,
              value: prefs.extraPillows,
              onChanged: (v) => controller.service.preferences.value =
                  prefs.copyWith(extraPillows: v),
            ),
            Text(
              AppTranslations.specialRequestsTitle,
              style: Get.textTheme.titleSmall
                  ?.copyWith(color: AppColors.primary),
            ),
            CustomTextField(
              controller: controller.notesController,
              textInputType: TextInputType.multiline,
              hintText: AppTranslations.specialRequestsHint,
              maxLines: 4,
            ),
            CustomFilledButton(
              height: 50,
              onPressed: controller.savePreferencesAndAdvance,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.continueLabel),
            ),
          ],
        );
      }),
    );
  }
}
```

- [ ] **Step 5: Add the controller members the tab needs**

Append to `CheckInController`:

```dart
  final TextEditingController notesController = TextEditingController();

  void savePreferencesAndAdvance() {
    service.savePreferences(
      service.preferences.value.copyWith(notes: notesController.text),
    );
    advanceTo(2);
  }
```

and dispose it in `onClose()` alongside `pageController`:

```dart
    notesController.dispose();
```

- [ ] **Step 6: Confirm the CustomTextField parameter names**

Run: `sed -n '9,45p' lib/customWidgets/custom_text_field.dart`
`hintText` and `maxLines` are assumed. If the field uses different names (e.g. `hint`, `lines`), adjust the call in Step 4 to match — do not add new parameters to `CustomTextField`.

- [ ] **Step 7: Wire it into the shell**

Replace the second `SizedBox.shrink()` in `check_in_view.dart` with `PreferencesTab()` and add the import.

- [ ] **Step 8: Run test to verify it passes**

Run: `flutter test test/check_in_preferences_tab_test.dart`
Expected: PASS — 1 test

- [ ] **Step 9: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 10: Commit** *(only with authorization)*

```bash
git add lib/components/custom_toggle_tile.dart lib/views/check_in lib/controllers/check_in test/check_in_preferences_tab_test.dart
git commit -m "feat(check-in): add Preferences tab and reusable toggle tile"
```

---

### Task 12: Digital key button and Room Key tab

**Files:**
- Create: `lib/components/check_in/digital_key_button.dart`
- Create: `lib/views/check_in/tabs/room_key_tab.dart`
- Modify: `lib/views/check_in/check_in_view.dart` (swap third child)
- Test: `test/digital_key_button_test.dart`

**Interfaces:**
- Consumes: `CheckInService.key`, `.activateDigitalKey`, `.completeCheckIn`, `.reservation`; `SpinningIconIndicator` from `lib/customWidgets/custom_indicators.dart`.
- Produces: `DigitalKeyButton({required DigitalKeyStatus status, required VoidCallback onPressed})`, `RoomKeyTab`.

- [ ] **Step 1: Write the failing test**

```dart
// test/digital_key_button_test.dart
import 'package:carlton/components/check_in/digital_key_button.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

Future<void> _pump(WidgetTester tester, DigitalKeyStatus status) {
  return tester.pumpWidget(
    GetMaterialApp(
      home: Scaffold(
        body: DigitalKeyButton(status: status, onPressed: () {}),
      ),
    ),
  );
}

void main() {
  testWidgets('renders a distinct label per state', (tester) async {
    await _pump(tester, DigitalKeyStatus.idle);
    expect(find.text('Add Digital Key to phone'), findsOneWidget);

    await _pump(tester, DigitalKeyStatus.activating);
    expect(find.text('Activating Digital Key…'), findsOneWidget);

    await _pump(tester, DigitalKeyStatus.activated);
    expect(find.text('Activated Digital Key'), findsOneWidget);
  });

  testWidgets('activated state uses forestGreen', (tester) async {
    await _pump(tester, DigitalKeyStatus.activated);
    final card = tester.widget<Card>(find.byType(Card));
    expect(card.color, AppColors.forestGreen);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/digital_key_button_test.dart`
Expected: FAIL — `Target of URI doesn't exist: '.../digital_key_button.dart'`

- [ ] **Step 3: Write the button**

```dart
// lib/components/check_in/digital_key_button.dart
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Three-state digital key button (Figma 75:1218).
class DigitalKeyButton extends StatelessWidget {
  final DigitalKeyStatus status;
  final VoidCallback onPressed;

  const DigitalKeyButton({
    required this.status,
    required this.onPressed,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final activated = status == DigitalKeyStatus.activated;
    final label = switch (status) {
      DigitalKeyStatus.idle => AppTranslations.addDigitalKey,
      DigitalKeyStatus.activating => AppTranslations.activatingDigitalKey,
      DigitalKeyStatus.activated => AppTranslations.activatedDigitalKey,
    };

    return Card(
      color: activated ? AppColors.forestGreen : AppColors.primary06,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      child: InkWell(
        onTap: status == DigitalKeyStatus.idle ? onPressed : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 15),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            spacing: 10,
            children: [
              SvgPicture.asset('assets/icons/chk_phone.svg', width: 18),
              Text(
                label,
                style: Get.textTheme.titleSmall?.copyWith(
                  color: activated ? AppColors.cream : AppColors.primary,
                ),
              ),
              if (status == DigitalKeyStatus.activating)
                const SpinningIconIndicator(),
              if (activated)
                const Icon(Icons.check_circle,
                    color: AppColors.cream, size: 18),
            ],
          ),
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Confirm SpinningIconIndicator's constructor**

Run: `sed -n '46,64p' lib/customWidgets/custom_indicators.dart`
If it requires arguments (e.g. an asset path), pass them; if the class turns out to be unsuitable, substitute `const CircularProgressIndicator(strokeWidth: 2)`.

- [ ] **Step 5: Write the Room Key tab**

```dart
// lib/views/check_in/tabs/room_key_tab.dart
import 'package:carlton/components/check_in/digital_key_button.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Room Key tab (Figma 75:928).
class RoomKeyTab extends GetView<CheckInController> {
  const RoomKeyTab({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Obx(() {
        final reservation = controller.service.reservation.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 20,
          children: [
            Center(
              child: CircleAvatar(
                radius: 30,
                backgroundColor: AppColors.primary08,
                child: SvgPicture.asset(
                  'assets/icons/chk_key.svg',
                  width: 28,
                ),
              ),
            ),
            Text(
              AppTranslations.yourRoomKey,
              textAlign: TextAlign.center,
              style: Get.textTheme.headlineSmall
                  ?.copyWith(color: AppColors.primary),
            ),
            Text(
              AppTranslations.roomKeySubtitle,
              textAlign: TextAlign.center,
              style: Get.textTheme.bodyMedium
                  ?.copyWith(color: AppColors.taupeBrown),
            ),
            Card(
              color: AppColors.cream,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              child: Padding(
                padding: const EdgeInsets.all(15),
                child: Row(
                  spacing: 15,
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: AppColors.antiqueGold,
                      child: SvgPicture.asset(
                        'assets/icons/bed.svg',
                        width: 20,
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        spacing: 5,
                        children: [
                          Text(
                            reservation.suiteName,
                            style: Get.textTheme.titleSmall
                                ?.copyWith(color: AppColors.primary),
                          ),
                          Row(
                            spacing: 5,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                reservation.roomNumber,
                                style: Get.textTheme.headlineMedium
                                    ?.copyWith(color: AppColors.primary),
                              ),
                              Text(
                                reservation.floorLabel,
                                style: Get.textTheme.bodySmall?.copyWith(
                                  color: AppColors.taupeBrown,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Card(
              color: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              child: Padding(
                padding: const EdgeInsets.all(15),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 5,
                  children: [
                    Text(
                      AppTranslations.keyCardWaitingTitle,
                      style: Get.textTheme.titleSmall
                          ?.copyWith(color: AppColors.primary),
                    ),
                    Text(
                      AppTranslations.keyCardWaitingBody,
                      style: Get.textTheme.bodySmall
                          ?.copyWith(color: AppColors.taupeBrown),
                    ),
                  ],
                ),
              ),
            ),
            DigitalKeyButton(
              status: controller.service.key.value,
              onPressed: controller.service.activateDigitalKey,
            ),
            Text(
              AppTranslations.digitalKeyHint,
              textAlign: TextAlign.center,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.taupeBrown),
            ),
            CustomFilledButton(
              height: 50,
              onPressed: controller.completeAndExit,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.completeCheckIn),
            ),
          ],
        );
      }),
    );
  }
}
```

- [ ] **Step 6: Add the exit method to CheckInController**

```dart
  void completeAndExit() {
    service.completeCheckIn();
    Get.back<void>();
  }
```

- [ ] **Step 7: Wire it into the shell**

Replace the third `SizedBox.shrink()` in `check_in_view.dart` with `RoomKeyTab()` and add the import.

- [ ] **Step 8: Run test to verify it passes**

Run: `flutter test test/digital_key_button_test.dart`
Expected: PASS — 2 tests

- [ ] **Step 9: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 10: Commit** *(only with authorization)*

```bash
git add lib/components/check_in lib/views/check_in lib/controllers/check_in test/digital_key_button_test.dart
git commit -m "feat(check-in): add Room Key tab and three-state digital key button"
```

---

### Task 13: Arrival time sheet (gap G1)

**Files:**
- Create: `lib/components/check_in/arrival_time_sheet.dart`
- Test: `test/arrival_time_sheet_test.dart`

**Interfaces:**
- Consumes: `CustomBottomSheet({required Widget child, String? title, String? subtitle, bool showClose, List<Widget>? actions, double heightFactor, bool scrollable})`; `CheckInService.markArrivalTime`.
- Produces: `ArrivalTimeSheet` widget and `Future<void> showArrivalTimeSheet()`.

This screen exists in no Figma frame — the home checklist references it with no destination. It is our design; flag it in review.

- [ ] **Step 1: Write the failing test**

```dart
// test/arrival_time_sheet_test.dart
import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  testWidgets('picking a time completes the arrivalTime step',
      (tester) async {
    await tester.pumpWidget(
      const GetMaterialApp(home: Scaffold(body: ArrivalTimeSheet())),
    );
    await tester.pump();

    expect(
      CheckInService.find.isStepComplete(PreArrivalStep.arrivalTime),
      isFalse,
    );

    await tester.tap(find.text('3:00 PM'));
    await tester.pump();

    expect(
      CheckInService.find.isStepComplete(PreArrivalStep.arrivalTime),
      isTrue,
    );
    expect(CheckInService.find.arrivalTimeLabel.value, '3:00 PM');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/arrival_time_sheet_test.dart`
Expected: FAIL — `Target of URI doesn't exist: '.../arrival_time_sheet.dart'`

- [ ] **Step 3: Write the sheet**

```dart
// lib/components/check_in/arrival_time_sheet.dart
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Gap G1: the Home checklist offers "Set arrival time · Tap to add your ETA"
/// but the Figma file contains no screen for it. A slot picker is the smallest
/// thing that makes the row functional.
class ArrivalTimeSheet extends StatelessWidget {
  const ArrivalTimeSheet({super.key});

  static const _slots = <String>[
    '12:00 PM',
    '1:00 PM',
    '2:00 PM',
    '3:00 PM',
    '4:00 PM',
    '6:00 PM',
    '8:00 PM',
    'After 10:00 PM',
  ];

  @override
  Widget build(BuildContext context) {
    return CustomBottomSheet(
      title: AppTranslations.selectArrivalTime,
      heightFactor: 0.6,
      child: Obx(() {
        final selected = CheckInService.find.arrivalTimeLabel.value;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          spacing: 10,
          children: _slots.map((slot) {
            final active = slot == selected;
            return InkWell(
              onTap: () => CheckInService.find.markArrivalTime(slot),
              child: Card(
                color: active ? AppColors.primary08 : AppColors.primary06,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: BorderSide(
                    color: active
                        ? AppColors.lagoonTeal
                        : AppColors.primary00,
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 15,
                    vertical: 15,
                  ),
                  child: Text(
                    slot,
                    style: Get.textTheme.titleSmall
                        ?.copyWith(color: AppColors.primary),
                  ),
                ),
              ),
            );
          }).toList(),
        );
      }),
    );
  }
}

/// Opens the sheet. Home's checklist row calls this.
Future<void> showArrivalTimeSheet() =>
    Get.bottomSheet<void>(const ArrivalTimeSheet(), isScrollControlled: true);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `flutter test test/arrival_time_sheet_test.dart`
Expected: PASS — 1 test

- [ ] **Step 5: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 6: Commit** *(only with authorization)*

```bash
git add lib/components/check_in/arrival_time_sheet.dart test/arrival_time_sheet_test.dart
git commit -m "feat(check-in): add arrival time sheet for the fourth checklist step"
```

---

### Task 14: Home pre-arrival state

**Files:**
- Modify: `lib/views/home/home_view.dart`
- Test: `test/home_reservation_state_test.dart` (extend)

**Interfaces:**
- Consumes: `CheckInService.isPreArrival`, `.completed`, `.completedCount`, `.totalSteps`, `.progress`, `.reservation`; `showArrivalTimeSheet()`; `Routes.checkIn`.
- Produces: `HomeView.preArrivalSections` (exposed for the test), `_PreArrivalStaySection`, `_PreArrivalChecklistSection`, `_AirportTransferSection`.

- [ ] **Step 1: Write the failing test**

Append to `test/home_reservation_state_test.dart`:

```dart
// ── Pre-arrival section selection ──
// Guards the three-way selector. Reverting it would send a pre-arrival guest
// to the in-house dashboard, which shows a bill they have not incurred.
group('home section selection', () {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  test('pre-arrival guest gets the pre-arrival section list', () {
    expect(CheckInService.find.isPreArrival.value, isTrue);
    expect(
      HomeView.sectionsFor(hasReservation: true, isPreArrival: true),
      same(HomeView.preArrivalSections),
    );
  });

  test('checked-in guest falls back to the reservation list', () {
    expect(
      HomeView.sectionsFor(hasReservation: true, isPreArrival: false),
      same(HomeView.reservationSections),
    );
  });

  test('no reservation still gets the explore list', () {
    expect(
      HomeView.sectionsFor(hasReservation: false, isPreArrival: false),
      same(HomeView.exploreSections),
    );
  });
});
```

Add the imports `package:carlton/services/check_in_service.dart`,
`package:carlton/views/home/home_view.dart` and `package:get/get.dart`.

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/home_reservation_state_test.dart`
Expected: FAIL — `The getter 'preArrivalSections' isn't defined for the class 'HomeView'`

- [ ] **Step 3: Rename the two existing lists and add the third**

In `home_view.dart`, rename `_reservationSections` → `reservationSections` and `_exploreSections` → `exploreSections` (they must be public for the test to reference them), then add:

```dart
  /// Pre-arrival sections (Figma `75:133`). Half of this list is existing
  /// widgets — only the top three are new.
  static const preArrivalSections = <Widget>[
    _PreArrivalStaySection(),
    _PreArrivalChecklistSection(),
    _AirportTransferSection(),
    _AiConciergeSection(),
    _DiningCarousel(),
    _ExperiencesCarousel(),
  ];

  /// Pure selector, extracted so it is testable without pumping a widget.
  ///
  /// MUST keep returning the same const list instances: `Element.updateChild`
  /// short-circuits the whole subtree when the identical const list comes back,
  /// which is what stops an unrelated profile edit from rebuilding every
  /// carousel. Do not replace these with computed lists.
  static List<Widget> sectionsFor({
    required bool hasReservation,
    required bool isPreArrival,
  }) {
    if (!hasReservation) return exploreSections;
    return isPreArrival ? preArrivalSections : reservationSections;
  }
```

- [ ] **Step 4: Use the selector in build**

Replace the ternary inside the existing `Obx` with:

```dart
        final sections = HomeView.sectionsFor(
          hasReservation: controller.hasReservation,
          isPreArrival: CheckInService.find.isPreArrival.value,
        );
```

- [ ] **Step 5: Write the three new sections**

Append to `home_view.dart`:

```dart
/// Dark teal hero for a booked-but-not-checked-in stay (Figma `75:133`).
class _PreArrivalStaySection extends StatelessWidget {
  const _PreArrivalStaySection();

  @override
  Widget build(BuildContext context) {
    final service = CheckInService.find;
    return Obx(() {
      final reservation = service.reservation.value;
      return Card(
        color: AppColors.primary,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(15),
        ),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 15,
            children: [
              Row(
                spacing: 10,
                children: [
                  _Chip(
                    label: AppTranslations.roomChip(reservation.roomNumber),
                    background: AppColors.antiqueGold,
                    foreground: AppColors.primary,
                  ),
                  _Chip(
                    label: AppTranslations.preCheckInAvailable,
                    background: AppColors.cream20,
                    foreground: AppColors.cream,
                  ),
                ],
              ),
              Text(
                reservation.suiteName,
                style: Get.textTheme.headlineSmall
                    ?.copyWith(color: AppColors.cream),
              ),
              Text(
                reservation.stayRangeLabel,
                style: Get.textTheme.bodySmall
                    ?.copyWith(color: AppColors.cream60),
              ),
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.checkInLabel,
                      value: reservation.checkInDate,
                      hint: reservation.checkInTime,
                    ),
                  ),
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.checkOutLabel,
                      value: reservation.checkOutDate,
                      hint: reservation.checkOutTime,
                    ),
                  ),
                ],
              ),
              Row(
                spacing: 10,
                children: [
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.roomLabel,
                      value: 'Suite ${reservation.roomNumber}',
                      hint: reservation.suiteName,
                    ),
                  ),
                  Expanded(
                    child: _Tile(
                      label: AppTranslations.bookingRefLabel,
                      value: reservation.bookingRef,
                      hint: '',
                    ),
                  ),
                ],
              ),
              Row(
                children: [
                  Text(
                    AppTranslations.preArrivalProgress,
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.cream60),
                  ),
                  const Spacer(),
                  Text(
                    AppTranslations.completeFraction(
                      service.completedCount,
                      service.totalSteps,
                    ),
                    style: Get.textTheme.bodySmall
                        ?.copyWith(color: AppColors.antiqueGold),
                  ),
                ],
              ),
              LinearProgressIndicator(
                value: service.progress,
                color: AppColors.antiqueGold,
                backgroundColor: AppColors.cream20,
              ),
              CustomFilledButton(
                height: 50,
                width: double.infinity,
                onPressed: () => Get.toNamed(Routes.checkIn),
                backgroundColor: AppColors.ivoryCream,
                foregroundColor: AppColors.primary,
                child: Text(AppTranslations.checkInNow),
              ),
            ],
          ),
        ),
      );
    });
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final Color background;
  final Color foreground;

  const _Chip({
    required this.label,
    required this.background,
    required this.foreground,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: Get.textTheme.labelSmall
            ?.copyWith(color: foreground, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;

  const _Tile({
    required this.label,
    required this.value,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: AppColors.cream08,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 5,
        children: [
          Text(
            label.toUpperCase(),
            style: Get.textTheme.labelSmall
                ?.copyWith(color: AppColors.cream60),
          ),
          Text(
            value,
            style: Get.textTheme.titleMedium
                ?.copyWith(color: AppColors.cream),
          ),
          if (hint.isNotEmpty)
            Text(
              hint,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.cream60),
            ),
        ],
      ),
    );
  }
}

/// Four-row checklist, ticking live from CheckInService (Figma `75:133`).
class _PreArrivalChecklistSection extends StatelessWidget {
  const _PreArrivalChecklistSection();

  @override
  Widget build(BuildContext context) {
    final service = CheckInService.find;
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 15,
          children: [
            Text(
              AppTranslations.preArrivalChecklist,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.primary),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/check.svg',
                title: AppTranslations.confirmContactDetails,
                subtitle: AppTranslations.completedLabel,
                done: service.isStepComplete(PreArrivalStep.contactDetails),
                onTap: null,
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/chk_upload.svg',
                title: AppTranslations.uploadIdOrPassport,
                subtitle: AppTranslations.requiredForCheckIn,
                done: service.isStepComplete(PreArrivalStep.identity),
                onTap: () => Get.toNamed(Routes.checkIn),
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/clock.svg',
                title: AppTranslations.setArrivalTime,
                subtitle: AppTranslations.tapToAddEta,
                done: service.isStepComplete(PreArrivalStep.arrivalTime),
                onTap: showArrivalTimeSheet,
              ),
            ),
            Obx(
              () => _ChecklistRow(
                icon: 'assets/icons/act_request.svg',
                title: AppTranslations.preArrivalSpecialRequests,
                subtitle: AppTranslations.optionalPreferences,
                done:
                    service.isStepComplete(PreArrivalStep.specialRequests),
                onTap: () => Get.toNamed(Routes.checkIn),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  final String icon;
  final String title;
  final String subtitle;
  final bool done;
  final VoidCallback? onTap;

  const _ChecklistRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.done,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Row(
        spacing: 15,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.antiqueGold08,
              borderRadius: BorderRadius.circular(10),
            ),
            child: SvgPicture.asset(icon, width: 18),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              spacing: 5,
              children: [
                Text(
                  title,
                  style: Get.textTheme.titleSmall?.copyWith(
                    color:
                        done ? AppColors.successGreen : AppColors.primary,
                  ),
                ),
                Text(
                  subtitle,
                  style: Get.textTheme.bodySmall
                      ?.copyWith(color: AppColors.taupeBrown),
                ),
              ],
            ),
          ),
          done
              ? const Icon(Icons.check_circle,
                  color: AppColors.successGreen, size: 24)
              : const Icon(Icons.circle_outlined,
                  color: AppColors.stoneTaupe, size: 24),
        ],
      ),
    );
  }
}

/// Airport transfer promo (Figma `75:133`).
class _AirportTransferSection extends GetView<HomeController> {
  const _AirportTransferSection();

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(15),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 15,
          children: [
            Text(
              AppTranslations.airportTransfer,
              style: Get.textTheme.titleMedium
                  ?.copyWith(color: AppColors.primary),
            ),
            Text(
              AppTranslations.airportTransferBody,
              style: Get.textTheme.bodySmall
                  ?.copyWith(color: AppColors.taupeBrown),
            ),
            CustomFilledButton(
              height: 50,
              width: double.infinity,
              onPressed: controller.goToServices,
              backgroundColor: AppColors.lagoonTeal,
              child: Text(AppTranslations.requestAirportTransfer),
            ),
          ],
        ),
      ),
    );
  }
}
```

Add the imports these sections need: `check_in_service.dart`, `pre_arrival_step.dart`, `arrival_time_sheet.dart`, `custom_filled_button.dart`, `flutter_svg`, `app_translations.dart`, `routes.dart`, `app_colors.dart`.

- [ ] **Step 6: Fix the renamed references**

Run: `grep -n "_reservationSections\|_exploreSections" lib/views/home/home_view.dart`
Expected: no output. If any remain, update them to the public names.

- [ ] **Step 7: Run test to verify it passes**

Run: `flutter test test/home_reservation_state_test.dart`
Expected: PASS — existing folio tests plus 3 new selection tests

- [ ] **Step 8: Analyze**

Run: `flutter analyze`
Expected: 0 issues

- [ ] **Step 9: Commit** *(only with authorization)*

```bash
git add lib/views/home/home_view.dart test/home_reservation_state_test.dart
git commit -m "feat(home): add pre-arrival state with live checklist and progress"
```

---

### Task 15: Full verification

**Files:** none — verification only.

- [ ] **Step 1: Analyze**

Run: `flutter analyze`
Expected: **0 issues.** Any issue is a blocker; the project baseline is zero.

- [ ] **Step 2: Run the full suite**

Run: `flutter test`
Expected: all tests pass. Record the **actual** count — the suite was 11 files before this work and should now be 19.

- [ ] **Step 3: Confirm no `var()` survived in the icons**

Run: `grep -rl "var(--" assets/icons/ || echo "clean"`
Expected: `clean`. Any hit means that icon renders as blank space at runtime.

- [ ] **Step 4: Confirm no new dependencies were added**

Run: `git diff --stat pubspec.yaml pubspec.lock`
Expected: no changes to either file.

- [ ] **Step 5: Confirm no raw strings leaked into the new views**

Run: `grep -rnE "Text\('[A-Za-z]" lib/views/check_in lib/components/check_in`
Expected: no output. Every user-facing string must come from `AppTranslations`.

- [ ] **Step 6: Manual device check**

Run: `flutter run`

Walk the flow: Home shows the teal hero at **1/4** → *Check In Now* → Identity shows a disabled CTA → *Scan ID* → framing → *Scan* → scanning → success → *Continue* → Identity now green with the CTA enabled → *Continue to Preferences* → toggles and dropdowns respond → *Continue* → Room Key → *Add Digital Key* → activating → activated → *Complete Check-In* → Home returns to its in-house reservation state. Then re-run and confirm the checklist ticks and the progress bar move as steps complete.

Also run once in Arabic to confirm RTL layout, since the project supports `ar`.

- [ ] **Step 7: Report**

State the actual `flutter analyze` and `flutter test` results. Do not describe the work as done without both numbers.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §2 decisions (mock scanner, icons, state, colours) | Global Constraints, Tasks 3, 4 |
| §3 G1 arrival time | Task 13 |
| §3 G2 upload reuse | Task 9 (`openUpload`), Task 10 |
| §3 G3 inert flash | Task 10 |
| §3 G4 copy typos | Global Constraints, Task 2 |
| §3 G5 tab locking | Task 5 (`isTabUnlocked`), Task 6 |
| §4.1 file layout | Tasks 1, 3, 5, 6, 8, 10–13 |
| §4.2 state ownership | Task 3 |
| §4.3 no TabController | Task 5 |
| §5.1 Identity tab | Task 8 |
| §5.2 scanner | Tasks 9, 10 |
| §5.3 Preferences | Task 11 |
| §5.4 Room Key | Task 12 |
| §5.5 DigitalKeyButton | Task 12 |
| §6 home third list | Task 14 |
| §7 icons + `var()` trap | Task 4, Task 15 Step 3 |
| §8 demo data | Task 1 |
| §9 five tests | Tasks 3, 8, 9, 12, 14 |

All five spec tests are present: progress 1/4→2/4 (Task 3), scanner machine (Task 9), digital key states (Task 12), home selector (Task 14), Identity CTA gate (Task 8).

**Deviations from the spec, deliberate:** the spec said `CheckInService` registers in `InitialBinding`; the codebase has no such class and registers cross-screen singletons in `main.dart` (`BookingFlowController`, line 28). Task 3 follows `main.dart`. The spec placed `CustomToggleTile` under `components/check_in/`; it is app-generic, so Task 11 puts it in `components/`.

**Placeholder scan:** no TBDs. Three steps ask the implementer to verify an assumed API before using it (Task 1 Step 5 option ids, Task 11 Step 6 `CustomTextField` params, Task 12 Step 4 `SpinningIconIndicator`) — each states exactly what to check and what to do if it differs, so none is an open question.

**Type consistency:** `CheckInService` members are referenced identically across Tasks 8–14 (`service.identity`, `service.reservation`, `service.completed`, `service.key`, `service.preferences`). `CheckInController` gains `notesController`, `savePreferencesAndAdvance` and `completeAndExit` in the tasks that use them (11, 12), all disposed in `onClose`. `advanceTo(int)` is defined in Task 5 and called in Tasks 8 and 11.
