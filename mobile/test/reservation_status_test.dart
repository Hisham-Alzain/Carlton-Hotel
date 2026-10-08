import 'package:carlton/models/reservation.dart';
import 'package:flutter_test/flutter_test.dart';

/// The Confirmed screen and the Home entitlement both follow
/// [Reservation.isAwaitingHotel]: `pending` waits on the hotel, `confirmed`
/// does not.
void main() {
  Reservation withStatus(String status) =>
      Reservation.fromJson({'uuid': 'r1', 'status': status});

  test('pending and unverified bookings wait on the hotel', () {
    expect(withStatus('pending').isAwaitingHotel, isTrue);
    expect(withStatus('pending_verification').isAwaitingHotel, isTrue);
  });

  test('a confirmed booking does not wait', () {
    expect(withStatus('confirmed').isAwaitingHotel, isFalse);
    expect(withStatus('checked_in').isAwaitingHotel, isFalse);
  });
}
