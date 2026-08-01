import 'package:carlton/customWidgets/custom_indicators.dart';
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
  final double? elevation;
  final bool isLoading;

  const CustomFilledButton({
    this.height,
    this.width,
    required this.onPressed,
    this.child,
    this.textStyle,
    this.backgroundColor,
    this.foregroundColor,
    this.shape,
    this.elevation,
    this.isLoading = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final buttonStyle = Theme.of(context).filledButtonTheme.style;
    // `fixedSize` is ignored by ButtonStyleButton when the width is infinite,
    // so a `width: double.infinity` caller has to be stretched explicitly. A
    // null width still means "size to the child" — some call sites put two of
    // these side by side in a Row, where an infinite width would overflow.
    final isFullWidth = width == double.infinity;

    final button = FilledButton(
      style: buttonStyle?.copyWith(
        fixedSize: WidgetStatePropertyAll(
          Size(width ?? double.infinity, height ?? 50),
        ),
        textStyle: WidgetStatePropertyAll(textStyle),
        backgroundColor: WidgetStatePropertyAll(backgroundColor),
        foregroundColor: WidgetStatePropertyAll(foregroundColor),
        shape: WidgetStatePropertyAll(shape),
        elevation: WidgetStatePropertyAll(elevation),
      ),
      onPressed: isLoading ? null : onPressed,
      child: isLoading
          ? const SpinningIconIndicator(size: 22, color: Colors.white)
          : child,
    );

    return isFullWidth
        ? SizedBox(width: double.infinity, child: button)
        : button;
  }
}
