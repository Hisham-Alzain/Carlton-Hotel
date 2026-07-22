import 'package:carlton/models/booking_models.dart';
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

  /// Bridge the sparse Home [RoomItem] to the rich [RoomOption] the details
  /// screen needs, matching on name (a real API would return one model).
  static RoomOption roomDetailsFor(RoomItem item) => roomOptions.firstWhere(
    (o) => o.name == item.name || o.name.startsWith(item.name),
    orElse: () => roomOptions.first,
  );

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

  // ── Booking draft defaults (Figma "Plan Your Stay") ────────────────────
  // The initial dates/guests the booking flow opens with. A real API would
  // return the calendar bounds + any pre-selected values instead.
  static final DateTime bookingFirstDay = DateTime(2026, 1, 1);
  static final DateTime bookingLastDay = DateTime(2027, 12, 31);
  static final DateTime bookingCheckIn = DateTime(2026, 8, 14);
  static final DateTime bookingCheckOut = DateTime(2026, 8, 16);
  static const int bookingAdults = 2;
  static const int bookingChildren = 1;

  // ── My Stays (Figma "Past Stays" 2116:198 / "Stays / Upcoming" 2116:483) ─
  static ReceiptData _receipt({
    required String room,
    required String dates,
    required String res,
    required String roomCost,
    required String dining,
    required String taxes,
    required String total,
    required String card,
  }) => ReceiptData(
    roomName: room,
    dateLabel: dates,
    resCode: res,
    lines: [
      (label: room, amount: roomCost),
      (label: 'Dining & Services', amount: dining),
      (label: 'Taxes & fees (15%)', amount: taxes),
    ],
    total: total,
    paymentInfo: 'Payment processed · VISA •••• $card',
  );

  /// Past stays — const-shaped but returned fresh so screens can't mutate the
  /// shared source.
  static List<Stay> pastStays() => [
    Stay(
      id: 'past-1',
      roomName: 'Grand Damascus Suite',
      status: StayStatus.past,
      imagePath: 'assets/images/room_classic_courtyard.jpg',
      dateRangeLabel: 'Jul 8 – Jul 10 · 2 nights',
      totalCharged: '\$596',
      receipt: _receipt(
        room: 'Grand Damascus Suite',
        dates: 'Jul 8 – Jul 10',
        res: 'CRS-812-4821',
        roomCost: '\$417',
        dining: '\$72',
        taxes: '\$107',
        total: '\$596',
        card: '4821',
      ),
    ),
    Stay(
      id: 'past-2',
      roomName: 'Classic Deluxe Room',
      status: StayStatus.past,
      imagePath: 'assets/images/room_deluxe_city.jpg',
      dateRangeLabel: 'Mar 14 – Mar 16 · 2 nights',
      totalCharged: '\$384',
      receipt: _receipt(
        room: 'Classic Deluxe Room',
        dates: 'Mar 14 – Mar 16',
        res: 'CRS-337-1180',
        roomCost: '\$300',
        dining: '\$18',
        taxes: '\$66',
        total: '\$384',
        card: '4821',
      ),
    ),
    Stay(
      id: 'past-3',
      roomName: 'Heritage Corner Suite',
      status: StayStatus.past,
      imagePath: 'assets/images/room_premier_terrace.jpg',
      dateRangeLabel: 'Jan 20 – Jan 23 · 3 nights',
      totalCharged: '\$768',
      receipt: _receipt(
        room: 'Heritage Corner Suite',
        dates: 'Jan 20 – Jan 23',
        res: 'CRS-201-9930',
        roomCost: '\$600',
        dining: '\$68',
        taxes: '\$100',
        total: '\$768',
        card: '4821',
      ),
    ),
  ];

  /// The single upcoming reservation (fresh list so cancel can remove it).
  static List<Stay> upcomingStays() => [
    const Stay(
      id: 'up-1',
      roomName: 'Grand Damascus Suite',
      status: StayStatus.upcoming,
      subtitle: 'Carlton Hotel Damascus · Room 504',
      imagePath: 'assets/images/room_classic_courtyard.jpg',
      checkInLabel: 'Sep 5, 2026',
      checkOutLabel: 'Sep 8, 2026',
      resCode: 'CRS-504-2891',
      pricePerNight: '\$240/night',
      nextCheckInDays: 52,
    ),
  ];

  /// The current in-house stay shown on the My Stays "Active" tab.
  static Stay activeStay() => const Stay(
    id: 'active-1',
    roomName: 'Grand Damascus Suite',
    status: StayStatus.active,
    subtitle: 'Room 812',
    imagePath: stayImagePath,
    checkedInSince: '3:00 PM',
    nightsRemaining: 2,
    checkInLabel: 'Aug 14, 2026',
    checkOutLabel: 'Aug 16, 2026',
  );

  /// Quick-request chips under the Services grid (Figma "Services").
  static const quickRequests = <String>[
    'Fresh Towels',
    'Extra Pillows',
    'Toiletries',
    'Ice',
    'Full Cleaning',
    'Bathrobe',
  ];

  // ── Booking flow (Figma "Booking / Step 1-8") ──────────────────────────
  static const _roomDescription =
      'An exceptional suite that blends traditional Syrian elegance with modern '
      'luxury. Floor-to-ceiling windows frame dramatic city views while the '
      'private balcony overlooks the historic Old City skyline. Features a marble '
      'jacuzzi, hand-crafted furnishings, and complimentary butler service.';

  static const roomOptions = <RoomOption>[
    RoomOption(
      id: 'room-grand',
      name: 'Grand Damascus Suite',
      images: [
        'assets/images/room_classic_courtyard.jpg',
        'assets/images/room_deluxe_city.jpg',
        'assets/images/room_premier_terrace.jpg',
      ],
      area: '85 m² space',
      view: 'City View',
      bed: 'King Bed',
      rating: 4.9,
      reviewCount: 142,
      pricePerNight: 280,
      amenityChips: ['City View Balcony', 'Jacuzzi', '+3 more'],
      highlights: [
        IconLabel('assets/icons/jacuzzi.svg', 'Marble Jacuzzi'),
        IconLabel('assets/icons/view.svg', 'City View Balcony'),
        IconLabel('assets/icons/butler.svg', 'Butler Service'),
        IconLabel('assets/icons/coffee.svg', 'Tea & Coffee Station'),
      ],
      amenities: [
        IconLabel('assets/icons/view.svg', 'City View Balcony'),
        IconLabel('assets/icons/jacuzzi.svg', 'Jacuzzi'),
        IconLabel('assets/icons/desk.svg', 'Work Desk'),
        IconLabel('assets/icons/tv.svg', 'Smart TV'),
        IconLabel('assets/icons/coffee.svg', 'Tea & Coffee Station'),
        IconLabel('assets/icons/info.svg', 'In-room safe'),
      ],
      description: _roomDescription,
    ),
    RoomOption(
      id: 'room-deluxe',
      name: 'Deluxe City View Suite',
      images: [
        'assets/images/room_deluxe_city.jpg',
        'assets/images/room_classic_courtyard.jpg',
      ],
      area: '48 m² space',
      view: 'City View',
      bed: 'King Bed',
      rating: 4.8,
      reviewCount: 98,
      pricePerNight: 240,
      amenityChips: ['City View', 'Work Desk', '+2 more'],
      highlights: [
        IconLabel('assets/icons/view.svg', 'City View'),
        IconLabel('assets/icons/desk.svg', 'Work Desk'),
        IconLabel('assets/icons/tv.svg', 'Smart TV'),
        IconLabel('assets/icons/coffee.svg', 'Coffee Station'),
      ],
      amenities: [
        IconLabel('assets/icons/view.svg', 'City View'),
        IconLabel('assets/icons/desk.svg', 'Work Desk'),
        IconLabel('assets/icons/tv.svg', 'Smart TV'),
        IconLabel('assets/icons/coffee.svg', 'Coffee Station'),
      ],
      description: _roomDescription,
    ),
    RoomOption(
      id: 'room-terrace',
      name: 'Premier Terrace Suite',
      images: [
        'assets/images/room_premier_terrace.jpg',
        'assets/images/room_deluxe_city.jpg',
      ],
      area: '52 m² space',
      view: 'Terrace',
      bed: 'King Bed',
      rating: 4.9,
      reviewCount: 121,
      pricePerNight: 320,
      amenityChips: ['Private Terrace', 'Jacuzzi', '+3 more'],
      highlights: [
        IconLabel('assets/icons/jacuzzi.svg', 'Marble Jacuzzi'),
        IconLabel('assets/icons/view.svg', 'Private Terrace'),
        IconLabel('assets/icons/butler.svg', 'Butler Service'),
        IconLabel('assets/icons/coffee.svg', 'Tea & Coffee Station'),
      ],
      amenities: [
        IconLabel('assets/icons/view.svg', 'Private Terrace'),
        IconLabel('assets/icons/jacuzzi.svg', 'Jacuzzi'),
        IconLabel('assets/icons/desk.svg', 'Work Desk'),
        IconLabel('assets/icons/tv.svg', 'Smart TV'),
      ],
      description: _roomDescription,
    ),
  ];

  static const addOns = <AddOn>[
    AddOn(
      id: 'addon-breakfast',
      iconPath: 'assets/icons/coffee.svg',
      title: 'Complimentary Breakfast',
      subtitle: 'Full buffet for 2 guests daily',
      price: 35,
    ),
    AddOn(
      id: 'addon-transfer',
      iconPath: 'assets/icons/butler.svg',
      title: 'Airport Transfer',
      subtitle: 'Round-trip luxury car service',
      price: 80,
    ),
    AddOn(
      id: 'addon-flowers',
      iconPath: 'assets/icons/jacuzzi.svg',
      title: 'Welcome Flowers & Fruits',
      subtitle: 'Fresh arrangement in room upon arrival',
      price: 45,
    ),
  ];
}
