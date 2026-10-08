import 'dart:io';

import 'package:carlton/controllers/account/account_controller.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

/// The delete-account dialog must warn about loyalty even when the balance
/// could not be read: deletion forfeits every point and voucher (LOY-23).
void main() {
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    tempDir = await Directory.systemTemp.createTemp('carlton_forfeit_test');
    // `formatPoints` reads the locale from SettingsService, whose onInit needs
    // GetStorage, which asks path_provider for a directory.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.flutter.io/path_provider'),
          (call) async => tempDir.path,
        );
    await GetStorage.init();
  });

  tearDownAll(() async {
    try {
      if (tempDir.existsSync()) await tempDir.delete(recursive: true);
    } on FileSystemException {
      // Windows keeps the box file open; left for the OS to reap.
    }
  });

  setUp(() {
    Get.testMode = true;
    Get.put<SettingsService>(SettingsService());
  });

  tearDown(Get.reset);

  String? message(int? points, int? vouchers) =>
      AccountController.forfeitMessage(points: points, vouchers: vouchers);

  test('nothing to lose adds no line', () {
    expect(message(0, 0), isNull);
  });

  test('an unread balance still warns the guest', () {
    expect(message(null, 0), AppTranslations.deleteForfeitUnknown);
    expect(message(500, null), AppTranslations.deleteForfeitUnknown);
    expect(message(null, null), AppTranslations.deleteForfeitUnknown);
  });

  test('known counts name what is lost', () {
    expect(message(500, 0), AppTranslations.deleteForfeitPoints('500'));
    expect(message(0, 2), AppTranslations.deleteForfeitVouchers('2'));
    expect(
      message(500, 2),
      AppTranslations.deleteForfeitBoth(points: '500', count: '2'),
    );
  });
}
