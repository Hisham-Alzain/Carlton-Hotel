import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Horizontal single-select filter row used at the top of the Discover screen
/// (Figma 2195:2648 — node no longer present; the file now covers only Home +
/// Check-In). The selected chip is a filled primary pill with white
/// text; the rest are light grey outlined pills. Purely presentational — the
/// caller owns the selected index and reacts to [onSelected].
class CustomFilterChips extends StatelessWidget {
  final List<String> labels;
  final int selectedIndex;
  final ValueChanged<int> onSelected;

  const CustomFilterChips({
    required this.labels,
    required this.selectedIndex,
    required this.onSelected,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return SizedBox(
      height: 34,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: labels.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, i) {
          final bool selected = i == selectedIndex;
          return Material(
            color: selected ? AppColors.primary : AppColors.whisperGrey,
            borderRadius: BorderRadius.circular(20),
            child: InkWell(
              onTap: () => onSelected(i),
              borderRadius: BorderRadius.circular(20),
              child: Container(
                alignment: Alignment.center,
                padding: const EdgeInsets.symmetric(horizontal: 18),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: selected ? AppColors.primary : AppColors.black10,
                  ),
                ),
                child: Text(
                  labels[i],
                  style: textStyle.labelMedium?.copyWith(
                    fontFamily: 'DM Sans',
                    fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                    color: selected ? AppColors.white : AppColors.graphite,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
