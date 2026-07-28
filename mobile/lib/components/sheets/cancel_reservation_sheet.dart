import 'package:carlton/components/custom_info_banner.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Body of the "Cancel Reservation?" sheet — a compact warning. The confirm /
/// keep buttons are supplied separately as the sheet's pinned actions (see
/// `StaysController.requestCancel`).
class CancelReservationSheet extends StatelessWidget {
  final Stay stay;

  const CancelReservationSheet({required this.stay, super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final dates = [
      stay.checkInLabel,
      stay.checkOutLabel,
    ].where((dateLabel) => dateLabel != null).join(' – ');

    return Column(
      mainAxisSize: MainAxisSize.min,
      spacing: 12,
      children: [
        Container(
          width: 56,
          height: 56,
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.crimsonRed10,
          ),
          child: SvgPicture.asset(
            'assets/icons/warning.svg',
            width: 26,
            height: 26,
          ),
        ),
        Text(
          'Cancel Reservation?',
          textAlign: TextAlign.center,
          style: textStyle.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
            color: AppColors.inkBlack,
          ),
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
