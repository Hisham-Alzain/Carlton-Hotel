# Task 9: Scanner state machine

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

