import 'package:carlton/models/json_num.dart';

DateTime? _date(dynamic v) =>
    v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

/// Which review target a `{type}` URL segment addresses. Both the public GET
/// (`/public/reviews/{type}/{uuid}`) and the tier-2 POST (`/reviews/{type}/{uuid}`)
/// derive their path segment from [apiPath] — never a raw string.
enum ReviewTargetType {
  roomType,
  diningVenue;

  String get apiPath =>
      this == ReviewTargetType.roomType ? 'room_type' : 'dining_venue';
}

/// A published guest review (`/public/reviews/{type}/{uuid}`). `is_verified_stay`
/// is derived server-side — the app cannot set it.
///
/// `created_at` is parsed to a [DateTime] here rather than carried as a raw ISO
/// string, so the view formats a real date instead of re-parsing on every
/// rebuild. Nullable because the field is optional and may be unparseable —
/// same shape as [Receipt]'s dates.
class Review {
  final String uuid;
  final int rating;
  final String? comment;
  final bool isVerifiedStay;
  final DateTime? createdAt;
  final String authorFirstName;
  final String authorLastName;

  const Review({
    required this.uuid,
    required this.rating,
    this.comment,
    this.isVerifiedStay = false,
    this.createdAt,
    this.authorFirstName = '',
    this.authorLastName = '',
  });

  factory Review.fromJson(Map<String, dynamic> json) {
    final author = json['author'] as Map<String, dynamic>?;
    return Review(
      uuid: json['uuid'] as String? ?? '',
      rating: asInt(json['rating']) ?? 0,
      comment: json['comment'] as String?,
      isVerifiedStay: json['is_verified_stay'] as bool? ?? false,
      createdAt: _date(json['created_at']),
      authorFirstName: author?['first_name'] as String? ?? '',
      authorLastName: author?['last_name'] as String? ?? '',
    );
  }

  String get authorName =>
      [authorFirstName, authorLastName].where((s) => s.isNotEmpty).join(' ');
}
