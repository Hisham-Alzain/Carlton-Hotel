import 'package:carlton/controllers/booking/pre_arrival_documents_controller.dart';
import 'package:carlton/models/pre_arrival_document.dart';
import 'package:carlton/models/service_booking.dart';
import 'package:carlton/models/service_request.dart';
import 'package:flutter_test/flutter_test.dart';

/// Hermetic Phase 5 guards — no GetStorage/backend/SettingsService. Each
/// assertion fails if its Phase 5 change is reverted. Getters that resolve a
/// [Bilingual] via the live locale (`title` with a service_item) are avoided;
/// `.name.en/.ar` are asserted directly instead.
void main() {
  group('ServiceRequest.fromJson', () {
    test('parses the full 201 body with a nested service_item', () {
      final request = ServiceRequest.fromJson({
        'uuid': 'req-1',
        'type': 'room_service',
        'department': 'kitchen',
        'status': 'new',
        'priority': 'normal',
        'notes': 'No nuts please',
        'created_at': '2026-07-28T10:00:00.000000Z',
        'category_code': 'room_service',
        'service_item': {
          'uuid': 'item-1',
          'name': {'en': 'Carlton Breakfast', 'ar': 'فطور كارلتون'},
          'description': {'en': 'Full breakfast', 'ar': 'فطور كامل'},
          'expected_minutes': 30,
          'price_usd': '18.00',
        },
      });

      expect(request.uuid, 'req-1');
      expect(request.type, 'room_service');
      expect(request.department, 'kitchen');
      expect(request.statusCode, 'new');
      expect(request.status, ServiceRequestStatus.requested);
      expect(request.priority, 'normal');
      expect(request.notes, 'No nuts please');
      expect(request.createdAt, '2026-07-28T10:00:00.000000Z');
      expect(request.categoryCode, 'room_service');
      expect(request.serviceItem, isNotNull);
      expect(request.serviceItem!.name.en, 'Carlton Breakfast');
      expect(request.serviceItem!.name.ar, 'فطور كارلتون');
      expect(request.serviceItem!.expectedMinutes, 30);
      expect(request.serviceItem!.priceUsd, '18.00');
      // `detail` off expected_minutes stays hermetic (no Bilingual.value).
      expect(request.detail, '~30 min');
    });

    test(
      'parses a legacy free-string body (service_item/category_code null)',
      () {
        final request = ServiceRequest.fromJson({
          'uuid': 'req-2',
          'type': 'wake_up_call',
          'department': 'concierge',
          'status': 'in_progress',
          'priority': 'high',
          'notes': null,
          'created_at': '2026-07-28T11:00:00.000000Z',
          'category_code': null,
          'service_item': null,
        });

        expect(request.serviceItem, isNull);
        expect(request.categoryCode, isNull);
        expect(request.status, ServiceRequestStatus.inProgress);
        // No service_item → title humanizes the raw type (hermetic).
        expect(request.title, 'Wake Up Call');
      },
    );
  });

  group('ServiceRequestStatus.fromApi', () {
    test('maps every backend status, unknown → requested', () {
      expect(
        ServiceRequestStatus.fromApi('new'),
        ServiceRequestStatus.requested,
      );
      expect(
        ServiceRequestStatus.fromApi('in_progress'),
        ServiceRequestStatus.inProgress,
      );
      expect(
        ServiceRequestStatus.fromApi('completed'),
        ServiceRequestStatus.completed,
      );
      expect(
        ServiceRequestStatus.fromApi('cancelled'),
        ServiceRequestStatus.cancelled,
      );
      expect(
        ServiceRequestStatus.fromApi('something_new'),
        ServiceRequestStatus.requested,
      );
    });
  });

  group('ServiceBooking.fromJson', () {
    test('parses a restaurant_table booking (bookable.label, guest_count)', () {
      final booking = ServiceBooking.fromJson({
        'uuid': 'sb-1',
        'bookable_type': 'restaurant_table',
        'bookable': {'uuid': 'tbl-4', 'label': 'Table 4 (window)'},
        'scheduled_at': null,
        'status': 'pending',
        'notes': 'Anniversary',
        'guest_count': 4,
      });

      expect(booking.bookableType, 'restaurant_table');
      expect(booking.bookable, isNotNull);
      expect(booking.bookable!.uuid, 'tbl-4');
      expect(booking.bookable!.label, 'Table 4 (window)');
      expect(booking.label, 'Table 4 (window)');
      expect(booking.status, 'pending');
      expect(booking.guestCount, 4);
    });
  });

  group('PreArrivalDocumentsController.buildMultipart', () {
    test('emits explicit indexed bracket keys with per-doc mime', () {
      const docs = [
        PreArrivalDocumentUpload(
          type: 'passport',
          filePath: '/tmp/passport.pdf',
          mime: 'application/pdf',
        ),
        PreArrivalDocumentUpload(
          type: 'visa',
          filePath: '/tmp/visa.jpg',
          mime: 'image/jpeg',
        ),
      ];

      final built = PreArrivalDocumentsController.buildMultipart(docs);

      // Scalar type fields, explicit indices.
      expect(built.fields['documents[0][type]'], 'passport');
      expect(built.fields['documents[1][type]'], 'visa');

      // File groups, explicit indices, one file each, per-doc mime.
      expect(built.files.containsKey('documents[0][file]'), isTrue);
      expect(built.files.containsKey('documents[1][file]'), isTrue);
      expect(built.files['documents[0][file]']!.files.length, 1);
      expect(built.files['documents[0][file]']!.mime, 'application/pdf');
      expect(built.files['documents[1][file]']!.mime, 'image/jpeg');
      // No collapsed `documents[][...]` keys.
      expect(built.fields.containsKey('documents[][type]'), isFalse);
    });
  });
}
