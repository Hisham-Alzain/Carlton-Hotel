/// Receipt DTO — `GET /api/stays/{uuid}/receipt`. Read-only bill for any of
/// your stays that has a folio. Mapped at the controller boundary into the
/// existing `ReceiptData` view model so `ReceiptSheet` stays unchanged.
///
/// `items[].description` is a PLAIN string (renders as recorded regardless of
/// locale); `guest_name` is a single pre-joined string. The receipt carries no
/// room name — the sheet header's room name comes from the tapped `PastStay`.
DateTime? _date(dynamic v) =>
    v is String && v.isNotEmpty ? DateTime.tryParse(v) : null;

class Receipt {
  final ReceiptReservation reservation;
  final ReceiptFolio folio;
  final List<ReceiptItem> items;
  final List<ReceiptPayment> payments;
  final String balanceDueUsd;

  const Receipt({
    required this.reservation,
    required this.folio,
    this.items = const [],
    this.payments = const [],
    this.balanceDueUsd = '0',
  });

  factory Receipt.fromJson(Map<String, dynamic> json) => Receipt(
    reservation: ReceiptReservation.fromJson(
      (json['reservation'] as Map?)?.cast<String, dynamic>() ?? const {},
    ),
    folio: ReceiptFolio.fromJson(
      (json['folio'] as Map?)?.cast<String, dynamic>() ?? const {},
    ),
    items:
        (json['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(ReceiptItem.fromJson)
            .toList() ??
        const [],
    payments:
        (json['payments'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(ReceiptPayment.fromJson)
            .toList() ??
        const [],
    balanceDueUsd: json['balance_due_usd']?.toString() ?? '0',
  );
}

class ReceiptReservation {
  final String uuid;
  final String bookingCode;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final int nights;
  final String guestName;

  const ReceiptReservation({
    this.uuid = '',
    this.bookingCode = '',
    this.checkIn,
    this.checkOut,
    this.nights = 0,
    this.guestName = '',
  });

  factory ReceiptReservation.fromJson(Map<String, dynamic> json) =>
      ReceiptReservation(
        uuid: json['uuid'] as String? ?? '',
        bookingCode: json['booking_code'] as String? ?? '',
        checkIn: _date(json['check_in']),
        checkOut: _date(json['check_out']),
        nights: (json['nights'] as num?)?.toInt() ?? 0,
        guestName: json['guest_name'] as String? ?? '',
      );
}

class ReceiptFolio {
  final String uuid;
  final String status;
  final String subtotalUsd;
  final String totalUsd;
  final DateTime? approvedByGuestAt;
  final DateTime? settledAt;

  const ReceiptFolio({
    this.uuid = '',
    this.status = '',
    this.subtotalUsd = '0',
    this.totalUsd = '0',
    this.approvedByGuestAt,
    this.settledAt,
  });

  factory ReceiptFolio.fromJson(Map<String, dynamic> json) => ReceiptFolio(
    uuid: json['uuid'] as String? ?? '',
    status: json['status'] as String? ?? '',
    subtotalUsd: json['subtotal_usd']?.toString() ?? '0',
    totalUsd: json['total_usd']?.toString() ?? '0',
    approvedByGuestAt: _date(json['approved_by_guest_at']),
    settledAt: _date(json['settled_at']),
  );
}

class ReceiptItem {
  final String description; // PLAIN string
  final String amountUsd;
  final String sourceType;

  const ReceiptItem({
    this.description = '',
    this.amountUsd = '0',
    this.sourceType = '',
  });

  factory ReceiptItem.fromJson(Map<String, dynamic> json) => ReceiptItem(
    description: json['description'] as String? ?? '',
    amountUsd: json['amount_usd']?.toString() ?? '0',
    sourceType: json['source_type'] as String? ?? '',
  );
}

class ReceiptPayment {
  final String method;
  final String amountUsd;
  final String status;
  final DateTime? createdAt;

  const ReceiptPayment({
    this.method = '',
    this.amountUsd = '0',
    this.status = '',
    this.createdAt,
  });

  factory ReceiptPayment.fromJson(Map<String, dynamic> json) => ReceiptPayment(
    method: json['method'] as String? ?? '',
    amountUsd: json['amount_usd']?.toString() ?? '0',
    status: json['status'] as String? ?? '',
    createdAt: _date(json['created_at']),
  );
}
