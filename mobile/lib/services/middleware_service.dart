import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/guest.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';

/// Single source of truth for the guest session — the bearer token plus the
/// authenticated [Guest] identity and its entitlement flags. Permanent
/// singleton (see main.dart).
class MiddlewareService extends GetxService {
  late bool isFirstTime;

  /// The authenticated guest, or null when signed out. Hydrated from the cache
  /// on cold start, then refreshed by [checkToken] against `/auth/guest/me`.
  final Rx<Guest?> guest = Rx<Guest?>(null);

  MiddlewareCases middlewareCase = MiddlewareCases.noToken;

  static MiddlewareService get find => Get.find();

  late final AppLifecycleListener _lifecycle;
  DateTime? _lastStatusProbe;

  /// When `/me` last answered; see [checkToken]'s `reuseRecent`.
  DateTime? _meFetchedAt;
  static const _meReuseWindow = Duration(seconds: 10);

  @override
  void onInit() {
    super.onInit();
    isFirstTime = StorageService.getBool(StorageKeys.isFirstTime) ?? true;
    _hydrateGuestFromCache();
    _lifecycle = AppLifecycleListener(onResume: _probeStatus);
  }

  @override
  void onClose() {
    _lifecycle.dispose();
    super.onClose();
  }

  /// On return to the app, asks the cheap `GET /stays/status` whether the
  /// guest's stay changed while away (the desk confirmed, checked them in or
  /// out) and only then refetches `/auth/guest/me`, which every screen keyed
  /// on the entitlements reacts to. At most once a minute.
  Future<void> _probeStatus() async {
    if (!isAuthenticated) return;
    final now = DateTime.now();
    final last = _lastStatusProbe;
    if (last != null && now.difference(last) < const Duration(minutes: 1)) {
      return;
    }
    _lastStatusProbe = now;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/stays/status',
      showErrorDialog: false,
    );
    if (!res.hasData || !isAuthenticated) return;
    final booked = res.data!['has_booking'] == true;
    final checkedIn = res.data!['is_checked_in'] == true;
    if (booked != hasBooking || checkedIn != isCheckedIn) await checkToken();
  }

  // ── Session state (read across the app) ───────────────────────────────────
  bool get isAuthenticated => guest.value != null;
  bool get hasBooking => guest.value?.hasBooking ?? false;
  bool get isCheckedIn => guest.value?.isCheckedIn ?? false;

  /// The guest holds an upcoming booking the hotel has not confirmed yet
  /// (`pending` / `pending_verification`). Every booking made in the app starts
  /// that way, and the server leaves it out of `has_booking` until reception
  /// confirms it. Set from `GET /stays/upcoming` (HomeController) and right
  /// after `POST /reservations` (BookingFlowController).
  final RxBool hasPendingBooking = false.obs;

  /// **The** Home-state decision, and the only place it is made.
  ///
  /// Booked-but-not-arrived covers both a confirmed booking (`has_booking`)
  /// and one still awaiting the hotel ([hasPendingBooking]): a guest who has
  /// just booked sees the pre-arrival Home at once, with an "awaiting hotel
  /// confirmation" note, instead of the explore page they booked from.
  ///
  /// Reading it inside an `Obx` subscribes to [guest] and [hasPendingBooking],
  /// so Home re-renders the moment either changes.
  HomeViewState get homeState {
    if (isCheckedIn) return HomeViewState.activeBooking;
    if (hasBooking || hasPendingBooking.value) return HomeViewState.preCheckIn;
    return HomeViewState.defaultHome;
  }

  bool get isTokenValid => middlewareCase == MiddlewareCases.validToken;
  bool get isTokenInvalid => middlewareCase == MiddlewareCases.invalidToken;
  bool get hasNoToken => middlewareCase == MiddlewareCases.noToken;

  // ── Lifecycle ─────────────────────────────────────────────────────────────

  /// Refreshes the guest + entitlements from `/auth/guest/me`. Called on splash
  /// after cache hydration. A network/server hiccup keeps the cached session —
  /// only a real 401 signs the guest out (handled by the error interceptor →
  /// [ApiService] `_handleUnauthorized`).
  ///
  /// [reuseRecent] skips the call when `/me` answered moments ago. Only for
  /// navigation (splash or sign-in then Home), where the same session is read
  /// twice in a row; anything that just changed the stay must refetch.
  Future<void> checkToken({bool reuseRecent = false}) async {
    final token = StorageService.token;
    if (token == null || token.isEmpty) {
      middlewareCase = MiddlewareCases.noToken;
      return;
    }
    final last = _meFetchedAt;
    if (reuseRecent &&
        last != null &&
        DateTime.now().difference(last) < _meReuseWindow) {
      return;
    }
    final response = await ApiService.find.get<Map<String, dynamic>>(
      path: '/auth/guest/me',
      showErrorDialog: false,
    );
    if (response.hasData) {
      _meFetchedAt = DateTime.now();
      _setGuest(Guest.fromJson(response.data!));
      middlewareCase = MiddlewareCases.validToken;
    } else if (response.statusCode == 401) {
      middlewareCase = MiddlewareCases.invalidToken;
    } else {
      middlewareCase = MiddlewareCases.validToken;
    }
  }

  /// The only place a token is written — from OTP verify on success. Signing
  /// in never changes the app language: the guest's device choice stands, and
  /// the profile's `preferred_locale` is only written to (SettingsService).
  Future<void> saveSession({
    required String token,
    required Guest guest,
  }) async {
    await StorageService.setToken(token);
    _setGuest(guest);
    middlewareCase = MiddlewareCases.validToken;
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
    if (revokeRemotely && StorageService.token != null) {
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

    await StorageService.clearToken();
    await StorageService.remove(StorageKeys.guest);
    await StorageService.remove(StorageKeys.fcmToken);
    guest.value = null;
    _meFetchedAt = null;
    hasPendingBooking.value = false;
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
