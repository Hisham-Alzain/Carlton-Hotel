/// Route argument passed to the OTP screen. Carries everything `verify-otp`
/// (and a resend of `request-otp` / `link-booking-code`) needs, replacing the
/// old bare display string.
class OtpVerifyArgs {
  /// `sms`, `whatsapp`, or `email`.
  final String channel;

  /// `login`, `register`, or `booking_link`.
  final String purpose;

  /// The E.164 phone or email the code was sent to — echoed in `verify-otp`.
  final String identifier;

  /// Masked contact string for `booking_link` (`**@ex***.com`); the plain
  /// [identifier] for the direct login/register paths. Shown to the guest.
  final String display;

  const OtpVerifyArgs({
    required this.channel,
    required this.purpose,
    required this.identifier,
    String? display,
  }) : display = display ?? identifier;

  bool get isEmail => channel == 'email';
  bool get isBookingLink => purpose == 'booking_link';
}
