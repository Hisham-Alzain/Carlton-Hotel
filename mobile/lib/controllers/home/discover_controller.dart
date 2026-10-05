import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/components/sheets/offer_terms_sheet.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/experience.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/promotion.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

/// Backs the shared "Discover All" listing screen. The [section] (route
/// argument) decides the title and which content endpoint is fetched.
///
/// Paginated through [PaginatedControllerMixin]: every endpoint behind this
/// screen is a `BasePublicIndexController` list at 15 per page, so a one-shot
/// fetch silently dropped everything past the first page. The mixin holds one
/// list; since a Discover screen shows exactly one section, it is typed
/// `Object` and the section getters below narrow it with `whereType`, which
/// cannot throw the way a cast would.
class DiscoverController extends GetxController
    with PaginatedControllerMixin<Object> {
  final DiscoverSection section = Get.arguments is DiscoverSection
      ? Get.arguments
      : DiscoverSection.rooms;

  final CancelToken _cancelToken = CancelToken();

  // Typed read-throughs over the one paginated list. Reading them inside an Obx
  // subscribes to `items`, so the list repaints as each page lands.
  List<RoomItem> get rooms => items.whereType<RoomItem>().toList();
  List<RestaurantItem> get restaurants =>
      items.whereType<RestaurantItem>().toList();
  List<ExperienceItem> get experiences =>
      items.whereType<ExperienceItem>().toList();
  List<Promotion> get offers => items.whereType<Promotion>().toList();

  /// The experience category chip in effect, or empty for "All". Only
  /// experiences carry a category — rooms, venues and offers have no field to
  /// filter on — so the chips appear on that section alone.
  final RxString categoryFilter = ''.obs;

  /// Distinct categories present in the pages loaded so far, in first-seen
  /// order (the endpoint already sorts by `sort_order`). Derived rather than
  /// hardcoded, so a category the CMS adds shows up without an app release.
  List<String> get experienceCategories {
    final seen = <String>{};
    return [
      for (final item in experiences)
        if (item.category.isNotEmpty && seen.add(item.category)) item.category,
    ];
  }

  /// [experiences] narrowed to [categoryFilter]; all of them when it is empty.
  List<ExperienceItem> get visibleExperiences {
    final filter = categoryFilter.value;
    final all = experiences;
    if (filter.isEmpty) return all;
    return all.where((item) => item.category == filter).toList();
  }

  /// Tapping the active chip clears it, so "All" is always one tap away.
  void selectCategory(String category) =>
      categoryFilter.value = categoryFilter.value == category ? '' : category;

  String get title => switch (section) {
    DiscoverSection.rooms => AppTranslations.roomsSuites,
    DiscoverSection.dining => AppTranslations.diningRestaurants,
    DiscoverSection.experiences => AppTranslations.experiences,
    DiscoverSection.offers => AppTranslations.offersPackages,
  };

  String get _path => switch (section) {
    DiscoverSection.rooms => '/public/room-types',
    DiscoverSection.dining => '/public/dining-venues',
    DiscoverSection.experiences => '/public/experiences',
    DiscoverSection.offers => '/public/promotions',
  };

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancelToken);
    loadItems(_cancelToken);
  }

  @override
  void onClose() {
    _cancelToken.cancel();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  /// Retry from the error state. The view has no cancel token to pass, so it
  /// cannot call [loadItems] itself.
  Future<void> reload() => loadItems(_cancelToken);

  @override
  Future<({List<Object> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: _path,
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    final rows = res.data!.whereType<Map<String, dynamic>>();
    final List<Object> mapped = switch (section) {
      DiscoverSection.rooms =>
        rows.map(RoomType.fromJson).map(RoomItem.fromRoomType).toList(),
      DiscoverSection.dining =>
        rows
            .map(DiningVenue.fromJson)
            .map(RestaurantItem.fromDiningVenue)
            .toList(),
      DiscoverSection.experiences =>
        rows
            .map(Experience.fromJson)
            .map(ExperienceItem.fromExperience)
            .toList(),
      DiscoverSection.offers => rows.map(Promotion.fromJson).toList(),
    };
    return (items: mapped, pagination: res.meta ?? Pagination());
  }

  // ── Row taps ────────────────────────────────────────────────────────────
  void openRoom(RoomItem item) =>
      Get.find<BookingFlowController>().openRoomListing(item.uuid);

  void openRestaurant(RestaurantItem item) =>
      Get.toNamed(Routes.restaurantDetail, arguments: item);

  void openExperience(ExperienceItem item) =>
      Get.toNamed(Routes.experienceDetail, arguments: item);

  /// Shows an offer's small print. `GET /public/promotions` already carries
  /// every field the detail needs, so this opens a sheet rather than spending a
  /// round trip on `/public/promotions/{uuid}` to learn nothing new.
  void openOffer(Promotion offer) => CustomBottomSheet.show<void>(
    // Title only: both description blocks belong in the body, around the
    // banner — see OfferTermsSheet.
    title: offer.title.value,
    child: OfferTermsSheet(offer: offer),
  );
}
