import 'package:flutter/material.dart';

class CustomElevatedButton extends StatelessWidget {
  final double? height;
  final double? width;
  final void Function()? onPressed;
  final Widget? child;
  final TextStyle? textStyle;
  final Color? backgroundColor;
  final Color? foregroundColor;
  final double? elevation;
  final OutlinedBorder? shape;

  const CustomElevatedButton({
    super.key,
    this.height,
    this.width,
    required this.onPressed,
    required this.child,
    this.textStyle,
    this.backgroundColor,
    this.foregroundColor,
    this.elevation,
    this.shape,
  });
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context).elevatedButtonTheme.style;

    return ElevatedButton(
      style: theme?.copyWith(
        fixedSize: WidgetStatePropertyAll(
          Size(width ?? double.infinity, height ?? 50),
        ),
        textStyle: WidgetStatePropertyAll(textStyle),
        backgroundColor: WidgetStatePropertyAll(backgroundColor),
        foregroundColor: WidgetStatePropertyAll(foregroundColor),
        elevation: WidgetStatePropertyAll(elevation),
        shape: WidgetStatePropertyAll(shape),
      ),
      onPressed: onPressed,
      child: child,
    );
  }
}
