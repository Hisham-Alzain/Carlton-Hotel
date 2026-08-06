import 'package:carlton/l10n/local.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the l10n rule: every check-in key must exist in BOTH locales.
/// A key added to `en` only would silently render its raw id in Arabic.
void main() {
  test('every check-in key exists in en and ar', () {
    final translations = Local().keys;
    final en = translations['en_US'] ?? translations['en']!;
    final ar = translations['ar_SA'] ?? translations['ar']!;

    const required = <String>[
      'checkIn.title',
      'checkIn.tabIdentity',
      'checkIn.tabPreferences',
      'checkIn.tabRoomKey',
      'checkIn.completeCheckIn',
      'checkIn.addDigitalKey',
      'checkIn.scanYourId',
      'preArrival.checklist',
      'preArrival.checkInNow',
    ];

    for (final key in required) {
      expect(en.containsKey(key), isTrue, reason: 'missing in en: $key');
      expect(ar.containsKey(key), isTrue, reason: 'missing in ar: $key');
    }
  });
}
