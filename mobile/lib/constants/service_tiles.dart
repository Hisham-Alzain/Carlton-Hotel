import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/service_item.dart';

/// The eight Services hub tiles (Figma "Services 1", node 2073:133 — no longer
/// present in the file, which now covers only Home + Check-In).
///
/// Presentation only — artwork, title and subtitle. Each carries a stable
/// [ServiceItem.code] that is matched against the live catalogue fetched from
/// `GET /public/service-catalog`, which supplies the actual behaviour. The
/// endpoint carries no artwork, so these stay client-side.
abstract class ServiceTiles {
  /// Resolved per read rather than `const`: title/subtitle are `.tr`
  /// lookups, so a const list would freeze the launch locale.
  static List<ServiceItem> get services => <ServiceItem>[
    ServiceItem(
      title: AppTranslations.tileRoomService,
      code: 'room_service',
      subtitle: AppTranslations.sub24Hrs,
      imagePath: 'assets/images/tile_room_service.png',
      imageWidth: 57,
      imageHeight: 64,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileLaundry,
      code: 'laundry',
      subtitle: AppTranslations.subSameDay,
      imagePath: 'assets/images/tile_laundry.png',
      imageWidth: 76,
      imageHeight: 71,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileHousekeeping,
      code: 'housekeeping',
      subtitle: AppTranslations.subOnDemand,
      imagePath: 'assets/images/tile_housekeeping.png',
      imageWidth: 70,
      imageHeight: 59,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileConcierge,
      code: 'concierge',
      subtitle: AppTranslations.subAlwaysAvailable,
      imagePath: 'assets/images/tile_concierge.png',
      imageWidth: 65,
      imageHeight: 46,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileTransport,
      code: 'transport',
      subtitle: AppTranslations.subCarAndValet,
      imagePath: 'assets/images/tile_transport.png',
      imageWidth: 79,
      imageHeight: 73,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileRestaurant,
      code: 'restaurant',
      subtitle: AppTranslations.subAlwaysAvailable,
      imagePath: 'assets/images/tile_restaurant.png',
      imageWidth: 88,
      imageHeight: 82,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.tileMaintenance,
      code: 'maintenance',
      subtitle: AppTranslations.subQuickRequest,
      imagePath: 'assets/images/tile_maintenance.png',
      imageWidth: 65,
      imageHeight: 57,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: AppTranslations.dndTitle,
      code: 'do_not_disturb',
      subtitle: AppTranslations.subPrivacyMode,
      imagePath: 'assets/images/tile_dnd.png',
      imageWidth: 74,
      imageHeight: 74,
      imageOpacity: 1,
    ),
  ];
}
