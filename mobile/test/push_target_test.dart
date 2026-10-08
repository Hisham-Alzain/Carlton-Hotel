import 'package:carlton/services/notifications_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('points-expiring push opens Loyalty', () {
    expect(
      pushTargetFor({'points': '1200', 'expires_at': '2027-01-03T00:00:00Z'}),
      PushTarget.loyalty,
    );
  });

  test('room-ready and check-in-approved pushes open the stay', () {
    expect(pushTargetFor({'reservation_uuid': 'r1'}), PushTarget.stay);
  });

  test('welcome and unknown pushes open Home', () {
    expect(pushTargetFor({}), PushTarget.home);
    expect(pushTargetFor({'points': '5'}), PushTarget.home);
  });

  test('device token body keeps the server shape', () {
    expect(deviceTokenPayload('t', platform: 'android'), {
      'token': 't',
      'platform': 'android',
    });
  });
}
