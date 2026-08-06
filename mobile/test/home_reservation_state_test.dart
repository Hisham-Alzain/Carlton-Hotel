import 'package:carlton/models/folio.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/views/home/home_view.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

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

  // ── Pre-arrival section selection ──
  // Guards the three-way selector. Reverting it would send a pre-arrival guest
  // to the in-house dashboard, which shows a bill they have not incurred.
  group('home section selection', () {
    setUp(() {
      Get.reset();
      Get.put(CheckInService());
    });

    tearDown(Get.reset);

    test('pre-arrival guest gets the pre-arrival section list', () {
      expect(CheckInService.find.isPreArrival.value, isTrue);
      expect(
        HomeView.sectionsFor(hasReservation: true, isPreArrival: true),
        same(HomeView.preArrivalSections),
      );
    });

    test('checked-in guest falls back to the reservation list', () {
      expect(
        HomeView.sectionsFor(hasReservation: true, isPreArrival: false),
        same(HomeView.reservationSections),
      );
    });

    test('no reservation still gets the explore list', () {
      expect(
        HomeView.sectionsFor(hasReservation: false, isPreArrival: false),
        same(HomeView.exploreSections),
      );
      expect(
        HomeView.sectionsFor(hasReservation: false, isPreArrival: true),
        same(HomeView.exploreSections),
      );
    });

    test('completeCheckIn flips Home out of the pre-arrival list', () {
      final service = CheckInService.find;
      service.completeCheckIn();
      expect(
        HomeView.sectionsFor(
          hasReservation: true,
          isPreArrival: service.isPreArrival.value,
        ),
        same(HomeView.reservationSections),
      );
    });
  });
}
