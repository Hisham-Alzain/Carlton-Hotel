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
    if (value!.isNotEmpty && !value.isEmail) {
      return AppTranslations.invalidEmail;
    }
    return null;
  }

  String? validatePhoneNumber(String? value) {
    if (value!.isEmpty) {
      return AppTranslations.requiredField;
    }
    // else if (!value.startsWith('+963')) {
    //   return 'Number should begin with +963';
    // }
    else if (!value.isNum || value.length < 9) {
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
