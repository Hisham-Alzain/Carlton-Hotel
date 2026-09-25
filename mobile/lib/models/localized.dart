import 'package:carlton/services/settings_service.dart';

/// A server-localized string. The CMS stores every translatable column as a
/// Spatie translations map and the API resources hand it over whole
/// (`getTranslations('name')`), so the payload is `{ "en": ..., "ar": ...,
/// "fr": ..., "tr": ..., "es": ... }` — one entry per locale in
/// `config/cms.php → cms.locales`.
///
/// [value] resolves against the current app locale **live**, so switching
/// language re-renders content without a refetch. Reused across every content
/// DTO.
///
/// Replaced the earlier `Bilingual`, which parsed only `en`/`ar` and silently
/// dropped the other three locales the backend was already sending.
class Localized {
  /// Locale code → translated string. Empty strings are treated as absent.
  final Map<String, String> values;

  const Localized(this.values);

  /// A blank value — the default for `whenLoaded` relations the API omitted.
  static const Localized empty = Localized(<String, String>{});

  /// Parses the translations map. Defensively accepts a bare string (used for
  /// every locale) or null, since not every endpoint returns the map form.
  factory Localized.fromJson(dynamic json) {
    if (json is String) return Localized(<String, String>{_fallbackCode: json});
    if (json is Map) {
      final parsed = <String, String>{};
      json.forEach((key, dynamic v) {
        if (v is String && v.isNotEmpty) parsed['$key'] = v;
      });
      return Localized(parsed);
    }
    return empty;
  }

  /// The app's `fallbackLocale`, and the locale a bare-string payload is
  /// attributed to. Kept in one place so it tracks `main.dart`.
  static const String _fallbackCode = 'en';

  /// The variant for the current locale.
  ///
  /// Falls back current locale → `en` → `ar` → any locale that has content.
  /// `en`/`ar` come first because `cms.required_locales` guarantees an editor
  /// filled them; `fr`/`tr`/`es` are optional and may be blank on a record.
  String get value {
    final code = SettingsService.find.locale.value.languageCode;
    return _at(code) ?? _at(_fallbackCode) ?? _at('ar') ?? _anyNonEmpty ?? '';
  }

  /// The variant for an explicit locale, ignoring the current app locale.
  /// Use when rendering a language the user is not currently in — e.g. a
  /// language picker previewing each option in its own script.
  String forLocale(String code) => _at(code) ?? value;

  /// True when no locale carries content — lets a caller hide a whole row
  /// rather than render an empty line.
  bool get isEmpty => values.values.every((v) => v.isEmpty);

  bool get isNotEmpty => !isEmpty;

  String? _at(String code) {
    final v = values[code];
    return (v == null || v.isEmpty) ? null : v;
  }

  String? get _anyNonEmpty {
    for (final v in values.values) {
      if (v.isNotEmpty) return v;
    }
    return null;
  }

  @override
  String toString() => value;
}
