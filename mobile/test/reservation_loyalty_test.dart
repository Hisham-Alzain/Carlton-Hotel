import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/reservation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

void main() {
  setUpAll(() {
    Get.addTranslations(Local().keys);
    Get.locale = const Locale('en');
  });

  test('a booking without rewards has no loyalty block', () {
    expect(Reservation.fromJson({'uuid': 'r1'}).loyalty, isNull);
    expect(
      Reservation.fromJson({'uuid': 'r1', 'loyalty': null}).loyalty,
      isNull,
    );
  });

  test('parses points, voucher and reversed status', () {
    final r = Reservation.fromJson({
      'uuid': 'r1',
      'loyalty': {
        'points_redeemed': 2000,
        'points_discount_usd': '20.00',
        'voucher': null,
        'voucher_discount_usd': '0.00',
        'upgrade_requested': false,
        'status': 'reversed',
      },
    });
    expect(r.loyalty!.pointsRedeemed, 2000);
    expect(r.loyalty!.isReversed, isTrue);
  });

  test('the upcoming card names what the booking used', () {
    expect(StaysController.rewardsNote(null), isNull);
    expect(
      StaysController.rewardsNote(
        const ReservationLoyalty(
          pointsRedeemed: 2000,
          pointsDiscountUsd: '20.00',
        ),
      ),
      'Paid \$20 with 2000 points',
    );
    expect(
      StaysController.rewardsNote(
        const ReservationLoyalty(
          voucherCode: 'LOY-ABC',
          voucherDiscountUsd: '50.00',
        ),
      ),
      'Voucher LOY-ABC · −\$50',
    );
    // A reversed booking no longer used anything.
    expect(
      StaysController.rewardsNote(
        const ReservationLoyalty(pointsRedeemed: 5, status: 'reversed'),
      ),
      isNull,
    );
  });

  test('a voucher is previewed only once the whole code is typed', () {
    expect(BookingFlowController.isCompleteVoucherCode('ty'), isFalse);
    expect(BookingFlowController.isCompleteVoucherCode('LOY-ABCD'), isFalse);
    expect(BookingFlowController.isCompleteVoucherCode('LOY-ABCD1234'), isTrue);
    expect(
      BookingFlowController.isCompleteVoucherCode('loy abcd 1234'),
      isFalse,
    );
    expect(
      BookingFlowController.isCompleteVoucherCode('LOY-ABCD 1234'),
      isTrue,
    );
  });

  test('the cancel message says what came back', () {
    expect(
      StaysController.cancelledMessage(
        const ReservationLoyalty(pointsRedeemed: 300, status: 'reversed'),
      ),
      'Reservation cancelled. 300 points returned to your balance.',
    );
    expect(
      StaysController.cancelledMessage(
        const ReservationLoyalty(voucherCode: 'LOY-X', status: 'reversed'),
      ),
      'Reservation cancelled. Voucher LOY-X is usable again.',
    );
  });
}
