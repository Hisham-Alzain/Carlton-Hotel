import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

part 'phone_field_state.dart';

/// Syria — the default country for every phone field in the app.
const String kDefaultDialCode = '+963';
const String kDefaultCountryCode = 'SY';

class CustomCountryCodePicker extends StatelessWidget {
  final PhoneFieldState phoneField;

  /// Optional extra hook for callers that need the raw [CountryCode].
  final void Function(CountryCode)? onCodeChanged;

  /// Fill for the picker button, the dialog, and its search field. Defaults to
  /// cream; pass the field's fill (e.g. whisperGrey) so the two match.
  final Color? fillColor;

  const CustomCountryCodePicker({
    required this.phoneField,
    this.onCodeChanged,
    this.fillColor,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final resolvedFillColor = fillColor ?? AppColors.cream;

    final inputStyle = textStyle.bodyLarge?.copyWith(
      color: AppColors.espressoInk,
      fontWeight: FontWeight.w400,
    );

    final hintStyle = textStyle.bodyLarge?.copyWith(
      color: AppColors.espressoInk50,
      fontWeight: FontWeight.w400,
    );

    OutlineInputBorder border(Color borderColor) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(width: 2, color: borderColor),
    );

    return CountryCodePicker(
      onChanged: (code) {
        phoneField.select(code);
        onCodeChanged?.call(code);
      },
      onInit: (code) {
        phoneField.seed(code);
        if (code != null) onCodeChanged?.call(code);
      },
      // Not a constant: the picker re-fires onInit on every mount, so this has
      // to be the field's remembered country or toggling away and back resets it.
      initialSelection: phoneField.countryCode,
      favorite: const [kDefaultCountryCode],
      countryFilter: allCountryCodesExcept('IL'),
      builder: (CountryCode? code) => Container(
        margin: EdgeInsets.only(top: 30),
        height: 60,
        decoration: BoxDecoration(
          color: resolvedFillColor,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          spacing: 10,
          children: [
            const Icon(Icons.phone_outlined, color: AppColors.mediumGrey),
            Text(code?.dialCode ?? phoneField.dialCode, style: hintStyle),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: AppColors.mediumGrey,
            ),
          ],
        ),
      ),
      dialogBackgroundColor: resolvedFillColor,
      barrierColor: Colors.transparent,
      dialogItemPadding: const EdgeInsetsGeometry.all(10),
      dialogTextStyle: inputStyle,
      searchStyle: inputStyle,
      searchDecoration: InputDecoration(
        border: border(resolvedFillColor),
        enabledBorder: border(AppColors.antiqueGold),
        focusedBorder: border(AppColors.antiqueGold),
        errorBorder: border(AppColors.salmonRed),
        hint: Text(AppTranslations.search, style: hintStyle),
        fillColor: resolvedFillColor,
        filled: true,
        iconColor: AppColors.antiqueGold,
      ),
      searchPadding: const EdgeInsetsGeometry.all(10),
      topBarPadding: const EdgeInsets.all(10),
      textStyle: inputStyle,
    );
  }

  List<String> allCountryCodesExcept(String excludedCode) {
    return codes
        .map((country) => country['code'] as String)
        .where((code) => code != excludedCode)
        .toList();
  }
}

/// The country picker beside its phone number field — the one phone input
/// every form uses, so the formatter, direction and validation stay the same
/// everywhere.
class CustomPhoneField extends StatelessWidget {
  final PhoneFieldState phoneField;
  final String? captionLabel;
  final Color? fillColor;
  final Color? labelColor;
  final CrossAxisAlignment crossAxisAlignment;

  const CustomPhoneField({
    required this.phoneField,
    this.captionLabel,
    this.fillColor,
    this.labelColor,
    this.crossAxisAlignment = CrossAxisAlignment.center,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 10,
      crossAxisAlignment: crossAxisAlignment,
      children: [
        CustomCountryCodePicker(phoneField: phoneField, fillColor: fillColor),
        Expanded(
          child: CustomTextField(
            controller: phoneField.controller,
            inputFormatters: [phoneField.formatter],
            textInputType: TextInputType.phone,
            textDirection: TextDirection.ltr,
            captionLabel: captionLabel ?? AppTranslations.phoneNumber,
            labelColor: labelColor,
            hintText: AppTranslations.phoneNumberHint,
            fillColor: fillColor,
            validator: (enteredPhoneNumber) =>
                CustomValidation().validatePhoneNumber(
                  enteredPhoneNumber,
                  dialCode: phoneField.dialCode,
                ),
          ),
        ),
      ],
    );
  }
}
