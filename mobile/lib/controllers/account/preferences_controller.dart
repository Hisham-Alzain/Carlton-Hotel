import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:get/get.dart';

/// Stay + app preferences. Bed / pillow / mattress and the room toggles are
/// device-local: they are persisted via [StorageService] and travel to the
/// hotel with the check-in payload, so there is nothing to read back from the
/// API. Language and currency are NOT stored here — they are
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
    mattressId.value =
        StorageService.getString(StorageKeys.prefMattress) ?? 'medium';
    smoking.value = StorageService.getBool(StorageKeys.prefSmoking) ?? false;
    earlyCheckIn.value =
        StorageService.getBool(StorageKeys.prefEarlyCheckIn) ?? false;
    lateCheckout.value =
        StorageService.getBool(StorageKeys.prefLateCheckout) ?? false;
  }

  PreferenceOption _optionOf(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first);

  String get bedLabel =>
      _optionOf(PreferenceOptions.bedOptions, bedId.value).label;
  // No ' Pillow' suffix: the option labels already carry the noun ('Soft
  // Pillow', 'Firm Pillow', …), so appending it rendered "Firm Pillow Pillow"
  // in the field while the dropdown row below read "Firm Pillow".
  String get pillowLabel =>
      _optionOf(PreferenceOptions.pillowOptions, pillowId.value).label;
  String get mattressLabel =>
      _optionOf(PreferenceOptions.mattressOptions, mattressId.value).label;
  String get languageLabel =>
      _optionOf(PreferenceOptions.languageOptions, languageId).label;
  String get currencyLabel =>
      _optionOf(PreferenceOptions.currencyOptions, currencyId).label;

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
    final currency = SettingsService.currencies.firstWhere(
      (c) => c.value == o.id,
      orElse: () => SettingsService.currencies.first,
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
