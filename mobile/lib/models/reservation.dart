/// A booking (`POST /reservations`, `GET /reservations[/{uuid}]`). Replaces the
/// old 2-field pending-link stub (that role now belongs to
/// `models/pending_booking_link.dart`). The Stays flow (Phase 4) consumes this.
class Reservation {
  final String uuid;
  final String bookingCode; // "CARL-XXXXXXXX"
  final String status; // pending, confirmed, checked_in, cancelled, …
  final DateTime? checkIn;
  final DateTime? checkOut;
  final int nights;
  final String? source; // "direct", …
  final String paymentMethod; // "cash" | "on_arrival"
  final String totalUsd; // decimal string, e.g. "270.00"
  final DateTime? holdExpiresAt;

  const Reservation({
    required this.uuid,
    required this.bookingCode,
    required this.status,
    this.checkIn,
    this.checkOut,
    this.nights = 0,
    this.source,
    this.paymentMethod = '',
    this.totalUsd = '0',
    this.holdExpiresAt,
  });

  /// The guest is in-house. Drives [HomeViewState.activeBooking] — see
  /// `HomeController.resolveHomeState`.
  bool get isCheckedIn => status == 'checked_in';

  /// Whether this reservation is still live enough for Home to render it.
  /// Mirrors the backend's `Guest::activeReservations` scope, which is what
  /// `has_booking` is derived from — keep the two in step.
  bool get isCurrent => status != 'cancelled' && status != 'checked_out';

  /// `DELETE /reservations/{uuid}` succeeds only before check-in — from
  /// `pending_verification`, `pending`, or `confirmed`. `checked_in`+ returns
  /// `reservation_state` (422), so the cancel affordance is hidden past that.
  bool get isCancellable =>
      status == 'pending_verification' ||
      status == 'pending' ||
      status == 'confirmed';

  static DateTime? _date(dynamic v) =>
      v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

  factory Reservation.fromJson(Map<String, dynamic> json) => Reservation(
    uuid: json['uuid'] as String? ?? '',
    bookingCode: json['booking_code'] as String? ?? '',
    status: json['status'] as String? ?? '',
    checkIn: _date(json['check_in']),
    checkOut: _date(json['check_out']),
    nights: (json['nights'] as num?)?.toInt() ?? 0,
    source: json['source'] as String?,
    paymentMethod: json['payment_method'] as String? ?? '',
    totalUsd: json['total_usd']?.toString() ?? '0',
    holdExpiresAt: _date(json['hold_expires_at']),
  );

  /// Parses a `GET /reservations` page (envelope already unwrapped to items).
  static List<Reservation> listFromJson(dynamic data) => data is List
      ? data
            .whereType<Map<String, dynamic>>()
            .map(Reservation.fromJson)
            .toList()
      : const [];
}
