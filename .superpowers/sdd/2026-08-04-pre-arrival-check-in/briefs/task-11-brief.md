# Task 11: Toggle tile and Preferences tab

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

