import 'package:carlton/theme/theme.dart';
import 'package:carlton/theme/app_shadows.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Upcoming-stay card for the My Stays "Upcoming" tab: photo header with an
/// "Upcoming" pill and nightly-price badge, room + room number, CHECK-IN /
/// CHECK-OUT chips, a copyable reservation pill, and the outlined
/// Cancel Reservation button.
class CustomUpcomingStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onCancel;

  /// Copies the booking code (the icon beside it).
  final VoidCallback onCopyCode;

  const CustomUpcomingStayCard({
    required this.stay,
    required this.onCancel,
    required this.onCopyCode,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.cloudGrey48, width: 1),
        boxShadow: const [AppShadows.banner],
      ),
      child: Column(
        spacing: 10,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 100,
            width: double.infinity,
            child: Stack(
              fit: StackFit.expand,
              children: [
                if (stay.imagePath != null)
                  Image.asset('assets/images/stay_room.png', fit: BoxFit.cover),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: AlignmentDirectional.topStart,
                      end: AlignmentDirectional.bottomEnd,
                      colors: [AppColors.iceBlue70, AppColors.steelTeal70],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: Align(
                    alignment: AlignmentDirectional.bottomStart,
                    child: PillContainer(
                      backgroundColor: AppColors.sandBeige,
                      child: Text(
                        AppTranslations.upcoming,
                        style: textStyle.dmLabelSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.espressoBrown,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  spacing: 10,
                  children: [
                    Text(
                      stay.roomName,
                      style: textStyle.titleMedium?.copyWith(
                        fontFamily: 'Plus Jakarta Sans',
                        fontWeight: FontWeight.w600,
                        color: AppColors.inkBlack,
                      ),
                    ),
                    PillContainer(
                      padding: EdgeInsets.zero,
                      backgroundColor: AppColors.white90,
                      child: Text(
                        stay.pricePerNight!,
                        style: textStyle.dmTitleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                  ],
                ),
                Text(
                  stay.subtitle!,
                  style: textStyle.dmLabelMedium?.copyWith(
                    color: AppColors.taupeBrown,
                  ),
                ),
                if (stay.rewardsNote != null)
                  RowTextComponent(
                    icon: Icons.card_giftcard_outlined,
                    iconSize: 16,
                    spacing: 6,
                    iconColor: AppColors.primary,
                    text: stay.rewardsNote!,
                    textStyle: textStyle.dmLabelMedium?.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                Row(
                  spacing: 10,
                  children: [
                    Expanded(
                      child: _DateContainer(
                        label: AppTranslations.checkInLabel,
                        formattedDate: stay.checkInLabel ?? '',
                      ),
                    ),

                    Expanded(
                      child: _DateContainer(
                        label: AppTranslations.checkOutLabel,
                        formattedDate: stay.checkOutLabel ?? '',
                      ),
                    ),
                  ],
                ),

                PillContainer(
                  backgroundColor: AppColors.cream,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        AppTranslations.resCode(stay.resCode ?? ''),
                        style: textStyle.labelLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                      IconButton(
                        onPressed: onCopyCode,
                        icon: Icon(Icons.copy, color: AppColors.taupeBrown),
                      ),
                    ],
                  ),
                ),

                Center(
                  child: CustomOutlinedButton(
                    onPressed: onCancel,
                    foregroundColor: AppColors.brickRed,
                    borderColor: AppColors.crimsonRed30,
                    child: Text(AppTranslations.cancelReservation),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DateContainer extends StatelessWidget {
  final String label;
  final String formattedDate;

  const _DateContainer({required this.label, required this.formattedDate});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return PillContainer(
      backgroundColor: AppColors.frostGrey,
      radius: 8,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: textStyle.dmLabelSmall?.copyWith(
              color: AppColors.bronzeGold,
            ),
          ),

          Text(
            formattedDate,
            style: textStyle.labelMedium?.copyWith(
              fontWeight: FontWeight.w500,
              color: AppColors.inkBlack,
            ),
          ),
        ],
      ),
    );
  }
}
