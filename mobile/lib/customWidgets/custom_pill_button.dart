import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// A pill-shaped tappable button: an optional leading icon (Material [icon] or
/// an [iconAsset] SVG, both tinted [foregroundColor]) beside a [label]. Covers
/// the filled and outlined pill variants used across the app (Reserve, Call,
/// Download, Sign Out …) so those don't each hand-roll a
/// `Material → InkWell → Container → Row`.
class CustomPillButton extends StatelessWidget {
  final String label;
  final VoidCallback onTap;
  final IconData? icon;
  final String? iconAsset;
  final Color backgroundColor;
  final Color foregroundColor;

  /// Null = no outline.
  final Color? borderColor;
  final double radius;

  /// Fixed height (centred content); when null the [padding] sizes the button.
  final double? height;
  final EdgeInsets padding;

  /// Stretch to the full available width.
  final bool expand;
  final double iconSize;
  final double? fontSize;
  final FontWeight fontWeight;
  final String? fontFamily;

  const CustomPillButton({
    required this.label,
    required this.onTap,
    this.icon,
    this.iconAsset,
    this.backgroundColor = AppColors.white,
    this.foregroundColor = AppColors.primary,
    this.borderColor,
    this.radius = 10,
    this.height,
    this.padding = const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    this.expand = false,
    this.iconSize = 16,
    this.fontSize,
    this.fontWeight = FontWeight.w600,
    this.fontFamily,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final row = Row(
      mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      spacing: 6,
      children: [
        if (iconAsset != null)
          SvgPicture.asset(
            iconAsset!,
            width: iconSize,
            height: iconSize,
            colorFilter: ColorFilter.mode(foregroundColor, BlendMode.srcIn),
          )
        else if (icon != null)
          Icon(icon, size: iconSize, color: foregroundColor),
        Text(
          label,
          style: Get.textTheme.labelLarge?.copyWith(
            fontFamily: fontFamily,
            fontSize: fontSize,
            fontWeight: fontWeight,
            color: foregroundColor,
          ),
        ),
      ],
    );

    final button = Material(
      color: backgroundColor,
      borderRadius: BorderRadius.circular(radius),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(radius),
        child: Container(
          height: height,
          alignment: Alignment.center,
          padding: height == null ? padding : null,
          decoration: borderColor != null
              ? BoxDecoration(
                  borderRadius: BorderRadius.circular(radius),
                  border: Border.all(color: borderColor!),
                )
              : null,
          child: row,
        ),
      ),
    );

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
