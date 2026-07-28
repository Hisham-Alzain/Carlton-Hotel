import 'dart:developer';

import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/models/service_request.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';

/// Backs the homepage (Figma "homepage" 2089:861): the looping hero video plus
/// the demo room/restaurant listings. Demo-only — the CTAs just show snackbars.
class HomeController extends GetxController with WidgetsBindingObserver {
  // Rooms + dining come from the public content API (mapped to the UI models at
  // this boundary so the cards stay unchanged); experiences stay demo-only.
  List<RoomItem> rooms = [];
  List<RestaurantItem> restaurants = [];
  final List<ExperienceItem> experiences = DemoData.experiences;
  bool contentLoading = true;

  // ── Active-booking dashboard (shown when the guest has a reservation) ──────
  /// When true, Home renders the active-booking dashboard instead of the
  /// default explore sections. Backed by the authenticated guest's `has_booking`
  /// entitlement (MiddlewareService); read live so a rebuild reflects it.
  bool get hasReservation => MiddlewareService.find.hasBooking;

  /// The guest's active (checked-in) stay, or null when they have a booking but
  /// aren't checked in yet. Fetched from `GET /stays/active`.
  Stay? activeStay;

  /// The guest's next reservation when they have a booking but aren't checked in
  /// yet (`GET /stays/upcoming`). Rendered on Home in place of the in-stay
  /// dashboard so a booked-not-yet-arrived guest still sees their reservation.
  Stay? upcomingStay;

  /// The guest's in-house service requests (`GET /service-requests`, tier-3b).
  List<ServiceRequest> activeRequests = [];

  /// Running bill (`GET /folio`, tier-3b) — line items + total.
  List<(String, String)> billLines = [];
  String billTotal = r'$0';

