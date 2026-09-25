// UI models for the homepage listings. Rooms/dining/experiences are mapped
// from the public content DTOs at the controller boundary (see the
// fromRoomType/fromDiningVenue/fromExperience factories).

import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/experience.dart';
import 'package:carlton/models/room_type.dart';

/// Which Home section a "Discover All" tap opened, carried as the route
/// argument to the shared Discover listing screen.
enum DiscoverSection { rooms, dining, experiences }

/// UI projection of a room type — fed either by `DemoData` or, once wired, by a
/// `RoomType` DTO mapped at the controller boundary. [uuid] is empty for demo
/// rows and set for API-backed ones (used for navigation).
class RoomItem {
  final String uuid;
  final String name;
  final String view;
  final String area;
  final String guests;
  final String bed;

  /// Nightly price amount only (e.g. `'$580'`) — the card renders it as
  /// "From $580/night" with the amount emphasised.
  final String priceAmount;
  final String imagePath;

  final String category;
  final double rating;
  final int reviews;

  /// Short amenity tags shown as cream chips on the Discover room card.
  final List<String> amenities;

  const RoomItem({
    required this.name,
    required this.view,
    required this.area,
    required this.guests,
    required this.bed,
    required this.priceAmount,
    required this.imagePath,
    this.uuid = '',
    this.category = '',
    this.rating = 4.9,
    this.reviews = 142,
    this.amenities = const [],
  });

  factory RoomItem.fromRoomType(RoomType roomType) => RoomItem(
    uuid: roomType.uuid,
    name: roomType.name.value,
    // The meta row is a short "City view" label — NOT the full description,
    // which is a paragraph and overflowed the fixed-width meta chip.
    view: (roomType.viewType != null && roomType.viewType!.isNotEmpty)
        ? AppTranslations.roomView(
            '${roomType.viewType![0].toUpperCase()}${roomType.viewType!.substring(1)}',
          )
        : '',
    area: roomType.sizeSqm != null
        ? AppTranslations.roomSize('${roomType.sizeSqm}')
        : '',
    guests: '',
    bed: roomType.bedTypes.isNotEmpty
        ? AppTranslations.roomBed(roomType.bedTypes.first)
        : '',
    priceAmount: MoneyFormat.usdString(roomType.basePriceUsd),
    imagePath: roomType.banner?.url ?? '',
    rating: roomType.rating ?? 0,
    reviews: roomType.ratingCount,
    amenities: roomType.highlights
        .map((amenity) => amenity.name.value)
        .toList(),
  );
}

/// UI projection of a dining venue — fed by `DemoData` or a mapped `DiningVenue`.
class RestaurantItem {
  final String uuid;
  final String name;
  final String cuisine;
  final String hours;
  final String location;
  final String imagePath;

  final String category;
  final double rating;
  final int reviews;

  const RestaurantItem({
    required this.name,
    required this.cuisine,
    required this.hours,
    required this.location,
    required this.imagePath,
    this.uuid = '',
    this.category = '',
    this.rating = 4.9,
    this.reviews = 142,
  });

  /// Empty venue used for the single frame a detail screen may render before a
  /// routing error pops it. Deliberately blank rather than a plausible-looking
  /// demo venue, so a guest never briefly sees details for somewhere real.
  static const RestaurantItem blank = RestaurantItem(
    name: '',
    cuisine: '',
    hours: '',
    location: '',
    imagePath: '',
    rating: 0,
    reviews: 0,
  );

  factory RestaurantItem.fromDiningVenue(DiningVenue diningVenue) =>
      RestaurantItem(
        uuid: diningVenue.uuid,
        name: diningVenue.name.value,
        cuisine: diningVenue.cuisineType.value,
        hours: diningVenue.hours.value,
        location: diningVenue.location.value,
        imagePath: diningVenue.banner?.url ?? '',
        rating: diningVenue.rating ?? 0,
        reviews: diningVenue.ratingCount,
      );
}

class ExperienceItem {
  final String uuid;
  final String name;

  /// One-line descriptor shown on the first meta row (e.g. a cuisine or a
  /// guest count).
  final String subtitle;
  final String hours;
  final String imagePath;

  /// Filter bucket on the Discover screen (Gastronomy / Culture / Privilege).
  final String category;
  final double rating;
  final int reviews;

  /// Optional category tag rendered as a pill over the image (matches the
  /// Figma "Culture" badge); null hides it.
  final String? badge;

  const ExperienceItem({
    required this.name,
    required this.subtitle,
    required this.hours,
    required this.imagePath,
    required this.category,
    this.uuid = '',
    this.rating = 4.9,
    this.reviews = 142,
    this.badge,
  });

  /// [Experience] carries no review system yet (no `HasReviews` on the
  /// backend model), so rating/reviews come back honestly zeroed rather than
  /// inheriting the demo defaults above.
  factory ExperienceItem.fromExperience(Experience experience) =>
      ExperienceItem(
        uuid: experience.uuid,
        name: experience.title.value,
        subtitle: experience.groupSize.value,
        hours: experience.durationLabel.value,
        imagePath: experience.image ?? '',
        category: experience.category,
        rating: 0,
        reviews: 0,
        badge: experience.category.isEmpty ? null : experience.category,
      );
}
