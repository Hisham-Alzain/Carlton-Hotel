import 'package:carlton/services/settings_service.dart';

/// A localized string carrying its English + Arabic variants. [value] resolves
/// against the current app locale **live**, so switching language re-renders
/// content without a refetch. Reused across every content DTO.
class Bilingual {
  final String en;
  final String ar;

  const Bilingual({required this.en, required this.ar});

  /// Parses either a `{ "en": ..., "ar": ... }` object or, defensively, a bare
  /// string (used as both variants) / null.
  factory Bilingual.fromJson(dynamic json) {
    if (json is String) return Bilingual(en: json, ar: json);
    if (json is Map) {
      return Bilingual(
        en: json['en'] as String? ?? '',
        ar: json['ar'] as String? ?? '',
      );
    }
    return const Bilingual(en: '', ar: '');
  }

  /// The variant for the current locale (falls back to English).
  String get value {
    final isArabic = SettingsService.find.locale.value.languageCode == 'ar';
    if (isArabic) return ar.isNotEmpty ? ar : en;
    return en.isNotEmpty ? en : ar;
  }

  @override
  String toString() => value;
}
