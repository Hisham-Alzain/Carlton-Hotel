import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

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
    return PillContainer(
      backgroundColor: AppColors.pearlCream,
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: CustomImage(
              path: imagePath,
              width: 44,
              height: 44,
              fit: BoxFit.cover,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  roomName,
                  style: const TextStyle(
                    fontFamily: 'Plus Jakarta Sans',
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkBlack,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  detail,
                  style: const TextStyle(
                    fontFamily: 'DM Sans',
                    fontSize: 11,
                    color: AppColors.taupeBrown,
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
