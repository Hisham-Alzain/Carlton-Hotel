/// The thing a [ServiceBooking] books — a spa service, restaurant table, pool
/// cabana or transfer — carrying its uuid and a display [label].
class BookableRef {
  final String uuid;
  final String label;

  const BookableRef({required this.uuid, required this.label});

  factory BookableRef.fromJson(Map<String, dynamic> json) => BookableRef(
    uuid: json['uuid'] as String? ?? '',
    label: json['label'] as String? ?? '',
  );
}

/// A scheduled extra booked ahead of / during a stay. Covers the generic
/// `POST /service-bookings` shape and the restaurant-table variant returned by
/// `POST /dining-venues/{uuid}/table-reservations`
/// (`bookable_type: restaurant_table`, plus [guestCount]).
class ServiceBooking {
  final String uuid;
  final String bookableType;
  final BookableRef? bookable;
  final String? scheduledAt;

  /// One of `pending` / `confirmed` / `cancelled` / `completed`.
  final String status;
  final String? notes;
  final int? guestCount;

  const ServiceBooking({
    required this.uuid,
    required this.bookableType,
    this.bookable,
    this.scheduledAt,
    this.status = 'pending',
    this.notes,
    this.guestCount,
  });

  factory ServiceBooking.fromJson(Map<String, dynamic> json) => ServiceBooking(
    uuid: json['uuid'] as String? ?? '',
    bookableType: json['bookable_type'] as String? ?? '',
    bookable: json['bookable'] is Map<String, dynamic>
        ? BookableRef.fromJson(json['bookable'] as Map<String, dynamic>)
        : null,
    scheduledAt: json['scheduled_at'] as String?,
    status: json['status'] as String? ?? 'pending',
    notes: json['notes'] as String?,
    guestCount: (json['guest_count'] as num?)?.toInt(),
  );

  /// The assigned table / booked item label, for the success message.
  String get label => bookable?.label ?? '';
}
