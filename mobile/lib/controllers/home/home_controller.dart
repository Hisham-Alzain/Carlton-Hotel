import 'dart:developer';

import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/constants/app_assets.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
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
    await MiddlewareService.find.checkToken();
    await goHome();
  }

  /// Opens the check-in flow. Available for the whole of
  /// [HomeViewState.preCheckIn] — there is no arrival-time window: a guest with
  /// a booking that is not yet checked in can always start check-in.
  void startCheckIn() {
    if (currentState != HomeViewState.preCheckIn) return;
    Get.toNamed(Routes.checkIn);
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

  /// Jump to the Services tab (index 3) in the shell — where room-service
  /// requests are actually made.
  void goToServices() => Get.find<MainController>().changeTab(3);

  void quickRequest() => goToServices();
  void newRequest() => goToServices();
  void openRequest(ServiceRequest request) => goToServices();
  void openConcierge() => Get.toNamed(Routes.aiConcierge);
  void openExperience(ExperienceItem experience) =>
      Get.toNamed(Routes.discover, arguments: DiscoverSection.experiences);

  /// Express checkout: confirm, then `POST /folio/approve` (approves the bill
  /// and flips the stay to `checked_out`).
  void checkout() => CustomDialogs.showConfirmationDialog(
    title: AppTranslations.expressCheckoutCaps,
    message:
        '${AppTranslations.checkoutConfirmBody(activeStay.value?.subtitle ?? AppTranslations.yourStay)} '
        '${AppTranslations.checkoutStatementNote}',
    icon: 'assets/icons/act_checkout.svg',
    accentColor: AppColors.primary,
    onPressed: _confirmCheckout,
  );

  Future<void> _confirmCheckout() async {
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/folio/approve',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.ok) {
      CustomSnackbars.showSuccess(message: AppTranslations.checkoutRequested);
      // Stay is now checked_out — resync entitlements from /me.
      await MiddlewareService.find.checkToken();
    } else if (res.error?.errorCode == ErrorCodes.noActiveReservation) {
      CustomSnackbars.showInfo(message: AppTranslations.noActiveStayToCheckOut);
    } else {
      CustomSnackbars.showError(message: AppTranslations.checkoutFailed);
    }
  }

  /// Both bill entry points open the same statement screen, which refetches
  /// `GET /folio` itself — the dashboard card keeps only the flattened
  /// line/total pairs it renders, not the folio.
  void openBill() => Get.toNamed(Routes.folio);
  void fullStatement() => openBill();

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
  Future<void> refreshHome() {
    final inFlight = _refreshInFlight;
    if (inFlight != null) return inFlight;
    final run = _runRefreshHome().whenComplete(() => _refreshInFlight = null);
    _refreshInFlight = run;
    return run;
  }

  Future<void>? _refreshInFlight;

  Future<void> _runRefreshHome() async {
    contentError.value = false;
    // Entitlements first: the state Home renders is derived from them, and the
    // stay dashboard below reads them to decide what to fetch.
    if (MiddlewareService.find.isAuthenticated) {
      await MiddlewareService.find.checkToken();
      if (isClosed) return;
    }
    await Future.wait([_loadContent(), _loadActiveBooking()]);
  }

  /// True while [upcomingStay] is a booking the hotel has not confirmed yet.
  final RxBool upcomingPending = false.obs;

  /// Re-reads the guest's stay after something changed it outside Home (a
  /// booking just made, one cancelled from the Stays tab).
  Future<void> reloadBooking() => _loadActiveBooking();

  Future<void> _loadActiveBooking() async {
    // Nothing to load while signed out — and hitting /stays/active without a
    // token 401s, which ErrorInterceptor escalates to signOut() plus a redirect
    // to Sign In. `showErrorDialog: false` silences the dialog, not the
    // interceptor, so this guard is what keeps a browsing guest on Home.
    if (!MiddlewareService.find.isAuthenticated) {
      _clearActiveBooking();
      bookingLoading.value = false;
      return;
    }

    final activeRes = await ApiService.find.get<Map<String, dynamic>?>(
      path: '/stays/active',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (activeRes.ok) {
      activeStay.value = activeRes.data != null
          ? _toStay(ActiveStay.fromJson(activeRes.data!))
          : null;
    }
    // Not checked in: surface the upcoming reservation. A confirmed one drives
    // the pre-arrival dashboard; a `pending` one (every guest-made booking
    // until the hotel confirms it — the server does not count it toward
    // has_booking) is still shown on the explore Home, so a guest who just
    // booked sees their stay instead of an unchanged page.
    if (activeStay.value == null) {
      final upRes = await ApiService.find.get<List<dynamic>>(
        path: '/stays/upcoming',
        showErrorDialog: false,
      );
      if (isClosed) return;
      if (upRes.hasData) {
        final primary = UpcomingStay.primary(
          UpcomingStay.listFromJson(upRes.data),
        );
        upcomingStay.value = primary == null ? null : _upcomingToStay(primary);
        // Pending only when no upcoming booking is confirmed — a confirmed one
        // behind an earlier pending one still opens check-in and transfers.
        upcomingPending.value = primary?.isAwaitingHotel ?? false;
        MiddlewareService.find.hasPendingBooking.value = upcomingPending.value;
        // The pre-arrival hero reads the booking from CheckInService, which
        // otherwise loads it only when the check-in wizard opens.
        if (primary != null && Get.isRegistered<CheckInService>()) {
          CheckInService.find.loadReservation();
        }
      }
    } else {
      upcomingStay.value = null;
      upcomingPending.value = false;
      MiddlewareService.find.hasPendingBooking.value = false;
    }
    if (MiddlewareService.find.isCheckedIn) {
      final folioF = ApiService.find.get<Map<String, dynamic>>(
        path: '/folio',
        showErrorDialog: false,
      );
      final reqF = ApiService.find.get<List<dynamic>>(
        path: '/service-requests',
        showErrorDialog: false,
      );
      final folioRes = await folioF;
      final reqRes = await reqF;
      if (isClosed) return;
      if (folioRes.hasData) {
        final folio = Folio.fromJson(folioRes.data!);
        billLines.assignAll(
          folio.items.map((i) => (i.description, _usd(i.amountUsd))),
        );
        billTotal.value = _usd(folio.totalUsd);
      }
      if (reqRes.hasData) {
        activeRequests.assignAll(ServiceRequest.listFromJson(reqRes.data!));
      }
    } else {
      // Checked out (or never checked in): the bill and in-stay requests are
      // no longer this guest's, so drop them rather than leaving them stale.
      billLines.clear();
      billTotal.value = _emptyBillTotal();
      activeRequests.clear();
    }
    bookingLoading.value = false;
  }

  /// Drops every stay-scoped value, so a signed-out guest — or the next guest
  /// to sign in on this device — never sees the previous one's cards.
  void _clearActiveBooking() {
    activeStay.value = null;
    upcomingStay.value = null;
    activeRequests.clear();
    billLines.clear();
    billTotal.value = _emptyBillTotal();
    doNotDisturb.value = false;
  }

  /// Identity of the currently-loaded dashboard. Changes exactly when a reload
  /// is warranted, which is narrower than "the guest object changed".
  String _entitlementKey() {
    final middleware = MiddlewareService.find;
    return '${middleware.isAuthenticated}'
        '|${middleware.hasBooking}'
        '|${middleware.isCheckedIn}';
  }

  Stay _toStay(ActiveStay s) => Stay(
    id: s.uuid,
    uuid: s.uuid,
    roomName: s.roomName.value,
    status: StayStatus.active,
    subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
        ? AppTranslations.stayRoomNumber('${s.roomNumber}')
        : null,
    imagePath: 'assets/images/stay_room.png',
    checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
    checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
    nightsRemaining: s.nightsRemaining,
  );

  /// Maps an `/stays/upcoming` entry to the [Stay] the (pre-arrival) active-stay
  /// card renders: a short "Room N" badge, the room name, the date range, and
  /// the total nights shown in place of "nights left".
  Stay _upcomingToStay(UpcomingStay s) {
    final total = double.tryParse(s.priceUsd) ?? 0;
    final perNight = s.nights > 0 ? total / s.nights : total;
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.upcoming,
      // CustomUpcomingStayCard (the pending-booking card) force-unwraps
      // subtitle and pricePerNight, so neither may be left null.
      subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
          ? AppTranslations.stayRoomNumber('${s.roomNumber}')
          : AppTranslations.receiptHotelName,
      imagePath: 'assets/images/stay_room.png',
      checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
      checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
      nightsRemaining: s.nights,
      resCode: s.bookingCode,
      pricePerNight: AppTranslations.perNight(_usd(perNight.toString())),
      isCancellable: s.isCancellable,
    );
  }

  static DateFormat get _fullDate => DateFormat('MMM d, yyyy');

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
    refreshHome();

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
    // Prefer the bundled hotel promo clip; if it isn't in the bundle yet,
    // fall back to the demo network clip; failing both, the hero keeps its
    // poster image.
    videoController = VideoPlayerController.asset(AppAssets.heroVideoAssetPath);
    _start(videoController).catchError((Object e) {
      log('Hero video: bundled asset unavailable ($e); trying network clip');
      // If the controller was closed during the failed attempt, onClose has
      // already disposed the player — creating the network one here would
      // leak a muted looping video that nothing ever disposes.
      if (isClosed) return;
      videoController.dispose();
      videoController = VideoPlayerController.networkUrl(
        Uri.parse(AppAssets.heroVideoUrl),
      );
      _start(videoController).catchError((Object e) {
        log('Hero video: network clip failed ($e); keeping poster image');
      });
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

  /// Hero CTA: open the booking flow. "Plan Your Stay" is the Book tab of this
  /// same shell rather than a standalone route, so this switches tab instead of
  /// pushing — the same move `beginBookingWithRoom` makes.
  void bookNow() => Get.find<MainController>().changeTab(2);

  /// Secondary hero CTA: the rooms listing, which is what there is to explore.
  void explore() =>
      Get.toNamed(Routes.discover, arguments: DiscoverSection.rooms);

  void openRestaurant(RestaurantItem restaurant) =>
      Get.toNamed(Routes.restaurantDetail, arguments: restaurant);

  /// Tapping a room card opens its full-screen details page, fetching the real
  /// room-type detail (falling back to the local option if it has no uuid).
  void openRoomDetails(RoomItem item) =>
      Get.find<BookingFlowController>().openRoomListing(item.uuid);

  /// "Discover All" opens the shared listing screen for a rail.
  ///
  /// Takes the section itself, not its on-screen title. It used to switch on the
  /// English label, which silently stopped matching the moment those titles were
  /// localized — and cannot be a `switch` pattern at all now that they are
  /// `.tr` lookups rather than constants. A null [target] means "no listing
  /// behind this rail yet", and only then is [sectionLabel] used, for the
  /// coming-soon message.
  void discoverAll(DiscoverSection? target, {String sectionLabel = ''}) {
    if (target == null) {
      CustomSnackbars.showInfo(
        message: AppTranslations.sectionComingSoon(sectionLabel),
      );
      return;
    }
    Get.toNamed(Routes.discover, arguments: target);
  }

  /// Loads the Home rails from the public content API (bounded first page each).
  /// Failures leave the rail empty rather than error — the hero still renders.
  Future<void> _loadContent() async {
    final results = await Future.wait([
      ApiService.find.get<List<dynamic>>(
        path: '/public/room-types',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/dining-venues',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/experiences',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/home-sliders',
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
