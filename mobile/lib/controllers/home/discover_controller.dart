import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/experience.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:get/get.dart';

/// Backs the shared "Discover All" listing screen. The [section] (route
/// argument) decides the title and which content endpoint is fetched: rooms,
/// dining and experiences all come from the public content API (first page).
class DiscoverController extends GetxController {
  final DiscoverSection section = Get.arguments is DiscoverSection
      ? Get.arguments
      : DiscoverSection.rooms;

  final RxBool loading = true.obs;
  final RxList<RoomItem> rooms = <RoomItem>[].obs;
  final RxList<RestaurantItem> restaurants = <RestaurantItem>[].obs;
  final RxList<ExperienceItem> experiences = <ExperienceItem>[].obs;

  String get title => switch (section) {
    DiscoverSection.rooms => AppTranslations.roomsSuites,
    DiscoverSection.dining => AppTranslations.diningRestaurants,
    DiscoverSection.experiences => AppTranslations.experiences,
  };

  @override
  void onInit() {
    super.onInit();
    _load();
  }

  Future<void> _load() async {
    switch (section) {
      case DiscoverSection.rooms:
        final res = await ApiService.find.get<List<dynamic>>(
          path: '/public/room-types',
          showErrorDialog: false,
        );
        if (res.statusCode == 200 && res.data != null) {
          rooms.assignAll(
            res.data!
                .whereType<Map<String, dynamic>>()
                .map(RoomType.fromJson)
                .map(RoomItem.fromRoomType),
          );
        }
      case DiscoverSection.dining:
        final res = await ApiService.find.get<List<dynamic>>(
          path: '/public/dining-venues',
          showErrorDialog: false,
        );
        if (res.statusCode == 200 && res.data != null) {
          restaurants.assignAll(
            res.data!
                .whereType<Map<String, dynamic>>()
                .map(DiningVenue.fromJson)
                .map(RestaurantItem.fromDiningVenue),
          );
        }
      case DiscoverSection.experiences:
        final res = await ApiService.find.get<List<dynamic>>(
          path: '/public/experiences',
          showErrorDialog: false,
        );
        if (res.statusCode == 200 && res.data != null) {
          experiences.assignAll(
            Experience.listFromJson(
              res.data,
            ).map(ExperienceItem.fromExperience),
          );
        }
    }
    if (isClosed) return;
    loading.value = false;
  }

  // ── Row taps ────────────────────────────────────────────────────────────
  void openRoom(RoomItem item) =>
      Get.find<BookingFlowController>().openRoomListing(item.uuid);

  void openRestaurant(RestaurantItem item) =>
      Get.toNamed(Routes.restaurantDetail, arguments: item);

  void openExperience(ExperienceItem item) =>
      CustomSnackbars.showInfo(message: '${item.name} — coming soon');
}
