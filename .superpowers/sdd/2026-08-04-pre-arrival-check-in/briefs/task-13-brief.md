# Task 13: Arrival time sheet (gap G1)

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

