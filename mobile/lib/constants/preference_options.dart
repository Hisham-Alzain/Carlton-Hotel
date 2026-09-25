import 'package:carlton/services/settings_service.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/stay_preferences.dart';
import 'package:carlton/models/preference_option.dart';
import 'package:flutter/material.dart';

/// The fixed catalogues behind every preference picker (Figma Preferences +
/// option-picker sheets). These are product reference data, not placeholder
/// content: there is no catalogue endpoint, and the icons are the exported
/// Figma glyphs under `assets/icons`. Language and currency use Material
/// glyphs because the design shows none.
abstract class PreferenceOptions {
  /// Resolved per read rather than `const`: the labels are `.tr` lookups,
  /// so a const list would freeze whichever locale was active at load.
  static List<PreferenceOption> get bedOptions => <PreferenceOption>[
    PreferenceOption(
      id: 'king',
      label: AppTranslations.bedKing,
      iconAsset: 'assets/icons/kingbed.svg',
    ),
    // queenbed.svg was byte-identical to kingbed.svg — one glyph, two files.
    // Pointing both at kingbed.svg changes nothing on screen and removes the
    // duplicate; a distinct queen glyph needs to come from Figma first.
    PreferenceOption(
      id: 'queen',
      label: AppTranslations.bedQueen,
      iconAsset: 'assets/icons/kingbed.svg',
    ),
    PreferenceOption(
      id: 'double',
      label: AppTranslations.bedDouble,
      iconAsset: 'assets/icons/doublebed.svg',
    ),
    PreferenceOption(
      id: 'twin',
      label: AppTranslations.bedTwin,
      iconAsset: 'assets/icons/twinbeds.svg',
    ),
    PreferenceOption(
      id: 'single',
      label: AppTranslations.bedSingle,
      iconAsset: 'assets/icons/singlebed.svg',
    ),
    // Same story as queen/king: extrabed.svg duplicated singlebed.svg byte for
    // byte.
    PreferenceOption(
      id: 'extra',
      label: AppTranslations.bedExtra,
      iconAsset: 'assets/icons/singlebed.svg',
    ),
  ];

  /// Resolved per read rather than `const`: the labels are `.tr` lookups,
  /// so a const list would freeze whichever locale was active at load.
  static List<PreferenceOption> get pillowOptions => <PreferenceOption>[
    PreferenceOption(
      id: 'soft',
      label: AppTranslations.pillowSoft,
      iconAsset: 'assets/icons/softpillow.svg',
    ),
    PreferenceOption(
      id: 'firm',
      label: AppTranslations.pillowFirm,
      iconAsset: 'assets/icons/firmpillow.svg',
    ),
    PreferenceOption(
      id: 'feather',
      label: AppTranslations.pillowFeather,
      iconAsset: 'assets/icons/featherpillow.svg',
    ),
  ];

  /// Resolved per read rather than `const`: the labels are `.tr` lookups,
  /// so a const list would freeze whichever locale was active at load.
  static List<PreferenceOption> get mattressOptions => <PreferenceOption>[
    PreferenceOption(
      id: 'soft',
      label: AppTranslations.mattressSoft,
      iconAsset: 'assets/icons/softmattress.svg',
    ),
    PreferenceOption(
      id: 'medium',
      label: AppTranslations.mattressMedium,
      iconAsset: 'assets/icons/mediummattress.svg',
    ),
    PreferenceOption(
      id: 'firm',
      label: AppTranslations.mattressFirm,
      iconAsset: 'assets/icons/firmmattress.svg',
    ),
    PreferenceOption(
      id: 'foam',
      label: AppTranslations.mattressMemory,
      iconAsset: 'assets/icons/memoryfoammattress.svg',
    ),
    PreferenceOption(
      id: 'orthopedic',
      label: AppTranslations.mattressOrtho,
      iconAsset: 'assets/icons/orthopedicmattress.svg',
    ),
    PreferenceOption(
      id: 'hotel',
      label: AppTranslations.mattressStandard,
      iconAsset: 'assets/icons/hotelstandardmattress.svg',
    ),
  ];

  /// Mirrors [SettingsService.langs] — an option the service can't set would
  /// silently revert on tap. Each label is the language's own endonym so it
  /// stays readable whatever locale the UI is currently in.
  /// Resolved per read rather than `const`: the labels are `.tr` lookups,
  /// so a const list would freeze whichever locale was active at load.
  static List<PreferenceOption> get languageOptions => const <PreferenceOption>[
    PreferenceOption(id: 'en', label: 'English', icon: Icons.language),
    PreferenceOption(id: 'ar', label: 'العربية', icon: Icons.language),
    PreferenceOption(id: 'fr', label: 'Français', icon: Icons.language),
    PreferenceOption(id: 'tr', label: 'Türkçe', icon: Icons.language),
    PreferenceOption(id: 'es', label: 'Español', icon: Icons.language),
  ];

  /// Derived from [SettingsService.currencies] rather than hand-listed — the
  /// two drifting apart is how an option becomes selectable but unsettable,
  /// silently reverting on tap. Labels come from [AppTranslations] so the
  /// picker follows the language switch.
  static List<PreferenceOption> get currencyOptions => [
    for (final c in SettingsService.currencies)
      PreferenceOption(
        id: c.value,
        label: '${c.code} — ${AppTranslations.currencyName(c.value)}',
        icon: Icons.payments_outlined,
      ),
  ];

  /// Seeds the check-in Preferences tab. Ids must exist in the lists above.
  static const defaultStayPreferences = StayPreferences(
    bedTypeId: 'king',
    pillowId: 'firm',
    mattressId: 'medium',
    smokingRoom: false,
    earlyCheckIn: true,
    lateCheckOut: false,
    extraPillows: false,
    notes: '',
  );
}
