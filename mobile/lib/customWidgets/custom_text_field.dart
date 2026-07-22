import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/app_colors.dart';
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
  final String? label;
  final Widget? suffixIcon;
  final String? hintText;
  final void Function(String)? onChanged;
  final void Function(String)? onSubmitted;
  final TextDirection? textDirection;
  final int? maxLength;
  final int? maxLines;
  final Color? fillColor;

  const CustomTextField({
    required this.controller,
    required this.textInputType,
    this.obsecureText = false,
    this.height,
    this.width,
    this.fillColor,
    this.labelText,
    this.label,
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

    final labelStyle = theme.textTheme.labelMedium?.copyWith(
      color: Colors.white,
      fontWeight: FontWeight.w900,
    );
    final inputStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.ink,
      fontWeight: FontWeight.w400,
    );
    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.inkHint,
      fontWeight: FontWeight.w400,
    );
    final errorStyle = theme.textTheme.bodySmall?.copyWith(
      color: AppColors.error,
    );

    final fill = fillColor ?? AppColors.cream;

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(width: 2, color: color),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text(label!.toUpperCase(), style: labelStyle),
          ),
        SizedBox(
          height: height,
          width: width,
          child: TextFormField(
            controller: controller,
            keyboardType: textInputType,
            obscureText: obsecureText,
            cursorColor: AppColors.gold,
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
              counterText: '',
              alignLabelWithHint: true,
              //labelText: ,
              labelStyle: labelStyle,
              fillColor: fill,
              hintText: hintText,
              hintStyle: hintStyle,
              errorStyle: errorStyle,
              prefixIcon: _buildPrefixIcon(),
              suffixIcon: suffixIcon,
              border: border(fill),
              enabledBorder: border(fill),
              focusedBorder: border(AppColors.gold),
              errorBorder: border(AppColors.error),
              focusedErrorBorder: border(AppColors.error),
            ),
            errorBuilder: (context, errorText) => RowTextComponent(
              text: errorText,
              textStyle: errorStyle,
              icon: Icons.error_outline,
              iconColor: AppColors.error,
            ),
            cursorErrorColor: AppColors.error,
            maxLines: obsecureText == true ? 1 : (maxLines ?? 1),
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
