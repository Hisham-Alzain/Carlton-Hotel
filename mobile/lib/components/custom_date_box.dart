import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Labelled date field used for CHECK-IN / CHECK-OUT (Upcoming stay card and
/// Plan Your Stay). Static, non-interactive. [selected] swaps to the warm fill +
/// gold border the Figma "active" state uses. Values come from Figma: gold
/// uppercase label, primary SemiBold value, 10px radius, 1px border.
class CustomDateBox extends StatelessWidget {
  final String label;
  final String value;
  final bool selected;

  const CustomDateBox({
    required this.label,
    required this.value,
    this.selected = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return PillContainer(
      radius: 10,
      backgroundColor: selected ? AppColors.ivoryCream : AppColors.ghostWhite,
      border: Border.all(
        color: selected ? AppColors.harvestGold46 : AppColors.fogGrey35,
        width: 1,
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
    );
  }
}
