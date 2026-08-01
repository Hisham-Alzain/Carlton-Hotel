import 'package:carlton/models/folio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the folio (running-bill) DTO that now backs the Home active-booking
/// dashboard's bill (`GET /folio`) — the demo bill was retired. Hermetic: no
/// HTTP. Money fields are decimal strings.
void main() {
  test('Folio.fromJson parses line items + total', () {
    final folio = Folio.fromJson(<String, dynamic>{
      'uuid': 'f1',
      'status': 'open',
      'subtotal_usd': '560.00',
      'total_usd': '688.00',
      'items': [
        {
          'uuid': 'i1',
          'description': 'Room × 2 nights',
          'amount_usd': '560.00',
          'source_type': 'reservation',
        },
        {
          'uuid': 'i2',
          'description': 'Airport Transfer',
          'amount_usd': '80.00',
          'source_type': 'service_booking',
        },
      ],
    });
    expect(folio.totalUsd, '688.00');
    expect(folio.items, hasLength(2));
    expect(folio.items.first.description, 'Room × 2 nights');
    expect(folio.items.first.amountUsd, '560.00');
  });

  test('Folio.fromJson tolerates a missing items list', () {
    final folio = Folio.fromJson(<String, dynamic>{
      'uuid': 'f2',
      'total_usd': '0',
    });
    expect(folio.items, isEmpty);
    expect(folio.totalUsd, '0');
  });
}
