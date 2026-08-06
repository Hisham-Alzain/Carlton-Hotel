import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Title + subtitle + Switch in a grey card (Figma 75:808).
///
/// App-generic, so it lives in components/ rather than components/check_in/.
/// `views/account/preferences_view.dart` inlines a private `_switch` helper for
/// the same shape; adopting this tile there is a separate change.
class CustomToggleTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  const CustomToggleTile({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.primary06,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 5),
        child: Row(
          spacing: 10,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                spacing: 5,
                children: [
                  Text(
                    title,
                    style: Get.textTheme.titleSmall?.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: Get.textTheme.bodySmall?.copyWith(
                      color: AppColors.taupeBrown,
                    ),
                  ),
                ],
              ),
            ),
            Switch(
              value: value,
              onChanged: onChanged,
              activeTrackColor: AppColors.lagoonTeal,
            ),
          ],
        ),
      ),
    );
  }
}
