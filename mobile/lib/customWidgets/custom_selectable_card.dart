import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Trailing control style for a [CustomSelectableCard].
/// TODO:move to enums
enum SelectableControl { checkbox, radio }

/// Selectable list tile shared by Add-Ons (checkbox) and Payment methods
/// (radio). Matched to Figma: white 12px card, primary border + primary control
/// when [selected], 14px title / 11px subtitle, optional leading icon chip and
/// trailing price. `CustomChoiceCard` couldn't be this — it has no selected
/// state and a fixed chevron.
class CustomSelectableCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;
  final SelectableControl control;
  final Widget? leading;
  final String? trailingText;

  const CustomSelectableCard({
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
    this.control = SelectableControl.checkbox,
    this.leading,
    this.trailingText,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    return Material(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? AppColors.primary : AppColors.black06,
              width: 1,
            ),
            boxShadow: const [
              BoxShadow(
                color: AppColors.black04,
                blurRadius: 3,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Row(
              spacing: 10,
              children: [
                if (leading != null) ...[leading!],
                Expanded(
                  child: Column(
                    spacing: 10,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: textStyle.labelLarge?.copyWith(
                          color: AppColors.inkBlack,
                        ),
                      ),
                      if (subtitle != null) ...[
                        Text(
                          subtitle!,
                          style: textStyle.labelSmall?.copyWith(
                            fontFamily: 'DM Sans',
                            color: AppColors.taupeBrown,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (trailingText != null) ...[
                  Text(
                    trailingText!,
                    style: textStyle.labelLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: AppColors.taupeBrown,
                    ),
                  ),
                ],
                _Control(
                  control: control,
                  selected: selected,
                  onChanged: onTap,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Control extends StatelessWidget {
  final SelectableControl control;
  final bool selected;

  /// Fired when the control is toggled; mirrors the card's own tap.
  final VoidCallback onChanged;

  const _Control({
    required this.control,
    required this.selected,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    // Both are styled through the app theme (checkboxTheme / radioTheme).
    if (control == SelectableControl.radio) {
      return RadioGroup<bool>(
        groupValue: selected,
        onChanged: (_) => onChanged(),
        child: const Radio<bool>(value: true),
      );
    }

    return Checkbox(value: selected, onChanged: (_) => onChanged());
  }
}
