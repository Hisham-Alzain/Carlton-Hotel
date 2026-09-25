import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/constants/app_assets.dart';
import 'dart:developer';

import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/enums/enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/experience.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/home_slider.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';

/// Backs the homepage (Figma "homepage" 2089:861): the looping hero video plus
/// the room/restaurant/experience listings and the hero-slider copy, all from
/// the public content API.
class HomeController extends GetxController with WidgetsBindingObserver {
  // Rooms, dining, experiences and the hero-slider copy all come from the
  // public content API, mapped to the UI models at this boundary so the cards
  // stay unchanged.
  final RxList<RoomItem> rooms = <RoomItem>[].obs;
  final RxList<RestaurantItem> restaurants = <RestaurantItem>[].obs;
  final RxList<ExperienceItem> experiences = <ExperienceItem>[].obs;

  /// The three explore-state hero cards (`GET /public/home-sliders`), always
  /// seeded in this exact order — video hero, experiences hero, dining hero
  /// (verified against `MobileDemoSeeder`'s copy, not just array position).
  final RxList<HomeSlider> heroSliders = <HomeSlider>[].obs;
  final RxBool contentLoading = true.obs;

  /// Null when the slider list hasn't loaded (or came back short) — callers
  /// fall back to the bundled asset + translated copy for that slot.
  HomeSlider? _sliderAt(int index) =>
      index < heroSliders.length ? heroSliders[index] : null;

  HomeSlider? get videoHeroSlider => _sliderAt(0);
  HomeSlider? get experiencesHeroSlider => _sliderAt(1);
  HomeSlider? get diningHeroSlider => _sliderAt(2);

  // ── Active-booking dashboard (shown when the guest has a reservation) ──────
  /// When true, Home renders the active-booking dashboard instead of the
  /// default explore sections. Backed by the authenticated guest's `has_booking`
  /// entitlement (MiddlewareService); read live so a rebuild reflects it.
  bool get hasReservation => MiddlewareService.find.hasBooking;

  /// The guest's active (checked-in) stay, or null when they have a booking but
  /// aren't checked in yet. Fetched from `GET /stays/active`.
  final Rx<Stay?> activeStay = Rx<Stay?>(null);

  /// The guest's next reservation when they have a booking but aren't checked in
  /// yet (`GET /stays/upcoming`). Rendered on Home in place of the in-stay
  /// dashboard so a booked-not-yet-arrived guest still sees their reservation.
  final Rx<Stay?> upcomingStay = Rx<Stay?>(null);

  /// Which body Home renders. Home never decides this itself — every entry
  /// point resolves it through [resolveHomeState] and hands it over via
  /// [goHome]; this controller only refreshes it against the same function.
  final Rx<HomeViewState> currentState = HomeViewState.defaultHome.obs;

  /// The reservation [currentState] was resolved from, or null when the guest
  /// has none. Kept so a refresh re-resolves from data rather than from the
  /// previous state.
  final Rx<Reservation?> currentReservation = Rx<Reservation?>(null);

  /// Handed over by [goHome] so Home paints the resolved state on its first
  /// frame instead of flashing [HomeViewState.defaultHome] while the refetch is
  /// still in flight. Consumed exactly once, in [onInit].
  static HomeViewState? pendingState;

  /// **The** Home-state decision — the single place this is derived. Pure: no
  /// GetX, no clock, no I/O, so every transition is directly testable.
  ///
  /// [authToken] is accepted but never read. The guest booking-lookup path
  /// resolves a reservation before any token is issued, so gating on the token
  /// would strand exactly those guests on [HomeViewState.defaultHome]. It stays
  /// in the signature because a reservation can only have been *fetched* with
  /// one, which keeps that dependency visible at each call site.
  static HomeViewState resolveHomeState({
    String? authToken,
    Reservation? reservation,
  }) {
    if (reservation == null) return HomeViewState.defaultHome;
    return reservation.isCheckedIn
        ? HomeViewState.activeBooking
        : HomeViewState.preCheckIn;
  }