  /// Do Not Disturb — optimistic toggle backed by `PATCH /stays/active/dnd`
  /// (tier-3b). Stored server-side as an expiry, not a flag, and never creates a
  /// service request. Reverts on failure.
  bool doNotDisturb = false;
  Future<void> toggleDoNotDisturb(bool value) async {
    final previous = doNotDisturb;
    doNotDisturb = value;
    update();
    final res = await ApiService.find.patch<Map<String, dynamic>>(
      path: '/stays/active/dnd',
      data: {'enabled': value},
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (!res.ok) {
      doNotDisturb = previous;
      update();
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
    title: 'Express Checkout',
    message:
        "Check out of ${activeStay?.subtitle ?? 'your stay'} now? "
        "We'll email your final statement.",
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
      CustomSnackbars.showSuccess(message: 'Checkout requested');
      // Stay is now checked_out — resync entitlements from /me.
      await MiddlewareService.find.checkToken();
    } else if (res.error?.errorCode == ErrorCodes.noActiveReservation) {
      CustomSnackbars.showInfo(message: 'No active stay to check out of.');
    } else {
      CustomSnackbars.showError(message: 'Could not complete checkout.');
    }
  }

  // No dedicated screens yet — kept as placeholders.
  void openBill() => _soon('My Bill');
  void fullStatement() => _soon('Full Statement');

  /// Loads the active-stay dashboard: `GET /stays/active` (the stay card) plus,
  /// once checked in, `GET /folio` (bill) and `GET /service-requests`. A booked-
  /// but-not-checked-in guest has no active stay/folio, so the hero/bill stay
  /// hidden and only the Dining/Experiences rails show.
  Future<void> _loadActiveBooking() async {
    final activeRes = await ApiService.find.get<Map<String, dynamic>?>(
      path: '/stays/active',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (activeRes.ok && activeRes.data != null) {
      activeStay = _toStay(ActiveStay.fromJson(activeRes.data!));
    }
    // Booked but not yet checked in: surface the upcoming reservation card in
    // place of the (absent) in-stay dashboard. Only the guest's booking state
    // reaches here, so a no-booking guest never pays for this call.
    if (activeStay == null && MiddlewareService.find.hasBooking) {
      final upRes = await ApiService.find.get<List<dynamic>>(
        path: '/stays/upcoming',
        showErrorDialog: false,
      );
      if (isClosed) return;
      if (upRes.statusCode == 200 && upRes.data != null) {
        final list = UpcomingStay.listFromJson(upRes.data);
        if (list.isNotEmpty) upcomingStay = _upcomingToStay(list.first);
      }
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
        billLines = folio.items
            .map((i) => (i.description, _usd(i.amountUsd)))
            .toList();
        billTotal = _usd(folio.totalUsd);
      }
      if (reqRes.statusCode == 200 && reqRes.data != null) {
        activeRequests = ServiceRequest.listFromJson(reqRes.data!);
      }
    }
    update();
  }

  Stay _toStay(ActiveStay s) => Stay(
    id: s.uuid,
    uuid: s.uuid,
    roomName: s.roomName.value,
    status: StayStatus.active,
    subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
        ? 'Room ${s.roomNumber}'
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
        ? 'Room ${s.roomNumber}'
        : null,
    imagePath: 'assets/images/stay_room.png',
    checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
    checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
    nightsRemaining: s.nights,
  );

  static final DateFormat _fullDate = DateFormat('MMM d, yyyy');

  static String _usd(String? amount) {
    final v = double.tryParse(amount ?? '') ?? 0;
    final whole = v == v.roundToDouble();
    return '\$${whole ? v.toStringAsFixed(0) : v.toStringAsFixed(2)}';
  }

  late VideoPlayerController videoController;
  bool isVideoReady = false;

  // The hero video only decodes while it's actually watchable: on the Home
  // tab (the keep-alive shell would otherwise keep it playing on every tab)
  // and with the app in the foreground.
  bool _tabVisible = true;
  bool _appForeground = true;

  @override
  void onInit() {
    super.onInit();
    WidgetsBinding.instance.addObserver(this);
    _loadContent();
    _loadActiveBooking();
    // Prefer the bundled hotel promo clip; if it isn't in the bundle yet,
    // fall back to the demo network clip; failing both, the hero keeps its
    // poster image.
    videoController = VideoPlayerController.asset(DemoData.heroVideoAssetPath);
    _start(videoController).catchError((Object e) {
      log('Hero video: bundled asset unavailable ($e); trying network clip');
      // If the controller was closed during the failed attempt, onClose has
      // already disposed the player — creating the network one here would
      // leak a muted looping video that nothing ever disposes.
      if (isClosed) return;
      videoController.dispose();
      videoController = VideoPlayerController.networkUrl(
        Uri.parse(DemoData.heroVideoUrl),
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
    isVideoReady = true;
    _syncPlayback();
    update();
  }

  /// The user may switch tabs or background the app while the video is still
  /// initializing, so play/pause is always derived from current visibility
  /// rather than decided once at startup.
  void _syncPlayback() {
    if (!isVideoReady) return;
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

  void bookNow() => CustomSnackbars.showInfo(message: 'Booking coming soon');

  void explore() => CustomSnackbars.showInfo(message: 'Explore coming soon');

  void openRestaurant(RestaurantItem restaurant) =>
      Get.toNamed(Routes.restaurantDetail, arguments: restaurant);

  /// Tapping a room card opens its full-screen details page, fetching the real
  /// room-type detail (falling back to the local option if it has no uuid).
  void openRoomDetails(RoomItem item) =>
      Get.find<BookingFlowController>().openRoomListing(item.uuid);

  /// "Discover All" opens the shared listing screen for sections that have a
  /// list behind them (Rooms/Dining/Experiences); Offers has no listing yet.
  void discoverAll(String section) {
    final target = switch (section) {
      'Rooms' => DiscoverSection.rooms,
      'Dining' => DiscoverSection.dining,
      'Experiences' => DiscoverSection.experiences,
      _ => null,
    };
    if (target == null) {
      CustomSnackbars.showInfo(message: '$section — coming soon');
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
    ]);
    if (isClosed) return;
    final roomsRes = results[0];
    final diningRes = results[1];
    if (roomsRes.statusCode == 200 && roomsRes.data != null) {
      rooms = roomsRes.data!
          .whereType<Map<String, dynamic>>()
          .map(RoomType.fromJson)
          .map(RoomItem.fromRoomType)
          .toList();
    }
    if (diningRes.statusCode == 200 && diningRes.data != null) {
      restaurants = diningRes.data!
          .whereType<Map<String, dynamic>>()
          .map(DiningVenue.fromJson)
          .map(RestaurantItem.fromDiningVenue)
          .toList();
    }
    contentLoading = false;
    update();
  }

  @override
  void onClose() {
    WidgetsBinding.instance.removeObserver(this);
    videoController.dispose();
    super.onClose();
  }
}
