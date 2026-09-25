import 'package:carlton/l10n/local.dart';
import 'package:flutter_test/flutter_test.dart';

/// The only thing standing between a forgotten key and a raw `auth.signInTitle`
/// rendering on a guest's screen.
///
/// GetX returns the key itself when a lookup misses, so a key added to
/// `english_locale.dart` and forgotten in the other four fails silently — no
/// analyzer error, no crash, just an untranslated identifier in the UI. These
/// tests are what makes that a red build instead.
void main() {
  final keys = Local().keys;

  test('every declared locale ships a key map', () {
    expect(keys.keys.toSet(), Local.supportedCodes.toSet());
  });

  test('every locale carries an identical key set', () {
    final reference = keys['en']!.keys.toSet();
    expect(reference, isNotEmpty);

    for (final code in Local.supportedCodes) {
      final actual = keys[code]!.keys.toSet();
      expect(
        reference.difference(actual),
        isEmpty,
        reason: 'Locale "$code" is MISSING keys present in en',
      );
      expect(
        actual.difference(reference),
        isEmpty,
        reason: 'Locale "$code" has EXTRA keys absent from en',
      );
    }
  });

  test('no locale has an empty translation', () {
    for (final code in Local.supportedCodes) {
      keys[code]!.forEach((key, value) {
        expect(
          value,
          isNotEmpty,
          reason: 'Locale "$code" has an empty value for "$key"',
        );
      });
    }
  });

  test('placeholders match across locales', () {
    // `@name` params are substituted by `trParams`. If a translation drops one
    // the value simply never appears — the sentence silently loses its number,
    // room or guest name rather than failing.
    final placeholder = RegExp(r'@\w+');
    final reference = keys['en']!;

    for (final code in Local.supportedCodes) {
      keys[code]!.forEach((key, value) {
        final expected = placeholder
            .allMatches(reference[key]!)
            .map((m) => m.group(0))
            .toSet();
        final actual = placeholder
            .allMatches(value)
            .map((m) => m.group(0))
            .toSet();
        expect(
          actual,
          expected,
          reason: 'Locale "$code" key "$key" has mismatched placeholders',
        );
      });
    }
  });

  test('each locale self-identifies with its own code', () {
    // `"language"` is the one key whose value is deliberately different per
    // locale and equal to the locale code — a copy-paste between locale files
    // shows up here first.
    for (final code in Local.supportedCodes) {
      expect(keys[code]!['language'], code);
    }
  });

  test('paired punctuation is balanced', () {
    // The « » around `services.editingRequest` shipped unclosed in fr, es and
    // tr. Key-set and placeholder parity both passed it, because neither looks
    // inside the value — so the guest saw a dangling guillemet.
    const pairs = {'«': '»', '“': '”', '(': ')', '[': ']'};

    for (final code in Local.supportedCodes) {
      keys[code]!.forEach((key, value) {
        pairs.forEach((open, close) {
          expect(
            value.split(open).length,
            value.split(close).length,
            reason: 'Locale "$code" key "$key" has unbalanced $open $close',
          );
        });
        expect(
          '"'.allMatches(value).length.isEven,
          isTrue,
          reason: 'Locale "$code" key "$key" has an odd number of quote marks',
        );
      });
    }
  });
}
