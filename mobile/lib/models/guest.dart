/// The authenticated guest, returned by `verify-otp`, `PUT /auth/guest/profile`,
/// and `GET /auth/guest/me`. The entitlement booleans ([hasBooking],
/// [isCheckedIn]) are only present on the `/me` response — they default to
/// false on the lighter verify-otp guest.
class Guest {
  final String uuid;
  final String? phone;
  final String? phoneCountry;
  final bool phoneVerified;
  final String? email;
  final bool emailVerified;
  final String? firstName;
  final String? lastName;
  final String preferredLocale;

  /// Unlocks the pre-arrival tier (tier-3a). `/me` only.
  final bool hasBooking;

  /// Reservation is `checked_in` — unlocks the in-stay tier (tier-3b). `/me`
  /// only. A checked-in guest always also has [hasBooking] == true.
  final bool isCheckedIn;

  const Guest({
    required this.uuid,
    this.phone,
    this.phoneCountry,
    this.phoneVerified = false,
    this.email,
    this.emailVerified = false,
    this.firstName,
    this.lastName,
    this.preferredLocale = 'en',
    this.hasBooking = false,
    this.isCheckedIn = false,
  });

  factory Guest.fromJson(Map<String, dynamic> json) {
    return Guest(
      uuid: json['uuid'] as String? ?? '',
      phone: json['phone'] as String?,
      phoneCountry: json['phone_country'] as String?,
      phoneVerified: json['phone_verified'] as bool? ?? false,
      email: json['email'] as String?,
      emailVerified: json['email_verified'] as bool? ?? false,
      firstName: json['first_name'] as String?,
      lastName: json['last_name'] as String?,
      preferredLocale: json['preferred_locale'] as String? ?? 'en',
      // `has_active_reservation` is a deprecated alias for `has_booking`.
      hasBooking:
          (json['has_booking'] ?? json['has_active_reservation']) as bool? ??
          false,
      isCheckedIn: json['is_checked_in'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
    'uuid': uuid,
    'phone': phone,
    'phone_country': phoneCountry,
    'phone_verified': phoneVerified,
    'email': email,
    'email_verified': emailVerified,
    'first_name': firstName,
    'last_name': lastName,
    'preferred_locale': preferredLocale,
    'has_booking': hasBooking,
    'is_checked_in': isCheckedIn,
  };

  /// True once the guest has completed their name (post-OTP profile step).
  bool get hasName =>
      (firstName?.isNotEmpty ?? false) || (lastName?.isNotEmpty ?? false);

  String get fullName =>
      [firstName, lastName].where((s) => s != null && s.isNotEmpty).join(' ');

  Guest copyWith({bool? hasBooking, bool? isCheckedIn}) => Guest(
    uuid: uuid,
    phone: phone,
    phoneCountry: phoneCountry,
    phoneVerified: phoneVerified,
    email: email,
    emailVerified: emailVerified,
    firstName: firstName,
    lastName: lastName,
    preferredLocale: preferredLocale,
    hasBooking: hasBooking ?? this.hasBooking,
    isCheckedIn: isCheckedIn ?? this.isCheckedIn,
  );
}
