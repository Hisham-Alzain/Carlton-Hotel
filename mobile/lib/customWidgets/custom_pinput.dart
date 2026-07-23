import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:pinput/pinput.dart';

class CustomPinput extends StatelessWidget {
  final TextEditingController controller;
  final int length;
  final String? Function(String?)? validator;
  final void Function(String)? onComplete;

  const CustomPinput({
    super.key,
    required this.controller,
    required this.length,
    required this.validator,
    this.onComplete,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;

    final errorStyle = theme.textTheme.bodySmall?.copyWith(
      color: AppColors.salmonRed,
    );

    final inputStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.espressoInk,
      fontWeight: FontWeight.w400,
    );

    final PinTheme defaultPinTheme = PinTheme(
      width: 45,
      height: 50,
      textStyle: inputStyle,
      decoration: BoxDecoration(
        color: AppColors.cream,
        borderRadius: BorderRadius.circular(10),
      ),
    );

    return Pinput(
      controller: controller,
      length: length,
      defaultPinTheme: defaultPinTheme,
      errorPinTheme: defaultPinTheme.copyDecorationWith(
        border: Border.all(color: AppColors.salmonRed, width: 1.5),
      ),
      validator: validator,
      errorTextStyle: errorStyle,
      onCompleted: onComplete,
      closeKeyboardWhenCompleted: true,
      keyboardType: TextInputType.number,
      errorBuilder: (context, errorText) => RowTextComponent(
        text: errorText,
        icon: Icons.error_outline,
        iconColor: AppColors.salmonRed,
      ),
    );
  }
}
