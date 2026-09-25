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

  final RxString bedId = ''.obs;
  final RxString pillowId = ''.obs;
  final RxString mattressId = ''.obs;
  final RxBool smoking = false.obs;
  final RxBool earlyCheckIn = false.obs;
  final RxBool lateCheckout = false.obs;

  String get languageId => _settings.locale.value.languageCode;
  String get currencyId => _settings.currency.value.value;

  @override
  void onInit() {
    super.onInit();
    bedId.value = StorageService.getString(StorageKeys.prefBed) ?? 'king';
    pillowId.value = StorageService.getString(StorageKeys.prefPillow) ?? 'firm';
    mattressId.value = StorageService.getString(StorageKeys.prefMattress) ?? 'medium';
    smoking.value = StorageService.getBool(StorageKeys.prefSmoking) ?? false;
    earlyCheckIn.value = StorageService.getBool(StorageKeys.prefEarlyCheckIn) ?? false;
    lateCheckout.value = StorageService.getBool(StorageKeys.prefLateCheckout) ?? false;
  }

  PreferenceOption _optionOf(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first);

  String get bedLabel => _optionOf(DemoData.bedOptions, bedId.value).label;
  String get pillowLabel =>
      '${_optionOf(DemoData.pillowOptions, pillowId.value).label} Pillow';
  String get mattressLabel =>
      _optionOf(DemoData.mattressOptions, mattressId.value).label;
  String get languageLabel =>
      _optionOf(DemoData.languageOptions, languageId).label;
  String get currencyLabel =>
      _optionOf(DemoData.currencyOptions, currencyId).label;

  void _persistString(String key, String value) =>
      StorageService.setString(key, value);

  void chooseBed(PreferenceOption o) {
    bedId.value = o.id;
    _persistString(StorageKeys.prefBed, o.id);
  }

  void choosePillow(PreferenceOption o) {
    pillowId.value = o.id;
    _persistString(StorageKeys.prefPillow, o.id);
  }

  void chooseMattress(PreferenceOption o) {
    mattressId.value = o.id;
    _persistString(StorageKeys.prefMattress, o.id);
  }

  /// Delegates to [SettingsService] so the choice actually changes the app
  /// locale and persists to the shared `language` key.
  Future<void> chooseLanguage(PreferenceOption o) async {
    final lang = _settings.langs.firstWhere(
      (l) => l.local == o.id,
      orElse: () => _settings.langs.first,
    );
    // languageId reads _settings.locale (Rx), so the Obx repaints on its own.
    await _settings.changeLanguage(lang);
  }

  Future<void> chooseCurrency(PreferenceOption o) async {
    final currency = _settings.currencies.firstWhere(
      (c) => c.value == o.id,
      orElse: () => _settings.currencies.first,
    );
    await _settings.changeCurrency(currency);
  }

  void toggleSmoking(bool value) {
    smoking.value = value;
    StorageService.setBool(StorageKeys.prefSmoking, value);
  }

  void toggleEarlyCheckIn(bool value) {
    earlyCheckIn.value = value;
    StorageService.setBool(StorageKeys.prefEarlyCheckIn, value);
  }

  void toggleLateCheckout(bool value) {
    lateCheckout.value = value;
    StorageService.setBool(StorageKeys.prefLateCheckout, value);
  }
}
