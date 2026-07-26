import 'package:flutter/services.dart';

/// Formats card-expiry input as `MM/YY`, inserting the `/` automatically as
/// soon as the two month digits are entered — the user never types the slash.
/// Strips non-digits and caps at 4 digits (MMYY).
///
/// The slash is *not* forced while the user is deleting, so backspacing from
/// `12/` removes the month's second digit instead of getting stuck on the `/`.
class ExpiryDateInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final capped = digits.length > 4 ? digits.substring(0, 4) : digits;
    final deleting = newValue.text.length < oldValue.text.length;

    final String text;
    if (capped.length < 2) {
      text = capped;
    } else if (capped.length == 2) {
      // Month complete: append the slash, unless the user is backspacing.
      text = deleting ? capped : '$capped/';
    } else {
      text = '${capped.substring(0, 2)}/${capped.substring(2)}';
    }

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
