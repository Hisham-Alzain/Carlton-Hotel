import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Title/subtitle row with a −/+ stepper, used for the Adults / Children guest
/// counts on Plan Your Stay. Controlled: it renders [value] and reports the
/// requested value through [onChanged], clamping to [min]..[max] itself so
/// callers never receive an out-of-range value. The − button greys out at [min]
/// and + at [max]. Styling is from Figma (white card, 12px radius, 32px round
/// buttons — teal +, grey −, primary 18px count).
class CustomCounterField extends StatelessWidget {
  final String title;
  final String? subtitle;
  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  const CustomCounterField({
    required this.title,
    required this.value,
    required this.onChanged,
    this.subtitle,
    this.min = 0,
    this.max = 99,
    super.key,
  }) : assert(min <= max);

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Card(
      color: AppColors.white,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(12),
      ),
      margin: const EdgeInsets.all(10),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Row(
          spacing: 10,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.inkBlack,
                  ),
                ),
                if (subtitle != null) ...[
                  Text(
                    subtitle!,
                    style: textTheme.labelMedium?.copyWith(
                      fontFamily: 'DM Sans',
                      color: AppColors.taupeBrown,
                    ),
                  ),
                ],
              ],
            ),
            Row(
              spacing: 20,
              children: [
                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.whisperGrey,
                  ),
                  child: IconButton(
                    onPressed: () => onChanged(value - 1),
                    icon: const Icon(Icons.remove, color: AppColors.inkBlack),
                  ),
                ),

                Text(
                  '$value',
                  textAlign: TextAlign.center,
                  style: textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),

                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.primary,
                  ),
                  child: IconButton(
                    onPressed: () => onChanged(value + 1),
                    icon: const Icon(Icons.add, color: AppColors.white),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
