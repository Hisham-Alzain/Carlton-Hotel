import 'dart:developer';

import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/constants/app_assets.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/experience.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/home_slider.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';

part 'home_navigation.dart';

part 'home_booking.dart';

/// Backs the homepage (Figma "homepage" 2089:861): the looping hero video plus
/// the room / restaurant / experience listings and the hero-slider copy, all
/// from the public content API.
class HomeController extends GetxController with WidgetsBindingObserver {
  // Rooms, dining, experiences and the hero-slider copy all come from the
  // public content API, mapped to the UI models at this boundary so the cards
  // stay unchanged.
  final RxList<RoomItem> rooms = <RoomItem>[].obs;
  final RxList<RestaurantItem> restaurants = <RestaurantItem>[].obs;
  final RxList<ExperienceItem> experiences = <ExperienceItem>[].obs;

  /// The three explore-state hero cards (`GET /public/home-sliders`), in the
  /// order the CMS sorts them: video hero, experiences hero, dining hero.
  final RxList<HomeSlider> heroSliders = <HomeSlider>[].obs;
  final RxBool contentLoading = true.obs;

  /// Null when the slider list has not loaded (or came back short) — each hero
  /// then falls back to the bundled still plus its translated copy.
  HomeSlider? _sliderAt(int index) =>
      index < heroSliders.length ? heroSliders[index] : null;

  HomeSlider? get videoHeroSlider => _sliderAt(0);
  HomeSlider? get experiencesHeroSlider => _sliderAt(1);
  HomeSlider? get diningHeroSlider => _sliderAt(2);

  /// True when the last content fetch failed. Distinct from "loaded but
  /// empty": both leave [rooms]/[restaurants] empty, but only this one should
  /// offer a Retry, so the rails need the two states separated.
  final RxBool contentError = false.obs;

  /// True while the first active-booking fetch is in flight, so the
  /// reservation-state sections can render a placeholder instead of collapsing
  /// to nothing and popping in.
  final RxBool bookingLoading = true.obs;

  // ── Active-booking dashboard (shown when the guest has a reservation) ──────
  /// When true, Home renders the active-booking dashboard instead of the
  /// default explore sections. Backed by the authenticated guest's `has_booking`
  /// entitlement (MiddlewareService); read live so a rebuild reflects it.
  bool get hasReservation => currentState != HomeViewState.defaultHome;

  /// Which body Home renders. A read-through to [MiddlewareService.homeState]
  /// — the single place that decision is made — so it is never stored here
  /// and cannot drift from what the server's gates enforce.
  HomeViewState get currentState => MiddlewareService.find.homeState;

  /// The one way into Home: replaces the stack so no entry point can leave an
  /// auth screen behind it. There is nothing to resolve first — Home derives
  /// its state from the session, which the caller has already refreshed.
  static Future<void> goHome() async {
    await Get.offAllNamed(Routes.main);
  }

  /// Token-restore entry point: refresh the entitlements from `/me`, then go
  /// Home. Shared by sign-in, booking-code lookup and first profile completion
  /// so all three land on the state the server actually reports.
  static Future<void> restoreAndGoHome() async {
    await MiddlewareService.find.checkToken(reuseRecent: true);
    await goHome();
  }

  /// The guest's active (checked-in) stay, or null when they have a booking but
  /// aren't checked in yet. Fetched from `GET /stays/active`.
  final Rx<Stay?> activeStay = Rx<Stay?>(null);

  /// The guest's next reservation when they have a booking but aren't checked in
  /// yet (`GET /stays/upcoming`). Rendered on Home in place of the in-stay
  /// dashboard so a booked-not-yet-arrived guest still sees their reservation.
  final Rx<Stay?> upcomingStay = Rx<Stay?>(null);

  /// The guest's in-house service requests (`GET /service-requests`, tier-3b).
  final RxList<ServiceRequest> activeRequests = <ServiceRequest>[].obs;

  /// Running bill (`GET /folio`, tier-3b) — line items + total.
  final RxList<(String, String)> billLines = <(String, String)>[].obs;
  final RxString billTotal = _emptyBillTotal().obs;

  /// Zero in the guest's currency. A function, not a `const r'\$0'`, because the
  /// symbol depends on the currency picker.
  static String _emptyBillTotal() => MoneyFormat.usd(0);

