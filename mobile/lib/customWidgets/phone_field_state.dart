part of 'custom_country_code_picker.dart';

/// The state behind one phone input: its text controller, the currently
/// selected dial code, and the formatter that keeps that code pinned to the
/// front of the field. Controllers own one of these per phone field and hand it
/// to both [CustomCountryCodePicker] and [CustomTextField].
class PhoneFieldState {
  final TextEditingController controller = TextEditingController();

  /// ISO country (`SY`, `TR`, …). Fed back to the picker as its
  /// `initialSelection` so a re-created picker restores this choice instead of
  /// snapping to the default.
  String countryCode = kDefaultCountryCode;
  String dialCode = kDefaultDialCode;

  late final DialCodePrefixFormatter formatter = DialCodePrefixFormatter(this);

  bool _initialised = false;

  /// Called when the picker mounts. Runs once per field: the picker re-fires
  /// `onInit` on every mount (Sign In toggling Phone <-> Email destroys and
  /// recreates it), and a second run would clobber the user's country.
  void seed(CountryCode? code) {
    if (_initialised) return;
    _initialised = true;
    countryCode = code?.code ?? kDefaultCountryCode;
    dialCode = code?.dialCode ?? kDefaultDialCode;
    if (controller.text.isEmpty) _setText(dialCode);
  }

  /// Called when the user picks a different country: clear whatever is there
  /// and start over from the new dial code.
  void select(CountryCode code) {
    _initialised = true;
    countryCode = code.code ?? kDefaultCountryCode;
    dialCode = code.dialCode ?? kDefaultDialCode;
    _setText(dialCode);
  }

  /// The number without its dial code — what validation actually cares about.
  String get nationalNumber => controller.text.startsWith(dialCode)
      ? controller.text.substring(dialCode.length).trim()
      : controller.text.trim();

  /// Fills the field with a stored E.164 number (`+963940001031`). [isoCountry]
  /// (`SY`) picks the flag; without it the dial code is matched from the
  /// number's own prefix. Marks the field initialised so the picker mounting
  /// afterwards does not reset it to the default country.
  void prefill(String e164, {String? isoCountry}) {
    final code =
        (isoCountry == null
            ? null
            : CountryCode.tryFromCountryCode(isoCountry.toUpperCase())) ??
        _codeForNumber(e164);
    if (code?.dialCode == null || !e164.startsWith(code!.dialCode!)) return;
    _initialised = true;
    countryCode = code.code ?? kDefaultCountryCode;
    dialCode = code.dialCode!;
    _setText(e164);
  }

  /// Longest dial code that prefixes [e164] — `+1` and `+1242` both exist, and
  /// only the longer one is right for a Bahamas number.
  static CountryCode? _codeForNumber(String e164) {
    for (var len = 5; len >= 2; len--) {
      if (e164.length <= len) continue;
      final code = CountryCode.tryFromDialCode(e164.substring(0, len));
      if (code != null) return code;
    }
    return null;
  }

  /// Back to the app default, for controller-level `reset()` — a fresh booking
  /// shouldn't inherit the last one's country.
  void reset() {
    _initialised = false;
    countryCode = kDefaultCountryCode;
    dialCode = kDefaultDialCode;
    _setText(dialCode);
  }

  void dispose() => controller.dispose();

  void _setText(String text) {
    controller.value = TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

/// Makes [PhoneFieldState.dialCode] an undeletable prefix: the code is
/// re-asserted if an edit would break it, non-digits typed after it are
/// dropped, and the caret is clamped past it so backspacing at the boundary is
/// a no-op.
class DialCodePrefixFormatter extends TextInputFormatter {
  final PhoneFieldState phoneField;

  DialCodePrefixFormatter(this.phoneField);

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final prefix = phoneField.dialCode;

    // The edit chewed into the dial code — true for "+96", "+9", "+", "" and
    // nothing else. Refuse it outright: treating the leftover code digits as a
    // phone number is what made backspace *grow* the field ("+963" -> "+96396").
    // Must be a *strict* prefix: text == dialCode is the legitimate result of
    // deleting the last national digit, and has to fall through.
    if (newValue.text.length < prefix.length &&
        prefix.startsWith(newValue.text)) {
      return oldValue.text.startsWith(prefix)
          ? oldValue
          : TextEditingValue(
              text: prefix,
              selection: TextSelection.collapsed(offset: prefix.length),
            );
    }

    // Whatever survives after the prefix, minus anything that isn't a digit.
    final afterPrefix = newValue.text.startsWith(prefix)
        ? newValue.text.substring(prefix.length)
        : newValue.text;
    final nationalDigits = afterPrefix.replaceAll(RegExp(r'\D'), '');
    final rebuiltText = '$prefix$nationalDigits';

    // Clamp both ends of the selection so it can never sit inside the prefix.
    int clampCaret(int caretOffset) => caretOffset < prefix.length
        ? prefix.length
        : (caretOffset > rebuiltText.length ? rebuiltText.length : caretOffset);

    return TextEditingValue(
      text: rebuiltText,
      selection: TextSelection(
        baseOffset: clampCaret(newValue.selection.baseOffset),
        extentOffset: clampCaret(newValue.selection.extentOffset),
      ),
      composing: TextRange.empty,
    );
  }
}
