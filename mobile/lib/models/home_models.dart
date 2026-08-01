// UI models for the homepage listings. Rooms/dining are mapped from the public
// content DTOs at the controller boundary (see the fromRoomType/fromDiningVenue
// factories); experiences stay demo-only.

import 'package:carlton/models/dining_venue.dart';
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

  factory RoomItem.fromRoomType(RoomType r) => RoomItem(
    uuid: r.uuid,
    name: r.name.value,
    // The meta row is a short "City view" label — NOT the full description,
    // which is a paragraph and overflowed the fixed-width meta chip.
    view: (r.viewType != null && r.viewType!.isNotEmpty)
        ? '${r.viewType![0].toUpperCase()}${r.viewType!.substring(1)} view'
        : '',
    area: r.sizeSqm != null ? '${r.sizeSqm} m²' : '',
    guests: '',
    bed: r.bedTypes.isNotEmpty ? '${r.bedTypes.first} bed' : '',
    priceAmount: '\$${r.basePriceUsd}',
    imagePath: r.banner?.url ?? '',
    rating: r.rating ?? 0,
    reviews: r.ratingCount,
    amenities: r.highlights.map((a) => a.name.value).toList(),
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

  factory RestaurantItem.fromDiningVenue(DiningVenue v) => RestaurantItem(
    uuid: v.uuid,
    name: v.name.value,
    cuisine: v.cuisineType.value,
    hours: v.hours.value,
    location: v.location.value,
    imagePath: v.banner?.url ?? '',
    rating: v.rating ?? 0,
    reviews: v.ratingCount,
  );
}

class ExperienceItem {
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
    this.rating = 4.9,
    this.reviews = 142,
    this.badge,
  });
}
