import 'dart:ui';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/models/currency.dart';
import 'package:carlton/models/language.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:get/get.dart';

class SettingsService extends GetxService {
  /// Reactive state
  final Rx<Locale> locale = const Locale('en').obs;
  final Rx<Language> language = Language(name: 'English', local: 'en').obs;
  final Rx<Currency> currency = Currency(
    name: 'USD \$',
    value: 'usd',
    symbol: '\$',
  ).obs;

  /// Available options
  final List<Language> langs = [
    Language(name: 'English', local: 'en'),
    Language(name: 'العربية', local: 'ar'),
  ];

  final List<Currency> currencies = [
    Currency(name: 'USD \$', value: 'usd', symbol: '\$'),
    Currency(name: 'SYP', value: 'syp', symbol: 'SYP'),
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
    final storedLang = StorageService.getString(StorageKeys.language);

    final code =
        storedLang ?? ((Get.deviceLocale?.languageCode == 'ar') ? 'ar' : 'en');

    locale.value = Locale(code);

    language.value = langs.firstWhere(
      (l) => l.local == code,
      orElse: () => langs.first,
    );
  }

  Future<void> changeLanguage(Language lang) async {
    language.value = lang;
    locale.value = Locale(lang.local);

    await StorageService.setString(StorageKeys.language, lang.local);

    Get.updateLocale(locale.value);
  }

  bool get isArabic => locale.value.languageCode == 'ar';

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
