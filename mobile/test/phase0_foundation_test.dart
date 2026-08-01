import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/models/bilingual.dart';
import 'package:carlton/models/media_image.dart';
import 'package:carlton/models/pagination.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the Phase 0 foundation additions (value types, pagination unification,
/// guest error codes). Fails if any of them are reverted.
void main() {
  group('MediaImage', () {
    test('banner picks the lowest sort_order', () {
      const images = [
        MediaImage(uuid: 'c', url: '', fileName: '', sortOrder: 3),
        MediaImage(uuid: 'a', url: '', fileName: '', sortOrder: 1),
        MediaImage(uuid: 'b', url: '', fileName: '', sortOrder: 2),
      ];
      expect(MediaImage.banner(images)?.uuid, 'a');
      expect(MediaImage.banner(const []), isNull);
    });

    test('fromJson maps file_name + sort_order', () {
      final img = MediaImage.fromJson({
        'uuid': 'x',
        'url': 'u',
        'file_name': 'f.png',
        'sort_order': 5,
      });
      expect(img.fileName, 'f.png');
      expect(img.sortOrder, 5);
    });
  });

  test('Pagination.fromJson reads current/last page', () {
    final p = Pagination.fromJson({
      'current_page': 2,
      'last_page': 7,
      'per_page': 20,
      'total': 130,
    });
    expect(p.currentPage, 2);
    expect(p.lastPage, 7);
  });

  test('Bilingual.fromJson reads en + ar (and a bare string)', () {
    final b = Bilingual.fromJson({'en': 'Hello', 'ar': 'مرحبا'});
    expect(b.en, 'Hello');
    expect(b.ar, 'مرحبا');
    expect(Bilingual.fromJson('Solo').en, 'Solo');
  });

  test('guest/booking error codes exist', () {
    expect(ErrorCodes.invalidPromo, 'invalid_promo');
    expect(ErrorCodes.otpExpired, 'otp_expired');
    expect(ErrorCodes.reservationState, 'reservation_state');
  });
}
