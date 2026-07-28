import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The reservation time-slot grid (Figma): wrapping chips, three per row. The
/// selected slot fills primary with white text; the rest are grey.
class CustomTimeSlotSelector extends StatelessWidget {
  final List<String> slots;
  final String? selected;
  final ValueChanged<String> onSelected;

  const CustomTimeSlotSelector({
    required this.slots,
    required this.selected,
    required this.onSelected,
    super.key,
  });


//TODO: move design to theme
  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 10.0;
        final width = (constraints.maxWidth - spacing * 2) / 3;

        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final slot in slots)
              SizedBox(
                width: width,
                child: Material(
                  color: slot == selected
                      ? AppColors.primary
                      : AppColors.whisperGrey,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    onTap: () => onSelected(slot),
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      height: 40,
                      alignment: Alignment.center,
                      child: Text(
                        slot,
                        style: textStyle.labelMedium?.copyWith(
                          fontFamily: 'DM Sans',
                          fontWeight: FontWeight.w600,
                          color: slot == selected
                              ? AppColors.white
                              : AppColors.inkBlack,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
