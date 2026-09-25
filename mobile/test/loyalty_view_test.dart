import 'dart:io';

import 'package:carlton/bindings/binding.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/theme/theme.dart';
import 'package:carlton/views/account/loyalty_view.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

/// Renders the Loyalty screen end to end.
///
/// The screen is UI-only for now, so what is worth guarding is that the three
/// blocks actually reach the tree with the controller's figures in them — a
/// balance that silently renders as an unformatted `2450`, or a ledger that
/// drops its rows, is exactly the kind of regression a smoke pump catches.
void main() {
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    tempDir = await Directory.systemTemp.createTemp('carlton_loyalty_test');

    // `SettingsService.onInit` reads the stored locale through GetStorage,
    // which asks path_provider for a documents directory — a plugin channel
    // with no implementation on the test host.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.flutter.io/path_provider'),
          (call) async => tempDir.path,
        );
    await GetStorage.init();
    Get.put(SettingsService(), permanent: true);
  });

  tearDownAll(() {
    Get.reset();
    // Best-effort: GetStorage holds its box file open for the life of the
    // isolate, and Windows refuses to delete an open file.
    try {
      tempDir.deleteSync(recursive: true);
    } on FileSystemException {
      // ignored
    }
  });

  Future<void> pumpLoyalty(WidgetTester tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 1100));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      GetMaterialApp(
        translations: Local(),
        locale: const Locale('en'),
        theme: Themes.theme,
        initialBinding: LoyaltyBinding(),
        home: const LoyaltyView(),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('the balance card shows the formatted points balance', (
    tester,
  ) async {
    await pumpLoyalty(tester);

    // 2,450 and not 2450 — the grouping is what `formatPoints()` exists for.
    expect(find.text('2,450'), findsOneWidget);
    expect(find.text('Gold Member'), findsOneWidget);
    expect(find.text('850 pts to Platinum'), findsOneWidget);
  });

  testWidgets('the stats grid reports earned, redeemed, stays and net', (
    tester,
  ) async {
    await pumpLoyalty(tester);

    expect(find.text('3,150'), findsOneWidget);
    expect(find.text('700'), findsOneWidget);
    expect(find.text('6'), findsOneWidget);
    // Net change is signed, and 3,150 - 700 is the only place it comes from.
    expect(find.text('+2,450'), findsOneWidget);
  });

  testWidgets('every ledger entry names the reservation that moved it', (
    tester,
  ) async {
    await pumpLoyalty(tester);

    expect(find.text('4 Total'), findsOneWidget);
    expect(find.textContaining('RES-48219'), findsNWidgets(2));
    expect(find.textContaining('RES-47660'), findsNWidgets(2));
    // Redemptions read as debits even though the model stores them unsigned.
    expect(find.text('-400'), findsOneWidget);
  });
}