  /// Picks the one reservation Home renders out of everything
  /// `GET /reservations` returns (all of them, newest-created first): the
  /// in-house stay if there is one, otherwise the soonest arrival. Cancelled
  /// and checked-out rows are skipped — precisely what the backend excludes
  /// when deriving `has_booking`.
  static Reservation? currentOf(List<Reservation> all) {
    final live = all.where((r) => r.isCurrent).toList();
    if (live.isEmpty) return null;
    final inHouse = live.where((r) => r.isCheckedIn);
    if (inHouse.isNotEmpty) return inHouse.first;
    // No arrival date sorts last so a dateless row never outranks a real one.
    live.sort(
      (a, b) => (a.checkIn ?? _noArrival).compareTo(b.checkIn ?? _noArrival),
    );
    return live.first;
  }

  static final DateTime _noArrival = DateTime.utc(9999);

  /// `GET /reservations` → whether the question could be answered, plus the
  /// reservation Home should render.
  ///
  /// `ok: false` means the server was not reached or refused — which is *not*
  /// the same as the guest having no reservation, even though both leave
  /// `reservation` null. A caller that overwrites live state must branch on
  /// this; a caller starting from nothing can ignore it.
  ///
  /// A signed-out guest is `ok: true` with no reservation: that is a real,
  /// known answer.
  static Future<({bool ok, Reservation? reservation})>
  fetchCurrentReservationResult() async {
    final token = StorageService.getString(StorageKeys.token);
    if (token == null || token.isEmpty) return (ok: true, reservation: null);
    final response = await ApiService.find.get<List<dynamic>>(
      path: '/reservations',
      showErrorDialog: false,
    );
    if (response.statusCode != 200 || response.data == null) {
      return (ok: false, reservation: null);
    }
    return (
      ok: true,
      reservation: currentOf(Reservation.listFromJson(response.data)),
    );
  }

  /// Cold-start convenience: the reservation, with a failed fetch flattened to
  /// null. Correct only where there is no previous value to lose — [goHome]'s
  /// callers start from an empty Home, so the explore variant is the right
  /// fallback there. Never throws and never surfaces a dialog.
  static Future<Reservation?> fetchCurrentReservation() async =>
      (await fetchCurrentReservationResult()).reservation;

  /// The one way into Home. Resolves the state, then replaces the stack so no
  /// entry point can leave an auth screen behind it. Entry points call this
  /// instead of navigating to [Routes.main] themselves.
  static Future<void> goHome({
    String? authToken,
    Reservation? reservation,
  }) async {
    final state = resolveHomeState(
      authToken: authToken,
      reservation: reservation,
    );
    // Main is `fenix`, so a live HomeController survives the stack swap and
    // its onInit never re-runs — hand the state over directly as well.
    if (Get.isRegistered<HomeController>()) {
      Get.find<HomeController>().applyState(state, reservation);
      // Consumed here, so it must NOT also be left standing for the next
      // freshly-constructed controller: only onInit clears it, and after a
      // fenix disposal that controller would adopt this stale state for its
      // first frame.
      pendingState = null;
    } else {
      pendingState = state;
    }
    await Get.offAllNamed(Routes.main);
  }

  /// Token-restore entry point: fetch this session's reservation, then route
  /// through [goHome]. Shared by cold start, post-sign-in and post-profile
  /// creation so all three land identically.
  static Future<void> restoreAndGoHome() async {
    final token = StorageService.getString(StorageKeys.token);
    final reservation = await fetchCurrentReservation();
    await goHome(authToken: token, reservation: reservation);
  }

  /// Adopts an already-resolved state instead of re-deciding it.
  void applyState(HomeViewState state, Reservation? reservation) {
    currentReservation.value = reservation;
    currentState.value = state;
  }

  /// Re-resolves [currentState] from the reservation currently loaded.
  void computeHomeState() {
    currentState.value = resolveHomeState(
      authToken: StorageService.getString(StorageKeys.token),
      reservation: currentReservation.value,
    );
  }

