/// The hotel's clock. Times a guest picks for the hotel — an airport pickup,
/// a spa slot — are hotel time, wherever the guest's phone is.
///
/// Syria has kept UTC+3 all year since 2022 (no daylight saving), so a fixed
/// offset matches the backend's `HOTEL_TIMEZONE=Asia/Damascus` without
/// bundling a timezone database.
abstract final class HotelTime {
  static const Duration utcOffset = Duration(hours: 3);

  /// The hotel's current wall-clock time, as a plain (non-UTC) DateTime so it
  /// compares directly with a time the guest picked.
  static DateTime now() => fromInstant(DateTime.now());

  /// [instant] (e.g. a server `scheduled_at`, which is a true UTC instant) as
  /// the hotel's wall-clock time, so it reads the same as the slot the guest
  /// picked whatever zone the phone is in.
  static DateTime fromInstant(DateTime instant) {
    final hotel = instant.toUtc().add(utcOffset);
    return DateTime(
      hotel.year,
      hotel.month,
      hotel.day,
      hotel.hour,
      hotel.minute,
      hotel.second,
    );
  }
}
