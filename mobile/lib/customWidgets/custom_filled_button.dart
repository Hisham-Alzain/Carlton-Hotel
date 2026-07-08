import 'package:flutter/material.dart';

class CustomFilledButton extends StatelessWidget {
  final double? height;
  final double? width;
  final void Function()? onPressed;
  final Widget? child;
  final TextStyle? textStyle;
  final Color? backgroundColor;
  final Color? foregroundColor;
  final OutlinedBorder? shape;

  const CustomFilledButton({
    this.height,
    this.width,
    required this.onPressed,
    required this.child,
    this.textStyle,
    this.backgroundColor,
    this.foregroundColor,
    this.shape,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context).filledButtonTheme.style;

    return FilledButton(
      style: theme?.copyWith(
        fixedSize: WidgetStatePropertyAll(
          Size(width ?? double.infinity, height ?? 50),
        ),
        textStyle: WidgetStatePropertyAll(textStyle),
        backgroundColor: WidgetStatePropertyAll(backgroundColor),
        foregroundColor: WidgetStatePropertyAll(foregroundColor),
        shape: WidgetStatePropertyAll(shape),
        elevation: const WidgetStatePropertyAll(0),
        padding: const WidgetStatePropertyAll(EdgeInsets.zero),
      ),
      onPressed: onPressed,
      child: child,
    );
  }
}
