import 'package:carlton/models/stay.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('reads the server checklist and arrival time', () {
    final stay = UpcomingStay.fromJson({
      'uuid': 'r1',
      'booking_code': 'CARL-1',
      'online_check_in': {'arrival_time': '15:00', 'approval_status': null},
      'pre_arrival_checklist': {
        'complete': false,
        'items': [
          {'key': 'documents_uploaded', 'done': true},
          {'key': 'preferences_set', 'done': true},
          {'key': 'check_in_approved', 'done': false},
        ],
      },
    });
    expect(stay.arrivalTime, '15:00');
    expect(stay.checklistDone, {'documents_uploaded', 'preferences_set'});
  });

  test('a stay without the blocks parses to nothing done', () {
    final stay = UpcomingStay.fromJson({'uuid': 'r1', 'booking_code': 'C'});
    expect(stay.arrivalTime, isNull);
    expect(stay.checklistDone, isEmpty);
  });
}
