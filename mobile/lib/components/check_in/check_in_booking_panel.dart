import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/reservation_summary.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The cream "YOUR BOOKING" card shared by both Identity states
/// (Figma 75:463 and 75:565).
class CheckInBookingPanel extends StatelessWidget {
  final ReservationSummary reservation;

  const CheckInBookingPanel({required this.reservation, super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.pearlCream,
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
                color: AppColors.lagoonTeal,
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
              '${reservation.checkInDate}, 2026 · 3:00 PM',
            ),
            _PanelRow(
              AppTranslations.checkOutLabel,
              '${reservation.checkOutDate}, 2026 · 12:00 PM',
            ),
          ],
        ),
      ),
    );
  }
}

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
            color: AppColors.taupeBrown,
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: Get.textTheme.bodyMedium?.copyWith(
              color: AppColors.primary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
