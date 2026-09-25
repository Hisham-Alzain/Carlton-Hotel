import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:get/get.dart';

class CustomValidation {
  String? validateRequiredField(String? fieldText) {
    if (fieldText!.isEmpty) {
      return AppTranslations.requiredField;
    }
    return null;
  }

  String? validateEmail(String? fieldText) {
    final enteredEmail = fieldText ?? '';
    if (enteredEmail.isEmpty) {
      return AppTranslations.pleaseEnterEmailAddress;
    } else if (!enteredEmail.isEmail) {
      return AppTranslations.invalidEmail;
    }
    return null;
  }

  /// Validates a field whose text carries its dial code as a prefix (see
  /// [PhoneFieldState]) — the code is stripped before the digits are checked,
  /// so `+963` on its own reads as empty rather than as a valid number.
  String? validatePhoneNumber(
    String? fieldText, {
    String dialCode = kDefaultDialCode,
  }) {
    final enteredPhone = fieldText ?? '';
    final nationalDigits =
        (enteredPhone.startsWith(dialCode)
                ? enteredPhone.substring(dialCode.length)
                : enteredPhone)
            .trim();

    if (nationalDigits.isEmpty) {
      return AppTranslations.pleaseEnterPhoneNumber;
    } else if (!nationalDigits.isNumericOnly || nationalDigits.length < 9) {
      return AppTranslations.invalidNumber;
    }
    return null;
  }

  String? validatePassword(String? fieldText) {
    // 1. Check if the field is empty
    if (fieldText == null || fieldText.isEmpty) {
      return AppTranslations.requiredField;
    }

    // 2. Check for minimum length
    if (fieldText.length < 8) {
      return AppTranslations.invalidPasswordLength;
    }

    // 3. Check for at least one letter
    if (!fieldText.contains(RegExp(r'[a-zA-Z]'))) {
      return AppTranslations.invalidPasswordChar;
    }

    // 4. Check for at least one number
    if (!fieldText.contains(RegExp(r'[0-9]'))) {
      return AppTranslations.invalidPasswordNumber;
    }

    return null; // Password is valid
  }

  String? validateConfirmPassword(String? fieldText, String? originalPassword) {
    if (fieldText!.isEmpty) {
      return AppTranslations.requiredField;
    } else if (fieldText != originalPassword) {
      return AppTranslations.invalidConfirmPassword;
    }
    return null;
  }

  String? validateNumberField(String? fieldText) {
    if (fieldText!.isEmpty) {
      return AppTranslations.requiredField;
    } else if (!fieldText.isNumericOnly) {
      return AppTranslations.numberField;
    }
    return null;
  }

  /// The 6-digit OTP. Length and digits-only are client-side concerns, so this
  /// is independent of the backend — a wrong-but-well-formed code still comes
  /// back as `unauthorized` from the verify endpoint.
  String? validateOtp(String? fieldText, {int digitCount = 6}) {
    final entered = fieldText ?? '';
    if (entered.isEmpty) {
      return AppTranslations.requiredField;
    }
    if (entered.length != digitCount || !entered.isNumericOnly) {
      return AppTranslations.invalidOtp;
    }
    return null;
  }

  String? validateRequiredDropDown(dynamic selectedItem) {
    if (selectedItem == null) {
      return AppTranslations.requiredField;
    }
    return null;
  }
}
