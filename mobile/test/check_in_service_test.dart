import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

/// Guards the link between the check-in wizard and the Home progress bar.
/// Reverting the linkage would leave Home permanently at 1/4 while the guest
/// completes steps.
void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
  });

  tearDown(Get.reset);

  test('opens at 1/4 with only contact details complete', () {
    final service = CheckInService.find;
    expect(service.completedCount, 1);
    expect(service.totalSteps, 4);
    expect(service.isStepComplete(PreArrivalStep.contactDetails), isTrue);
    expect(service.isStepComplete(PreArrivalStep.identity), isFalse);
    expect(service.progress, closeTo(0.25, 0.001));
  });

  test('verifying identity advances progress from 1/4 to 2/4', () {
    final service = CheckInService.find;
    service.markIdentityVerified('SY-20480831');

    expect(service.identity.value, IdentityStatus.verified);
    expect(service.documentNumber.value, 'SY-20480831');
    expect(service.isStepComplete(PreArrivalStep.identity), isTrue);
    expect(service.completedCount, 2);
    expect(service.progress, closeTo(0.5, 0.001));
  });

  test('marking a step twice does not double-count it', () {
    final service = CheckInService.find;
    service.markIdentityVerified('SY-20480831');
    service.markIdentityVerified('SY-20480831');
    expect(service.completedCount, 2);
  });

  test('savePreferences stores the payload and ticks the requests step', () {
    final service = CheckInService.find;
    final next = service.preferences.value.copyWith(
      smokingRoom: true,
      notes: 'High floor please',
    );
    service.savePreferences(next);

    expect(service.preferences.value.smokingRoom, isTrue);
    expect(service.preferences.value.notes, 'High floor please');
    expect(service.isStepComplete(PreArrivalStep.specialRequests), isTrue);
  });

  test('completeCheckIn ends the pre-arrival state', () {
    final service = CheckInService.find;
    expect(service.isPreArrival.value, isTrue);
    service.completeCheckIn();
    expect(service.isPreArrival.value, isFalse);
    expect(service.completedCount, 4);
  });
}
