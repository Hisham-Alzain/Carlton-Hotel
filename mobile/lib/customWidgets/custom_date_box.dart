import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

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
    return Material(
      color: selected ? AppColors.dateBoxSelectedBg : AppColors.background,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(
            horizontal: 15.18,
            vertical: 13.18,
          ),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: selected
                  ? AppColors.dateBoxSelectedBorder
                  : AppColors.dateBoxBorder,
              width: 1.18,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label.toUpperCase(),
                style: const TextStyle(
                  fontFamily: 'DM Sans',
                  fontSize: 10,
                  letterSpacing: 0.5,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                style: const TextStyle(
                  fontFamily: 'Plus Jakarta Sans',
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
