import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Labelled date field used for CHECK-IN / CHECK-OUT (Upcoming stay card, static;
/// Plan Your Stay, tappable). [selected] swaps to the warm fill + gold border the
/// Figma "active" state uses. Values come from Figma: gold uppercase 10px label,
/// primary SemiBold 15px value, 10px radius, 1.18px border.
class CustomDateBox extends StatelessWidget {
  final String label;
  final String value;
  final bool selected;
  final VoidCallback? onTap;

  const CustomDateBox({
    required this.label,
    required this.value,
    this.selected = false,
    this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: selected ? AppColors.ivoryCream : AppColors.ghostWhite,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: selected ? AppColors.harvestGold46 : AppColors.fogGrey35,
            width: 1,
          ),
        ),
        child: Column(
          spacing: 10,
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label.toUpperCase(),
              style: textStyle.labelSmall?.copyWith(
                fontFamily: 'DM Sans',
                color: AppColors.antiqueGold,
              ),
            ),

            Text(
              value,
              style: textStyle.labelLarge?.copyWith(
                fontWeight: FontWeight.w600,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
