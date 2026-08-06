# Task 12: Digital key button and Room Key tab

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

