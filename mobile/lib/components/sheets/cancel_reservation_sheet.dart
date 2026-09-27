import 'package:carlton/l10n/app_translations.dart';
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
          AppTranslations.cancelReservationTitle,
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
              // One sentence per variant rather than three glued fragments: the
              // date clause sits mid-sentence in English and at the front in
              // Turkish, which no fixed span order can express.
              TextSpan(
                text: dates.isEmpty
                    ? AppTranslations.cancelReservationBody(stay.roomName)
                    : AppTranslations.cancelReservationBodyDated(
                        stay.roomName,
                        dates,
                      ),
              ),
            ],
          ),
          textAlign: TextAlign.center,
        ),
        CustomInfoBanner(
          tone: InfoBannerTone.warning,
          message: AppTranslations.freeCancellationNoCharges,
        ),
      ],
    );
  }
}
