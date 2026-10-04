import 'package:intl/intl.dart';

extension DateExtensions on DateTime {
  String formatDate() {
    return DateFormat('dd-MMM-yyyy').format(this);
  }

  String formatDateMonth() {
    return DateFormat('MMM-yyyy').format(this);
  }

  String formatDatePicker() {
    return DateFormat('MMM d, yyyy').format(this);
  }

  /// `yyyy-MM-dd` — the `date` field the table-reservation endpoint expects.
  String formatApiDate() {
    return DateFormat('yyyy-MM-dd').format(this);
  }

  /// `2026-08-14T11:00:00.000Z` — the moment in UTC, for datetime fields the
  /// API stores. Laravel saves a datetime's wall-clock part and drops any
  /// offset, so a local time (or one sent as `+03:00`) would be stored as if
  /// it were UTC and come back shifted by the guest's offset.
  String toApiDateTime() => toUtc().toIso8601String();
}
