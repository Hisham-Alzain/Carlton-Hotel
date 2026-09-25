import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/quote.dart';
import 'package:carlton/models/reservation.dart';
import 'package:flutter_test/flutter_test.dart';

/// Phase 3 — guards the booking data layer + controller display logic after the
/// client-side tax/promo engine was replaced by `GET /public/quote` +
/// `POST /reservations`. Hermetic: no HTTP, no GetStorage. Each assertion would
/// fail if the corresponding wiring were reverted.
void main() {
  group('Quote.fromJson', () {
    test('parses the priced breakdown (total already nets the discount)', () {
      final q = Quote.fromJson(<String, dynamic>{
        'daily_rate_usd': 150.0,
        'nights': 2,
        'subtotal_usd': 300.0,
        'discount_usd': 30.0,
        'total_usd': 270.0,
        'promo_code_id': 5,
        'rules_applied': 1,
      });
      expect(q.subtotalUsd, 300.0);
      expect(q.discountUsd, 30.0);
      expect(q.totalUsd, 270.0);
      expect(q.hasPromo, isTrue);
    });

    test('hasPromo needs both a promo id AND a real discount', () {
      expect(
        Quote.fromJson(<String, dynamic>{
          'subtotal_usd': 300,
          'total_usd': 300,
        }).hasPromo,
        isFalse,
      );
      expect(
        Quote.fromJson(<String, dynamic>{
          'promo_code_id': 5,
          'discount_usd': 0,
          'total_usd': 300,
        }).hasPromo,
        isFalse,
      );
    });
  });

  group('Reservation.fromJson', () {
    test('parses the POST /reservations shape', () {
      final r = Reservation.fromJson(<String, dynamic>{
        'uuid': 'r1',
        'booking_code': 'CARL-ABCD1234',
        'status': 'pending',
        'check_in': '2026-07-20',
        'check_out': '2026-07-22',
        'nights': 2,
        'source': 'direct',
        'payment_method': 'on_arrival',
        'total_usd': '270.00',
        'hold_expires_at': null,
      });
      expect(r.bookingCode, 'CARL-ABCD1234');
      expect(r.nights, 2);
      expect(r.checkIn, DateTime(2026, 7, 20));
      expect(r.totalUsd, '270.00');
    });

    test('isCancellable is true only before check-in', () {
      Reservation res(String status) =>
          Reservation(uuid: 'x', bookingCode: 'c', status: status);
      expect(res('pending').isCancellable, isTrue);
      expect(res('confirmed').isCancellable, isTrue);
      expect(res('checked_in').isCancellable, isFalse);
      expect(res('cancelled').isCancellable, isFalse);
    });
  });

  group('payment mapping (decision #1)', () {
    test('only Pay at Hotel is submittable, mapping to on_arrival', () {
      final c = BookingFlowController();
      c.paymentMethod.value = PaymentMethod.payAtHotel;
      expect(c.paymentApiValue, 'on_arrival');
      expect(c.isPaymentSubmittable, isTrue);

      for (final m in [
        PaymentMethod.card,
        PaymentMethod.applePay,
        PaymentMethod.googlePay,
      ]) {
        c.paymentMethod.value = m;
        expect(c.paymentApiValue, isNull, reason: '$m has no gateway yet');
        expect(c.isPaymentSubmittable, isFalse);
      }
    });
  });

  group('price display', () {
    test('money strips a trailing .00 but keeps real cents', () {
      final c = BookingFlowController();
      expect(c.money(270.0), r'$270');
      expect(c.money(270.5), r'$270.50');
    });

    test('total falls back to the room estimate before a quote arrives', () {
      final c = BookingFlowController();
      c.selectedRoom.value = const RoomOption(
        id: 'x',
        name: 'Suite',
        images: [],
        area: '',
        view: '',
        bed: '',
        rating: 5,
        reviewCount: 0,
        pricePerNight: 200,
        amenityChips: [],
        highlights: [],
        amenities: [],
        description: '',
      );
      // reset-state dates are today → today+1 (1 night); no quote yet.
      expect(c.quote.value, isNull);
      expect(c.totalDisplay, r'$200');
    });
  });
}
