# Task 7: Wizard shell

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

