import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:get/get.dart';

class CustomValidation {
  String? validateRequiredField(String? value) {
    if (value!.isEmpty) {
      return AppTranslations.requiredField;
    }
    return null;
  }

  String? validateEmail(String? value) {
    final text = value ?? '';
    if (text.isEmpty) {
      return AppTranslations.pleaseEnterEmailAddress;
    } else if (!text.isEmail) {
      return AppTranslations.invalidEmail;
    }
    return null;
  }

  /// Validates a field whose text carries its dial code as a prefix (see
  /// [PhoneFieldState]) — the code is stripped before the digits are checked,
  /// so `+963` on its own reads as empty rather than as a valid number.
  String? validatePhoneNumber(
    String? value, {
    String dialCode = kDefaultDialCode,
  }) {
    final text = value ?? '';
    final national =
        (text.startsWith(dialCode) ? text.substring(dialCode.length) : text)
            .trim();

    if (national.isEmpty) {
      return AppTranslations.pleaseEnterPhoneNumber;
    } else if (!national.isNumericOnly || national.length < 9) {
      return AppTranslations.invalidNumber;
    }
    return null;
  }

  String? validatePassword(String? value) {
    // 1. Check if the field is empty
    if (value == null || value.isEmpty) {
      return AppTranslations.requiredField;
    }

    // 2. Check for minimum length
    if (value.length < 8) {
      return AppTranslations.invalidPasswordLength;
    }

    // 3. Check for at least one letter
    if (!value.contains(RegExp(r'[a-zA-Z]'))) {
      return AppTranslations.invalidPasswordChar;
    }

    // 4. Check for at least one number
    if (!value.contains(RegExp(r'[0-9]'))) {
      return AppTranslations.invalidPasswordNumber;
    }

    return null; // Password is valid
  }

  String? validateConfirmPassword(String? value, String? value2) {
    if (value!.isEmpty) {
      return AppTranslations.requiredField;
    } else if (value != value2) {
      return AppTranslations.invalidConfirmPassword;
    }
    return null;
  }

  String? validateNumberField(String? value) {
    if (value!.isEmpty) {
      return AppTranslations.requiredField;
    } else if (!value.isNumericOnly) {
      return AppTranslations.numberField;
    }
    return null;
  }

  String? validateRequiredDropDown(dynamic value) {
    if (value == null) {
      return AppTranslations.requiredField;
    }
    return null;
  }
}
