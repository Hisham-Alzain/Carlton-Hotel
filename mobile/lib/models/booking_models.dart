// Domain models for the My Stays + booking flow. Demo-oriented (string labels
// pre-formatted to match Figma copy); a real API layer would swap these for
// typed dates/amounts. Nothing here talks to a backend yet.

import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/models/amenity.dart';
import 'package:carlton/models/room_type.dart';

enum StayStatus { active, upcoming, past }

/// One line in a receipt breakdown.
typedef ReceiptLine = ({String label, String amount});

class ReceiptData {
  final String roomName;
  final String dateLabel;
  final String resCode;
  final List<ReceiptLine> lines;
  final String total;
  final String paymentInfo;

  const ReceiptData({
    required this.roomName,
    required this.dateLabel,
    required this.resCode,
    required this.lines,
    required this.total,
    required this.paymentInfo,
  });
}

/// The charge breakdown for one priced stay, already formatted for display.
/// Assembled by `BookingFlowController.priceSummary` from the `/public/quote`
/// result so the breakdown widget takes one model instead of seven strings.
class BookingPriceSummary {
  final String roomName;

  /// The quote's night count, which can differ from the picked range once the
  /// server has priced the stay.
  final int nights;
  final String subtotal;

  /// [promoCode] is only rendered when [hasDiscount] — an unapplied code in the
  /// field must not show up as a charged line.
  final bool hasDiscount;
  final String promoCode;
  final String discount;

  final String total;

  const BookingPriceSummary({
    required this.roomName,
    required this.nights,
    required this.subtotal,
    required this.hasDiscount,
    required this.promoCode,
    required this.discount,
    required this.total,
  });
}

/// A stay in any of the three My Stays tabs. Fields are optional because each
/// status renders a different card (see StaysView tab bodies).
class Stay {
  final String id;

  /// The reservation `uuid` (Phase 4) — drives cancel
  /// (`DELETE /reservations/{uuid}`) and receipt (`GET /stays/{uuid}/receipt`).
  /// Empty for demo-shaped stays; stamped during controller-boundary mapping.
  final String uuid;

  /// Whether `DELETE /reservations/{uuid}` will succeed (upcoming stays only).
  final bool isCancellable;

  /// Whether a folio/receipt exists for this stay (past stays only). Gates the
  /// receipt fetch — a cancelled stay has none.
  final bool hasReceipt;

  final String roomName;
  final StayStatus status;

  // Past + upcoming
  final String? subtitle; // "Carlton Hotel Damascus · Room 504"
  final String? imagePath;

  // Past
  final String? dateRangeLabel; // "Jul 8 – Jul 10 · 2 nights"
  final String? totalCharged; // "$596"
  final ReceiptData? receipt;

  // Upcoming
  final String? checkInLabel; // "Sep 5, 2026"
  final String? checkOutLabel; // "Sep 8, 2026"
  final String? resCode; // "CRS-504-2891"
  final String? pricePerNight; // "$240/night"
  final int? nextCheckInDays; // 52

  // Active
  final String? checkedInSince; // "3:00 PM"
  final int? nightsRemaining;

  const Stay({
    required this.id,
    required this.roomName,
    required this.status,
    this.uuid = '',
    this.isCancellable = false,
    this.hasReceipt = false,
    this.subtitle,
    this.imagePath,
    this.dateRangeLabel,
    this.totalCharged,
    this.receipt,
    this.checkInLabel,
    this.checkOutLabel,
    this.resCode,
    this.pricePerNight,
    this.nextCheckInDays,
    this.checkedInSince,
    this.nightsRemaining,
  });
}

/// Icon + label pair for room highlights and amenities.
class IconLabel {
  final String iconPath;
  final String label;

  const IconLabel(this.iconPath, this.label);
}

class RoomOption {
  /// The real `room_type_uuid` when the booking began from an API-backed room
  /// (Home/Discover → room details); empty for pure-demo rooms (Book-tab
  /// choose-room list). Quote + `POST /reservations` require it.
  final String uuid;
  final String id;
  final String name;
  final List<String> images;
  final String area; // "85 m² space"
  final String view; // "City View"
  final String bed; // "King Bed"
  final double rating;
  final int reviewCount;
  final int pricePerNight;
  final List<String> amenityChips; // short chips on the result card
  final List<IconLabel> highlights;
  final List<IconLabel> amenities;
  final String description;

  const RoomOption({
    required this.id,
    required this.name,
    required this.images,
    required this.area,
    required this.view,
    required this.bed,
    required this.rating,
    required this.reviewCount,
    required this.pricePerNight,
    required this.amenityChips,
    required this.highlights,
    required this.amenities,
    required this.description,
    this.uuid = '',
  });

  /// Stamps a real `room_type_uuid` onto an otherwise-demo option (used when a
  /// booking starts from an API-backed room).
  RoomOption copyWith({String? uuid}) => RoomOption(
    uuid: uuid ?? this.uuid,
    id: id,
    name: name,
    images: images,
    area: area,
    view: view,
    bed: bed,
    rating: rating,
    reviewCount: reviewCount,
    pricePerNight: pricePerNight,
    amenityChips: amenityChips,
    highlights: highlights,
    amenities: amenities,
    description: description,
  );

