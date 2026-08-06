# Task 8: Identity tab

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

