import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';

class CustomPhoneField extends StatelessWidget {
  final TextEditingController controller;
  final String? errorText;
  final String label;

  final String initialCountryCode;

  final ValueChanged<CountryCode>? onCountryChanged;

  const CustomPhoneField({
    required this.controller,
    this.errorText,
    this.label = 'Phone Number',
    this.initialCountryCode = 'SY',
    this.onCountryChanged,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text(
            label.toUpperCase(),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.8,
            ),
          ),
        ),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          spacing: 8,
          children: [
            _CountryCodeBadge(
              initialCountryCode: initialCountryCode,
              onCountryChanged: onCountryChanged,
            ),
            Expanded(
              child: CustomTextField.auth(
                controller: controller,
                textInputType: TextInputType.phone,
                hintText: 'Phone number',
                errorText: errorText,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _CountryCodeBadge extends StatelessWidget {
  final String initialCountryCode;
  final ValueChanged<CountryCode>? onCountryChanged;

  const _CountryCodeBadge({
    required this.initialCountryCode,
    this.onCountryChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 52,
      width: 95,
      decoration: BoxDecoration(
        color: AppColors.cream,
        borderRadius: BorderRadius.circular(8),
      ),
      alignment: Alignment.center,
      child: CountryCodePicker(
        initialSelection: initialCountryCode,
        favorite: const ['SY'],
        onChanged: onCountryChanged,
        padding: EdgeInsets.zero,
        flagWidth: 22,
        dialogBackgroundColor: AppColors.surface,
        boxDecoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(20),
        ),
        dialogTextStyle: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w500,
          color: AppColors.textPrimary,
        ),
        searchStyle: const TextStyle(
          fontSize: 15,
          color: AppColors.textPrimary,
        ),
        searchDecoration: InputDecoration(
          filled: true,
          fillColor: AppColors.cream,
          hintText: 'Search country',
          hintStyle: const TextStyle(color: AppColors.textMuted),
          prefixIcon: const Icon(Icons.search, color: AppColors.primary),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide.none,
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.primary, width: 1.4),
          ),
        ),
        builder: (code) => Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          spacing: 3,
          children: [
            if (code?.flagUri != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(2),
                child: Image.asset(
                  code!.flagUri!,
                  package: 'country_code_picker',
                  width: 22,
                ),
              ),
            Text(
              code?.dialCode ?? '',
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.textTertiary,
              ),
            ),
            const Icon(
              Icons.keyboard_arrow_down,
              size: 16,
              color: AppColors.textTertiary,
            ),
          ],
        ),
      ),
    );
  }
}
