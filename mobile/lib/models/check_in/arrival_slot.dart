/// One selectable ETA on the Home checklist's arrival-time sheet.
///
/// Carries the wall-clock hour rather than a formatted label so the chip text
/// can be produced by [TimeOfDay.format], which follows the locale's numerals
/// and its 12/24-hour convention. The previous version stored `'2:00 PM'`
/// strings, which stayed Latin and 12-hour in Arabic.
enum ArrivalSlot {
  noon(12, ArrivalPeriod.afternoon),
  onePm(13, ArrivalPeriod.afternoon),
  twoPm(14, ArrivalPeriod.afternoon),
  threePm(15, ArrivalPeriod.afternoon),
  fourPm(16, ArrivalPeriod.evening),
  sixPm(18, ArrivalPeriod.evening),
  eightPm(20, ArrivalPeriod.evening),
  afterTenPm(22, ArrivalPeriod.lateNight);

  const ArrivalSlot(this.hour, this.period);

  /// 24-hour clock hour. Every slot lands on the hour, so there is no minute.
  final int hour;

  /// Which heading the slot sits under on the sheet.
  final ArrivalPeriod period;

  /// The last slot is a bucket, not an exact ETA — reception only needs to
  /// know the guest is arriving late, so it renders as "After 10:00 PM".
  bool get isOpenEnded => this == ArrivalSlot.afterTenPm;
}

/// The three groups the slots are rendered under. Ordered earliest to latest;
/// the sheet iterates `values` directly, so the declaration order is the
/// on-screen order.
enum ArrivalPeriod { afternoon, evening, lateNight }
