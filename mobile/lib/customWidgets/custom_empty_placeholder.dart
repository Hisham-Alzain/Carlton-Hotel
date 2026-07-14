import 'package:carlton/customWidgets/custom_elevated_button.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

enum EmptyActionStyle { neutral, filled }

class CustomEmptyPlaceholder extends StatelessWidget {
  final String? iconPath;
  final double iconWidth;
  final double iconHeight;
  final Widget? iconWidget;

  final String title;
  final String? subtitle;
  final Color titleColor;
  final Color subtitleColor;

  final String? primaryLabel;
  final VoidCallback? onPrimary;

  final EmptyActionStyle primaryStyle;

  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  const CustomEmptyPlaceholder({
    required this.title,
    this.iconPath,
    this.iconWidget,
    this.primaryLabel,
    this.onPrimary,
    this.iconWidth = 70,
    this.iconHeight = 70,
    this.subtitle,
    this.titleColor = AppColors.textPrimary,
    this.subtitleColor = AppColors.textPrimary,
    this.primaryStyle = EmptyActionStyle.neutral,
    this.secondaryLabel,
    this.onSecondary,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 40),
        child: Column(
          mainAxisSize: MainAxisSize.min,

          spacing: 12,
          children: [
            _icon(),
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                title,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: titleColor,
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (subtitle != null)
              Text(
                subtitle!,
                textAlign: TextAlign.center,
                style: TextStyle(color: subtitleColor, fontSize: 13),
              ),
            if (onPrimary != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _buildPrimary(),
              ),
            if (secondaryLabel != null && onSecondary != null)
              CustomOutlinedButton(
                height: 50,
                width: double.infinity,
                onPressed: onSecondary,
                child: Text(secondaryLabel!),
              ),
          ],
        ),
      ),
    );
  }

  Widget _icon() {
    if (iconWidget != null) return iconWidget!;
    if (iconPath!.endsWith('.svg')) {
      return SvgPicture.asset(iconPath!, width: iconWidth, height: iconHeight);
    }
    return Image.asset(iconPath!, width: iconWidth, height: iconHeight);
  }

  Widget _buildPrimary() {
    return SizedBox(
      width: double.infinity,
      child: primaryStyle == EmptyActionStyle.filled
          ? CustomFilledButton(onPressed: onPrimary, child: Text(primaryLabel!))
          : CustomElevatedButton(
              onPressed: onPrimary,
              child: Text(primaryLabel!),
            ),
    );
  }
}
