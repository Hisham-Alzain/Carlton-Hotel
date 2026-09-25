import 'package:carlton/models/currency.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:intl/intl.dart';

/// The single place a monetary amount becomes text.
///
/// Every price on the wire is a `*_usd` field, so callers pass the USD figure
/// and this converts it into whatever the guest selected. Before this existed
/// three controllers each carried their own `'\$$amount'` formatter and the
/// currency picker only swapped the symbol — a $150 room read "SYP 150".
extension PriceExtensions on double {
  /// Formats this **USD** amount in the guest's selected currency.
  ///
  /// Whole amounts drop their decimals (`$580`, not `$580.00`); fractional ones
  /// keep them. Non-base currencies are prefixed `≈` while rates are
  /// hand-maintained — see [ExchangeRates].
  String formatPrice({int? decimalDigits, bool approximate = true}) =>
      MoneyFormat.usd(
        this,
        decimalDigits: decimalDigits,
        approximate: approximate,
      );
}

/// Non-extension entry points, for the many call sites holding a `String?`
/// straight off a JSON payload rather than a parsed double.
abstract class MoneyFormat {
  /// Formats a USD amount in the active currency.
  static String usd(
    double amount, {
    int? decimalDigits,
    bool approximate = true,
  }) {
    final settings = SettingsService.find;
    final currency = settings.currency.value;
    final converted = ExchangeRates.convert(amount, currency.value);

    // Whole amounts read better without minor units, and after conversion to a
    // thousands-scale currency like SYP they are effectively always whole.
    final digits =
        decimalDigits ??
        (converted == converted.roundToDouble() ? 0 : currency.decimalDigits);

    final text = NumberFormat.currency(
      locale: settings.locale.value.toString(),
      symbol: currency.symbol,
      decimalDigits: digits,
    ).format(converted);

    // Only the base currency is an exact quote. Suppress the marker once real
    // rates are wired, since a live conversion is not an estimate.
    final needsMarker =
        approximate && !currency.isBase && !ExchangeRates.isLive;
    return needsMarker ? '≈$text' : text;
  }

  /// Same, for a decimal string straight off the API (`"380.00"`). Unparseable
  /// or absent values format as zero rather than throwing mid-build.
  static String usdString(String? amount, {int? decimalDigits}) =>
      usd(double.tryParse(amount ?? '') ?? 0, decimalDigits: decimalDigits);
}
