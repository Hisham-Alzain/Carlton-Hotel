import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ButtonsContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final String? title;
  final TextStyle? titleTextStyle;
  final Widget buttons;
  final Widget child;

  const ButtonsContainer({
    this.height,
    this.width,
    this.title,
    this.titleTextStyle,
    required this.buttons,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.featherGrey,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.linenGrey),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        spacing: 10,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (title != null) Text('$title', style: titleTextStyle),
              buttons,
            ],
          ),
          child,
        ],
      ),
    );
  }
}

/// A rounded, solid-filled container used all over the app for status pills,
/// price badges, chips and summary strips.
///
/// It only covers the plain `Container(padding + BoxDecoration(color,
/// borderRadius))` shape — anything that also needs a border, gradient or
/// shadow should stay a bespoke `Container`.
class PillContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final Widget child;
  final Color backgroundColor;
  final EdgeInsetsGeometry padding;
  final double radius;

  const PillContainer({
    this.height,
    this.width,
    required this.child,
    required this.backgroundColor,
    this.padding = const EdgeInsets.all(10),
    this.radius = 6,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      padding: padding,
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(radius),
      ),
      child: child,
    );
  }
}

class SectionContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final int? sectionIndex;
  final String? title;
  final String? buttonText;
  final IconData? icon;
  final void Function()? onPressed;
  final Color? iconColor;
  final Widget child;

  const SectionContainer({
    this.height,
    this.width,
    this.sectionIndex,
    this.title,
    this.buttonText = 'Discover All',
    this.icon = Icons.arrow_forward,
    this.onPressed,
    this.iconColor,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final textStyle = Get.textTheme;
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        spacing: 10,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (title != null)
                Text(
                  title!,
                  style: textStyle.titleMedium?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              if (buttonText != null || icon != null)
                TextButton(
                  onPressed: onPressed,
                  child: InvertedRowTextComponent(
                    spacing: 10,
                    text: buttonText ?? '',
                    textStyle: textStyle.labelLarge?.copyWith(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w500,
                    ),
                    icon: icon,
                    iconColor: iconColor,
                  ),
                ),
            ],
          ),

          child,
        ],
      ),
    );
  }
}
