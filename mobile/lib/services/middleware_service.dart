import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:get/get.dart';

/// Single source of truth for the guest session — the bearer token plus the
/// authenticated [Guest] identity and its entitlement flags. Permanent
/// singleton (see main.dart). Replaces the old dual auth mechanism
/// (SessionService's fake booleans + a mis-directed token check).
class MiddlewareService extends GetxService {
  late bool isFirstTime;

  /// The authenticated guest, or null when signed out. Hydrated from the cache
  /// on cold start, then refreshed by [checkToken] against `/auth/guest/me`.
  final Rx<Guest?> guest = Rx<Guest?>(null);

  MiddlewareCases middlewareCase = MiddlewareCases.noToken;

  static MiddlewareService get find => Get.find();

  @override
  void onInit() {
    super.onInit();
    isFirstTime = StorageService.getBool(StorageKeys.isFirstTime) ?? true;
    _hydrateGuestFromCache();
  }

  // ── Session state (read across the app) ───────────────────────────────────
  bool get isAuthenticated => guest.value != null;
  bool get hasBooking => guest.value?.hasBooking ?? false;
  bool get isCheckedIn => guest.value?.isCheckedIn ?? false;

  bool get isTokenValid => middlewareCase == MiddlewareCases.validToken;
  bool get isTokenInvalid => middlewareCase == MiddlewareCases.invalidToken;
  bool get hasNoToken => middlewareCase == MiddlewareCases.noToken;

  // ── Lifecycle ─────────────────────────────────────────────────────────────

  /// Refreshes the guest + entitlements from `/auth/guest/me`. Called on splash
  /// after cache hydration. A network/server hiccup keeps the cached session —
  /// only a real 401 signs the guest out (handled by the error interceptor →
  /// [ApiService] `_handleUnauthorized`).
  Future<void> checkToken() async {
    final token = StorageService.getString(StorageKeys.token);
    if (token == null || token.isEmpty) {
      middlewareCase = MiddlewareCases.noToken;
      return;
    }
    final response = await ApiService.find.get<Map<String, dynamic>>(
      path: '/auth/guest/me',
      showErrorDialog: false,
    );
    if (response.statusCode == 200 && response.data != null) {
      _setGuest(Guest.fromJson(response.data!));
      middlewareCase = MiddlewareCases.validToken;
    } else if (response.statusCode == 401) {
      middlewareCase = MiddlewareCases.invalidToken;
    } else {
      middlewareCase = MiddlewareCases.validToken;
    }
  }

  /// The only place a token is written — from OTP verify on success. Mirrors
  /// the guest's `preferred_locale` into the app on first sign-in.
  Future<void> saveSession({
    required String token,
    required Guest guest,
  }) async {
    await StorageService.setString(StorageKeys.token, token);
    _setGuest(guest);
    middlewareCase = MiddlewareCases.validToken;
    await SettingsService.find.setLocaleFromCode(guest.preferredLocale);
  }

  /// Merge a fresh guest (e.g. after a profile edit) into the session.
  void updateGuest(Guest next) => _setGuest(next);

  /// Clears token + guest everywhere. Called on explicit sign-out and on a 401.
  ///
  /// [revokeRemotely] tells the server to delete the token too. Clearing local
  /// storage alone left the bearer token valid until expiry, so a "signed out"
  /// device still held a working credential. Skipped for the 401 path, where
  /// the token is already dead and the call would only 401 again.
  Future<void> signOut({bool revokeRemotely = true}) async {
    if (revokeRemotely && StorageService.getString(StorageKeys.token) != null) {
      // Best-effort and awaited before the local wipe, since the request needs
      // the token it is revoking. A failure here must not strand the guest in a
      // signed-in UI, so errors are swallowed and the local clear runs anyway.
      await ApiService.find.post(
        path: '/auth/guest/logout',
        data: {
          // Detaches this phone's push registration server-side; other devices
          // the guest is signed in on keep receiving notifications.
          'device_token': StorageService.getString(StorageKeys.fcmToken),
        },
        showErrorDialog: false,
      );
    }

    await StorageService.remove(StorageKeys.token);
    await StorageService.remove(StorageKeys.guest);
    await StorageService.remove(StorageKeys.fcmToken);
    guest.value = null;
    middlewareCase = MiddlewareCases.noToken;

    // CheckInService is permanent, so its state outlives the session unless it
    // is cleared here — including the previous guest's scanned ID number and
    // document photo path.
    if (Get.isRegistered<CheckInService>()) CheckInService.find.reset();
  }

  // ── Private ───────────────────────────────────────────────────────────────
  void _setGuest(Guest next) {
    guest.value = next;
    StorageService.write(StorageKeys.guest, next.toJson());
  }

  void _hydrateGuestFromCache() {
    final cached = StorageService.read<Map>(StorageKeys.guest);
    if (cached == null) return;
    guest.value = Guest.fromJson(Map<String, dynamic>.from(cached));
    middlewareCase = MiddlewareCases.validToken;
  }
}
