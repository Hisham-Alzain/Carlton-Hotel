// test/check_in_models_test.dart
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('PreArrivalStep has exactly the four home-checklist steps', () {
    expect(PreArrivalStep.values, hasLength(4));
    expect(PreArrivalStep.values, contains(PreArrivalStep.contactDetails));
    expect(PreArrivalStep.values, contains(PreArrivalStep.identity));
    expect(PreArrivalStep.values, contains(PreArrivalStep.arrivalTime));
    expect(PreArrivalStep.values, contains(PreArrivalStep.specialRequests));
  });

  test('demo reservation carries the Figma booking values', () {
    final r = DemoData.preArrivalReservation;
    expect(r.guestName, 'Ahmed Al-Hassan');
    expect(r.roomNumber, '812');
    expect(r.suiteName, 'Grand Damascus Suite');
    expect(r.bookingRef, '#CLT-0082');
    expect(r.floorLabel, '3rd floor');
  });

  test('StayPreferences.copyWith replaces only the named field', () {
    const base = StayPreferences(
      bedTypeId: 'king',
      pillowId: 'firm',
      mattressId: 'medium',
      smokingRoom: false,
      earlyCheckIn: true,
      lateCheckOut: false,
      extraPillows: false,
      notes: '',
    );
    final next = base.copyWith(smokingRoom: true);
    expect(next.smokingRoom, isTrue);
    expect(next.earlyCheckIn, isTrue);
    expect(next.bedTypeId, 'king');
    expect(next.notes, '');
  });
}
