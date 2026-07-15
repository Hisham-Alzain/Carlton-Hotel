import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

class CustomCard extends StatelessWidget {
  final double? height;
  final double? width;
  final EdgeInsetsGeometry? padding;
  final Color? color;
  final Color? borderColor;
  final double borderWidth;
  final double? borderRadius;
  final VoidCallback? onTap;
  final Widget child;

  const CustomCard({
    this.height,
    this.width,
    this.padding,
    this.color,
    this.borderColor,
    this.borderWidth = 1,
    this.borderRadius,
    this.onTap,
    required this.child,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final cardTheme = Theme.of(context).cardTheme;
    final radius = BorderRadius.circular(
      borderRadius ??
          (cardTheme.shape as RoundedRectangleBorder?)?.borderRadius
              .resolve(Directionality.of(context))
              .topLeft
              .x ??
          16,
    );
    final resolvedBorderColor =
        borderColor ?? (cardTheme.shape as RoundedRectangleBorder?)?.side.color;

    return SizedBox(
      height: height,
      width: width,
      child: Material(
        color: color ?? cardTheme.color,
        borderRadius: radius,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            decoration: resolvedBorderColor != null
                ? BoxDecoration(
                    border: Border.all(
                      color: resolvedBorderColor,
                      width: borderWidth,
                    ),
                    borderRadius: radius,
                  )
                : null,
            padding: padding ?? const EdgeInsets.all(16),
            child: child,
          ),
        ),
      ),
    );
  }
}

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
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.cardBorder),
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
