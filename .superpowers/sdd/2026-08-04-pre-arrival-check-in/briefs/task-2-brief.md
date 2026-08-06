# Task 2: Localization strings

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

