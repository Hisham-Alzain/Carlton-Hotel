/// Immutable snapshot of the reservation the check-in flow operates on.
/// Demo-only for now; a real integration replaces the DemoData constant with
/// a JSON factory and nothing else changes.
class ReservationSummary {
  final String guestName;
  final String suiteName;
  final String roomNumber;
  final String floorLabel;
  final String checkInDate;
  final String checkInTime;
  final String checkOutDate;
  final String checkOutTime;
  final String stayRangeLabel;
  final String bookingRef;

  const ReservationSummary({
    required this.guestName,
    required this.suiteName,
    required this.roomNumber,
    required this.floorLabel,
    required this.checkInDate,
    required this.checkInTime,
    required this.checkOutDate,
    required this.checkOutTime,
    required this.stayRangeLabel,
    required this.bookingRef,
  });
}
