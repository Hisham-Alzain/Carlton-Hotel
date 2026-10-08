import 'package:carlton/extensions/date_extension.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

/// Shown dates follow each language's own order; dates sent to the API stay
/// `yyyy-MM-dd` with Latin digits whatever the app language.
void main() {
  final day = DateTime(2026, 10, 7);

  setUpAll(initializeDateFormatting);
  tearDown(() => Intl.defaultLocale = null);

  test('shown dates use the language order', () {
    Intl.defaultLocale = 'en';
    expect(day.formatDatePicker(), 'Oct 7, 2026');
    Intl.defaultLocale = 'fr';
    expect(day.formatDatePicker(), '7 oct. 2026');
  });

  test('API dates ignore the app language', () {
    for (final code in ['en', 'ar', 'fr', 'tr', 'es']) {
      Intl.defaultLocale = code;
      expect(day.formatApiDate(), '2026-10-07', reason: code);
    }
  });
}
