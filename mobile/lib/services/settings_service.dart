import 'dart:ui';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/currency.dart';
import 'package:carlton/models/language.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

class SettingsService extends GetxService {
  /// Reactive state
  final Rx<Locale> locale = const Locale('en').obs;
  final Rx<Language> language = Language(name: 'English', local: 'en').obs;
  final Rx<Currency> currency = currencies.first.obs;

  /// Available options
  /// Each language is named in its own script — a guest who cannot read the
  /// current UI language still has to be able to find their own row.
  /// Order and membership follow [Local.supportedCodes].
  static const Map<String, String> _endonyms = {
    'en': 'English',
    'ar': 'العربية',
    'fr': 'Français',
    'tr': 'Türkçe',
    'es': 'Español',
  };

  final List<Language> langs = [
    for (final code in Local.supportedCodes)
      Language(name: _endonyms[code]!, local: code),
  ];

  /// The three currencies the app ships. USD is the base — every monetary
  /// field on the wire is `*_usd` — and the other two are converted at display
  /// time by [MoneyFormat]. Order here is picker order.
  ///
  /// `value` is the persisted code: renaming one resets every guest who had it
  /// selected. Display names are localized via [AppTranslations], not stored
  /// here, so they follow the language switch.
  static const List<Currency> currencies = [
    Currency(value: 'usd', code: 'USD', symbol: '\$'),
    Currency(value: 'syp', code: 'SYP', symbol: '£S', decimalDigits: 0),
    Currency(value: 'try', code: 'TRY', symbol: '₺'),
  ];

  @override
  void onInit() {
    _loadLocale();
    _loadCurrency();
    super.onInit();
  }

  static SettingsService get find => Get.find();

  /// -------- LANGUAGE --------

  void _loadLocale() {
    // An explicit choice always wins. Failing that, fall back to the device
    // locale when the app actually ships it — previously this only ever
    // resolved to `ar` or `en`, so a French or Turkish phone silently got
    // English even after those locales existed.
    final stored = StorageService.getString(StorageKeys.language);
    final device = Get.deviceLocale?.languageCode;
    final code = [stored, device].firstWhere(
      (c) => c != null && Local.supportedCodes.contains(c),
      orElse: () => Local.supportedCodes.first,
    )!;

    locale.value = Locale(code);
    Intl.defaultLocale = code;

    language.value = langs.firstWhere(
      (l) => l.local == code,
      orElse: () => langs.first,
    );
  }

  Future<void> changeLanguage(Language lang) async {
    language.value = lang;
    locale.value = Locale(lang.local);
    Intl.defaultLocale = lang.local;

    await StorageService.setString(StorageKeys.language, lang.local);

    Get.updateLocale(locale.value);
    _saveToProfile(lang.local);
  }

  /// Remembers a signed-in guest's language on their profile
  /// (`preferred_locale`, which the server accepts for all five app languages),
  /// so it follows them to another device and the server writes push
  /// notifications in it. Silent and best-effort: the language already
  /// switched locally, and nothing here may block that.
  void _saveToProfile(String code) {
    if (!Get.isRegistered<MiddlewareService>() ||
        !MiddlewareService.find.isAuthenticated) {
      return;
    }
    if (MiddlewareService.find.guest.value?.preferredLocale == code) return;
    ApiService.find.put<Map<String, dynamic>>(
      path: '/auth/guest/profile',
      data: {'preferred_locale': code},
      showErrorDialog: false,
    );
  }

  bool get isArabic => locale.value.languageCode == 'ar';

  /// Loads the hotel's USD rates (`GET /public/exchange-rates`) so converted
  /// prices are live. Silent: a failed call, or a currency the hotel has not
  /// set a rate for (`rate: null`), keeps the built-in table. Called once at
  /// launch — the endpoint is cached for five minutes.
  Future<void> loadExchangeRates() async {
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/exchange-rates',
      showErrorDialog: false,
    );
    if (!res.hasData) return;
    final rates = <String, double>{'usd': 1.0};
    final stale = <String>{};
    for (final row in res.data!['rates'] as List? ?? const []) {
      if (row is! Map) continue;
      final code = (row['currency'] as String?)?.toLowerCase();
      final rate = double.tryParse('${row['rate'] ?? ''}');
      if (code == null || rate == null || rate <= 0) continue;
      rates[code] = rate;
      if (row['is_stale'] == true) stale.add(code);
    }
    if (rates.length > 1) ExchangeRates.override(rates, stale: stale);
  }

  /// -------- CURRENCY --------

  void _loadCurrency() {
    final storedCurrency = StorageService.getString(StorageKeys.currency);

    currency.value = currencies.firstWhere(
      (c) => c.value == storedCurrency,
      orElse: () => currencies.first,
    );
  }

  Future<void> changeCurrency(Currency newCurrency) async {
    currency.value = newCurrency;

    await StorageService.setString(StorageKeys.currency, newCurrency.value);
  }

  bool isCurrencySelected(Currency c) {
    return currency.value.value == c.value;
  }
}
