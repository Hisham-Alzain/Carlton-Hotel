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
    required this.onComplete,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final TextStyle errorStyle = Get.textTheme.labelLarge!.copyWith(
      color: Colors.red,
      fontSize: 10,
    );
    final PinTheme defaultPinTheme = PinTheme(
      width: 65,
      height: 65,
      textStyle: textStyle.bodySmall,
      decoration: BoxDecoration(
        // color: AppColors.grey9,
        borderRadius: BorderRadius.circular(15),
        // border: Border.all(color: AppColors.grey14),
      ),
    );
    return Pinput(
      controller: controller,
      length: length,
      defaultPinTheme: defaultPinTheme,
      // focusedPinTheme: defaultPinTheme.copyDecorationWith(
      //   border: Border.all(color: AppColors.primaryColor, width: 2),
      // ),
      // submittedPinTheme: defaultPinTheme.copyDecorationWith(
      //   color: AppColors.containerBackgroundColor,
      //   border: Border.all(color: AppColors.primaryColor, width: 2),
      // ),
      showCursor: true,
      validator: validator,
      errorTextStyle: errorStyle,
      onCompleted: onComplete,
      closeKeyboardWhenCompleted: true,
      keyboardType: TextInputType.number,
      //TODO: smsRetriever: ,
    );
  }
}