  /// Do Not Disturb — optimistic toggle backed by `PATCH /stays/active/dnd`
  /// (tier-3b). Stored server-side as an expiry, not a flag, and never creates a
  /// service request. Reverts on failure.
  final RxBool doNotDisturb = false.obs;
  Future<void> toggleDoNotDisturb(bool value) async {
    final previous = doNotDisturb.value;
    doNotDisturb.value = value;
    final res = await ApiService.find.patch<Map<String, dynamic>>(
      path: '/stays/active/dnd',
      data: {'enabled': value},
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (!res.ok) {
      doNotDisturb.value = previous;
      final message = res.error?.errorCode == ErrorCodes.noActiveReservation
          ? AppTranslations.dndNeedsActiveStay
          : AppTranslations.dndFailed;
      CustomSnackbars.showError(message: message);
    }
  }

  /// Express checkout: confirm, then `POST /folio/approve` (approves the bill
  /// and flips the stay to `checked_out`).
  void checkout() => CustomDialogs.showConfirmationDialog(
    title: AppTranslations.expressCheckoutCaps,
    message:
        '${AppTranslations.checkoutConfirmBody(activeStay.value?.subtitle ?? AppTranslations.yourStay)} '
        '${AppTranslations.checkoutStatementNote}',
    icon: 'assets/icons/act_checkout.svg',
    accentColor: AppColors.primary,
    // StaysController owns the call (one copy of its error handling); its /me
    // refresh trips this controller's entitlement worker, which reloads Home.
    onPressed: () => Get.find<StaysController>().confirmExpressCheckout(),
  );

  /// Loads the active-stay dashboard: `GET /stays/active` (the stay card) plus,
  /// once checked in, `GET /folio` (bill) and `GET /service-requests`. A booked-
  /// but-not-checked-in guest has no active stay/folio, so the hero/bill stay
  /// hidden and only the Dining/Experiences rails show.
  ///
  /// Re-runs whenever the guest's entitlements change — see the [ever] in
  /// [onInit]. Every branch *assigns* rather than only writing on success, so
  /// signing out or switching guest can never strand the previous guest's stay
  /// on screen.
  /// Pull-to-refresh: re-runs both fetches and completes only when they do, so
  /// the RefreshIndicator's spinner tracks the real work. Deliberately does not
  /// flip [contentLoading] — the indicator is already showing progress, and
  /// swapping loaded rails for shimmer mid-pull would flicker.
  ///
  /// NOT named `refresh`: that is `GetxController.refresh()`, which GetX calls
  /// internally to notify listeners. Overriding it to run network work would
  /// fire these fetches from framework internals.
  ///
  /// Concurrent callers are coalesced onto one run. Check-in triggers this twice
  /// — once via the entitlement worker when `/me` refreshes, once from the
  /// wizard — and two overlapping runs assign `currentReservation`,
  /// `activeStay` and the folio independently, so a slow first run could land
  /// its stale values on top of a fresh second one.
  ///
  /// [reuseRecentSession] is for opening Home: the splash or sign-in that led
  /// here has just read `/me`, so it is not read again.
  Future<void> refreshHome({bool reuseRecentSession = false}) {
    final inFlight = _refreshInFlight;
    if (inFlight != null) return inFlight;
    final run = _runRefreshHome(
      reuseRecentSession,
    ).whenComplete(() => _refreshInFlight = null);
    _refreshInFlight = run;
    return run;
  }

  Future<void>? _refreshInFlight;

  Future<void> _runRefreshHome(bool reuseRecentSession) async {
    contentError.value = false;
    // Entitlements first: the state Home renders is derived from them, and the
    // stay dashboard below reads them to decide what to fetch.
    if (MiddlewareService.find.isAuthenticated) {
      await MiddlewareService.find.checkToken(reuseRecent: reuseRecentSession);
      if (isClosed) return;
    }
    await Future.wait([_loadContent(), _loadActiveBooking()]);
  }

  /// True while [upcomingStay] is a booking the hotel has not confirmed yet.
  final RxBool upcomingPending = false.obs;

  /// Re-reads the guest's stay after something changed it outside Home (a
  /// booking just made, one cancelled from the Stays tab).
  Future<void> reloadBooking() => _loadActiveBooking();

  /// Identity of the currently-loaded dashboard. Changes exactly when a reload
  /// is warranted, which is narrower than "the guest object changed".
  String _entitlementKey() {
    final middleware = MiddlewareService.find;
    return '${middleware.isAuthenticated}'
        '|${middleware.hasBooking}'
        '|${middleware.isCheckedIn}';
  }

  static DateFormat get _fullDate => DateFormat.yMMMd();

  /// Folio amounts arrive as USD decimal strings. Routed through [MoneyFormat]
  /// so the bill follows the guest's currency choice — a bare `\$` prefix here
  /// used to show dollars on Home while every other price had converted.
  static String _usd(String? amount) => MoneyFormat.usdString(amount);

  /// Plain, not Rx: it is reassigned on asset-load failure *before*
  /// [isVideoReady] flips, so the rebuild that flag triggers always reads the
  /// current instance.
  late VideoPlayerController videoController;
  final RxBool isVideoReady = false.obs;

  // The hero video only decodes while it's actually watchable: on the Home
  // tab (the keep-alive shell would otherwise keep it playing on every tab)
  // and with the app in the foreground.
  bool _tabVisible = true;
  bool _appForeground = true;

  /// Watches the guest for entitlement changes; disposed in [onClose].
  Worker? _entitlementWorker;
  Worker? _pendingWorker;

  @override
  void onInit() {
    super.onInit();
    WidgetsBinding.instance.addObserver(this);
    refreshHome(reuseRecentSession: true);

    // This controller outlives sign-in. The auth flow is launched from the
    // Services tab of this same Main shell, and _KeepAlive + `fenix: true` hold
    // the instance across it, so onInit never runs a second time. Without this
    // watcher the layout flips to the reservation sections the moment the guest
    // arrives (hasReservation is reactive) while the stay, bill and requests
    // remain at their signed-out values — three sections rendering as
    // SizedBox.shrink() on an otherwise-correct screen.
    //
    // Keyed on the entitlements, not the guest object: a profile edit reassigns
    // `guest` too, and that must not refetch the whole dashboard.
    var entitlements = _entitlementKey();
    _entitlementWorker = ever(MiddlewareService.find.guest, (_) {
      final next = _entitlementKey();
      if (next == entitlements) return;
      entitlements = next;
      // Entitlements changing means the stay changed (linked, checked in,
      // checked out), so the dashboard underneath has to follow. The state
      // itself needs no work: it is a read-through to the session.
      _loadActiveBooking();
    });
    // A booking made just now is `pending`, which leaves the entitlements
    // above unchanged; the flag flipping true is what signals the new stay.
    _pendingWorker = ever(MiddlewareService.find.hasPendingBooking, (pending) {
      if (pending && upcomingStay.value == null) _loadActiveBooking();
    });
    // The bundled hotel promo clip; if it fails, the hero keeps its poster
    // image.
    videoController = VideoPlayerController.asset(AppAssets.heroVideoAssetPath);
    _start(videoController).catchError((Object e) {
      log('Hero video: bundled asset unavailable ($e); keeping poster image');
    });
  }

  Future<void> _start(VideoPlayerController vc) async {
    await vc.initialize();
    vc
      ..setLooping(true)
      ..setVolume(0);
    isVideoReady.value = true;
    _syncPlayback();
  }

  /// The user may switch tabs or background the app while the video is still
  /// initializing, so play/pause is always derived from current visibility
  /// rather than decided once at startup.
  void _syncPlayback() {
    if (!isVideoReady.value) return;
    if (_tabVisible && _routeVisible && _appForeground) {
      videoController.play();
    } else {
      videoController.pause();
    }
  }

  /// Called by [MainController] when the shell switches tabs.
  void setTabVisible(bool visible) {
    _tabVisible = visible;
    _syncPlayback();
  }

  /// Called from the app's routing callback. A screen pushed over the Main shell
  /// (room details, a restaurant, check-in) covers Home without changing the tab
  /// or the app lifecycle, so without this the hero kept decoding video — and
  /// its audio track — behind it: a steady decoder load that, on an older
  /// device, starved the UI thread into an ANR.
  void setRouteVisible(bool visible) {
    _routeVisible = visible;
    _syncPlayback();
  }

  bool _routeVisible = true;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appForeground = state == AppLifecycleState.resumed;
    _syncPlayback();
  }

