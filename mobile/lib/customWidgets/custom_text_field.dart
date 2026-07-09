import 'package:carlton/services/settings_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

class CustomTextField extends StatelessWidget {
  final TextEditingController controller;
  final TextInputType textInputType;
  final bool obsecureText;
  final double? height;
  final double? width;
  final Color? prefixIconColor;
  final IconData? prefixIcon;
  final String? prefixIconPath;
  final String? Function(String?)? validator;
  final String? labelText;
  final Widget? suffixIcon;
  final String? hintText;
  final void Function(String)? onChanged;
  final void Function(String)? onSubmitted;
  final TextDirection? textDirection;
  final int? maxLength;
  final int? maxLines;

  const CustomTextField({
    required this.controller,
    required this.textInputType,
    required this.obsecureText,
    this.height,
    this.width,
    this.labelText,
    this.prefixIconColor,
    this.prefixIcon,
    this.prefixIconPath,
    this.validator,
    this.suffixIcon,
    this.hintText,
    this.onChanged,
    this.onSubmitted,
    this.textDirection,
    this.maxLength,
    this.maxLines,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;

    final labelStyle = theme.textTheme.bodySmall;
    final inputStyle = theme.textTheme.bodyLarge;
    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      // color: AppColors.grey13,
    );
    final errorStyle = theme.textTheme.bodySmall?.copyWith(color: Colors.red);

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(width: 2, color: color),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (labelText != null && labelText!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 5),
            child: Text(labelText!, style: labelStyle),
          ),
        SizedBox(
          height: height,
          width: width,
          child: TextFormField(
            controller: controller,
            keyboardType: textInputType,
            obscureText: obsecureText,
            // cursorColor: AppColors.primaryColor,
            style: inputStyle,
            validator: validator,
            onChanged: onChanged,
            onFieldSubmitted: onSubmitted,
            textDirection:
                SettingsService.find.locale.value.languageCode == 'ar'
                ? TextDirection.rtl
                : TextDirection.ltr,
            maxLength: maxLength,
            decoration: InputDecoration(
              filled: true,
              alignLabelWithHint: true,
              //labelText: ,
              labelStyle: labelStyle,
              // fillColor: AppColors.grey11,
              hintText: hintText,
              hintStyle: hintStyle,
              errorStyle: errorStyle,
              prefixIcon: _buildPrefixIcon(),
              suffixIcon: suffixIcon,
              // border: border(AppColors.grey11),
              // enabledBorder: border(AppColors.grey11),
              // focusedBorder: border(AppColors.primaryColor),
              errorBorder: border(Colors.red),
              focusedErrorBorder: border(Colors.red),
            ),
            cursorErrorColor: Colors.red,
            maxLines: obsecureText == true ? 1 : maxLines,
          ),
        ),
      ],
    );
  }

  Widget? _buildPrefixIcon() {
    if (prefixIcon != null) {
      return Icon(
        prefixIcon,
        //  color: prefixIconColor ?? AppColors.primaryColor
      );
    } else if (prefixIconPath != null) {
      return Padding(
        padding: const EdgeInsets.all(10),
        child: prefixIconPath != null
            ? SvgPicture.asset(
                prefixIconPath.toString(),
                // colorFilter: ColorFilter.mode(
                //   prefixIconColor ?? AppColors.primaryColor,
                //   BlendMode.srcIn,
                // ),
                height: 15,
              )
            : null,
      );
    }
    return null;
  }
}
