import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A title with an up/down chevron that opens to show its body text — one
/// FAQ answer, one legal page. The caller owns which tile is open.
class ExpandableTextTile extends StatelessWidget {
  final String title;
  final String body;
  final bool expanded;
  final VoidCallback onToggle;

  const ExpandableTextTile({
    required this.title,
    required this.body,
    required this.expanded,
    required this.onToggle,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return InkWell(
      onTap: onToggle,
      child: Column(
        spacing: 10,
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            spacing: 10,
            children: [
              Expanded(
                child: Text(
                  title,
                  style: textStyle.titleSmall?.copyWith(
                    color: AppColors.inkBlack,
                  ),
                ),
              ),
              Icon(
                expanded
                    ? Icons.keyboard_arrow_up_rounded
                    : Icons.keyboard_arrow_down_rounded,
                color: AppColors.mediumGrey,
              ),
            ],
          ),
          if (expanded)
            Text(
              body,
              style: textStyle.dmBodySmall?.copyWith(
                color: AppColors.taupeBrown,
              ),
            ),
        ],
      ),
    );
  }
}