  /// Loads the Home rails from the public content API (bounded first page each).
  /// Failures leave the rail empty rather than error — the hero still renders.
  Future<void> _loadContent() async {
    final results = await Future.wait([
      ApiService.find.get<List<dynamic>>(
        path: '/public/room-types',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/dining-venues',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/experiences',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/home-sliders',
        queryParameters: {'per_page': 100},
        showErrorDialog: false,
      ),
    ]);
    if (isClosed) return;
    final roomsRes = results[0];
    final diningRes = results[1];
    final experiencesRes = results[2];
    final slidersRes = results[3];
    // Both calls pass showErrorDialog:false, so without this flag a failed
    // request would be indistinguishable from an empty result.
    contentError.value = !roomsRes.ok || !diningRes.ok;
    if (roomsRes.hasData) {
      rooms.assignAll(
        roomsRes.data!
            .whereType<Map<String, dynamic>>()
            .map(RoomType.fromJson)
            .map(RoomItem.fromRoomType),
      );
    }
    if (diningRes.hasData) {
      restaurants.assignAll(
        diningRes.data!
            .whereType<Map<String, dynamic>>()
            .map(DiningVenue.fromJson)
            .map(RestaurantItem.fromDiningVenue),
      );
    }
    if (experiencesRes.hasData) {
      experiences.assignAll(
        Experience.listFromJson(
          experiencesRes.data,
        ).map(ExperienceItem.fromExperience),
      );
    }
    if (slidersRes.hasData) {
      heroSliders.assignAll(HomeSlider.listFromJson(slidersRes.data));
    }
    contentLoading.value = false;
  }

  @override
  void onClose() {
    _entitlementWorker?.dispose();
    _pendingWorker?.dispose();
    WidgetsBinding.instance.removeObserver(this);
    videoController.dispose();
    super.onClose();
  }
}
