import 'package:carlton/models/preference_option.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// A labelled dropdown field (Figma Preferences): a primary bold [label] above a
/// light-grey box showing the selected [value] with a chevron. Tapping opens an
/// inline dropdown list of [options] (not a bottom sheet) that matches the
/// field width; the selected row is highlighted. Text and icons are primary.
class CustomDropdownField extends StatelessWidget {
  final String label;
  final String value;
  final List<PreferenceOption> options;
  final String selectedId;
  final ValueChanged<PreferenceOption> onSelected;

  const CustomDropdownField({
    required this.label,
    required this.value,
    required this.options,
    required this.selectedId,
    required this.onSelected,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final selected = options.firstWhere(
      (o) => o.id == selectedId,
      orElse: () => options.first,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      spacing: 8,
      children: [
        Text(
          label,
          style: textStyle.labelLarge?.copyWith(
            fontWeight: FontWeight.w700,
            color: AppColors.primary,
          ),
        ),
        LayoutBuilder(
          builder: (context, constraints) => MenuAnchor(
            crossAxisUnconstrained: false,
            style: MenuStyle(
              backgroundColor: const WidgetStatePropertyAll(AppColors.white),
              elevation: const WidgetStatePropertyAll(4),
              shadowColor: const WidgetStatePropertyAll(AppColors.black10),
              padding: const WidgetStatePropertyAll(
                EdgeInsets.symmetric(vertical: 6),
              ),
              minimumSize: WidgetStatePropertyAll(
                Size(constraints.maxWidth, 0),
              ),
              maximumSize: WidgetStatePropertyAll(
                Size(constraints.maxWidth, double.infinity),
              ),
              shape: WidgetStatePropertyAll(
                RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: const BorderSide(color: AppColors.black06),
                ),
              ),
            ),
            menuChildren: [
              for (final option in options)
                _MenuRow(
                  option: option,
                  selected: option.id == selectedId,
                  width: constraints.maxWidth,
                  onTap: () => onSelected(option),
                ),
            ],
            builder: (context, controller, _) {
              final bool open = controller.isOpen;
              return Material(
                color: AppColors.whisperGrey,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  onTap: () => open ? controller.close() : controller.open(),
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 14,
                    ),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: open ? AppColors.primary : AppColors.black06,
                        width: open ? 1.5 : 1,
                      ),
                    ),
                    child: Row(
                      spacing: 10,
                      children: [
                        if (selected.iconAsset != null) _icon(selected),
                        Expanded(
                          child: Text(
                            value,
                            style: textStyle.labelLarge?.copyWith(
                              fontWeight: FontWeight.w500,
                              color: AppColors.inkBlack,
                            ),
                          ),
                        ),
                        AnimatedRotation(
                          turns: open ? 0.5 : 0,
                          duration: const Duration(milliseconds: 150),
                          child: const Icon(
                            Icons.keyboard_arrow_down,
                            size: 22,
                            color: AppColors.taupeBrown,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _icon(PreferenceOption option) {
    if (option.iconAsset != null) {
      return SvgPicture.asset(
        option.iconAsset!,
        width: 20,
        height: 20,
        colorFilter: const ColorFilter.mode(
          AppColors.inkBlack,
          BlendMode.srcIn,
        ),
      );
    }
    return Icon(option.icon, size: 20, color: AppColors.inkBlack);
  }
}

class _MenuRow extends StatelessWidget {
  final PreferenceOption option;
  final bool selected;
  final double width;
  final VoidCallback onTap;

  const _MenuRow({
    required this.option,
    required this.selected,
    required this.width,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Material(
      color: selected ? AppColors.primary08 : Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          width: width,
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            spacing: 12,
            children: [
              SizedBox(
                width: 20,
                height: 20,
                child: option.iconAsset != null
                    ? SvgPicture.asset(
                        option.iconAsset!,
                        colorFilter: const ColorFilter.mode(
                          AppColors.primary,
                          BlendMode.srcIn,
                        ),
                      )
                    : Icon(option.icon, size: 20, color: AppColors.primary),
              ),
              Expanded(
                child: Text(
                  option.label,
                  style: textStyle.labelLarge?.copyWith(
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    color: AppColors.primary,
                  ),
                ),
              ),
              if (selected)
                SvgPicture.asset(
                  'assets/icons/check.svg',
                  width: 18,
                  height: 18,
                  colorFilter: const ColorFilter.mode(
                    AppColors.primary,
                    BlendMode.srcIn,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
