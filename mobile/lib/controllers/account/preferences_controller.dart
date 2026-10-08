import 'package:carlton/constants/preference_options.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:get/get.dart';

/// Stay + app preferences. Bed, pillow and floor live on the guest's profile
/// (`PATCH /auth/guest/preferences`), so the front desk sees them and the
/// check-in checklist's "preferences set" step can complete; a copy is kept on
/// the device so the screen is never empty while the server answers. Mattress
/// and the room toggles have no server field and stay device-local. Language
/// and currency are NOT stored here — they are owned by [SettingsService]
/// (which drives the app locale), so this screen reads and writes them through
/// that service.
class PreferencesController extends GetxController {
  final SettingsService _settings = SettingsService.find;

  final RxString bedId = ''.obs;
  final RxString pillowId = ''.obs;
  final RxString floorId = ''.obs;
  final RxString mattressId = ''.obs;
  final RxBool smoking = false.obs;
  final RxBool earlyCheckIn = false.obs;
  final RxBool lateCheckout = false.obs;

  String get languageId => _settings.locale.value.languageCode;
  String get currencyId => _settings.currency.value.value;

  @override
  void onInit() {
    super.onInit();
    // A value the server no longer accepts (the old `extra` bed) falls back to
    // the default instead of being sent and refused.
    bedId.value = _known(
      PreferenceOptions.bedOptions,
      StorageService.getString(StorageKeys.prefBed),
      'king',
    );
    pillowId.value = _known(
      PreferenceOptions.pillowOptions,
      StorageService.getString(StorageKeys.prefPillow),
      'firm',
    );
    floorId.value = _known(
      PreferenceOptions.floorOptions,
      StorageService.getString(StorageKeys.prefFloor),
      'any',
    );
    mattressId.value =
        StorageService.getString(StorageKeys.prefMattress) ?? 'medium';
    smoking.value = StorageService.getBool(StorageKeys.prefSmoking) ?? false;
    earlyCheckIn.value =
        StorageService.getBool(StorageKeys.prefEarlyCheckIn) ?? false;
    lateCheckout.value =
        StorageService.getBool(StorageKeys.prefLateCheckout) ?? false;
    _loadFromServer();
  }

  static String _known(
    List<PreferenceOption> options,
    String? id,
    String fallback,
  ) => options.any((o) => o.id == id) ? id! : fallback;

  /// The server's copy wins over this device's: the guest may have set it from
  /// another phone. Silent — a failure leaves the local values showing.
  Future<void> _loadFromServer() async {
    if (!MiddlewareService.find.isAuthenticated) return;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/auth/guest/me',
      showErrorDialog: false,
    );
    if (isClosed || !res.hasData) return;
    final prefs = res.data!['preferences'];
    if (prefs is! Map) return;
    // A field the guest already changed while this loaded keeps their pick:
    // it is newer than the server's copy and is being saved over it.
    if (!_picks.containsKey('bed_type')) {
      bedId.value = _known(
        PreferenceOptions.bedOptions,
        prefs['bed_type'] as String?,
        bedId.value,
      );
    }
    if (!_picks.containsKey('pillow_type')) {
      pillowId.value = _known(
        PreferenceOptions.pillowOptions,
        prefs['pillow_type'] as String?,
        pillowId.value,
      );
    }
    if (!_picks.containsKey('floor_preference')) {
      floorId.value = _known(
        PreferenceOptions.floorOptions,
        prefs['floor_preference'] as String?,
        floorId.value,
      );
    }
  }

  /// How many picks each server field has had this visit. A save's answer
  /// only counts while it is still the latest pick for its field.
  final Map<String, int> _picks = {};

  PreferenceOption _optionOf(List<PreferenceOption> options, String id) =>
      options.firstWhere((o) => o.id == id, orElse: () => options.first);

  String get bedLabel =>
      _optionOf(PreferenceOptions.bedOptions, bedId.value).label;
  // No ' Pillow' suffix: the option labels already carry the noun ('Soft
  // Pillow', 'Firm Pillow', …), so appending it rendered "Firm Pillow Pillow"
  // in the field while the dropdown row below read "Firm Pillow".
  String get pillowLabel =>
      _optionOf(PreferenceOptions.pillowOptions, pillowId.value).label;
  String get floorLabel =>
      _optionOf(PreferenceOptions.floorOptions, floorId.value).label;
  String get mattressLabel =>
      _optionOf(PreferenceOptions.mattressOptions, mattressId.value).label;
  String get languageLabel =>
      _optionOf(PreferenceOptions.languageOptions, languageId).label;
  String get currencyLabel =>
      _optionOf(PreferenceOptions.currencyOptions, currencyId).label;

  void _persistString(String key, String value) =>
      StorageService.setString(key, value);

  /// Applies the choice at once, saves it to the profile, and puts the old
  /// value back if the server refuses — a preference the desk never received
  /// must not look saved. A refusal that arrives after a newer pick for the
  /// same field is ignored: the newer pick owns the field.
  Future<void> _chooseServerField({
    required RxString state,
    required String storageKey,
    required String field,
    required String id,
  }) async {
    final previous = state.value;
    final pick = (_picks[field] ?? 0) + 1;
    _picks[field] = pick;
    state.value = id;
    _persistString(storageKey, id);
    if (!MiddlewareService.find.isAuthenticated) return;
    final res = await ApiService.find.patch<Map<String, dynamic>>(
      path: '/auth/guest/preferences',
      data: {field: id},
      showErrorDialog: false,
    );
    if (isClosed || res.ok || _picks[field] != pick) return;
    state.value = previous;
    _persistString(storageKey, previous);
    if (res.error != null) ApiService.find.dialogs.showError(res.error!);
  }

  void chooseBed(PreferenceOption o) => _chooseServerField(
    state: bedId,
    storageKey: StorageKeys.prefBed,
    field: 'bed_type',
    id: o.id,
  );

  void choosePillow(PreferenceOption o) => _chooseServerField(
    state: pillowId,
    storageKey: StorageKeys.prefPillow,
    field: 'pillow_type',
    id: o.id,
  );

  void chooseFloor(PreferenceOption o) => _chooseServerField(
    state: floorId,
    storageKey: StorageKeys.prefFloor,
    field: 'floor_preference',
    id: o.id,
  );

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
