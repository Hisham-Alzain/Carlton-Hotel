import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Trailing control style for a [CustomSelectableCard].
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
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected ? AppColors.primary : AppColors.hairline,
              width: 1.18,
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0A000000),
                blurRadius: 3,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: 15.18,
              vertical: 13.18,
            ),
            child: Row(
              children: [
                if (leading != null) ...[leading!, const SizedBox(width: 12)],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontFamily: 'Plus Jakarta Sans',
                          fontSize: 14,
                          fontWeight: FontWeight.w500,
                          color: AppColors.navLabel,
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: const TextStyle(
                            fontFamily: 'DM Sans',
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (trailingText != null) ...[
                  Text(
                    trailingText!,
                    style: const TextStyle(
                      fontFamily: 'Plus Jakarta Sans',
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textMuted,
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
                _Control(control: control, selected: selected),
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

  const _Control({required this.control, required this.selected});

  @override
  Widget build(BuildContext context) {
    if (control == SelectableControl.radio) {
      return Container(
        width: 20,
        height: 20,
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.transparent,
          shape: BoxShape.circle,
          border: Border.all(
            color: selected
                ? AppColors.primary
                : AppColors.calendarDisabledText,
            width: 1.18,
          ),
        ),
        child: selected
            ? Center(
                child: Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                ),
              )
            : null,
      );
    }

    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        color: selected ? AppColors.primary : AppColors.neutralIconBg,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: const Color(0x1A000000), width: 1.18),
      ),
      child: selected
          ? Center(
              child: SvgPicture.asset(
                'assets/icons/check.svg',
                width: 13,
                height: 13,
                colorFilter: const ColorFilter.mode(
                  Colors.white,
                  BlendMode.srcIn,
                ),
              ),
            )
          : null,
    );
  }
}
