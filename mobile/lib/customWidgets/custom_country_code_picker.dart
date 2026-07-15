import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomCountryCodePicker extends StatelessWidget {
  final void Function(CountryCode) onCodeChanged;

  const CustomCountryCodePicker({required this.onCodeChanged, super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;

    // final labelStyle = theme.textTheme.labelSmall?.copyWith(
    //   fontFamily: 'JetJetBrainsMono',
    //   color: AppColors.beige,
    // );
    final inputStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.ink,
      fontWeight: FontWeight.w400,
    );

    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.inkHint,
      fontWeight: FontWeight.w400,
    );
    // final errorStyle = theme.textTheme.bodySmall?.copyWith(
    //   color: AppColors.red,
    // );

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(8),
      borderSide: BorderSide(width: 2, color: color),
    );

    return CountryCodePicker(
      onChanged: onCodeChanged,
      onInit: (code) {
        if (code != null) onCodeChanged(code);
      },
      initialSelection: 'SY',
      favorite: const ['SY'],
      countryFilter: kAllCountryCodesExcept('IL'),
      builder: (CountryCode? code) => Container(
        margin: EdgeInsets.only(top: 30),
        // width: 90,
        height: 60,
        decoration: BoxDecoration(
          color: AppColors.cream,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          spacing: 10,
          children: [
            const Icon(Icons.phone_outlined, color: AppColors.textTertiary),
            Text(code?.dialCode ?? '+963', style: hintStyle),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: AppColors.textTertiary,
            ),
          ],
        ),
      ),
      dialogBackgroundColor: AppColors.cream,
      barrierColor: Colors.transparent,
      dialogItemPadding: const EdgeInsetsGeometry.all(10),
      dialogTextStyle: inputStyle,
      searchStyle: inputStyle,
      searchDecoration: InputDecoration(
        border: border(AppColors.cream),
        enabledBorder: border(AppColors.gold),
        focusedBorder: border(AppColors.gold),
        errorBorder: border(AppColors.error),
        hint: Text(AppTranslations.search, style: hintStyle),
        fillColor: AppColors.cream,
        filled: true,
        iconColor: AppColors.gold,
      ),
      searchPadding: const EdgeInsetsGeometry.all(10),
      topBarPadding: const EdgeInsets.all(10),
      textStyle: inputStyle,
    );
  }

  List<String> kAllCountryCodesExcept(String excludedCode) {
    return codes
        .map((c) => c['code'] as String)
        .where((code) => code != excludedCode)
        .toList();
  }
}
