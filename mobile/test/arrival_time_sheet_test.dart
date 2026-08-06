import 'package:carlton/components/check_in/arrival_time_sheet.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

/// The arrival-time step has no Figma screen — this sheet is the only thing
/// that makes the Home checklist's third row functional. Guard it.
void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  testWidgets('picking a slot completes the arrivalTime step', (tester) async {
    await tester.pumpWidget(
      GetMaterialApp(
        translations: Local(),
        locale: const Locale('en'),
        home: const Scaffold(body: ArrivalTimeSheet()),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      CheckInService.find.isStepComplete(PreArrivalStep.arrivalTime),
      isFalse,
    );
    expect(CheckInService.find.completedCount, 1);

    await tester.tap(find.text('3:00 PM'));
    await tester.pump();

    expect(
      CheckInService.find.isStepComplete(PreArrivalStep.arrivalTime),
      isTrue,
    );
    expect(CheckInService.find.arrivalTimeLabel.value, '3:00 PM');
    expect(CheckInService.find.completedCount, 2);
  });
}
