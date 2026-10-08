part of 'booking_models.dart';

enum PaymentMethod {
  card,
  applePay,
  googlePay,
  payAtHotel;

  /// Resolved per read rather than held as `const` enum fields — `.tr` is a
  /// runtime lookup, so a const field would freeze the launch locale.
  /// The wallet brand names stay untranslated on purpose: Apple and Google
  /// ship them as proper nouns in every locale.
  String get label => switch (this) {
    PaymentMethod.card => AppTranslations.creditCard,
    PaymentMethod.applePay => 'Apple Pay',
    PaymentMethod.googlePay => 'Google Pay',
    PaymentMethod.payAtHotel => AppTranslations.payAtHotel,
  };

  String get subtitle => switch (this) {
    PaymentMethod.card => AppTranslations.acceptedCardsFull,
    PaymentMethod.applePay => AppTranslations.applePayTagline,
    PaymentMethod.googlePay => AppTranslations.googlePayTagline,
    PaymentMethod.payAtHotel => AppTranslations.payAtHotelTagline,
  };
}

extension PaymentMethodIcon on PaymentMethod {
  /// Brand glyph for the wallet methods; null for card / pay-at-hotel.
  String? get iconPath => switch (this) {
    PaymentMethod.applePay => 'assets/icons/pay_apple.svg',
    PaymentMethod.googlePay => 'assets/icons/pay_google.svg',
    PaymentMethod.card || PaymentMethod.payAtHotel => null,
  };
}

/// Mutable draft of the guest form (Step 4).
class GuestDetails {
  String firstName;
  String lastName;
  String email;
  String dialCode;
  String phone;
  String specialRequests;

  GuestDetails({
    this.firstName = '',
    this.lastName = '',
    this.email = '',
    this.dialCode = kDefaultDialCode,
    this.phone = '',
    this.specialRequests = '',
  });
}

/// Mutable draft of the card form (Step 5).
class CardDetails {
  String number;
  String expiry;
  String cvv;
  String nameOnCard;

  CardDetails({
    this.number = '',
    this.expiry = '',
    this.cvv = '',
    this.nameOnCard = '',
  });
}
