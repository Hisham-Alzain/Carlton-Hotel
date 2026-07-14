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
  final String? errorText;
  final Widget? suffixIcon;
  final String? hintText;
  final void Function(String)? onChanged;
  final void Function(String)? onSubmitted;
  final TextDirection? textDirection;
  final int? maxLength;
  final int? maxLines;

  final Color? fillColor;
  final Color? textColor;
  final Color? hintColor;
  final double? borderRadius;

  const CustomTextField({
    required this.controller,
    required this.textInputType,
    this.obsecureText = false,
    this.height,
    this.width,
    this.labelText,
    this.label,
    this.errorText,
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
    this.fillColor,
    this.textColor,
    this.hintColor,
    this.borderRadius,
    super.key,
  });

  /// The Auth flow's cream filled field (Figma Text Input): 52px, radius 8,
  /// no border, with the uppercase white [label] and red [errorText] row.
  const CustomTextField.auth({
    required this.controller,
    required this.textInputType,
    this.hintText,
    this.label,
    this.errorText,
    this.onChanged,
    this.onSubmitted,
    super.key,
  }) : obsecureText = false,
       height = 52,
       width = null,
       labelText = null,
       prefixIconColor = null,
       prefixIcon = null,
       prefixIconPath = null,
       validator = null,
       suffixIcon = null,
       textDirection = null,
       maxLength = null,
       maxLines = null,
       fillColor = AppColors.cream,
       textColor = AppColors.ink,
       hintColor = AppColors.inkHint,
       borderRadius = 8;

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;

    final labelStyle = theme.textTheme.bodySmall;
    final inputStyle = theme.textTheme.bodyLarge?.copyWith(color: textColor);
    final hintStyle = theme.textTheme.bodyLarge?.copyWith(color: hintColor);
    final errorStyle = theme.textTheme.bodySmall?.copyWith(color: Colors.red);

    final noBorder = borderRadius != null
        ? OutlineInputBorder(
            borderRadius: BorderRadius.circular(borderRadius!),
            borderSide: BorderSide.none,
          )
        : null;

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(width: 2, color: color),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              label!.toUpperCase(),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w600,
                letterSpacing: 0.8,
              ),
            ),
          ),
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
              fillColor: fillColor,
              hintText: hintText,
              hintStyle: hintStyle,
              errorStyle: errorStyle,
              prefixIcon: _buildPrefixIcon(),
              suffixIcon: suffixIcon,
              border: noBorder,
              enabledBorder: noBorder,
              focusedBorder: noBorder,
              errorBorder: border(Colors.red),
              focusedErrorBorder: border(Colors.red),
            ),
            cursorErrorColor: Colors.red,
            maxLines: obsecureText == true ? 1 : maxLines,
          ),
        ),
        if (errorText != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Row(
              spacing: 6,
              children: [
                const Icon(
                  Icons.error_outline,
                  size: 13,
                  color: AppColors.error,
                ),
                Expanded(
                  child: Text(
                    errorText!,
                    style: const TextStyle(
                      color: AppColors.error,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
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
