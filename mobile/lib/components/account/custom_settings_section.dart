import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// A titled group of settings rows (Figma Account "Account" / "Support" and
/// Check-In "Room Type"): a black bold header above a column of
/// individually-carded [CustomListRow]s separated by gaps.
class CustomSettingsSection extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const CustomSettingsSection({
    required this.title,
    required this.children,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 12,
      children: [
        Text(
          title,
          style: textStyle.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.nearBlack,
          ),
        ),
        ...children,
      ],
    );
  }
}
