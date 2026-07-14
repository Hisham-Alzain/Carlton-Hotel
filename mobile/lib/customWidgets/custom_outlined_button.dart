import 'package:flutter/material.dart';

class CustomOutlinedButton extends StatelessWidget {
  final double height;
  final double width;
  final Widget? child;
  final String? labelText;
  final TextStyle? labelStyle;
  final Color? borderColor;
  final double borderWidth;
  final Color? backgroundColor;
  final Color? foregroundColor;

  final double? elevation;
  final void Function()? onPressed;

  const CustomOutlinedButton({
    required this.height,
    required this.width,
    required this.child,
    this.labelText,
    this.labelStyle,
    this.borderColor,
    this.borderWidth = 1.5,
    this.backgroundColor,
    this.foregroundColor,
    this.elevation,
    this.onPressed,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final outlineButtonTheme = Theme.of(context).outlinedButtonTheme.style;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (labelText != null && labelText!.isNotEmpty)
          Text(labelText!, style: labelStyle),
        OutlinedButton(
          onPressed: onPressed,
          style: outlineButtonTheme?.copyWith(
            fixedSize: WidgetStatePropertyAll(Size(width, height)),
            minimumSize: WidgetStatePropertyAll(Size(width, height)),
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            backgroundColor: backgroundColor != null
                ? WidgetStatePropertyAll(backgroundColor)
                : null,
            foregroundColor: foregroundColor != null
                ? WidgetStatePropertyAll(foregroundColor)
                : null,
            side: borderColor != null
                ? WidgetStatePropertyAll(
                    BorderSide(color: borderColor!, width: borderWidth),
                  )
                : null,
            elevation: elevation != null
                ? WidgetStatePropertyAll(elevation)
                : null,
          ),
          child: child,
        ),
      ],
    );
  }
}
