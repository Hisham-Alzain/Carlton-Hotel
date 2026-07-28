/// Folio DTO — `GET /api/folio` (current bill) and the response of
/// `POST /api/folio/approve` (express checkout, with `approved_by_guest_at`
/// set). Tier-3b. Money fields are decimal strings; item descriptions are plain
/// strings (not `{en, ar}`).
DateTime? _date(dynamic v) =>
    v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

class Folio {
  final String uuid;
  final String? reservationUuid; // whenLoaded — may be absent
  final String status; // 'open' | 'settled'
  final String subtotalUsd;
  final String totalUsd;
  final DateTime? approvedByGuestAt;
  final DateTime? settledAt;
  final List<FolioItem> items;

  const Folio({
    required this.uuid,
    this.reservationUuid,
    this.status = '',
    this.subtotalUsd = '0',
    this.totalUsd = '0',
    this.approvedByGuestAt,
    this.settledAt,
    this.items = const [],
  });

  factory Folio.fromJson(Map<String, dynamic> json) => Folio(
    uuid: json['uuid'] as String? ?? '',
    reservationUuid: json['reservation_uuid'] as String?,
    status: json['status'] as String? ?? '',
    subtotalUsd: json['subtotal_usd']?.toString() ?? '0',
    totalUsd: json['total_usd']?.toString() ?? '0',
    approvedByGuestAt: _date(json['approved_by_guest_at']),
    settledAt: _date(json['settled_at']),
    items:
        (json['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(FolioItem.fromJson)
            .toList() ??
        const [],
  );
}

class FolioItem {
  final String uuid;
  final String description; // PLAIN string, not {en, ar}
  final String amountUsd;
  final String sourceType; // 'reservation' | 'service_booking'

  const FolioItem({
    required this.uuid,
    this.description = '',
    this.amountUsd = '0',
    this.sourceType = '',
  });

  factory FolioItem.fromJson(Map<String, dynamic> json) => FolioItem(
    uuid: json['uuid'] as String? ?? '',
    description: json['description'] as String? ?? '',
    amountUsd: json['amount_usd']?.toString() ?? '0',
    sourceType: json['source_type'] as String? ?? '',
  );
}
