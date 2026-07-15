import 'package:flutter/material.dart';

class CustomFilledButton extends StatelessWidget {
  final double? height;
  final double? width;
  final void Function()? onPressed;
  final Widget? child;

  /// Plain-text alternative to [child]; exactly one of the two is used.
  final String? label;
  final TextStyle? textStyle;
  final Color? backgroundColor;
  final Color? foregroundColor;
  final OutlinedBorder? shape;

  /// While true the button is disabled and shows a small spinner instead of
  /// its content — the shared submit-in-flight treatment.
  final bool isLoading;

  const CustomFilledButton({
    this.height,
    this.width,
    required this.onPressed,
    this.child,
    this.label,
    this.textStyle,
    this.backgroundColor,
    this.foregroundColor,
    this.shape,
    this.isLoading = false,
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
      onPressed: isLoading ? null : onPressed,
      //TODO: make this a custom indicator using the lgog
      child: isLoading
          ? const SizedBox(
              height: 18,
              width: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: Colors.white,
              ),
            )
          : child ?? Text(label!),
    );
  }
}
