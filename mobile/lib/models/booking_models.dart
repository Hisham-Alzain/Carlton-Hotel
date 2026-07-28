// Domain models for the My Stays + booking flow. Demo-oriented (string labels
// pre-formatted to match Figma copy); a real API layer would swap these for
// typed dates/amounts. Nothing here talks to a backend yet.

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
  /// booking starts from an API-backed room — see `DemoData.roomDetailsFor`).
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
      area: r.sizeSqm != null ? '${r.sizeSqm} m²' : '',
      view: r.viewType != null ? '${r.viewType} view' : '',
      bed: r.bedTypes.isNotEmpty ? '${r.bedTypes.first} bed' : '',
      rating: r.rating ?? 0,
      reviewCount: r.ratingCount,
      pricePerNight: double.tryParse(r.basePriceUsd)?.round() ?? 0,
      amenityChips: r.highlights.map((a) => a.name.value).toList(),
      highlights: r.highlights.map(toIconLabel).toList(),
      amenities: r.amenities.map(toIconLabel).toList(),
      description: r.description.value,
    );
  }

  static String _amenityAsset(String icon) {
    const present = {'jacuzzi', 'desk', 'tv', 'coffee', 'butler', 'view'};
    return present.contains(icon)
        ? 'assets/icons/$icon.svg'
        : 'assets/icons/view.svg';
  }
}

class AddOn {
  final String id;
  final String iconPath;
  final String title;
  final String subtitle;
  final int price;

  const AddOn({
    required this.id,
    required this.iconPath,
    required this.title,
    required this.subtitle,
    required this.price,
  });
}

enum PaymentMethod {
  card('Credit / Debit Card', 'Visa, Mastercard, Amex'),
  applePay('Apple Pay', 'Pay with Face ID or Touch ID'),
  googlePay('Google Pay', 'Pay with your Google account'),
  payAtHotel('Pay at Hotel', 'No payment required today');

  const PaymentMethod(this.label, this.subtitle);
  final String label;
  final String subtitle;
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
