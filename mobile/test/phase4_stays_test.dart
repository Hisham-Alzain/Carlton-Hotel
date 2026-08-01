import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/models/receipt.dart';
import 'package:carlton/models/stay.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phase 4 — guards the Stays/Folio DTO parsing + the pure controller helpers
/// that back My Stays. All hermetic (pure JSON→DTO factories and static pure
/// functions — no `SettingsService`/`GetStorage`/HTTP). `Bilingual.value` locale
/// resolution and the DTO→view-model mappers are verified on-device.
void main() {
  group('ActiveStay.fromJson', () {
    test('full parse of an eager-loaded, checked-in stay', () {
      final s = ActiveStay.fromJson(<String, dynamic>{
        'uuid': 'res-1',
        'booking_code': 'CARL-ABC123',
        'status': 'checked_in',
        'room_number': '812',
        'room_name': {'en': 'Grand Damascus Suite', 'ar': 'جناح دمشق الكبير'},
        'check_in': '2026-08-14',
        'check_out': '2026-08-16',
        'checked_in_at': '2026-08-14T15:00:00Z',
        'nights': 2,
        'nights_remaining': 1,
        'dnd': {'enabled': true, 'until': '2026-08-15T23:59:00Z'},
        'folio_total_usd': '560.00',
      });
      expect(s.uuid, 'res-1');
      expect(s.bookingCode, 'CARL-ABC123');
      expect(s.roomNumber, '812');
      expect(s.roomName.en, 'Grand Damascus Suite');
      expect(s.roomName.ar, 'جناح دمشق الكبير');
      expect(s.nights, 2);
      expect(s.nightsRemaining, 1);
      expect(s.dndEnabled, isTrue);
      expect(s.dndUntil, isNotNull);
      expect(s.checkedInAt, isNotNull);
      expect(s.folioTotalUsd, '560.00');
    });

    test('tolerates absent whenLoaded keys and dnd.until null', () {
      // room_name / room_number / folio_total_usd absent (relation/folio not
      // loaded); dnd present but until null (guest never set an expiry).
      final s = ActiveStay.fromJson(<String, dynamic>{
        'uuid': 'res-2',
        'booking_code': 'CARL-NOEAGER',
        'status': 'checked_in',
        'check_in': '2026-09-01',
        'check_out': '2026-09-03',
        'checked_in_at': null,
        'nights': 2,
        'nights_remaining': 2,
        'dnd': {'enabled': false, 'until': null},
      });
      expect(s.roomNumber, isNull);
      expect(s.roomName.en, ''); // absent → Bilingual('','')
      expect(s.roomName.ar, '');
      expect(s.folioTotalUsd, isNull);
      expect(s.dndEnabled, isFalse);
      expect(s.dndUntil, isNull);
      expect(s.checkedInAt, isNull); // legacy → mapper falls back to check_in
    });
  });

  group('UpcomingStay.fromJson', () {
    test('price_usd stays a string, is_cancellable is a bool, dates parse', () {
      final list = UpcomingStay.listFromJson([
        <String, dynamic>{
          'uuid': 'res-3',
          'booking_code': 'CARL-UP1',
          'status': 'confirmed',
          'room_number': '801',
          'room_name': {'en': 'Deluxe', 'ar': 'ديلوكس'},
          'price_usd': '720.00',
          'check_in': '2026-10-05',
          'check_out': '2026-10-08',
          'nights': 3,
          'is_cancellable': true,
        },
      ]);
      expect(list, hasLength(1));
      final s = list.first;
      expect(s.priceUsd, '720.00'); // NOT coerced to a number
      expect(s.priceUsd, isA<String>());
      expect(s.isCancellable, isTrue);
      expect(s.nights, 3);
      expect(s.checkIn, isNotNull);
      expect(s.bookingCode, 'CARL-UP1');
    });

    test('is_cancellable defaults to false when absent', () {
      final s = UpcomingStay.fromJson(<String, dynamic>{
        'uuid': 'res-4',
        'booking_code': 'CARL-UP2',
      });
      expect(s.isCancellable, isFalse);
      expect(s.priceUsd, '0');
    });
  });

  group('PastStay.fromJson + statusLabel', () {
    test('checked_out parses and labels as Completed', () {
      final s = PastStay.fromJson(<String, dynamic>{
        'uuid': 'res-5',
        'booking_code': 'CARL-PAST1',
        'room_name': {'en': 'Suite', 'ar': 'جناح'},
        'total_nights': 2,
        'check_in': '2026-07-08',
        'check_out': '2026-07-10',
        'checked_out_at': '2026-07-10T11:00:00Z',
        'total_charge_usd': '596.00',
        'status': 'checked_out',
        'has_receipt': true,
        'room_type_uuid': 'rt-9',
      });
      expect(s.statusLabel, 'Completed');
      expect(s.hasReceipt, isTrue);
      expect(s.roomTypeUuid, 'rt-9');
      expect(s.totalNights, 2);
      expect(s.totalChargeUsd, '596.00');
    });

    test('cancelled labels as Cancelled; checked_out_at null tolerated', () {
      final s = PastStay.fromJson(<String, dynamic>{
        'uuid': 'res-6',
        'booking_code': 'CARL-PAST2',
        'room_name': {'en': 'Room', 'ar': 'غرفة'},
        'total_nights': 1,
        'status': 'cancelled',
        'has_receipt': false,
      });
      expect(s.statusLabel, 'Cancelled');
      expect(s.hasReceipt, isFalse);
      expect(s.checkedOutAt, isNull);
      expect(s.roomTypeUuid, isNull);
    });
  });

  group('Folio.fromJson', () {
    test('parses status, money strings and the items list', () {
      final f = Folio.fromJson(<String, dynamic>{
        'uuid': 'folio-1',
        'reservation_uuid': 'res-1',
        'status': 'open',
        'subtotal_usd': '380.00',
        'total_usd': '380.00',
        'approved_by_guest_at': null,
        'settled_at': null,
        'items': [
          {
            'uuid': 'fi-1',
            'description': 'Room charge',
            'amount_usd': '300.00',
            'source_type': 'reservation',
          },
          {
            'uuid': 'fi-2',
            'description': 'Pool Cabana',
            'amount_usd': '80.00',
            'source_type': 'service_booking',
          },
        ],
      });
      expect(f.status, 'open');
      expect(f.subtotalUsd, '380.00');
      expect(f.totalUsd, '380.00');
      expect(f.approvedByGuestAt, isNull);
      expect(f.items, hasLength(2));
      expect(f.items.first.description, 'Room charge');
      expect(f.items.first.sourceType, 'reservation');
      expect(f.items[1].amountUsd, '80.00');
    });

    test('tolerates a missing items array', () {
      final f = Folio.fromJson(<String, dynamic>{
        'uuid': 'folio-2',
        'status': 'settled',
      });
      expect(f.items, isEmpty);
      expect(f.reservationUuid, isNull);
    });
  });

  group('Receipt.fromJson', () {
    test('parses nested reservation/folio/items(plain)/payments/balance', () {
      final r = Receipt.fromJson(<String, dynamic>{
        'reservation': {
          'uuid': 'res-1',
          'booking_code': 'CARL-R1',
          'check_in': '2026-07-08',
          'check_out': '2026-07-10',
          'nights': 2,
          'guest_name': 'Mohamed Majzoub',
        },
        'folio': {
          'uuid': 'folio-1',
          'status': 'settled',
          'subtotal_usd': '489.00',
          'total_usd': '596.00',
          'approved_by_guest_at': '2026-07-10T10:00:00Z',
          'settled_at': '2026-07-10T11:00:00Z',
        },
        'items': [
          {
            'description': 'Room charge',
            'amount_usd': '417.00',
            'source_type': 'reservation',
          },
        ],
        'payments': [
          {
            'method': 'cash',
            'amount_usd': '596.00',
            'status': 'completed',
            'created_at': '2026-07-10T11:00:00Z',
          },
        ],
        'balance_due_usd': 0,
      });
      expect(r.reservation.bookingCode, 'CARL-R1');
      expect(r.reservation.guestName, 'Mohamed Majzoub');
      expect(r.folio.totalUsd, '596.00');
      expect(r.items, hasLength(1));
      expect(r.items.first.description, 'Room charge'); // plain string
      expect(r.payments, hasLength(1));
      expect(r.payments.first.method, 'cash');
      expect(r.balanceDueUsd, '0'); // numeric 0 coerced to string
    });

    test('tolerates empty payments and missing nested objects', () {
      final r = Receipt.fromJson(<String, dynamic>{
        'reservation': {'uuid': 'res-x', 'booking_code': 'CARL-RX'},
        'folio': {'uuid': 'folio-x', 'total_usd': '120.00'},
        'items': [],
        'balance_due_usd': '120.00',
      });
      expect(r.payments, isEmpty);
      expect(r.items, isEmpty);
      expect(r.folio.totalUsd, '120.00');
      expect(r.balanceDueUsd, '120.00');
    });
  });

  group('StaysController pure helpers', () {
    test('usd strips a trailing .00 but keeps real cents', () {
      expect(StaysController.usd('380.00'), r'$380');
      expect(StaysController.usd('80.50'), r'$80.50');
      expect(StaysController.usd('0.00'), r'$0');
      expect(StaysController.usd(null), r'$0');
      expect(StaysController.usd(''), r'$0');
    });

    test('cancelErrorMessage: reservation_state gets specific copy', () {
      final specific = StaysController.cancelErrorMessage(
        ErrorCodes.reservationState,
      );
      expect(specific, contains('can no longer be cancelled'));
    });

    test('cancelErrorMessage: any other code falls back to generic', () {
      final generic = StaysController.cancelErrorMessage(ErrorCodes.notFound);
      expect(generic, isNot(contains('can no longer be cancelled')));
      final nullCase = StaysController.cancelErrorMessage(null);
      expect(nullCase, isNot(contains('can no longer be cancelled')));
    });
  });
}
