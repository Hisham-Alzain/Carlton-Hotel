/// Scratch bridge between the "Find Booking" screen and OTP verification —
/// **not** an auth mechanism. Holds the booking code + second factor the guest
/// entered so the OTP step can re-trigger `link-booking-code` on resend. Cleared
/// once a token is obtained.
class PendingBookingLink {
  final String bookingCode;
  final String? lastName;
  final String? phone;

  const PendingBookingLink({
    required this.bookingCode,
    this.lastName,
    this.phone,
  });
}