  /// Pull-to-refresh: refetch the reservation and the stay dashboard, then
  /// re-resolve the state from them.
  ///
  /// Concurrent callers are coalesced onto one run. Check-in triggers this
  /// twice — once via the entitlement worker when `/me` refreshes, once from
  /// the wizard — and two overlapping runs assign `currentReservation`,
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
    final result = await fetchCurrentReservationResult();
    if (isClosed) return;
    // A failed fetch is not "no reservation". Assigning null here would drop a
    // checked-in guest to the explore Home on one flaky request — precisely the
    // flash `pendingState` was introduced to prevent, reintroduced one call
    // later. Keep what we had and let the next refresh correct it.
    if (result.ok) currentReservation.value = result.reservation;
    await _loadActiveBooking();
    if (isClosed) return;
    // Load-bearing: the `result.ok` guard here is what makes the guard above
    // mean anything. On cold start `goHome` hands over only `pendingState` —
    // never the reservation it was resolved from — so `currentReservation` is
    // still null on this first run. Re-resolving unconditionally would ask
    // `resolveHomeState(reservation: null)`, get `defaultHome`, and drop a
    // checked-in guest to the explore Home: the exact flash `pendingState`
    // exists to prevent. When the refetch was inconclusive the handed-over
    // state is the better answer, so leave it alone.
    if (result.ok) computeHomeState();
  }

  /// Opens the check-in flow. Available for the whole of [HomeViewState.preCheckIn]
  /// — there is no arrival-time window: a guest with a booking that isn't yet
  /// checked in can always start check-in.
  void startCheckIn() {
    if (currentState.value != HomeViewState.preCheckIn) return;
    Get.toNamed(Routes.checkIn);
  }

  /// The guest's in-house service requests (`GET /service-requests`, tier-3b).
  final RxList<ServiceRequest> activeRequests = <ServiceRequest>[].obs;

  /// Running bill (`GET /folio`, tier-3b) — line items + total.
  static const _emptyBillTotal = r'$0';
  final RxList<(String, String)> billLines = <(String, String)>[].obs;
  final RxString billTotal = _emptyBillTotal.obs;

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
          ? 'Do Not Disturb needs an active stay.'
          : 'Could not update Do Not Disturb.';
      CustomSnackbars.showError(message: message);
    }
  }

  void _soon(String label) =>
      CustomSnackbars.showInfo(message: '$label — coming soon');

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
  /// and flips the stay to `checked_out`). Needs no display data, so it is safe
  /// to wire even while the hero/bill above stay demo-backed.
  void checkout() => CustomDialogs.showConfirmationDialog(
    title: AppTranslations.expressCheckout,
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

  // No dedicated screens yet — kept as placeholders.
  void openBill() => _soon(AppTranslations.myBill);
  void fullStatement() => _soon('Full Statement');

  /// Loads the active-stay dashboard: `GET /stays/active` (the stay card) plus,
  /// once checked in, `GET /folio` (bill) and `GET /service-requests`. A booked-
  /// but-not-checked-in guest has no active stay/folio, so the hero/bill stay
  /// hidden and only the Dining/Experiences rails show.
  ///
  /// Re-runs whenever the guest's entitlements change — see the [ever] in
  /// [onInit]. Every branch *assigns* rather than only writing on success, so
  /// signing out or switching guest can never strand the previous guest's stay
  /// on screen.
  Future<void> _loadActiveBooking() async {
    // Nothing to load while signed out — and hitting /stays/active without a
    // token 401s, which ErrorInterceptor escalates to signOut() plus a redirect
    // to Sign In. `showErrorDialog: false` silences the dialog, not the
    // interceptor, so this guard is what keeps a browsing guest on Home.
    if (!MiddlewareService.find.isAuthenticated) {
      _clearActiveBooking();
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
    // Booked but not yet checked in: surface the upcoming reservation card in
    // place of the (absent) in-stay dashboard. Only the guest's booking state
    // reaches here, so a no-booking guest never pays for this call.
    if (activeStay.value == null && MiddlewareService.find.hasBooking) {
      final upRes = await ApiService.find.get<List<dynamic>>(
        path: '/stays/upcoming',
        showErrorDialog: false,
      );
      if (isClosed) return;
      if (upRes.statusCode == 200 && upRes.data != null) {
        final list = UpcomingStay.listFromJson(upRes.data);
        upcomingStay.value = list.isNotEmpty
            ? _upcomingToStay(list.first)
            : null;
      }
    } else {
      upcomingStay.value = null;
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
      if (folioRes.statusCode == 200 && folioRes.data != null) {
        final folio = Folio.fromJson(folioRes.data!);
        billLines.assignAll(
          folio.items.map((i) => (i.description, _usd(i.amountUsd))),
        );
        billTotal.value = _usd(folio.totalUsd);
      }
      if (reqRes.statusCode == 200 && reqRes.data != null) {
        activeRequests.assignAll(ServiceRequest.listFromJson(reqRes.data!));
      }
    } else {
      // Checked out (or never checked in): the bill and in-stay requests are
      // no longer this guest's, so drop them rather than leaving them stale.
      billLines.clear();
      billTotal.value = _emptyBillTotal;
      activeRequests.clear();
    }
  }

  /// Drops every stay-scoped value, so a signed-out guest — or the next guest
  /// to sign in on this device — never sees the previous one's cards.
  void _clearActiveBooking() {
    activeStay.value = null;
    upcomingStay.value = null;
    activeRequests.clear();
    billLines.clear();
    billTotal.value = _emptyBillTotal;
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
  Stay _upcomingToStay(UpcomingStay s) => Stay(
    id: s.uuid,
    uuid: s.uuid,
    roomName: s.roomName.value,
    status: StayStatus.upcoming,
    subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
        ? AppTranslations.stayRoomNumber('${s.roomNumber}')
        : null,
    imagePath: 'assets/images/stay_room.png',
    checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
    checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
    nightsRemaining: s.nights,
  );

  static final DateFormat _fullDate = DateFormat('MMM d, yyyy');

  /// Folio amounts arrive as USD decimal strings; render them in the guest's
  /// selected currency via the shared formatter.
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

  @override
  void onInit() {
    super.onInit();
    WidgetsBinding.instance.addObserver(this);
    // Adopt whatever goHome() already resolved so the first frame is correct,
    // rather than flashing defaultHome until the refetch below lands.
    final resolved = pendingState;
    pendingState = null;
    if (resolved != null) currentState.value = resolved;
    _loadContent();
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
      // Entitlements changing means the reservation changed too (linked,
      // checked in, checked out) — re-resolve the state, don't just reload the
      // dashboard underneath a now-stale one.
      refreshHome();
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
    if (_tabVisible && _appForeground) {
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

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appForeground = state == AppLifecycleState.resumed;
    _syncPlayback();
  }

  void bookNow() =>
      CustomSnackbars.showInfo(message: AppTranslations.bookingComingSoon);

  void explore() =>
      CustomSnackbars.showInfo(message: AppTranslations.exploreComingSoon);

  void openRestaurant(RestaurantItem restaurant) =>
      Get.toNamed(Routes.restaurantDetail, arguments: restaurant);

  /// Tapping a room card opens its full-screen details page, fetching the real
  /// room-type detail (falling back to the local option if it has no uuid).
  void openRoomDetails(RoomItem item) =>
      Get.find<BookingFlowController>().openRoomListing(item.uuid);

  /// "Discover All" opens the shared listing screen for sections that have a
  /// list behind them (Rooms/Dining/Experiences); Offers has no listing yet.
  /// Takes the section itself, not its on-screen title. It used to switch on
  /// the English label, which silently stopped matching the moment those
  /// titles were localized — `null` means "no listing behind this rail yet"
  /// and only then is [sectionLabel] used, for the coming-soon message.
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
    if (roomsRes.statusCode == 200 && roomsRes.data != null) {
      rooms.assignAll(
        roomsRes.data!
            .whereType<Map<String, dynamic>>()
            .map(RoomType.fromJson)
            .map(RoomItem.fromRoomType),
      );
    }
    if (diningRes.statusCode == 200 && diningRes.data != null) {
      restaurants.assignAll(
        diningRes.data!
            .whereType<Map<String, dynamic>>()
            .map(DiningVenue.fromJson)
            .map(RestaurantItem.fromDiningVenue),
      );
    }
    if (experiencesRes.statusCode == 200 && experiencesRes.data != null) {
      experiences.assignAll(
        Experience.listFromJson(
          experiencesRes.data,
        ).map(ExperienceItem.fromExperience),
      );
    }
    if (slidersRes.statusCode == 200 && slidersRes.data != null) {
      heroSliders.assignAll(HomeSlider.listFromJson(slidersRes.data));
    }
    contentLoading.value = false;
  }

  @override
  void onClose() {
    _entitlementWorker?.dispose();
    WidgetsBinding.instance.removeObserver(this);
    videoController.dispose();
    super.onClose();
  }
}
