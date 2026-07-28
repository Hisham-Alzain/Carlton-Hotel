import 'package:carlton/models/bilingual.dart';

/// The three stay-projection DTOs behind the My Stays tabs. Each is a genuinely
/// different response shape (guide "Module: Stays"), so they are three classes
/// rather than one nullable-heavy model. `StaysController` maps each into the
/// existing `Stay` view model at the controller boundary, so the cards stay
/// unchanged.
///
/// All three carry `uuid` + `booking_code` (confirmed present on the real
/// resources even though the guide field tables omit them) — `uuid` drives
/// cancel (`DELETE /reservations/{uuid}`) and receipt
/// (`GET /stays/{uuid}/receipt`).
DateTime? _date(dynamic v) =>
    v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

/// `GET /api/stays/active` — a single object, or `null` when not checked in.
///
/// `room_name` / `room_number` / `folio_total_usd` are whenLoaded-conditional:
/// the key can be **absent** (not merely null) when the relation/folio isn't
/// loaded yet, so `fromJson` uses null-safe access throughout.
class ActiveStay {
  final String uuid;
  final String bookingCode;
  final String status;
  final String? roomNumber; // whenLoaded — may be absent
  final Bilingual roomName; // whenLoaded — absent → Bilingual('','')
  final DateTime? checkIn;
  final DateTime? checkOut;
  final DateTime? checkedInAt; // null for legacy stays → fall back to checkIn
  final int nights;
  final int nightsRemaining;
  final bool dndEnabled;
  final DateTime? dndUntil;
  final String?
  folioTotalUsd; // decimal string; null/absent until staff bill it

  const ActiveStay({
    required this.uuid,
    required this.bookingCode,
    required this.status,
    this.roomNumber,
    this.roomName = const Bilingual(en: '', ar: ''),
    this.checkIn,
    this.checkOut,
    this.checkedInAt,
    this.nights = 0,
    this.nightsRemaining = 0,
    this.dndEnabled = false,
    this.dndUntil,
    this.folioTotalUsd,
  });

  factory ActiveStay.fromJson(Map<String, dynamic> json) {
    final dnd = json['dnd'];
    return ActiveStay(
      uuid: json['uuid'] as String? ?? '',
      bookingCode: json['booking_code'] as String? ?? '',
      status: json['status'] as String? ?? '',
      roomNumber: json['room_number']?.toString(),
      roomName: Bilingual.fromJson(json['room_name']),
      checkIn: _date(json['check_in']),
      checkOut: _date(json['check_out']),
      checkedInAt: _date(json['checked_in_at']),
      nights: (json['nights'] as num?)?.toInt() ?? 0,
      nightsRemaining: (json['nights_remaining'] as num?)?.toInt() ?? 0,
      dndEnabled: dnd is Map ? (dnd['enabled'] as bool? ?? false) : false,
      dndUntil: dnd is Map ? _date(dnd['until']) : null,
      folioTotalUsd: json['folio_total_usd']?.toString(),
    );
  }
}

/// `GET /api/stays/upcoming` — a plain (non-paginated) array, soonest first.
class UpcomingStay {
  final String uuid;
  final String bookingCode;
  final String status;
  final String? roomNumber;
  final Bilingual roomName;
  final String priceUsd; // decimal string = reservation total
  final DateTime? checkIn;
  final DateTime? checkOut;
  final int nights;
  final bool isCancellable;

  const UpcomingStay({
    required this.uuid,
    required this.bookingCode,
    this.status = '',
    this.roomNumber,
    this.roomName = const Bilingual(en: '', ar: ''),
    this.priceUsd = '0',
    this.checkIn,
    this.checkOut,
    this.nights = 0,
    this.isCancellable = false,
  });

  factory UpcomingStay.fromJson(Map<String, dynamic> json) => UpcomingStay(
    uuid: json['uuid'] as String? ?? '',
    bookingCode: json['booking_code'] as String? ?? '',
    status: json['status'] as String? ?? '',
    roomNumber: json['room_number']?.toString(),
    roomName: Bilingual.fromJson(json['room_name']),
    priceUsd: json['price_usd']?.toString() ?? '0',
    checkIn: _date(json['check_in']),
    checkOut: _date(json['check_out']),
    nights: (json['nights'] as num?)?.toInt() ?? 0,
    isCancellable: json['is_cancellable'] as bool? ?? false,
  );

  /// Parses the plain `GET /stays/upcoming` array (already unwrapped).
  static List<UpcomingStay> listFromJson(dynamic data) => data is List
      ? data
            .whereType<Map<String, dynamic>>()
            .map(UpcomingStay.fromJson)
            .toList()
      : const [];
}

/// `GET /api/stays/past` — paginated (`data.items` + `data.meta`), most recent
/// checkout first, includes cancelled stays.
class PastStay {
  final String uuid;
  final String bookingCode;
  final Bilingual roomName;
  final int totalNights;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final DateTime? checkedOutAt; // null for older stays
  final String totalChargeUsd; // folio total if any, else reservation total
  final String status; // raw: 'checked_out' | 'cancelled' (no 'complete')
  final bool hasReceipt;
  final String? roomTypeUuid; // powers Book Again

  const PastStay({
    required this.uuid,
    required this.bookingCode,
    this.roomName = const Bilingual(en: '', ar: ''),
    this.totalNights = 0,
    this.checkIn,
    this.checkOut,
    this.checkedOutAt,
    this.totalChargeUsd = '0',
    this.status = '',
    this.hasReceipt = false,
    this.roomTypeUuid,
  });

  /// Client-side label — the backend has no `complete` status.
  String get statusLabel => switch (status) {
    'checked_out' => 'Completed',
    'cancelled' => 'Cancelled',
    _ => status,
  };

  factory PastStay.fromJson(Map<String, dynamic> json) => PastStay(
    uuid: json['uuid'] as String? ?? '',
    bookingCode: json['booking_code'] as String? ?? '',
    roomName: Bilingual.fromJson(json['room_name']),
    totalNights: (json['total_nights'] as num?)?.toInt() ?? 0,
    checkIn: _date(json['check_in']),
    checkOut: _date(json['check_out']),
    checkedOutAt: _date(json['checked_out_at']),
    totalChargeUsd: json['total_charge_usd']?.toString() ?? '0',
    status: json['status'] as String? ?? '',
    hasReceipt: json['has_receipt'] as bool? ?? false,
    roomTypeUuid: json['room_type_uuid'] as String?,
  );

  /// Parses a `GET /stays/past` page (envelope already unwrapped to items).
  static List<PastStay> listFromJson(dynamic data) => data is List
      ? data.whereType<Map<String, dynamic>>().map(PastStay.fromJson).toList()
      : const [];
}
