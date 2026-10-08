import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The top of the ID-scan result screens (camera unavailable, photo
/// captured): a round status icon over a centred headline.
class ScanStatusHeader extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final Color chipColor;
  final String title;

  const ScanStatusHeader({
    required this.icon,
    required this.iconColor,
    required this.chipColor,
    required this.title,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      spacing: 20,
      children: [
        Center(
          child: CustomIconChip.circle(
            size: 50,
            backgroundColor: chipColor,
            child: Icon(icon, color: iconColor),
          ),
        ),
        Text(
          title,
          textAlign: TextAlign.center,
          style: Get.textTheme.headlineSmall?.copyWith(
            color: AppColors.primary,
          ),
        ),
      ],
    );
  }
}
