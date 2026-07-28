import 'package:carlton/models/pending_booking_link.dart';
import 'package:carlton/services/get_storage_service.dart';

/// Scratch bridge **only** — stashes the booking-link details the guest entered
/// on "Find Booking" so the OTP step can re-trigger `link-booking-code` on
/// resend. This is NOT an auth mechanism: the token + guest identity live in
/// [MiddlewareService]. Cleared once a token is obtained.
class SessionService {
  static const _codeKey = 'session_pending_booking_code';
  static const _lastNameKey = 'session_pending_booking_last_name';
  static const _phoneKey = 'session_pending_booking_phone';

  static Future<void> setPendingBookingLink(PendingBookingLink link) async {
    await StorageService.setString(_codeKey, link.bookingCode);
    if (link.lastName != null) {
      await StorageService.setString(_lastNameKey, link.lastName!);
    }
    if (link.phone != null) {
      await StorageService.setString(_phoneKey, link.phone!);
    }
  }

  static PendingBookingLink? get pendingBookingLink {
    final code = StorageService.getString(_codeKey);
    if (code == null) return null;
    return PendingBookingLink(
      bookingCode: code,
      lastName: StorageService.getString(_lastNameKey),
      phone: StorageService.getString(_phoneKey),
    );
  }

  static Future<void> clearPendingBookingLink() async {
    await StorageService.remove(_codeKey);
    await StorageService.remove(_lastNameKey);
    await StorageService.remove(_phoneKey);
  }
}
