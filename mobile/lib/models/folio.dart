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
  final List<FolioPayment> payments;
  final String paidUsd;

  /// Signed: negative means the hotel owes the guest (refunded at the desk).
  final String balanceDueUsd;
  final int openDisputesCount;

  const Folio({
    required this.uuid,
    this.reservationUuid,
    this.status = '',
    this.subtotalUsd = '0',
    this.totalUsd = '0',
    this.approvedByGuestAt,
    this.settledAt,
    this.items = const [],
    this.payments = const [],
    this.paidUsd = '0',
    this.balanceDueUsd = '0',
    this.openDisputesCount = 0,
  });

  double get balanceDue => double.tryParse(balanceDueUsd) ?? 0;

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
    payments:
        (json['payments'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(FolioPayment.fromJson)
            .toList() ??
        const [],
    paidUsd: json['paid_usd']?.toString() ?? '0',
    balanceDueUsd: json['balance_due_usd']?.toString() ?? '0',
    openDisputesCount: (json['open_disputes_count'] as num?)?.toInt() ?? 0,
  );

  /// The same folio with [item] swapped in by uuid — a dispute answers with
  /// the updated item only, so the rest of the bill is kept as loaded.
  Folio withItem(FolioItem item) {
    final wasOpen = items.any((i) => i.uuid == item.uuid && i.hasOpenDispute);
    final nowOpen = item.hasOpenDispute;
    return Folio(
      uuid: uuid,
      reservationUuid: reservationUuid,
      status: status,
      subtotalUsd: subtotalUsd,
      totalUsd: totalUsd,
      approvedByGuestAt: approvedByGuestAt,
      settledAt: settledAt,
      items: [for (final i in items) i.uuid == item.uuid ? item : i],
      payments: payments,
      paidUsd: paidUsd,
      balanceDueUsd: balanceDueUsd,
      openDisputesCount: openDisputesCount + (nowOpen && !wasOpen ? 1 : 0),
    );
  }
}

class FolioItem {
  final String uuid;
  final String description; // PLAIN string, not {en, ar}
  final String amountUsd;

  /// 'reservation' | 'service_booking' | 'service_request' | 'manual' |
  /// 'credit' (a negative line the hotel adds, e.g. after a dispute).
  final String sourceType;
  final int quantity;
  final String? unitPriceUsd;
  final String? postedByName;
  final DateTime? postedAt;
  final String? reason;

  /// Set on a line that reverses another one.
  final String? reversesItemUuid;
  final FolioDispute? dispute;

  const FolioItem({
    required this.uuid,
    this.description = '',
    this.amountUsd = '0',
    this.sourceType = '',
    this.quantity = 1,
    this.unitPriceUsd,
    this.postedByName,
    this.postedAt,
    this.reason,
    this.reversesItemUuid,
    this.dispute,
  });

  /// A dispute the hotel has not decided yet. After a decision the guest may
  /// dispute the same line again.
  bool get hasOpenDispute => dispute?.isOpen ?? false;

  factory FolioItem.fromJson(Map<String, dynamic> json) {
    final postedBy = json['posted_by'];
    final dispute = json['dispute'];
    return FolioItem(
      uuid: json['uuid'] as String? ?? '',
      description: json['description'] as String? ?? '',
      amountUsd: json['amount_usd']?.toString() ?? '0',
      sourceType: json['source_type'] as String? ?? '',
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      unitPriceUsd: json['unit_price_usd']?.toString(),
      postedByName: postedBy is Map ? postedBy['name'] as String? : null,
      postedAt: _date(json['posted_at']),
      reason: json['reason'] as String?,
      reversesItemUuid: json['reverses_item_uuid'] as String?,
      dispute: dispute is Map<String, dynamic>
          ? FolioDispute.fromJson(dispute)
          : null,
    );
  }
}

/// A guest's dispute on one folio line. Disputes never change amounts; if the
/// hotel agrees it adds a `credit` line.
class FolioDispute {
  final String uuid;
  final String status; // 'open' | 'resolved' | 'rejected'
  final String reason;
  final DateTime? raisedAt;
  final DateTime? resolvedAt;
  final String? resolutionNote;

  const FolioDispute({
    required this.uuid,
    this.status = '',
    this.reason = '',
    this.raisedAt,
    this.resolvedAt,
    this.resolutionNote,
  });

  bool get isOpen => status == 'open';

  factory FolioDispute.fromJson(Map<String, dynamic> json) => FolioDispute(
    uuid: json['uuid'] as String? ?? '',
    status: json['status'] as String? ?? '',
    reason: json['reason'] as String? ?? '',
    raisedAt: _date(json['raised_at']),
    resolvedAt: _date(json['resolved_at']),
    resolutionNote: json['resolution_note'] as String?,
  );
}

class FolioPayment {
  final String uuid;
  final String method;
  final String amountUsd;
  final String status;
  final DateTime? createdAt;

  const FolioPayment({
    required this.uuid,
    this.method = '',
    this.amountUsd = '0',
    this.status = '',
    this.createdAt,
  });

  factory FolioPayment.fromJson(Map<String, dynamic> json) => FolioPayment(
    uuid: json['uuid'] as String? ?? '',
    method: json['method'] as String? ?? '',
    amountUsd: json['amount_usd']?.toString() ?? '0',
    status: json['status'] as String? ?? '',
    createdAt: _date(json['created_at']),
  );
}
