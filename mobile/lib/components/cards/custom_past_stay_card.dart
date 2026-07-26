import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Past-stay card for the My Stays "Past" tab: room + dates + COMPLETED chip,
/// total charged, and the View Receipt / Book Again button row. Kept as a
/// feature component so `StaysView` stays declarative.
class CustomPastStayCard extends StatelessWidget {
  final Stay stay;
  final VoidCallback onViewReceipt;
  final VoidCallback onBookAgain;

  const CustomPastStayCard({
    required this.stay,
    required this.onViewReceipt,
    required this.onBookAgain,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Card(
      color: AppColors.white,
      shape: BeveledRectangleBorder(
        side: BorderSide(color: AppColors.white92, width: 1),
        borderRadius: BorderRadiusGeometry.circular(12),
      ),
      margin: const EdgeInsets.all(10),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        stay.roomName,
                        style: textStyle.titleSmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.inkBlack,
                        ),
                      ),

                      Text(
                        stay.dateRangeLabel ?? '',
                        style: textStyle.labelMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          color: AppColors.taupeBrown,
                        ),
                      ),
                    ],
                  ),
                ),
                PillContainer(
                  backgroundColor: AppColors.successGreen07,
                  child: Text(
                    'COMPLETED',
                    style: textStyle.labelSmall?.copyWith(
                      fontFamily: 'DM Sans',
                      fontWeight: FontWeight.w700,
                      color: AppColors.forestGreen,
                    ),
                  ),
                ),
              ],
            ),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total charged',
                  style: textStyle.labelLarge?.copyWith(
                    fontFamily: 'DM Sans',
                    color: AppColors.taupeBrown,
                  ),
                ),
                Text(
                  stay.totalCharged ?? '',
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),

            Row(
              spacing: 10,
              children: [
                CustomFilledButton(
                  backgroundColor: AppColors.pearlCream,
                  foregroundColor: AppColors.inkBlack,
                  onPressed: onViewReceipt,
                  child: Text('View Receipt'),
                ),
                CustomFilledButton(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  onPressed: onBookAgain,
                  child: Text('Book Again'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