  /// Maps the API room-type detail (`GET /public/room-types/{uuid}`) to the
  /// booking option the details screen renders. Amenity icons arrive as names
  /// (e.g. "jacuzzi"); map to the matching bundled asset, falling back to a
  /// generic glyph for names we didn't extract.
  factory RoomOption.fromRoomType(RoomType r) {
    IconLabel toIconLabel(Amenity a) =>
        IconLabel(_amenityAsset(a.icon), a.name.value);
    return RoomOption(
      uuid: r.uuid,
      id: r.uuid,
      name: r.name.value,
      images: r.images.map((i) => i.url).toList(),
      area: r.sizeSqm != null ? AppTranslations.roomSize('${r.sizeSqm}') : '',
      view: r.viewType != null ? AppTranslations.roomView(r.viewType!) : '',
      bed: r.bedTypes.isNotEmpty
          ? AppTranslations.roomBed(r.bedTypes.first)
          : '',
      rating: r.rating ?? 0,
      reviewCount: r.ratingCount,
      pricePerNight: double.tryParse(r.basePriceUsd)?.round() ?? 0,
      amenityChips: r.highlights.map((a) => a.name.value).toList(),
      highlights: r.highlights.map(toIconLabel).toList(),
      amenities: r.amenities.map(toIconLabel).toList(),
      description: r.description.value,
    );
  }

  /// Maps the server's amenity `icon` key to a bundled SVG. Only files that
  /// exist in assets/icons/ may appear here — a missing asset throws
  /// "Unable to load asset" on every rebuild of the room screen.
  static String _amenityAsset(String icon) {
    const assets = {
      'jacuzzi': 'jacuzzi',
      'coffee': 'coffee',
      'butler': 'butler',
      'view': 'view',
      'balcony': 'view',
      'safe': 'lock',
      'desk': 'space',
      'wifi': 'wifi',
    };
    return 'assets/icons/${assets[icon] ?? 'view'}.svg';
  }
}

/// One selectable extra on the booking wizard's Add-Ons step.
///
/// These are the real bookables the hotel sells alongside a room — spa
/// treatments (`GET /public/spa-services`), poolside cabanas
/// (`GET /public/pool-cabanas`) and airport transfers
/// (`GET /public/transfers`). [id] is the server `uuid` and [bookableType] the
/// morph alias `POST /service-bookings` expects, so a selection made here can
/// be submitted verbatim once the reservation exists.
class AddOn {
  /// The bookable's server uuid, sent as `bookable_uuid`.
  final String id;

  /// `spa_service` | `pool_cabana` | `transfer` — the `bookable_type` the
  /// booking endpoint validates against its `BookableType` enum.
  final String bookableType;
  final String iconPath;
  final String title;

  /// Duration, capacity, or whatever the endpoint gave us for this kind. Empty
  /// when it gave us nothing — the row hides the line rather than inventing
  /// one.
  final String subtitle;

  /// USD decimal string straight off the wire (`"120.00"`). Kept as text so it
  /// is rendered through `MoneyFormat.usdString` in the guest's currency
  /// instead of being rounded to an int here.
  final String priceUsd;

  const AddOn({
    required this.id,
    required this.bookableType,
    required this.iconPath,
    required this.title,
    required this.subtitle,
    required this.priceUsd,
  });

  /// The numeric value, for totals. Unparseable reads as 0 rather than throwing
  /// mid-build.
  double get price => double.tryParse(priceUsd) ?? 0;
}

enum PaymentMethod {
  card,
  applePay,
  googlePay,
  payAtHotel;

  /// Resolved per read rather than held as `const` enum fields — `.tr` is a
  /// runtime lookup, so a const field would freeze the launch locale.
  /// The wallet brand names stay untranslated on purpose: Apple and Google
  /// ship them as proper nouns in every locale.
  String get label => switch (this) {
    PaymentMethod.card => AppTranslations.creditCard,
    PaymentMethod.applePay => 'Apple Pay',
    PaymentMethod.googlePay => 'Google Pay',
    PaymentMethod.payAtHotel => AppTranslations.payAtHotel,
  };

  String get subtitle => switch (this) {
    PaymentMethod.card => AppTranslations.acceptedCardsFull,
    PaymentMethod.applePay => AppTranslations.applePayTagline,
    PaymentMethod.googlePay => AppTranslations.googlePayTagline,
    PaymentMethod.payAtHotel => AppTranslations.payAtHotelTagline,
  };
}

extension PaymentMethodIcon on PaymentMethod {
  /// Brand glyph for the wallet methods; null for card / pay-at-hotel.
  String? get iconPath => switch (this) {
    PaymentMethod.applePay => 'assets/icons/pay_apple.svg',
    PaymentMethod.googlePay => 'assets/icons/pay_google.svg',
    PaymentMethod.card || PaymentMethod.payAtHotel => null,
  };
}

/// Mutable draft of the guest form (Step 4).
class GuestDetails {
  String firstName;
  String lastName;
  String email;
  String dialCode;
  String phone;
  String specialRequests;

  GuestDetails({
    this.firstName = '',
    this.lastName = '',
    this.email = '',
    this.dialCode = kDefaultDialCode,
    this.phone = '',
    this.specialRequests = '',
  });
}

/// Mutable draft of the card form (Step 5).
class CardDetails {
  String number;
  String expiry;
  String cvv;
  String nameOnCard;

  CardDetails({
    this.number = '',
    this.expiry = '',
    this.cvv = '',
    this.nameOnCard = '',
  });
}
