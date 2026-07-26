import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Upcoming-stay card for the My Stays "Upcoming" tab: photo header with an
/// "Upcoming" pill and nightly-price badge, room + room number, CHECK-IN /
/// CHECK-OUT chips, a copyable reservation pill with share, and the outlined
/// Cancel Reservation button.
class CustomUpcomingStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onCancel;
  final VoidCallback? onShare;

  const CustomUpcomingStayCard({
    required this.stay,
    required this.onCancel,
    this.onShare,
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
        boxShadow: const [
          BoxShadow(
            color: AppColors.pebbleGrey32,
            blurRadius: 4,
            spreadRadius: 0,
            offset: Offset(0, 2),
          ),
        ],
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
                  CustomImage(
                    path: 'assets/images/stay_room.png',
                    fit: BoxFit.cover,
                  ),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [AppColors.iceBlue70, AppColors.steelTeal70],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: Align(
                    alignment: Alignment.bottomLeft,
                    child: PillContainer(
                      backgroundColor: AppColors.sandBeige,
                      child: Text(
                        'Upcoming',
                        style: textStyle.labelSmall?.copyWith(
                          fontFamily: 'DM Sans',
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
                        style: textStyle.titleMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          fontWeight: FontWeight.w600,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                  ],
                ),
                Text(
                  stay.subtitle!,
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
                Row(
                  spacing: 10,
                  children: [
                    Expanded(
                      child: _DateContainer(
                        label: 'Check-in',
                        value: stay.checkInLabel ?? '',
                      ),
                    ),

                    Expanded(
                      child: _DateContainer(
                        label: 'Check-out',
                        value: stay.checkOutLabel ?? '',
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
                        'Res. #${stay.resCode ?? ''}',
                        style: textStyle.labelLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                      IconButton(
                        onPressed: () {},
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
                    child: Text('Cancel Reservation'),
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
  final String value;

  const _DateContainer({required this.label, required this.value});

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
            style: textStyle.labelSmall?.copyWith(
              fontFamily: 'DM Sans',
              color: AppColors.bronzeGold,
            ),
          ),

          Text(
            value,
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
