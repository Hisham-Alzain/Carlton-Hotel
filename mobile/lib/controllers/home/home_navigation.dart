part of 'home_controller.dart';

extension HomeNavigation on HomeController {
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

  /// Jump to the Services tab (index 3) in the shell — where room-service
  /// requests are actually made.
  void goToServices() => Get.find<MainController>().changeTab(3);

  void quickRequest() => goToServices();

  void newRequest() => goToServices();

  void openRequest(ServiceRequest request) => goToServices();

  void openConcierge() => Get.toNamed(Routes.aiConcierge);

  void openExperience(ExperienceItem experience) =>
      Get.toNamed(Routes.discover, arguments: DiscoverSection.experiences);

  /// Both bill entry points open the same statement screen, which refetches
  /// `GET /folio` itself — the dashboard card keeps only the flattened
  /// line/total pairs it renders, not the folio.
  void openBill() => Get.toNamed(Routes.folio);

  void fullStatement() => openBill();

  /// Opens the check-in flow. Available for the whole of
  /// [HomeViewState.preCheckIn] — there is no arrival-time window: a guest with
  /// a booking that is not yet checked in can always start check-in.
  void startCheckIn() {
    if (currentState != HomeViewState.preCheckIn) return;
    Get.toNamed(Routes.checkIn);
  }
}
