# Task 5: Routes, bindings and view shells

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

