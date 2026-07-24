import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

/// Syria — the default country for every phone field in the app.
const String kDefaultDialCode = '+963';
const String kDefaultCountryCode = 'SY';

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

  /// Back to the app default, for controller-level `reset()` — a fresh booking
  /// shouldn't inherit the last one's country.
  void reset() {
    _initialised = false;
    countryCode = kDefaultCountryCode;
    dialCode = kDefaultDialCode;
    _setText(dialCode);
  }

  void dispose() => controller.dispose();

  void _setText(String value) {
    controller.value = TextEditingValue(
      text: value,
      selection: TextSelection.collapsed(offset: value.length),
    );
  }
}

/// Makes [PhoneFieldState.dialCode] an undeletable prefix: the code is
/// re-asserted if an edit would break it, non-digits typed after it are
/// dropped, and the caret is clamped past it so backspacing at the boundary is
/// a no-op.
class DialCodePrefixFormatter extends TextInputFormatter {
  final PhoneFieldState phone;

  DialCodePrefixFormatter(this.phone);

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final prefix = phone.dialCode;

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
    final rest = newValue.text.startsWith(prefix)
        ? newValue.text.substring(prefix.length)
        : newValue.text;
    final digits = rest.replaceAll(RegExp(r'\D'), '');
    final text = '$prefix$digits';

    // Clamp both ends of the selection so it can never sit inside the prefix.
    int clamp(int offset) => offset < prefix.length
        ? prefix.length
        : (offset > text.length ? text.length : offset);

    return TextEditingValue(
      text: text,
      selection: TextSelection(
        baseOffset: clamp(newValue.selection.baseOffset),
        extentOffset: clamp(newValue.selection.extentOffset),
      ),
      composing: TextRange.empty,
    );
  }
}

class CustomCountryCodePicker extends StatelessWidget {
  final PhoneFieldState phone;

  /// Optional extra hook for callers that need the raw [CountryCode].
  final void Function(CountryCode)? onCodeChanged;

  /// Fill for the picker button, the dialog, and its search field. Defaults to
  /// cream; pass the field's fill (e.g. whisperGrey) so the two match.
  final Color? fillColor;

  const CustomCountryCodePicker({
    required this.phone,
    this.onCodeChanged,
    this.fillColor,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;
    final fill = fillColor ?? AppColors.cream;

    // final labelStyle = theme.textTheme.labelSmall?.copyWith(
    //   fontFamily: 'JetJetBrainsMono',
    //   color: AppColors.beige,
    // );
    final inputStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.espressoInk,
      fontWeight: FontWeight.w400,
    );

    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      color: AppColors.espressoInk50,
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
      onChanged: (code) {
        phone.select(code);
        onCodeChanged?.call(code);
      },
      onInit: (code) {
        phone.seed(code);
        if (code != null) onCodeChanged?.call(code);
      },
      // Not a constant: the picker re-fires onInit on every mount, so this has
      // to be the field's remembered country or toggling away and back resets it.
      initialSelection: phone.countryCode,
      favorite: const [kDefaultCountryCode],
      countryFilter: allCountryCodesExcept('IL'),
      builder: (CountryCode? code) => Container(
        margin: EdgeInsets.only(top: 30),
        // width: 90,
        height: 60,
        decoration: BoxDecoration(
          color: fill,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          spacing: 10,
          children: [
            const Icon(Icons.phone_outlined, color: AppColors.mediumGrey),
            Text(code?.dialCode ?? phone.dialCode, style: hintStyle),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: AppColors.mediumGrey,
            ),
          ],
        ),
      ),
      dialogBackgroundColor: fill,
      barrierColor: Colors.transparent,
      dialogItemPadding: const EdgeInsetsGeometry.all(10),
      dialogTextStyle: inputStyle,
      searchStyle: inputStyle,
      searchDecoration: InputDecoration(
        border: border(fill),
        enabledBorder: border(AppColors.antiqueGold),
        focusedBorder: border(AppColors.antiqueGold),
        errorBorder: border(AppColors.salmonRed),
        hint: Text(AppTranslations.search, style: hintStyle),
        fillColor: fill,
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
        .map((c) => c['code'] as String)
        .where((code) => code != excludedCode)
        .toList();
  }
}
