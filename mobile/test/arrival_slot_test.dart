import 'package:carlton/models/check_in/arrival_slot.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('tryFromHour maps a slot hour to its slot', () {
    expect(ArrivalSlot.tryFromHour(14), ArrivalSlot.twoPm);
    expect(ArrivalSlot.tryFromHour(22), ArrivalSlot.afterTenPm);
  });

  test('tryFromHour returns null for an hour with no slot', () {
    // The server accepts any HH:mm, e.g. "17:00" or "09:30".
    expect(ArrivalSlot.tryFromHour(17), isNull);
    expect(ArrivalSlot.tryFromHour(9), isNull);
    expect(ArrivalSlot.tryFromHour(null), isNull);
  });
}
