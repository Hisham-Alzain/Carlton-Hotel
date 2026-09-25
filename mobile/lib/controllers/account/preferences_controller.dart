import 'package:carlton/constants/preference_options.dart';
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

  late final RxString bedId;
  late final RxString pillowId;
  late final RxString mattressId;
  late final RxBool smoking;
  late final RxBool earlyCheckIn;
  late final RxBool lateCheckout;

  String get languageId => _settings.locale.value.languageCode;
  String get currencyId => _settings.currency.value.value;

  @override
  void onInit() {
    super.onInit();
    bedId = (StorageService.getString(StorageKeys.prefBed) ?? 'king').obs;
    pillowId = (StorageService.getString(StorageKeys.prefPillow) ?? 'firm').obs;
    mattressId =
        (StorageService.getString(StorageKeys.prefMattress) ?? 'medium').obs;
    smoking = (StorageService.getBool(StorageKeys.prefSmoking) ?? false).obs;
    earlyCheckIn =
        (StorageService.getBool(StorageKeys.prefEarlyCheckIn) ?? false).obs;
    lateCheckout =
        (StorageService.getBool(StorageKeys.prefLateCheckout) ?? false).obs;
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

  void chooseBed(PreferenceOption o) {
    bedId.value = o.id;
    StorageService.setString(StorageKeys.prefBed, o.id);
  }

  void choosePillow(PreferenceOption o) {
    pillowId.value = o.id;
    StorageService.setString(StorageKeys.prefPillow, o.id);
  }

  void chooseMattress(PreferenceOption o) {
    mattressId.value = o.id;
    StorageService.setString(StorageKeys.prefMattress, o.id);
  }

  /// Delegates to [SettingsService] so the choice actually changes the app
  /// locale and persists to the shared `language` key.
  Future<void> chooseLanguage(PreferenceOption o) async {
    final lang = _settings.langs.firstWhere(
      (l) => l.local == o.id,
      orElse: () => _settings.langs.first,
    );
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
