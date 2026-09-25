/// A currency the app can display prices in.
///
/// Every price on the wire is a `*_usd` field, so USD is the base and the other
/// entries are conversions — see [ExchangeRates]. [value] is the code persisted
/// under `StorageKeys.currency` and must stay stable; renaming one silently
/// resets every guest who had it selected back to the default.
class Currency {
  /// Storage/API code — `usd`, `syp`, `try`. Never change these.
  final String value;

  /// ISO 4217 code shown in the picker (`USD`, `SYP`, `TRY`).
  final String code;

  /// Prefix used when formatting an amount.
  final String symbol;

  /// Decimals to render. SYP is quoted in the thousands, where minor units are
  /// noise — `£S1,950,000` reads correctly, `£S1,950,000.00` does not.
  final int decimalDigits;

  const Currency({
    required this.value,
    required this.code,
    required this.symbol,
    this.decimalDigits = 2,
  });

  /// True for the currency prices are actually quoted in. Only the base is an
  /// exact figure; everything else is a conversion and is marked as such.
  bool get isBase => value == ExchangeRates.baseCode;

  @override
  String toString() => code;

  @override
  bool operator ==(Object other) => other is Currency && other.value == value;

  @override
  int get hashCode => value.hashCode;
}

/// USD → target multipliers.
///
/// ⚠️ HAND-MAINTAINED AND INDICATIVE. The backend exposes no rate: every
/// monetary column is `*_usd` and there is no `/exchange-rates` endpoint, so
/// these are the only numbers available and they go stale. Prices in a
/// non-base currency are therefore rendered with a `≈` so a guest is never
/// shown a converted figure as if it were exact.
///
/// Replacing this with live rates is deliberately a one-method change: have
/// something fetch the map and hand it to [ExchangeRates.override], then the
/// rest of the app is already correct. Until that exists, update
/// [_rates] and [asOf] together — a rate without a date is unauditable.
abstract class ExchangeRates {
  /// The currency prices arrive in. Not configurable: it is what the API sends.
  static const String baseCode = 'usd';

  /// When [_rates] was last checked, so staleness is visible in review.
  static const String asOf = '2026-09-08';

  static const Map<String, double> _rates = {
    'usd': 1.0,
    // Syrian pound, indicative parallel-market rate.
    'syp': 13000.0,
    'try': 41.0,
  };

  static Map<String, double>? _live;

  /// Swap in rates from a real source once one exists. Pass null to fall back
  /// to the hand-maintained table.
  static void override(Map<String, double>? rates) => _live = rates;

  /// True once [override] has supplied real rates — the `≈` marker and the
  /// staleness caveat can be dropped at that point.
  static bool get isLive => _live != null;

  /// Multiplier from the base currency to [code]. Unknown codes fall back to
  /// 1.0, which renders the base amount rather than a wrong one.
  static double rateFor(String code) =>
      (_live ?? _rates)[code] ?? _rates[code] ?? 1.0;

  /// Converts a base-currency (USD) amount into [code].
  static double convert(double baseAmount, String code) =>
      baseAmount * rateFor(code);
}
