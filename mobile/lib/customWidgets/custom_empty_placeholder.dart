import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_outlined_button.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

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
    this.secondaryLabel,
    this.onSecondary,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          spacing: 20,
          children: [
            _icon(),
            Text(
              title,
              textAlign: TextAlign.center,
              style: textStyle.titleLarge?.copyWith(
                fontWeight: FontWeight.w500,
              ),
            ),
            if (subtitle != null)
              Text(
                subtitle!,
                textAlign: TextAlign.center,
                style: textStyle.labelLarge?.copyWith(
                  color: subtitleColor,
                  fontWeight: FontWeight.w500,
                ),
              ),
            if (primaryLabel != null && onPrimary != null)
              CustomFilledButton(
                width: 300,
                onPressed: onPrimary,
                elevation: 10,
                child: Text(primaryLabel!),
              ),
            if (secondaryLabel != null && onSecondary != null)
              CustomOutlinedButton(
                backgroundColor: Color(0x8FECECEC),
                width: 300,
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
}
