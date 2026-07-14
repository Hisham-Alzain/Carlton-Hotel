import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:pinput/pinput.dart';

class CustomPinput extends StatelessWidget {
  final TextEditingController controller;
  final int length;
  final String? Function(String?)? validator;
  final void Function(String)? onComplete;

  final Color? fillColor;
  final Color? textColor;
  final double? boxSize;
  final bool hasError;
  final Color? errorBorderColor;

  const CustomPinput({
    super.key,
    required this.controller,
    required this.length,
    required this.validator,
    required this.onComplete,
    this.fillColor,
    this.textColor,
    this.boxSize,
    this.hasError = false,
    this.errorBorderColor,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final TextStyle errorStyle = Get.textTheme.labelLarge!.copyWith(
      color: Colors.red,
      fontSize: 10,
    );
    final size = boxSize ?? 65;
    final PinTheme defaultPinTheme = PinTheme(
      width: size,
      height: size,
      textStyle: (textStyle.bodySmall ?? const TextStyle()).copyWith(
        color: textColor,
      ),
      decoration: BoxDecoration(
        color: fillColor,
        borderRadius: BorderRadius.circular(15),
      ),
    );
    return Pinput(
      controller: controller,
      length: length,
      defaultPinTheme: defaultPinTheme,
      errorPinTheme: defaultPinTheme.copyDecorationWith(
        border: Border.all(color: errorBorderColor ?? Colors.red, width: 1.4),
      ),
      forceErrorState: hasError,
      showCursor: true,
      validator: validator,
      errorTextStyle: errorStyle,
      onCompleted: onComplete,
      closeKeyboardWhenCompleted: true,
      keyboardType: TextInputType.number,
    );
  }
}
