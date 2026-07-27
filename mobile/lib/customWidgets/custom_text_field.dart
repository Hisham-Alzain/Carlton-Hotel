import 'package:carlton/customWidgets/custom_texts.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

class CustomTextField extends StatelessWidget {
  final TextEditingController controller;
  final TextInputType textInputType;
  final bool obscureText;
  final double? height;
  final double? width;
  final Color? prefixIconColor;
  final IconData? prefixIcon;
  final String? prefixIconPath;
  final String? Function(String?)? validator;

  /// Uppercase caption rendered *above* the field. Not the in-field floating
  /// label — this widget doesn't use one.
  final String? captionLabel;

  /// Colour of [captionLabel]. Defaults to white for the dark auth backgrounds;
  /// pass a dark colour (e.g. on light forms like Guest Details) so it stays
  /// legible.
  final Color? labelColor;
  final Widget? suffixIcon;
  final String? hintText;
  final void Function(String)? onChanged;
  final void Function(String)? onSubmitted;
  final TextDirection? textDirection;
  final int? maxLength;
  final int? maxLines;
  final Color? fillColor;

  /// Resting border. Defaults to the fill, i.e. no visible outline.
  final Color? borderColor;
  final List<TextInputFormatter>? inputFormatters;

  const CustomTextField({
    required this.controller,
    required this.textInputType,
    this.obscureText = false,
    this.height,
    this.width,
    this.fillColor,
    this.borderColor,
    this.captionLabel,
    this.labelColor,
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
    this.inputFormatters,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final labelStyle = textStyle.labelMedium?.copyWith(
      fontFamily: 'DM Sans',
      color: labelColor ?? Colors.white,
      fontWeight: FontWeight.w900,
    );
    final inputStyle = textStyle.bodyLarge?.copyWith(
      color: AppColors.espressoInk,
      fontWeight: FontWeight.w400,
    );
    final hintStyle = textStyle.bodyLarge?.copyWith(
      color: AppColors.espressoInk50,
      fontWeight: FontWeight.w400,
    );
    final errorStyle = textStyle.bodySmall?.copyWith(
      color: AppColors.salmonRed,
    );

    final resolvedFillColor = fillColor ?? AppColors.cream;
    final restingBorderColor = borderColor ?? resolvedFillColor;

    OutlineInputBorder border(Color borderColor) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(width: 2, color: borderColor),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (captionLabel != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text(captionLabel!.toUpperCase(), style: labelStyle),
          ),
        SizedBox(
          height: height,
          width: width,
          child: TextFormField(
            controller: controller,
            keyboardType: textInputType,
            obscureText: obscureText,
            cursorColor: AppColors.antiqueGold,
            style: inputStyle,
            validator: validator,
            onChanged: onChanged,
            onFieldSubmitted: onSubmitted,
            textDirection:
                textDirection ??
                (SettingsService.find.locale.value.languageCode == 'ar'
                    ? TextDirection.rtl
                    : TextDirection.ltr),
            maxLength: maxLength,
            inputFormatters: inputFormatters,
            decoration: InputDecoration(
              filled: true,
              counterText: '',
              alignLabelWithHint: true,
              labelStyle: labelStyle,
              fillColor: resolvedFillColor,
              hintText: hintText,
              hintStyle: hintStyle,
              errorStyle: errorStyle,
              prefixIcon: _buildPrefixIcon(),
              suffixIcon: suffixIcon,
              border: border(restingBorderColor),
              enabledBorder: border(restingBorderColor),
              focusedBorder: border(AppColors.antiqueGold),
              errorBorder: border(AppColors.salmonRed),
              focusedErrorBorder: border(AppColors.salmonRed),
            ),
            errorBuilder: (context, errorText) => RowTextComponent(
              text: errorText,
              textStyle: errorStyle,
              icon: Icons.error_outline,
              iconColor: AppColors.salmonRed,
              expandText: true,
            ),
            cursorErrorColor: AppColors.salmonRed,
            maxLines: obscureText == true ? 1 : (maxLines ?? 1),
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
