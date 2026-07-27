abstract class StorageKeys {
  static const token = 'token';
  static const fcmToken = 'fcmToken';
  static const user = 'user';
  static const isFirstTime = 'isFirstTime';
  static const language = 'language';
  static const currency = 'currency';

  // Stay preferences (Account → Preferences). Language/currency are not here:
  // those are owned by SettingsService under `language`/`currency` above.
  static const prefBed = 'pref_bed';
  static const prefPillow = 'pref_pillow';
  static const prefMattress = 'pref_mattress';
  static const prefSmoking = 'pref_smoking';
  static const prefEarlyCheckIn = 'pref_early_check_in';
  static const prefLateCheckout = 'pref_late_checkout';
}
