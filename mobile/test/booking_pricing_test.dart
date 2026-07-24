import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late BookingFlowController c;

  setUp(() {
    c = BookingFlowController();
    c.selectedRoom = DemoData.roomOptions.first;
    c.selectedAddOnIds
      ..clear()
      ..addAll(DemoData.addOns.map((a) => a.id));
  });

  group('checkout pricing', () {
    test('subtotal = room total + add-ons', () {
      expect(c.subtotal, c.roomTotal + c.extrasTotal);
      expect(c.roomTotal, c.selectedRoom!.pricePerNight * c.nights);
    });

    test('taxes are 15% of subtotal, rounded', () {
      expect(c.taxes, (c.subtotal * DemoData.taxRate).round());
    });

    test('grandTotal includes taxes (regression: was subtotal-only)', () {
      // Before this feature grandTotal was just room+extras with no tax/promo,
      // so it equalled the subtotal. It must now be strictly greater.
      expect(c.promoApplied, isFalse);
      expect(c.grandTotal, c.subtotal + c.taxes);
      expect(c.grandTotal, greaterThan(c.subtotal));
    });

    test('promo applies a 10% discount only once flagged', () {
      expect(c.promoDiscount, 0);
      c.promoApplied = true;
      expect(c.promoDiscount, (c.subtotal * DemoData.promoRate).round());
      expect(c.grandTotal, c.subtotal + c.taxes - c.promoDiscount);
    });
  });

  group('checkout labels', () {
    test('confirm CTA varies by payment method', () {
      c.paymentMethod = PaymentMethod.payAtHotel;
      expect(c.confirmCtaLabel, 'Confirm Booking');
      c.paymentMethod = PaymentMethod.card;
      expect(c.confirmCtaLabel, 'Confirm & Pay \$${c.grandTotal}');
    });

    test('payment display masks the card to its last 4 digits', () {
      c.paymentMethod = PaymentMethod.card;
      c.cardNumberCtrl.text = '4111 1111 1111 1234';
      expect(c.paymentMethodDisplay, 'Credit Card ••••1234');
      c.paymentMethod = PaymentMethod.applePay;
      expect(c.paymentMethodDisplay, 'Apple Pay');
    });
  });

  test('confirmation code has the CRS-###-#### shape', () {
    expect(
      DemoData.newConfirmationCode(),
      matches(RegExp(r'^CRS-\d{3}-\d{4}$')),
    );
  });
}
