import 'package:carlton/enums/enums.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Selectable list tile shared by Add-Ons (checkbox) and Payment methods
/// (radio). Matched to Figma: white 12px card, primary border + primary control
/// when [selected], 14px title / 11px subtitle, optional leading icon chip and
/// trailing price. `CustomChoiceCard` couldn't be this — it has no selected
/// state and a fixed chevron.
///
/// Provide the leading either as a ready-made [leading] widget (e.g. a payment
/// logo) or as an [iconPath] the card wraps in its own rounded icon badge
/// (tinted with [iconColor] on an [iconBackgroundColor] background).
class CustomSelectableCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;
  final SelectableControl control;
  final Widget? leading;

  /// SVG glyph the card renders in its built-in rounded badge, used when no
  /// explicit [leading] is given. Tinted with [iconColor] on [iconBackgroundColor].
  final String? iconPath;
  final Color iconColor;
  final Color iconBackgroundColor;
  final String? trailingText;

  const CustomSelectableCard({
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
    this.control = SelectableControl.checkbox,
    this.leading,
    this.iconPath,
    this.iconColor = AppColors.primary,
    this.iconBackgroundColor = AppColors.pearlCream,
    this.trailingText,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final leadingWidget =
        leading ?? (iconPath != null ? _iconBadge(iconPath!) : null);
    return Card(
      color: AppColors.white,
      elevation: 1,
      shadowColor: AppColors.black04,
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: selected ? AppColors.primary : AppColors.black06,
          width: 1,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            spacing: 10,
            children: [
              if (leadingWidget != null) ...[leadingWidget],
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
              _Control(control: control, selected: selected),
            ],
          ),
        ),
      ),
    );
  }

  /// The [iconPath] glyph wrapped in the card's rounded icon badge.
  Widget _iconBadge(String path) => Container(
    width: 40,
    height: 40,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: selected ? AppColors.primary08 : iconBackgroundColor,
      borderRadius: BorderRadius.circular(10),
    ),
    child: SvgPicture.asset(
      path,
      width: 16,
      height: 16,
      colorFilter: ColorFilter.mode(iconColor, BlendMode.srcIn),
    ),
  );
}

/// Custom (non-Material) checkbox / radio driven purely by [selected]; the tap
/// is handled by the enclosing card's InkWell, so this is display-only.
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
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: selected ? AppColors.primary : AppColors.pearlGrey,
            width: 2,
          ),
        ),
        child: selected
            ? Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.primary,
                ),
              )
            : null,
      );
    }

    return Container(
      width: 20,
      height: 20,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(6),
        color: selected ? AppColors.primary : Colors.transparent,
        border: selected
            ? null
            : Border.all(color: AppColors.black10, width: 1),
      ),
      child: selected
          ? SvgPicture.asset(
              'assets/icons/check.svg',
              width: 12,
              height: 12,
              colorFilter: const ColorFilter.mode(
                Colors.white,
                BlendMode.srcIn,
              ),
            )
          : null,
    );
  }
}
