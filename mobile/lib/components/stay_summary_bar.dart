import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// "Aug 14 → Aug 16 · 2 nights" on the left, "2 Adults" on the right: the
/// booking-flow bar that recaps the dates and party (Plan and Choose Room).
class StaySummaryBar extends StatelessWidget {
  final String dateSummary;
  final String guestSummary;
  final Color backgroundColor;

  const StaySummaryBar({
    required this.dateSummary,
    required this.guestSummary,
    this.backgroundColor = AppColors.cream,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return PillContainer(
      padding: const EdgeInsets.all(10),
      backgroundColor: backgroundColor,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(
              dateSummary,
              style: textStyle.dmLabelMedium?.copyWith(
                color: AppColors.inkBlack,
              ),
            ),
          ),
          Text(
            guestSummary,
            style: textStyle.labelMedium?.copyWith(color: AppColors.walnutGold),
          ),
        ],
      ),
    );
  }
}
