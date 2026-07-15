/// Demo models for the homepage listings. Replace with API-backed models when
/// a backend exists.
class RoomItem {
  final String name;
  final String view;
  final String area;
  final String guests;
  final String bed;

  /// Nightly price amount only (e.g. `'$580'`) — the card renders it as
  /// "From $580/night" with the amount emphasised.
  final String priceAmount;
  final String imagePath;

  const RoomItem({
    required this.name,
    required this.view,
    required this.area,
    required this.guests,
    required this.bed,
    required this.priceAmount,
    required this.imagePath,
  });
}

class RestaurantItem {
  final String name;
  final String cuisine;
  final String hours;
  final String location;
  final String imagePath;

  const RestaurantItem({
    required this.name,
    required this.cuisine,
    required this.hours,
    required this.location,
    required this.imagePath,
  });
}
