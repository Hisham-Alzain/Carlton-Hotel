import 'package:carlton/controllers/services/airport_transfer_controller.dart';
import 'package:carlton/models/localized.dart';
import 'package:carlton/models/transfer.dart';
import 'package:flutter_test/flutter_test.dart';

/// Covers the transfer flow's decisions — the ones that fail silently rather
/// than throwing: step navigation, the seat-capacity gate, and parsing a
/// `TransferResource` payload that is missing the fields the design assumes.
void main() {
  group('Transfer.fromJson', () {
    test('parses what TransferResource actually sends today', () {
      final t = Transfer.fromJson({
        'uuid': 'abc',
        'name': {'en': 'Airport Transfer (Sedan)', 'ar': 'نقل المطار'},
        'price_usd': '35.00',
        'is_active': true,
      });

      expect(t.uuid, 'abc');
      expect(t.priceUsd, '35.00');
      // The resource sends neither, and the card must cope rather than render
      // a placeholder that looks like real vehicle data.
      expect(t.description, isNull);
      expect(t.maxPassengers, isNull);
    });

    test('picks up description and capacity once the API sends them', () {
      final t = Transfer.fromJson({
        'uuid': 'x',
        'name': {'en': 'Business'},
        'price_usd': '65.00',
        'description': {'en': 'BMW 5-Series or similar'},
        'max_passengers': 3,
      });

      expect(t.description!.values['en'], 'BMW 5-Series or similar');
      expect(t.maxPassengers, 3);
    });

    test('a non-list payload yields an empty catalogue, not a crash', () {
      expect(Transfer.listFromJson(null), isEmpty);
      expect(Transfer.listFromJson({'unexpected': 'shape'}), isEmpty);
    });

    test('price_usd survives arriving as a number instead of a string', () {
      expect(
        Transfer.fromJson({'uuid': 'n', 'name': 'X', 'price_usd': 45}).priceUsd,
        '45',
      );
    });
  });

  group('capacity gate', () {
    late AirportTransferController c;

    Transfer seating(int? seats) => Transfer(
      uuid: 's$seats',
      name: const Localized({'en': 'Car'}),
      priceUsd: '50',
      maxPassengers: seats,
    );

    setUp(() => c = AirportTransferController());
    tearDown(() => c.onClose());

    test('a vehicle with no declared capacity is always offered', () {
      c.passengers.value = 7;
      // Every seeded transfer is in this state today — gating them out would
      // leave step 2 permanently empty.
      expect(c.seatsParty(seating(null)), isTrue);
    });

    test('a vehicle too small for the party is not offered', () {
      c.passengers.value = 5;
      expect(c.seatsParty(seating(3)), isFalse);
      expect(c.seatsParty(seating(5)), isTrue);
      expect(c.seatsParty(seating(7)), isTrue);
    });

    test('raising the party size clears a car that no longer fits', () {
      final small = seating(2);
      c.selectTransfer(small);
      expect(c.selected.value, small);

      c.setPassengers(4);
      expect(
        c.selected.value,
        isNull,
        reason: 'a 2-seater must not survive into the summary for 4 guests',
      );
    });

    test('raising the party size keeps a car that still fits', () {
      final big = seating(7);
      c.selectTransfer(big);
      c.setPassengers(4);
      expect(c.selected.value, big);
    });
  });

  group('navigation', () {
    late AirportTransferController c;

    setUp(() => c = AirportTransferController());
    tearDown(() => c.onClose());

    test('starts on flight details', () => expect(c.step.value, 0));

    test('back from the first step stays put rather than closing', () {
      c.back();
      expect(c.step.value, 0);
    });

    test('back walks the steps down one at a time', () {
      c.goTo(2);
      c.back();
      expect(c.step.value, 1);
      c.back();
      expect(c.step.value, 0);
    });

    test('confirm is gated until a vehicle is chosen', () {
      expect(c.canConfirm, isFalse);
      c.selectTransfer(
        const Transfer(
          uuid: 'v',
          name: Localized({'en': 'Car'}),
          priceUsd: '50',
        ),
      );
      expect(c.canConfirm, isTrue);
    });

    test('terminal index maps to the airport signage label', () {
      expect(c.terminal, 'T1');
      c.setTerminal(2);
      expect(c.terminal, 'T3');
    });
  });
}
