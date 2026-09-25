import 'package:carlton/l10n/locales/arabic_locale.dart';
import 'package:carlton/l10n/locales/english_locale.dart';
import 'package:carlton/l10n/locales/spanish_locale.dart';
import 'package:carlton/l10n/locales/french_locale.dart';
import 'package:carlton/l10n/locales/turkish_locale.dart';
import 'package:get/get.dart';

/// Merges the per-locale key maps under `l10n/locales/`.
///
/// The five locales mirror `config/cms.php → cms.locales` on the backend, so a
/// guest who picks French here also gets French CMS content out of
/// [Localized]. `en`/`ar` are the backend's `required_locales` and are the
/// fallback chain; `fr`/`tr`/`es` are optional there and machine-drafted here.
///
/// Splitting one map per file (rather than one 2,000-line `local.dart`) is what
/// makes a five-way diff reviewable — and `test/l10n_parity_test.dart` asserts
/// every locale carries an identical key set, which is the only thing standing
/// between a forgotten key and a raw `auth.signInTitle` on a guest's screen.
class Local implements Translations {
  @override
  Map<String, Map<String, String>> get keys => const {
    'en': enKeys,
    'ar': arKeys,
    'fr': frKeys,
    'tr': trKeys,
    'es': esKeys,
  };

  /// Every locale the app ships, in picker order. Single source of truth for
  /// `main.dart`'s `supportedLocales`, [SettingsService.langs] and the
  /// Preferences language sheet — those three drifting apart is how a locale
  /// ends up selectable but untranslated.
  static const List<String> supportedCodes = ['en', 'ar', 'fr', 'tr', 'es'];
}
