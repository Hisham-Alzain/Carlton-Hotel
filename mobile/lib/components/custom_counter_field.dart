import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Title/subtitle row with a −/+ stepper, used for the Adults / Children guest
/// counts on Plan Your Stay. Controlled: it renders [value] and reports the
/// requested value through [onChanged], clamping to [minCount]..[maxCount]
/// itself so callers never receive an out-of-range value. The − button greys out
/// at [minCount] and + at [maxCount]. Styling is from Figma (white card, 12px
/// radius, 32px round buttons — teal +, grey −, primary 18px count).
class CustomCounterField extends StatelessWidget {
  final String title;
  final String? subtitle;
  final int value;
  final ValueChanged<int> onChanged;
  final int minCount;
  final int maxCount;

  const CustomCounterField({
    required this.title,
    required this.value,
    required this.onChanged,
    this.subtitle,
    this.minCount = 0,
    this.maxCount = 99,
    super.key,
  }) : assert(minCount <= maxCount);

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final canDecrement = value > minCount;
    final canIncrement = value < maxCount;

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
                  style: textStyle.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: AppColors.inkBlack,
                  ),
                ),
                if (subtitle != null) ...[
                  Text(
                    subtitle!,
                    style: textStyle.labelMedium?.copyWith(
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
                    onPressed: canDecrement
                        ? () => onChanged((value - 1).clamp(minCount, maxCount))
                        : null,
                    icon: Icon(
                      Icons.remove,
                      color: canDecrement
                          ? AppColors.inkBlack
                          : AppColors.pearlGrey,
                    ),
                  ),
                ),

                Text(
                  '$value',
                  textAlign: TextAlign.center,
                  style: textStyle.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),

                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: canIncrement
                        ? AppColors.primary
                        : AppColors.whisperGrey,
                  ),
                  child: IconButton(
                    onPressed: canIncrement
                        ? () => onChanged((value + 1).clamp(minCount, maxCount))
                        : null,
                    icon: Icon(
                      Icons.add,
                      color: canIncrement
                          ? AppColors.white
                          : AppColors.pearlGrey,
                    ),
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
