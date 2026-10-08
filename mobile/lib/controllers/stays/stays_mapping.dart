part of 'stays_controller.dart';

extension StaysMapping on StaysController {
  Stay _activeToStay(ActiveStay s) {
    final since = s.checkedInAt != null
        ? StaysController._time.format(s.checkedInAt!)
        : (s.checkIn != null
              ? StaysController._shortDate.format(s.checkIn!)
              : '');
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.active,
      subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
          ? AppTranslations.stayRoomNumber(s.roomNumber!)
          : null,
      checkedInSince: since,
      nightsRemaining: s.nightsRemaining,
      checkInLabel: s.checkIn != null
          ? StaysController._fullDate.format(s.checkIn!)
          : '',
      checkOutLabel: s.checkOut != null
          ? StaysController._fullDate.format(s.checkOut!)
          : '',
    );
  }

  Stay _upcomingToStay(UpcomingStay s, ReservationLoyalty? rewards) {
    final total = double.tryParse(s.priceUsd) ?? 0;
    final perNight = s.nights > 0 ? total / s.nights : total;
    final days = s.checkIn?.difference(DateTime.now()).inDays;
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.upcoming,
      // The card force-unwraps subtitle/pricePerNight — never leave them null.
      subtitle: [
        // A guest-made booking stays `pending` until the hotel confirms it.
        if (s.isAwaitingHotel) AppTranslations.awaitingConfirmation,
        (s.roomNumber != null && s.roomNumber!.isNotEmpty)
            ? '${AppTranslations.receiptHotelName} · '
                  '${AppTranslations.stayRoomNumber('${s.roomNumber}')}'
            : AppTranslations.receiptHotelName,
      ].join(' · '),
      imagePath: 'assets/images/stay_room.png',
      checkInLabel: s.checkIn != null
          ? StaysController._fullDate.format(s.checkIn!)
          : '',
      checkOutLabel: s.checkOut != null
          ? StaysController._fullDate.format(s.checkOut!)
          : '',
      resCode: s.bookingCode,
      pricePerNight: AppTranslations.perNight(
        StaysController.usd(perNight.toString()),
      ),
      isCancellable: s.isCancellable,
      nextCheckInDays: (days != null && days > 0) ? days : null,
      rewardsNote: StaysController.rewardsNote(rewards),
    );
  }

  Stay _pastToStay(PastStay s) {
    final range = (s.checkIn != null && s.checkOut != null)
        ? '${StaysController._shortDate.format(s.checkIn!)} – '
              '${StaysController._shortDate.format(s.checkOut!)} · '
              '${AppTranslations.nightsCount(s.totalNights)}'
        : AppTranslations.nightsCount(s.totalNights);
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.past,
      dateRangeLabel: range,
      totalCharged: StaysController.usd(s.totalChargeUsd),
      resCode: s.bookingCode,
      hasReceipt: s.hasReceipt,
      isCancelled: s.isCancelled,
    );
  }

  ReceiptData _receiptToData(Stay stay, Receipt r) {
    final dateLabel =
        (r.reservation.checkIn != null && r.reservation.checkOut != null)
        ? '${StaysController._shortDate.format(r.reservation.checkIn!)} – '
              '${StaysController._shortDate.format(r.reservation.checkOut!)}'
        : (stay.dateRangeLabel ?? '');
    final balance = double.tryParse(r.balanceDueUsd) ?? 0;
    final paymentInfo = balance > 0
        ? AppTranslations.balanceDue(StaysController.usd(r.balanceDueUsd))
        : (r.payments.isNotEmpty
              ? AppTranslations.paymentProcessed(r.payments.first.method)
              : AppTranslations.settledAtFrontDesk);
    return ReceiptData(
      roomName: stay.roomName,
      dateLabel: dateLabel,
      resCode: r.reservation.bookingCode,
      lines: r.items
          .map(
            (i) => (
              label: i.description,
              amount: StaysController.usd(i.amountUsd),
            ),
          )
          .toList(),
      total: StaysController.usd(r.folio.totalUsd),
      paymentInfo: paymentInfo,
    );
  }
}
