/// One selectable ETA on the Home checklist's arrival-time sheet.
///
/// Carries the wall-clock hour rather than a formatted label so the chip text
/// can be produced by [TimeOfDay.format], which follows the locale's numerals
/// and its 12/24-hour convention. The previous version stored `'2:00 PM'`
/// strings, which stayed Latin and 12-hour in Arabic.
enum ArrivalSlot {
  noon(12),
  onePm(13),
  twoPm(14),
  threePm(15),
  fourPm(16),
  sixPm(18),
  eightPm(20),
  afterTenPm(22);

  const ArrivalSlot(this.hour);

  /// 24-hour clock hour. Every slot lands on the hour, so there is no minute.
  final int hour;

  /// Which heading the slot sits under on the sheet.
  ArrivalPeriod get period => ArrivalPeriod.of(hour);

  /// The last slot is a bucket, not an exact ETA — reception only needs to
  /// know the guest is arriving late, so it renders as "After 10:00 PM".
  bool get isOpenEnded => this == ArrivalSlot.afterTenPm;

  static ArrivalSlot fromHour(int hour) =>
      values.firstWhere((slot) => slot.hour == hour);

  /// The slot for a server-sent hour, or null: the server accepts any HH:mm,
  /// and most hours have no slot here.
  static ArrivalSlot? tryFromHour(int? hour) =>
      values.where((slot) => slot.hour == hour).firstOrNull;
}

/// The groups the arrival-time sheet renders its hours under. Ordered earliest
/// to latest; the sheet iterates `values` directly, so the declaration order
/// is the on-screen order. Check-in's slots only reach the last three; the
/// airport transfer offers the whole day, since flights land at any hour.
enum ArrivalPeriod {
  earlyHours,
  morning,
  afternoon,
  evening,
  lateNight;

  static ArrivalPeriod of(int hour) {
    if (hour < 6) return earlyHours;
    if (hour < 12) return morning;
    if (hour < 16) return afternoon;
    if (hour < 22) return evening;
    return lateNight;
  }
}
