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
}
