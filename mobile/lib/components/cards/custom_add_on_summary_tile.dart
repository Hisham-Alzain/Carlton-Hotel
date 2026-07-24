import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Compact room summary tile shown at the top of the Add-Ons / Guest / Payment
/// steps: thumbnail + room name + dates and nightly rate.
class CustomAddOnSummaryTile extends StatelessWidget {
  final String imagePath;
  final String roomName;
  final String detail; // "Aug 21 – Aug 24 · $280/night"

  const CustomAddOnSummaryTile({
    required this.imagePath,
    required this.roomName,
    required this.detail,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return PillContainer(
      backgroundColor: AppColors.pearlCream,
      child: Row(
        spacing: 10,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: CustomImage(
              path: imagePath,
              width: 50,
              height: 50,
              fit: BoxFit.cover,
            ),
          ),
          Column(
            spacing: 10,
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                roomName,
                style: textStyle.labelMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: AppColors.inkBlack,
                ),
              ),
              Text(
                detail,
                style: textStyle.labelSmall?.copyWith(
                  fontFamily: 'DM Sans',
                  color: AppColors.taupeBrown,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
