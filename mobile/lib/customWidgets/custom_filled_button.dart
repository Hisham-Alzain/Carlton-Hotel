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

  //TODO: check if able to send custom text style
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
    final theme = Theme.of(context).filledButtonTheme.style;
    // `fixedSize` is ignored by ButtonStyleButton when the width is infinite,
    // so a `width: double.infinity` caller has to be stretched explicitly. A
    // null width still means "size to the child" — some call sites put two of
    // these side by side in a Row, where an infinite width would overflow.
    final stretch = width == double.infinity;

    final button = FilledButton(
      style: theme?.copyWith(
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
      //TODO: make this a custom indicator using the icon
      child: isLoading
          ? const SizedBox(
              height: 18,
              width: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: Colors.white,
              ),
            )
          : child,
    );

    return stretch ? SizedBox(width: double.infinity, child: button) : button;
  }
}
