import 'package:carlton/models/folio.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, dynamic> _folioJson() => {
  'uuid': 'f1',
  'status': 'open',
  'subtotal_usd': '300.00',
  'total_usd': '280.00',
  'paid_usd': '300.00',
  'balance_due_usd': '-20.00',
  'open_disputes_count': 1,
  'items': [
    {
      'uuid': 'i1',
      'description': 'Minibar',
      'amount_usd': '30.00',
      'source_type': 'manual',
      'quantity': 3,
      'unit_price_usd': '10.00',
      'posted_by': {'uuid': 's1', 'name': 'Desk'},
      'posted_at': '2026-10-01T10:00:00Z',
      'reason': null,
      'reverses_item_uuid': null,
      'dispute': {
        'uuid': 'd1',
        'status': 'open',
        'reason': 'Not mine',
        'raised_by': 'guest',
        'raised_at': '2026-10-02T10:00:00Z',
        'resolved_at': null,
        'resolution_note': null,
      },
    },
    {
      'uuid': 'i2',
      'description': 'Goodwill',
      'amount_usd': '-20.00',
      'source_type': 'credit',
      'reverses_item_uuid': 'i1',
      'dispute': null,
    },
    {'uuid': 'i3', 'description': 'Room', 'amount_usd': '270.00'},
  ],
  'payments': [
    {
      'uuid': 'p1',
      'method': 'cash',
      'amount_usd': '300.00',
      'status': 'completed',
      'note': null,
      'created_at': '2026-10-02T12:00:00Z',
    },
  ],
};

void main() {
  test('parses payments, signed balance and open disputes', () {
    final folio = Folio.fromJson(_folioJson());
    expect(folio.paidUsd, '300.00');
    expect(folio.balanceDue, -20);
    expect(folio.openDisputesCount, 1);
    expect(folio.payments.single.method, 'cash');
  });

  test('parses item detail, dispute and credit lines', () {
    final items = Folio.fromJson(_folioJson()).items;
    expect(items[0].quantity, 3);
    expect(items[0].unitPriceUsd, '10.00');
    expect(items[0].postedByName, 'Desk');
    expect(items[0].hasOpenDispute, isTrue);
    expect(items[1].sourceType, 'credit');
    expect(items[1].reversesItemUuid, 'i1');
    expect(items[1].hasOpenDispute, isFalse);
    // Older rows without the new keys keep safe defaults.
    expect(items[2].quantity, 1);
    expect(items[2].dispute, isNull);
  });

  test('withItem swaps by uuid and counts a newly opened dispute once', () {
    final json = _folioJson();
    final folio = Folio.fromJson(json);
    final disputed = FolioItem.fromJson({
      ...(json['items'] as List)[2] as Map<String, dynamic>,
      'dispute': {'uuid': 'd2', 'status': 'open', 'reason': 'Wrong rate'},
    });
    final updated = folio.withItem(disputed);
    expect(updated.items[2].hasOpenDispute, isTrue);
    expect(updated.items.length, 3);
    expect(updated.openDisputesCount, 2);
    // Re-applying the same open dispute does not count it again.
    expect(updated.withItem(disputed).openDisputesCount, 2);
  });
}
