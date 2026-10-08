part of 'booking_models.dart';

/// Icon + label pair for room highlights and amenities. The icon is a bundled
/// SVG ([iconPath]) or, for an amenity with no SVG, a Material icon
/// ([iconData], which then wins).
class IconLabel {
  final String iconPath;
  final String label;
  final IconData? iconData;

  const IconLabel(this.iconPath, this.label, {this.iconData});
}

class RoomOption {
  /// The `room_type_uuid`. Quote + `POST /reservations` require it, so a room
  /// without one is left out of the bookable list.
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

  /// Copy with a different `room_type_uuid`.
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
    IconLabel toIconLabel(Amenity a) => IconLabel(
      _amenityAsset(a.icon),
      a.name.value,
      iconData: _amenityMaterialIcons[a.icon],
    );
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

  /// Amenity `icon` keys with no bundled SVG, drawn with a Material icon
  /// instead of falling back to the generic view glyph.
  static const _amenityMaterialIcons = {'tv': Icons.tv_outlined};

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
