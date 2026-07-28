import 'package:carlton/models/guest.dart';
import 'package:carlton/models/otp_verify_args.dart';
import 'package:carlton/models/pending_booking_link.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the Phase 1 auth model logic — the highest-risk parsing in the
/// unified-session rewrite. Fails if Guest parsing / entitlement preservation /
/// OtpVerifyArgs helpers are reverted.
void main() {
  group('Guest', () {
    test('parses /me entitlement flags + name', () {
      final g = Guest.fromJson({
        'uuid': 'u1',
        'phone': '+963900',
        'first_name': 'Ahmad',
        'last_name': 'Khalil',
        'preferred_locale': 'ar',
        'has_booking': true,
        'is_checked_in': true,
      });
      expect(g.hasBooking, isTrue);
      expect(g.isCheckedIn, isTrue);
      expect(g.hasName, isTrue);
      expect(g.fullName, 'Ahmad Khalil');
      expect(g.preferredLocale, 'ar');
    });

    test('nameless verify-otp guest defaults entitlements to false', () {
      final g = Guest.fromJson({'uuid': 'u2', 'first_name': null});
      expect(g.hasName, isFalse);
      expect(g.hasBooking, isFalse);
    });

    test('has_active_reservation is honoured as a has_booking alias', () {
      final g = Guest.fromJson({'uuid': 'u3', 'has_active_reservation': true});
      expect(g.hasBooking, isTrue);
    });

    test('copyWith preserves entitlements across a profile edit', () {
      final base = Guest.fromJson({
        'uuid': 'u',
        'has_booking': true,
        'is_checked_in': true,
      });
      // A profile PUT response omits the /me-only flags — parsing loses them…
      final edited = Guest.fromJson({
        'uuid': 'u',
        'first_name': 'New',
      }).copyWith(hasBooking: base.hasBooking, isCheckedIn: base.isCheckedIn);
      expect(edited.firstName, 'New');
      expect(edited.hasBooking, isTrue); // …but copyWith restores them.
      expect(edited.isCheckedIn, isTrue);
    });
  });

  test('OtpVerifyArgs channel/purpose helpers + display default', () {
    const a = OtpVerifyArgs(
      channel: 'email',
      purpose: 'booking_link',
      identifier: 'x@y.com',
    );
    expect(a.isEmail, isTrue);
    expect(a.isBookingLink, isTrue);
    expect(a.display, 'x@y.com'); // defaults to identifier

    const b = OtpVerifyArgs(
      channel: 'sms',
      purpose: 'login',
      identifier: '+963',
      display: '**63',
    );
    expect(b.display, '**63');
    expect(b.isEmail, isFalse);
  });

  test('PendingBookingLink holds code + second factor', () {
    const p = PendingBookingLink(bookingCode: 'CARL-1', lastName: 'Khalil');
    expect(p.bookingCode, 'CARL-1');
    expect(p.lastName, 'Khalil');
    expect(p.phone, isNull);
  });
}
