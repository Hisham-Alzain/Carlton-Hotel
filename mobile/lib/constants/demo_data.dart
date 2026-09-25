import 'package:carlton/models/check_in/reservation_summary.dart';

/// Pre-fetch placeholder only — `CheckInService.loadReservation()` overwrites
/// this with the guest's real upcoming stay (`GET /stays/upcoming`) on init,
/// so it is never what the guest actually sees once data loads.
abstract class DemoData {
  static const preArrivalReservation = ReservationSummary(
    guestName: '',
    suiteName: '',
    roomNumber: '',
    floorLabel: '',
    checkInDate: '',
    checkInTime: '',
    checkOutDate: '',
    checkOutTime: '',
    stayRangeLabel: '',
    bookingRef: '',
  );
}
