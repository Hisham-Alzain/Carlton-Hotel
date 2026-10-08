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
/// Live rates come from `GET /public/exchange-rates` and are handed to
/// [override] by `SettingsService.loadExchangeRates`. The hand-maintained table
/// below is only the fallback: for a currency the hotel has not set a rate for
/// yet, and while the first fetch is in flight or has failed. Every price is
/// still paid in USD — a converted figure is display only — so a currency
/// without a fresh live rate is rendered with a `≈` and the guest is never
/// shown an estimate as if it were exact. Keep [_rates] and [asOf] updated
/// together — a rate without a date is unauditable.
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
  static Set<String> _stale = const {};

  /// Swap in the server's rates. [stale] names the currencies the server marked
  /// out of date, which keep the `≈` marker. Pass null to fall back to the
  /// hand-maintained table.
  static void override(
    Map<String, double>? rates, {
    Set<String> stale = const {},
  }) {
    _live = rates;
    _stale = stale;
  }

  /// True when [code] has a fresh rate from the server — the `≈` marker can be
  /// dropped for it.
  static bool isLiveFor(String code) =>
      (_live?.containsKey(code) ?? false) && !_stale.contains(code);

  /// Multiplier from the base currency to [code]. Unknown codes fall back to
  /// 1.0, which renders the base amount rather than a wrong one.
  static double rateFor(String code) =>
      (_live ?? _rates)[code] ?? _rates[code] ?? 1.0;

  /// Converts a base-currency (USD) amount into [code].
  static double convert(double baseAmount, String code) =>
      baseAmount * rateFor(code);
}
