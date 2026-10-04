import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:pinput/pinput.dart';

class CustomPinput extends StatelessWidget {
  final TextEditingController controller;
  final int digitCount;
  final String? Function(String?)? validator;
  final void Function(String)? onComplete;
  final ValueChanged<String>? onChanged;

  /// An error from outside the validator (e.g. the server rejecting the
  /// code). When set, the boxes turn red and show it until it is cleared.
  final String? errorText;

  const CustomPinput({
    super.key,
    required this.controller,
    required this.digitCount,
    required this.validator,
    this.onComplete,
    this.onChanged,
    this.errorText,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final errorStyle = textStyle.bodySmall?.copyWith(
      color: AppColors.salmonRed,
    );

    final inputStyle = textStyle.bodyLarge?.copyWith(
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
      length: digitCount,
      defaultPinTheme: defaultPinTheme,
      errorPinTheme: defaultPinTheme.copyDecorationWith(
        border: Border.all(color: AppColors.salmonRed, width: 1.5),
      ),
      validator: validator,
      forceErrorState: errorText != null,
      errorText: errorText,
      errorTextStyle: errorStyle,
      onCompleted: onComplete,
      onChanged: onChanged,
      closeKeyboardWhenCompleted: true,
      keyboardType: TextInputType.number,
      // Pinput passes (errorText, pin). Naming them (context, errorText) had
      // this print the typed digits instead of the message. The style is
      // explicit: RowTextComponent's default text is dark and vanished on the
      // dark sign-in screens.
      errorBuilder: (errorText, _) => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: RowTextComponent(
          text: errorText ?? '',
          icon: Icons.error_outline,
          iconSize: 18,
          iconColor: AppColors.salmonRed,
          spacing: 6,
          textStyle: errorStyle,
        ),
      ),
    );
  }
}
