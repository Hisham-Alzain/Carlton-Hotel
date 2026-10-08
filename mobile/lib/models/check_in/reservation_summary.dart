import 'package:carlton/models/guest.dart';
import 'package:carlton/models/stay.dart';
import 'package:intl/intl.dart';

/// Immutable snapshot of the reservation the check-in flow operates on.
///
/// Built from `GET /stays/upcoming` via [ReservationSummary.fromUpcomingStay].
/// Check-in is a real state transition, so the booking shown while performing
/// it is always the guest's own — [empty] covers only the frames before that
/// fetch lands.
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

  /// The pre-fetch state: every field blank, so the panel renders as absent
  /// rather than showing a stand-in booking. `CheckInService.loadReservation()`
  /// replaces it with the guest's real upcoming stay, and `reset()` restores it
  /// on sign-out.
  static const empty = ReservationSummary(
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

  /// Maps the guest's next reservation onto the wizard's booking panel.
  ///
  /// Fields the endpoint does not carry ([floorLabel], the two clock times)
  /// resolve to empty rather than to a plausible-looking guess: an invented
  /// floor number printed beside a real room number would be indistinguishable
  /// from a true one. The panel renders empty strings as absent.
  ///
  /// [suiteName] is passed in already resolved rather than read off
  /// `stay.roomName.value` here — `Localized.value` reaches for the live
  /// `SettingsService`, and the callers do that at the controller/service
  /// boundary (as `HomeController._upcomingToStay` does) so this factory stays
  /// a pure, testable mapping.
  factory ReservationSummary.fromUpcomingStay(
    UpcomingStay stay, {
    required String suiteName,
    Guest? guest,
  }) {
    final checkIn = stay.checkIn;
    final checkOut = stay.checkOut;
    return ReservationSummary(
      guestName: _guestName(guest),
      suiteName: suiteName,
      roomNumber: stay.roomNumber ?? '',
      floorLabel: '',
      checkInDate: checkIn != null ? _date.format(checkIn) : '',
      checkInTime: '',
      checkOutDate: checkOut != null ? _date.format(checkOut) : '',
      checkOutTime: '',
      stayRangeLabel: (checkIn != null && checkOut != null)
          ? '${_short.format(checkIn)} – ${_short.format(checkOut)}'
          : '',
      bookingRef: stay.bookingCode,
    );
  }

  /// Joins only the name parts that are present, so a guest who has given just
  /// a first name is greeted by it instead of by a dangling space.
  static String _guestName(Guest? guest) => [
    guest?.firstName,
    guest?.lastName,
  ].whereType<String>().where((p) => p.trim().isNotEmpty).join(' ').trim();

  static DateFormat get _date => DateFormat.yMMMd();
  static DateFormat get _short => DateFormat.MMMd();
}
