class Address {
  final int id;
  final int userId;
  final String label;
  final String description;
  final double lat;
  final double lng;
  final bool isDefault;

  Address({
    required this.id,
    required this.userId,
    required this.label,
    required this.description,
    required this.lat,
    required this.lng,
    required this.isDefault,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      label: json['label'] as String,
      description: json['description'] as String,
      lat: double.parse(json['lat']),
      lng: double.parse(json['lng']),
      isDefault: json['is_default'] as bool,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'label': label,
      'description': description,
      'lat': lat.toString(),
      'lng': lng.toString(),
      'is_default': isDefault,
    };
  }

  Address.empty()
    : id = -1,
      userId = 0,
      label = '',
      description = '',
      lat = 0.0,
      lng = 0.0,
      isDefault = false;

  @override
  String toString() {
    return label;
  }
}
