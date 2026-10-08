import 'package:carlton/constants/hotel_time.dart';
import 'package:intl/intl.dart';

extension DateExtensions on DateTime {
  String formatDate() {
    return DateFormat('dd-MMM-yyyy').format(this);
  }

  String formatDateMonth() {
    return DateFormat('MMM-yyyy').format(this);
  }

  String formatDatePicker() {
    return DateFormat.yMMMd().format(this);
  }

  /// `yyyy-MM-dd` — the `date` field the table-reservation endpoint expects.
  String formatApiDate() {
    return DateFormat('yyyy-MM-dd', 'en').format(this);
  }

  /// Reads this date-time's wall clock as hotel time (see [HotelTime]) and
  /// returns that moment in UTC — 14:00 picked for Damascus is sent as
  /// `…T11:00:00.000Z` whatever zone the phone is in. UTC because Laravel
  /// stores a datetime's wall-clock part and drops any offset sent with it.
  String toApiDateTime() => DateTime.utc(
    year,
    month,
    day,
    hour,
    minute,
    second,
  ).subtract(HotelTime.utcOffset).toIso8601String();
}
