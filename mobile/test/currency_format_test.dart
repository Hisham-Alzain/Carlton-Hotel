import 'dart:io';

import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/models/currency.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

/// Guards the defect that made the currency picker unshippable: it swapped the
/// symbol without converting the amount, so a $150 room read "SYP 150" —
/// roughly four orders of magnitude wrong.
///
/// Every test here fails if the conversion is removed from [MoneyFormat].
void main() {
  late SettingsService settings;
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    tempDir = await Directory.systemTemp.createTemp('carlton_currency_test');

    // `SettingsService.onInit` reads the stored locale/currency through
    // GetStorage, which asks path_provider for a documents directory — a
    // plugin channel with no implementation on the test host. Answering it
    // with a temp dir is what lets the real service boot here.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.flutter.io/path_provider'),
          (call) async => tempDir.path,
        );
    await GetStorage.init();
  });

  tearDownAll(() async {
    // Best-effort: GetStorage holds its box file open for the life of the
    // isolate, and Windows refuses to delete an open file. A leftover temp dir
    // is not worth failing a green suite over.
    try {
      if (tempDir.existsSync()) await tempDir.delete(recursive: true);
    } on FileSystemException {
      // Left for the OS to reap.
    }
  });

  setUp(() {
    Get.testMode = true;
    settings = SettingsService();
    Get.put<SettingsService>(settings);
    ExchangeRates.override(null); // use the hand-maintained table
  });

  tearDown(Get.reset);

  void select(String code) => settings.currency.value = SettingsService
      .currencies
      .firstWhere((c) => c.value == code);

  group('conversion', () {
    test('base currency renders the amount unchanged', () {
      select('usd');
      expect(150.0.formatPrice().replaceAll(RegExp(r'[^0-9]'), ''), '150');
    });

    test('SYP multiplies by its rate — not just a symbol swap', () {
      select('syp');
      final formatted = 150.0.formatPrice();

      // The regression: '150' with an SYP symbol in front of it.
      expect(
        formatted.replaceAll(RegExp(r'[^0-9]'), ''),
        isNot('150'),
        reason: 'SYP rendered the raw USD amount — conversion was skipped',
      );

      // Compare digits only: NumberFormat inserts thousands separators, so
      // `contains('1950000')` never matches `£S1,950,000`.
      final expected = (150 * ExchangeRates.rateFor('syp')).round();
      expect(formatted.replaceAll(RegExp(r'[^0-9]'), ''), expected.toString());
    });

    test('TRY multiplies by its rate', () {
      select('try');
      final digits = 100.0.formatPrice().replaceAll(RegExp(r'[^0-9]'), '');
      expect(digits, isNot('100'));
      expect(
        double.parse(digits),
        closeTo(100 * ExchangeRates.rateFor('try'), 1),
      );
    });

    test('every shipped currency has a rate', () {
      for (final c in SettingsService.currencies) {
        expect(
          ExchangeRates.rateFor(c.value),
          greaterThan(0),
          reason: '${c.code} has no exchange rate — prices would render as 0',
        );
      }
    });

    test('an unknown code falls back to the base amount, never zero', () {
      expect(ExchangeRates.convert(150, 'xyz'), 150);
    });
  });

  group('presentation', () {
    test('converted amounts are marked approximate, the base is not', () {
      select('usd');
      expect(150.0.formatPrice(), isNot(startsWith('≈')));

      select('syp');
      expect(150.0.formatPrice(), startsWith('≈'));
    });

    test('live rates drop the approximate marker', () {
      select('syp');
      ExchangeRates.override({'usd': 1.0, 'syp': 14000.0, 'try': 42.0});
      addTearDown(() => ExchangeRates.override(null));

      expect(ExchangeRates.isLive, isTrue);
      expect(150.0.formatPrice(), isNot(startsWith('≈')));
      expect(ExchangeRates.rateFor('syp'), 14000.0);
    });

    test('a null or unparseable API string formats as zero, not a crash', () {
      select('usd');
      expect(MoneyFormat.usdString(null), contains('0'));
      expect(MoneyFormat.usdString('not-a-number'), contains('0'));
    });

    test('whole amounts drop their decimals', () {
      select('usd');
      expect(580.0.formatPrice(), isNot(contains('.00')));
    });
  });

  group('picker wiring', () {
    test('ships exactly USD, SYP and TRY', () {
      expect(SettingsService.currencies.map((c) => c.value).toList(), [
        'usd',
        'syp',
        'try',
      ]);
    });

    test('USD is the base every wire amount is quoted in', () {
      expect(ExchangeRates.baseCode, 'usd');
      expect(SettingsService.currencies.first.isBase, isTrue);
    });
  });
}
