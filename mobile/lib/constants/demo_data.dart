import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:carlton/models/service_item.dart';
import 'package:flutter/material.dart';

/// Every hardcoded demo value in the app lives here, so wiring the real
/// backend later is a single-file hunt. Nothing in this file should survive
/// API integration.
abstract class DemoData {
  // ── Auth ────────────────────────────────────────────────────────────────
  /// Simulated network round-trip used by the remaining demo submits.
  static const networkDelay = Duration(milliseconds: 800);

  // Checkout pricing is no longer client-computed — Phase 3 replaced the
  // taxRate/promoRate engine and newConfirmationCode() with the real
  // `GET /public/quote` + `POST /reservations` endpoints.

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
      category: 'Suites',
      amenities: ['City View Balcony', 'Jacuzzi', '+3 more'],
    ),
    RoomItem(
      name: 'Premier Terrace',
      view: 'A private terrace with sun loungers',
      area: '52 m²',
      guests: '2',
      bed: 'King Bed',
      priceAmount: '\$850',
      imagePath: 'assets/images/room_premier_terrace.jpg',
      category: 'Deluxe',
      amenities: ['Private Terrace', 'Sun Loungers', '+2 more'],
    ),
    RoomItem(
      name: 'Deluxe City View Suite',
      view: 'Panoramic city view',
      area: '48 m²',
      guests: '2',
      bed: 'King Bed',
      priceAmount: '\$680',
      imagePath: 'assets/images/room_deluxe_city.jpg',
      category: 'Classic',
      amenities: ['City View', 'Rain Shower', '+2 more'],
    ),
  ];

  // Room details + the Choose-Room list now come from GET /public/room-types
  // (+ /{uuid}); mapped via RoomOption.fromRoomType. roomOptions/roomDetailsFor
  // retired.

  static const restaurants = <RestaurantItem>[
    RestaurantItem(
      name: 'Al-Sham Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_alsham.jpg',
      category: 'Mediterranean',
    ),
    RestaurantItem(
      name: 'Al-Qamar Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_alqamar.jpg',
      category: 'Fine Dining',
    ),
    RestaurantItem(
      name: 'ELENA Restaurant',
      cuisine: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      location: 'Ground Floor, Carlton Hotel',
      imagePath: 'assets/images/restaurant_elena.jpg',
      category: 'Italian',
    ),
  ];

  /// Curated experiences shown on the Discover "Experiences" listing (Figma
  /// frame 2195:2813). Images reuse existing hotel photography for the demo.
  static const experiences = <ExperienceItem>[
    ExperienceItem(
      name: 'Al-Sham Culinary Journey',
      subtitle: 'Syrian · Mediterranean',
      hours: '7:00 AM – 11:00 PM',
      imagePath: 'assets/images/restaurant_alsham.jpg',
      category: 'Gastronomy',
    ),
    ExperienceItem(
      name: 'Umayyad Mosque & Old City Walk',
      subtitle: '1–4 guests',
      hours: '7:00 AM – 10:00 PM',
      imagePath: 'assets/images/hero_experience.png',
      category: 'Culture',
      badge: 'Culture',
    ),
    ExperienceItem(
      name: 'Old City Heritage Tour',
      subtitle: '1–4 guests',
      hours: '9:00 AM – 5:00 PM',
      imagePath: 'assets/images/restaurant_alqamar.jpg',
      category: 'Culture',
      badge: 'Culture',
    ),
    ExperienceItem(
      name: 'Private Rooftop Dining',
      subtitle: '2 guests',
      hours: 'By reservation',
      imagePath: 'assets/images/restaurant_elena.jpg',
      category: 'Privilege',
      badge: 'Privilege',
    ),
  ];

  // ── Dining (Figma restaurant menu / info / reserve) ────────────────────
  // Venue about/gallery/rating/tagline are now fetched from
  // `GET /public/dining-venues/{uuid}` (see RestaurantController). Only the
  // reservation time slots stay demo — there is no backend slots endpoint wired.
  static const reserveTimeSlots = <String>[
    '12:30 PM',
    '1:00 PM',
    '7:00 PM',
    '7:30 PM',
    '8:00 PM',
    '8:30 PM',
    '9:00 PM',
  ];

  // ── Customer Service (Figma "Customer Service" chat) ───────────────────
  static const csAgentName = 'Lara K.';
  static const csAgentRole = 'Guest Relations';
  static const csAgentInitial = 'L';
  static const csPhone = '+963111234567';
  static const csQuickReplies = <String>[
    'Billing inquiry',
    'Room issue',
    'Special request',
    'Feedback',
  ];
  // The seeded customer-service thread is retired — the Customer Service tab is
  // wired to GET /conversations + GET /conversations/{uuid}/messages (Phase 6).
  // csAgentName/Role/Initial/csQuickReplies/csPhone stay demo: the message API
  // carries only sender_type, no staff identity or quick-replies.

  // ── Preferences (Figma Preferences + option-picker sheets) ─────────────
  // Bed / mattress / pillow use the exported Figma glyphs (assets/icons);
  // language / currency use Material icons.
  static const bedOptions = <PreferenceOption>[
    PreferenceOption(
      id: 'king',
      label: 'King Bed',
      iconAsset: 'assets/icons/kingbed.svg',
    ),
    PreferenceOption(
      id: 'queen',
      label: 'Queen Bed',
      iconAsset: 'assets/icons/queenbed.svg',
    ),
    PreferenceOption(
      id: 'double',
      label: 'Double Bed',
      iconAsset: 'assets/icons/doublebed.svg',
    ),
    PreferenceOption(
      id: 'twin',
      label: 'Twin Beds',
      iconAsset: 'assets/icons/twinbeds.svg',
    ),
    PreferenceOption(
      id: 'single',
      label: 'Single Bed',
      iconAsset: 'assets/icons/singlebed.svg',
    ),
    PreferenceOption(
      id: 'extra',
      label: 'Extra Bed',
      iconAsset: 'assets/icons/extrabed.svg',
    ),
  ];

  static const pillowOptions = <PreferenceOption>[
    PreferenceOption(
      id: 'soft',
      label: 'Soft',
      iconAsset: 'assets/icons/softpillow.svg',
    ),
    PreferenceOption(
      id: 'firm',
      label: 'Firm',
      iconAsset: 'assets/icons/firmpillow.svg',
    ),
    PreferenceOption(
      id: 'feather',
      label: 'Feather',
      iconAsset: 'assets/icons/featherpillow.svg',
    ),
  ];

  static const mattressOptions = <PreferenceOption>[
    PreferenceOption(
      id: 'soft',
      label: 'Soft Mattress',
      iconAsset: 'assets/icons/softmattress.svg',
    ),
    PreferenceOption(
      id: 'medium',
      label: 'Medium Mattress',
      iconAsset: 'assets/icons/mediummattress.svg',
    ),
    PreferenceOption(
      id: 'firm',
      label: 'Firm Mattress',
      iconAsset: 'assets/icons/firmmattress.svg',
    ),
    PreferenceOption(
      id: 'foam',
      label: 'Memory Foam Mattress',
      iconAsset: 'assets/icons/memoryfoammattress.svg',
    ),
    PreferenceOption(
      id: 'orthopedic',
      label: 'Orthopedic Mattress',
      iconAsset: 'assets/icons/orthopedicmattress.svg',
    ),
    PreferenceOption(
      id: 'hotel',
      label: 'Hotel Standard Mattress',
      iconAsset: 'assets/icons/hotelstandardmattress.svg',
    ),
  ];

  static const languageOptions = <PreferenceOption>[
    PreferenceOption(id: 'en', label: 'English', icon: Icons.language),
    PreferenceOption(id: 'ar', label: 'العربية', icon: Icons.language),
  ];

  // Mirrors the currencies SettingsService actually supports (usd/syp); the
  // picker delegates to it, so an option it can't set would silently revert.
  static const currencyOptions = <PreferenceOption>[
    PreferenceOption(id: 'usd', label: 'USD', icon: Icons.attach_money),
    PreferenceOption(id: 'syp', label: 'SYP', icon: Icons.payments_outlined),
  ];

  // ── Services grid (Figma "Services 1", node 2073:133) ──────────────────
  static const services = <ServiceItem>[
    ServiceItem(
      title: 'Room Service',
      code: 'room_service',
      subtitle: '24 hrs',
      imagePath: 'assets/images/tile_room_service.png',
      imageWidth: 57,
      imageHeight: 64,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Laundry',
      code: 'laundry',
      subtitle: 'Same-day',
      imagePath: 'assets/images/tile_laundry.png',
      imageWidth: 76,
      imageHeight: 71,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Housekeeping',
      code: 'housekeeping',
      subtitle: 'On demand',
      imagePath: 'assets/images/tile_housekeeping.png',
      imageWidth: 70,
      imageHeight: 59,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Concierge',
      code: 'concierge',
      subtitle: 'Always available',
      imagePath: 'assets/images/tile_concierge.png',
      imageWidth: 65,
      imageHeight: 46,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Transport',
      code: 'transport',
      subtitle: 'Car & Valet',
      imagePath: 'assets/images/tile_transport.png',
      imageWidth: 79,
      imageHeight: 73,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Restaurant Res.',
      code: 'restaurant',
      subtitle: 'Always available',
      imagePath: 'assets/images/tile_restaurant.png',
      imageWidth: 88,
      imageHeight: 82,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Maintenance',
      code: 'maintenance',
      subtitle: 'Quick request',
      imagePath: 'assets/images/tile_maintenance.png',
      imageWidth: 65,
      imageHeight: 57,
      imageOpacity: 1,
    ),
    ServiceItem(
      title: 'Do Not Disturb',
      code: 'do_not_disturb',
      subtitle: 'Privacy mode',
      imagePath: 'assets/images/tile_dnd.png',
      imageWidth: 74,
      imageHeight: 74,
      imageOpacity: 1,
    ),
  ];

  // Service categories are now fetched live (Phase 5 — GET /public/service-
  // catalog → ServiceCatalogItem/ServiceCatalogOption), and active requests
  // come from GET /service-requests. The demo category/request lists are
  // retired.

  // The Home active-booking dashboard (stay hero + running bill) is now fetched
  // from GET /stays/active + GET /folio (see HomeController). currentBillLines/
  // currentBillTotal/activeStay() retired.

  // ── AI Concierge (Figma AI1/AI2) ────────────────────────────────────────
  static const aiSuggestions = <String>[
    'Can I get a late check-out?',
    "What's near the hotel?",
    'Request housekeeping',
    'Pool & gym hours',
    'Airport transfer',
    'Help me booking',
  ];

  // Booking draft defaults are now live controller state seeded from "today"
  // (Phase 3), not fixed demo dates/guests.

  // ── My Stays (Figma "Past Stays" 2116:198 / "Stays / Upcoming" 2116:483) ─
  // Upcoming + past stays are fetched live (Phase 4). Home's active-booking
  // dashboard is now fetched too (GET /stays/active) — activeStay() retired.

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

  // ── Pre-arrival / check-in (Figma cpln3bQzXRnpkItKQkJCVs) ──
  static const preArrivalReservation = ReservationSummary(
    guestName: 'Ahmed Al-Hassan',
    suiteName: 'Grand Damascus Suite',
    roomNumber: '812',
    floorLabel: '3rd floor',
    checkInDate: 'Aug 14',
    checkInTime: 'From 3:00 PM',
    checkOutDate: 'Aug 16',
    checkOutTime: 'By 12:00 PM',
    stayRangeLabel: 'Grand Damascus Suite · Aug 14 – 16, 2026',
    bookingRef: '#CLT-0082',
  );

  /// Seeds the Preferences tab. Ids exist in bedOptions/pillowOptions/
  /// mattressOptions above.
  static const defaultStayPreferences = StayPreferences(
    bedTypeId: 'king',
    pillowId: 'firm',
    mattressId: 'medium',
    smokingRoom: false,
    earlyCheckIn: true,
    lateCheckOut: false,
    extraPillows: false,
    notes: '',
  );

  /// Passport still shown by the mocked scanner and the verified card.
  static const demoPassportNumber = 'SY-20480831';
  static const demoPassportAsset = 'assets/images/demo_passport.png';
  static const scanDuration = Duration(milliseconds: 1800);
  static const digitalKeyActivationDuration = Duration(seconds: 2);
}
