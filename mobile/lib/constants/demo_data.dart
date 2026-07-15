import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/service_item.dart';
import 'package:carlton/models/service_request.dart';

/// Every hardcoded demo value in the app lives here, so wiring the real
/// backend later is a single-file hunt. Nothing in this file should survive
/// API integration.
abstract class DemoData {
  // ── Auth ────────────────────────────────────────────────────────────────
  /// The only OTP code `OtpVerifyController` accepts.
  static const otpCode = '123456';
  static const otpResendSeconds = 20;

  /// Simulated network round-trip used by every demo submit.
  static const networkDelay = Duration(milliseconds: 800);

  // ── Homepage (Figma "homepage", node 2089:861) ─────────────────────────
  /// The hotel promo clip (the Figma hero's video fill). The source file is
  /// not exportable via the Figma API — drop the original MP4 at this path
  /// and it plays automatically; until then the [heroVideoUrl] demo clip is
  /// used, and failing that the poster image.
  static const heroVideoAssetPath = 'assets/videos/carlton_promo.mp4';

  /// Demo fallback hero video — a public Flutter sample clip.
  static const heroVideoUrl =
      'https://flutter.github.io/assets-for-api-docs/assets/videos/butterfly.mp4';

  /// Hero stills extracted from the Figma homepage (frames of the promo
  /// video). The top hero shows [heroHomeImagePath] as a poster until the
  /// video is ready.
  static const heroHomeImagePath = 'assets/images/hero_home.png';
  static const heroDiningImagePath = 'assets/images/hero_dining.png';
  static const heroExperienceImagePath = 'assets/images/hero_experience.png';

  static const rooms = <RoomItem>[
    RoomItem(
      name: 'Grand Damascus Suite',
      view: 'Panoramic city view',
      area: '45 m²',
      guests: '2',
      bed: 'King Bed',
      priceAmount: '\$580',
      imagePath: 'assets/images/room_classic_courtyard.jpg',
    ),
    RoomItem(
      name: 'Premier Terrace',
      view: 'A private terrace with sun loungers',
      area: '52 m²',
      guests: '2',
      bed: 'King Bed',
      priceAmount: '\$850',
      imagePath: 'assets/images/room_premier_terrace.jpg',
    ),
    RoomItem(
      name: 'Deluxe City View Suite',
      view: 'Panoramic city view',
      area: '48 m²',
      guests: '2',
      bed: 'King Bed',
      priceAmount: '\$680',
      imagePath: 'assets/images/room_deluxe_city.jpg',
    ),
  ];

  static const restaurants = <RestaurantItem>[
    RestaurantItem(
      name: 'Al-Sham Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_alsham.jpg',
    ),
    RestaurantItem(
      name: 'Al-Qamar Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_alqamar.jpg',
    ),
    RestaurantItem(
      name: 'ELENA Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_elena.jpg',
    ),
  ];

  // ── Current stay (Services stay card, Figma 2073:133) ──────────────────
  static const room = 'Room 812';
  static const checkedInTime = '3:00 PM';
  static const nightsRemaining = 2;
  static const stayImagePath = 'assets/images/stay_room.png';

  // ── Services grid (Figma "Services 1", node 2073:133) ──────────────────
  static const services = <ServiceItem>[
    ServiceItem(
      title: 'Room Service',
      subtitle: '24 hrs',
      imagePath: 'assets/images/tile_room_service.png',
      imageWidth: 57,
      imageHeight: 64,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Laundry',
      subtitle: 'Same-day',
      imagePath: 'assets/images/tile_laundry.png',
      imageWidth: 76,
      imageHeight: 71,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Housekeeping',
      subtitle: 'On demand',
      imagePath: 'assets/images/tile_housekeeping.png',
      imageWidth: 70,
      imageHeight: 59,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Concierge',
      subtitle: 'Always available',
      imagePath: 'assets/images/tile_concierge.png',
      imageWidth: 65,
      imageHeight: 46,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Transport',
      subtitle: 'Car & Valet',
      imagePath: 'assets/images/tile_transport.png',
      imageWidth: 79,
      imageHeight: 73,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Restaurant Res.',
      subtitle: 'Always available',
      imagePath: 'assets/images/tile_restaurant.png',
      imageWidth: 88,
      imageHeight: 82,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Maintenance',
      subtitle: 'Quick request',
      imagePath: 'assets/images/tile_maintenance.png',
      imageWidth: 65,
      imageHeight: 57,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Do Not Disturb',
      subtitle: 'Privacy mode',
      imagePath: 'assets/images/tile_dnd.png',
      imageWidth: 74,
      imageHeight: 74,
      imageOpacity: 1,
    ),
  ];

  // ── Active requests (Figma "Services 2") ────────────────────────────────
  /// Returned as a fresh mutable list — the controller removes entries when
  /// a request is cancelled.
  static List<ServiceRequest> initialActiveRequests() => [
    const ServiceRequest(
      title: 'Room service — Carlton Breakfast',
      detail: '25–35 min',
      status: ServiceRequestStatus.inProgress,
    ),
    const ServiceRequest(
      title: 'House Keeping - Fresh Towels',
      detail: 'Arriving soon',
      status: ServiceRequestStatus.confirmed,
    ),
  ];

  // ── AI Concierge (Figma AI1/AI2) ────────────────────────────────────────
  static const aiSuggestions = <String>[
    'Can I get a late check-out?',
    "What's near the hotel?",
    'Request housekeeping',
    'Pool & gym hours',
    'Airport transfer',
    'Help me booking',
  ];
}
