import 'package:flutter/material.dart';

class CustomTextButton extends StatelessWidget {
  final String text;
  final void Function()? onPressed;
  final TextStyle? textStyle;

  const CustomTextButton({
    required this.text,
    this.onPressed,
    this.textStyle,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onPressed,
      child: Text(text, style: textStyle),
    );
  }
}
