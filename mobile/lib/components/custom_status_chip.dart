import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Small status pill (e.g. COMPLETED on past stays, "Upcoming" on the upcoming
/// card). Named constructors bake the two Figma variants; the default lets
/// callers pass their own colors.
class CustomStatusChip extends StatelessWidget {
  final String label;
  final Color background;
  final Color foreground;
  final bool uppercase;

  const CustomStatusChip({
    required this.label,
    required this.background,
    required this.foreground,
    this.uppercase = false,
    super.key,
  });

  const CustomStatusChip.completed({Key? key})
    : this(
        key: key,
        label: 'COMPLETED',
        background: AppColors.statusCompletedBg,
        foreground: AppColors.statusCompletedText,
      );

  const CustomStatusChip.upcoming({Key? key})
    : this(
        key: key,
        label: 'Upcoming',
        background: AppColors.sandPillBg,
        foreground: AppColors.sandPillText,
      );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        uppercase ? label.toUpperCase() : label,
        style: TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: foreground,
        ),
      ),
    );
  }
}
