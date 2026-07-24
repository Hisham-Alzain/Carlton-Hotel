import 'package:carlton/customWidgets/custom_empty_placeholder.dart';
import 'package:carlton/customWidgets/custom_info_banner.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Body of the "Cancel Reservation?" sheet. The confirm / keep buttons are
/// supplied separately as the sheet's pinned actions — see
/// `StaysController.requestCancel`.
class CancelReservationSheet extends StatelessWidget {
  final Stay stay;

  const CancelReservationSheet({required this.stay, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final dates = [
      stay.checkInLabel,
      stay.checkOutLabel,
    ].where((e) => e != null).join(' – ');

    return Column(
      mainAxisSize: MainAxisSize.min,
      spacing: 10,
      children: [
        const CustomEmptyPlaceholder(
          iconPath: 'assets/icons/warning.svg',
          iconContainerColor: AppColors.crimsonRed10,
          iconHeight: 50,
          title: 'Cancel Reservation?',
        ),
        Text.rich(
          TextSpan(
            style: textStyle.labelMedium?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.steelGrey,
            ),
            children: [
              TextSpan(text: 'Your reservation for the ${stay.roomName}'),
              if (dates.isNotEmpty)
                TextSpan(
                  text: ' on $dates',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              const TextSpan(text: ' will be cancelled.'),
            ],
          ),
          textAlign: TextAlign.center,
        ),
        const CustomInfoBanner(
          tone: InfoBannerTone.warning,
          message:
              'Free cancellation is currently available for this booking, '
              'no charges will apply.',
        ),
      ],
    );
  }
}
