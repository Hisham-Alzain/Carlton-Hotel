import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// The brand mark in a circular tinted badge — the app bar's home affordance at
/// full size, and a label icon when shrunk (see the AI Concierge pill).
class CustomLogoAvatar extends StatelessWidget {
  /// The icon's share of the overall diameter, preserved at every [size] so the
  /// mark keeps the proportions it was drawn at (25 of 60).
  static const _iconRatio = 25 / 60;

  final VoidCallback? onTap;

  /// Diameter. Defaults to the app-bar size.
  final double size;

  /// White ring around the badge. Zero omits it entirely — `Border.all` still
  /// paints a hairline at width 0, which reads as an unwanted ring on a tinted
  /// background.
  final double borderWidth;

  const CustomLogoAvatar({
    this.onTap,
    this.size = 60,
    this.borderWidth = 2,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final iconSize = size * _iconRatio;

    return InkWell(
      onTap: onTap,

      child: Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.primary06,
          shape: BoxShape.circle,
          border: borderWidth > 0
              ? Border.all(color: Colors.white, width: borderWidth)
              : null,
        ),
        child: SvgPicture.asset(
          'assets/icons/badge_logo.svg',
          width: iconSize,
          height: iconSize,
        ),
      ),
    );
  }
}
