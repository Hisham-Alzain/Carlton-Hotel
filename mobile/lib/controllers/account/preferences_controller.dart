import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:get/get.dart';

/// Stay + app preferences. Bed / pillow / mattress and the room toggles are
/// demo-only values persisted via [StorageService] (a real backend would sync
/// them to the account). Language and currency are NOT stored here — they are
/// owned by [SettingsService] (which drives the app locale), so this screen
/// reads and writes them through that service.
class PreferencesController extends GetxController {
  final SettingsService _settings = SettingsService.find;

  late String bedId;
  late String pillowId;
  late String mattressId;
  late bool smoking;
  late bool earlyCheckIn;
  late bool lateCheckout;

  String get languageId => _settings.locale.value.languageCode;
  String get currencyId => _settings.currency.value.value;

  @override
  void onInit() {
    super.onInit();
    bedId = StorageService.getString(StorageKeys.prefBed) ?? 'king';
    pillowId = StorageService.getString(StorageKeys.prefPillow) ?? 'firm';
    mattressId = StorageService.getString(StorageKeys.prefMattress) ?? 'medium';
    smoking = StorageService.getBool(StorageKeys.prefSmoking) ?? false;
    earlyCheckIn =
        StorageService.getBool(StorageKeys.prefEarlyCheckIn) ?? false;
    lateCheckout =
        StorageService.getBool(StorageKeys.prefLateCheckout) ?? false;
  }

  PreferenceOption _optionOf(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first);

  String get bedLabel => _optionOf(DemoData.bedOptions, bedId).label;
  String get pillowLabel =>
      '${_optionOf(DemoData.pillowOptions, pillowId).label} Pillow';
  String get mattressLabel =>
      _optionOf(DemoData.mattressOptions, mattressId).label;
  String get languageLabel =>
      _optionOf(DemoData.languageOptions, languageId).label;
  String get currencyLabel =>
      _optionOf(DemoData.currencyOptions, currencyId).label;

  void _persistString(String key, String value) {
    StorageService.setString(key, value);
    update();
  }

  void chooseBed(PreferenceOption o) {
    bedId = o.id;
    _persistString(StorageKeys.prefBed, o.id);
  }

  void choosePillow(PreferenceOption o) {
    pillowId = o.id;
    _persistString(StorageKeys.prefPillow, o.id);
  }

  void chooseMattress(PreferenceOption o) {
    mattressId = o.id;
    _persistString(StorageKeys.prefMattress, o.id);
  }

  /// Delegates to [SettingsService] so the choice actually changes the app
  /// locale and persists to the shared `language` key.
  Future<void> chooseLanguage(PreferenceOption o) async {
    final lang = _settings.langs.firstWhere(
      (l) => l.local == o.id,
      orElse: () => _settings.langs.first,
    );
    await _settings.changeLanguage(lang);
    update();
  }

  Future<void> chooseCurrency(PreferenceOption o) async {
    final currency = _settings.currencies.firstWhere(
      (c) => c.value == o.id,
      orElse: () => _settings.currencies.first,
    );
    await _settings.changeCurrency(currency);
    update();
  }

  void toggleSmoking(bool value) {
    smoking = value;
    StorageService.setBool(StorageKeys.prefSmoking, value);
    update();
  }

  void toggleEarlyCheckIn(bool value) {
    earlyCheckIn = value;
    StorageService.setBool(StorageKeys.prefEarlyCheckIn, value);
    update();
  }

  void toggleLateCheckout(bool value) {
    lateCheckout = value;
    StorageService.setBool(StorageKeys.prefLateCheckout, value);
    update();
  }
}
