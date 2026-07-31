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

/// A rounded, filled container used all over the app for status pills, price
/// badges, chips and summary strips.
///
/// Covers `Container(padding + BoxDecoration(fill, borderRadius, border,
/// boxShadow))`. The fill is either a flat [backgroundColor] or a [gradient] —
/// exactly one, since `BoxDecoration` rejects both at once. Anything needing a
/// non-rounded shape should still be a bespoke `Container` (or
/// [CustomIconChip] for circles).
class PillContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final Widget child;

  /// Flat fill. Mutually exclusive with [gradient].
  final Color? backgroundColor;

  /// Gradient fill. Mutually exclusive with [backgroundColor].
  final Gradient? gradient;

  final EdgeInsetsGeometry padding;
  final double radius;
  final BoxBorder? border;

  /// Drop shadow. Null (the default) means none, so existing call sites are
  /// unaffected.
  final List<BoxShadow>? boxShadow;

  const PillContainer({
    this.height,
    this.width,
    required this.child,
    this.backgroundColor,
    this.gradient,
    this.padding = const EdgeInsets.all(10),
    this.radius = 6,
    this.border,
    this.boxShadow,
    super.key,
  }) : assert(
         (backgroundColor == null) != (gradient == null),
         'PillContainer needs exactly one of backgroundColor or gradient',
       );

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      padding: padding,
      decoration: BoxDecoration(
        color: backgroundColor,
        gradient: gradient,
        borderRadius: BorderRadius.circular(radius),
        border: border,
        boxShadow: boxShadow,
      ),
      child: child,
    );
  }
}

/// A fixed-size square holding a single centred icon on a tinted fill — the
/// leading badge on list rows, panels, quick actions and sheet headers.
///
/// Distinct from [PillContainer], which sizes to its child and is always a
/// rounded rectangle. This one is always [size] × [size], always centres its
/// child, and can be a circle.
class CustomIconChip extends StatelessWidget {
  final double size;
  final Color backgroundColor;
  final Widget child;
  final BoxBorder? border;

  /// Corner radius. Null renders a circle — use [CustomIconChip.circle] rather
  /// than passing null directly.
  final double? radius;

  /// [radius] is required so the shape is always a deliberate choice at the
  /// call site rather than an inherited default.
  const CustomIconChip({
    required this.size,
    required this.backgroundColor,
    required this.child,
    required this.radius,
    this.border,
    super.key,
  });

  const CustomIconChip.circle({
    required this.size,
    required this.backgroundColor,
    required this.child,
    this.border,
    super.key,
  }) : radius = null;

  @override
  Widget build(BuildContext context) {
    final isCircle = radius == null;

    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      // The conditional borderRadius is load-bearing: BoxDecoration asserts a
      // circle carries no borderRadius, so passing both throws at build time.
      decoration: BoxDecoration(
        color: backgroundColor,
        shape: isCircle ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: isCircle ? null : BorderRadius.circular(radius!),
        border: border,
      ),
      child: child,
    );
  }
}

class SectionContainer extends StatelessWidget {
  final double? height;
  final double? width;
  final String? title;
  final String? buttonText;
  final IconData? icon;
  final void Function()? onPressed;
  final Color? iconColor;
  final Widget child;

  const SectionContainer({
    this.height,
    this.width,
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
    final TextTheme textStyle = Get.textTheme;
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
