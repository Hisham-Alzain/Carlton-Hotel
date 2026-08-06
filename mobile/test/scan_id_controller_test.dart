import 'dart:io';

import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/check_in/scan_id_controller.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/services/permission_service.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

/// Guards the scanner's refusal handling and its hand-off to [CheckInService].
///
/// `PermissionService.requestCameraPermission()` is guarded by
/// `Platform.isAndroid || Platform.isIOS` and so returns false on the desktop
/// test host. That makes the *refused* branch the one a unit test can reach —
/// which is fortunate, because it is also the branch a device demo never
/// exercises and the one that decides whether a guest with no camera can still
/// finish check-in. The granted branch needs a real device.
void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
    Get.put(PermissionService());
  });

  tearDown(Get.reset);

  testWidgets(
    'a refused camera lands on the recovery card, not a dead preview',
    (WidgetTester tester) async {
      final ScanIdController controller = Get.put(ScanIdController());
      await tester.pumpAndSettle();

      expect(controller.stage.value, ScanStage.unavailable);
      // Drives the CTA: "Open Settings" rather than "Try again". Flipping this
      // strands the guest on a button that cannot fix anything.
      expect(controller.isBlockedByPermission.value, isTrue);
      expect(controller.errorMessage.value, isNotEmpty);
      // The camera was never built, so there is nothing to preview.
      expect(controller.isCameraReady, isFalse);
    },
  );

  testWidgets('backgrounding while framing rewinds and kills the torch', (
    WidgetTester tester,
  ) async {
    final ScanIdController controller = Get.put(ScanIdController());
    await tester.pumpAndSettle();

    // Stand in for a live preview, which the test host cannot produce.
    controller.stage.value = ScanStage.framing;
    controller.isTorchOn.value = true;

    controller.stopCamera();

    // Rewinding is what makes the view show the placeholder instead of a
    // frozen last frame after a resume.
    expect(controller.stage.value, ScanStage.initializing);
    expect(controller.isTorchOn.value, isFalse);
  });

  testWidgets('reviewing a capture survives backgrounding', (
    WidgetTester tester,
  ) async {
    final ScanIdController controller = Get.put(ScanIdController());
    await tester.pumpAndSettle();

    controller.stage.value = ScanStage.success;
    controller.didChangeAppLifecycleState(AppLifecycleState.paused);

    // Restarting the camera here would throw the guest off the review screen
    // and back to a live preview, losing the shot they just took.
    expect(controller.stage.value, ScanStage.success);
  });

  testWidgets('confirm hands the captured photo to CheckInService', (
    WidgetTester tester,
  ) async {
    final ScanIdController controller = Get.put(ScanIdController());
    await tester.pumpAndSettle();

    controller.capturedPhoto.value = File('/carlton/id_capture.jpg');
    controller.stage.value = ScanStage.success;
    controller.confirm();

    final CheckInService service = CheckInService.find;
    expect(service.identity.value, IdentityStatus.verified);
    expect(service.documentNumber.value, DemoData.demoPassportNumber);
    // Without this the Identity tab falls back to a grey placeholder even
    // though the guest just photographed their passport.
    expect(service.documentImagePath.value, '/carlton/id_capture.jpg');
  });

  testWidgets('confirm without a photo leaves the stored path untouched', (
    WidgetTester tester,
  ) async {
    final ScanIdController controller = Get.put(ScanIdController());
    await tester.pumpAndSettle();

    CheckInService.find.documentImagePath.value = '/carlton/existing.jpg';
    controller.confirm();

    // The upload route verifies identity with no local file; that must not
    // wipe a photo the scanner already captured.
    expect(
      CheckInService.find.documentImagePath.value,
      '/carlton/existing.jpg',
    );
  });
}
