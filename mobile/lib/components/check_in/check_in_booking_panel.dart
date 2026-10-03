import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The grey "YOUR BOOKING" card shared by both Identity states
/// (Figma 2237:4567 and 2237:4669).
class CheckInBookingPanel extends StatelessWidget {
  final ReservationSummary reservation;

  const CheckInBookingPanel({required this.reservation, super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.frostGrey,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 10,
          children: [
            Text(
              AppTranslations.yourBooking,
              style: Get.textTheme.labelMedium?.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.5,
              ),
            ),
            _PanelRow(AppTranslations.guestLabel, reservation.guestName),
            _PanelRow(
              AppTranslations.roomLabel,
              '${reservation.suiteName} · ${reservation.roomNumber}',
            ),
            _PanelRow(
              AppTranslations.checkInLabel,
              _dateAndTime(reservation.checkInDate, reservation.checkInTime),
            ),
            _PanelRow(
              AppTranslations.checkOutLabel,
              _dateAndTime(reservation.checkOutDate, reservation.checkOutTime),
            ),
          ],
        ),
      ),
    );
  }
}

/// The year and clock times used to be appended here as literals, which worked
/// only because the demo reservation stored a year-less "Aug 14". Real
/// `/stays/upcoming` data carries the year and no times, so the row now shows
/// exactly what the reservation has — an absent time is omitted rather than
/// replaced by a plausible-looking default.
String _dateAndTime(String date, String time) =>
    time.isEmpty ? date : '$date · $time';

class _PanelRow extends StatelessWidget {
  final String label;
  final String value;

  const _PanelRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 10,
      children: [
        Text(
          label,
          style: Get.textTheme.bodyMedium?.copyWith(
            color: AppColors.mediumGrey,
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: Get.textTheme.bodyMedium?.copyWith(
              color: AppColors.inkBlack,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
