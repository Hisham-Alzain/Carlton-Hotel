# Task 6: Wizard chrome — tab bar and booking panel

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

