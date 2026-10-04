import 'dart:io';

import 'package:carlton/components/sheets/airport_transfer_sheet.dart';
import 'package:carlton/controllers/services/airport_transfer_controller.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/localized.dart';
import 'package:carlton/models/transfer.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:intl/date_symbol_data_local.dart';

/// Pumps each step of the airport-transfer sheet with what `/public/transfers`
/// really returns (no description, no capacity) — a layout error in a step
/// only shows on the device as a grey box, so each is rendered here.
void main() {
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    await initializeDateFormatting();
    tempDir = await Directory.systemTemp.createTemp('carlton_transfer_test');
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
    try {
      tempDir.deleteSync(recursive: true);
    } on FileSystemException {
      // ignored — Windows keeps the storage file open.
    }
  });

  Future<AirportTransferController> pumpStep(
    WidgetTester tester,
    int step,
  ) async {
    await tester.binding.setSurfaceSize(const Size(393, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final c = Get.put(AirportTransferController());
    addTearDown(() => Get.delete<AirportTransferController>(force: true));
    c.transfers.assignAll([
      const Transfer(
        uuid: 'sedan',
        name: Localized({'en': 'Airport Transfer (Sedan)'}),
        priceUsd: '35.00',
      ),
      const Transfer(
        uuid: 'suv',
        name: Localized({'en': 'Airport Transfer (SUV)'}),
        priceUsd: '55.00',
      ),
    ]);
    c.selected.value = c.transfers.first;
    c.step.value = step;

    await tester.pumpWidget(
      GetMaterialApp(
        translations: Local(),
        locale: const Locale('en'),
        theme: Themes.theme,
        home: const Scaffold(
          body: SingleChildScrollView(
            padding: EdgeInsets.all(20),
            child: AirportTransferSheet(),
          ),
        ),
      ),
    );
    await tester.pump();
    return c;
  }

  testWidgets('step 1 — flight details renders', (tester) async {
    await pumpStep(tester, 0);
    expect(tester.takeException(), isNull);
    expect(find.text('Choose Your Car'), findsOneWidget);
  });

  testWidgets('step 2 — vehicle list renders the API cars', (tester) async {
    await pumpStep(tester, 1);
    expect(tester.takeException(), isNull);
    expect(find.text('Airport Transfer (Sedan)'), findsOneWidget);
    expect(find.text('Airport Transfer (SUV)'), findsOneWidget);
  });

  testWidgets('step 3 — confirm summary renders', (tester) async {
    await pumpStep(tester, 2);
    expect(tester.takeException(), isNull);
    expect(find.text('Confirm Booking'), findsOneWidget);
  });

  testWidgets('step 2 — while the catalogue loads', (tester) async {
    final c = await pumpStep(tester, 1);
    c.transfers.clear();
    c.loading.value = true;
    await tester.pump(const Duration(milliseconds: 100));
    expect(tester.takeException(), isNull);
    // The response lands: the list must replace the spinner without the
    // guest having to leave and reopen the step.
    c.transfers.add(
      const Transfer(
        uuid: 'late',
        name: Localized({'en': 'City Tour Shuttle'}),
        priceUsd: '25.00',
      ),
    );
    c.loading.value = false;
    await tester.pump();
    expect(tester.takeException(), isNull);
    expect(find.text('City Tour Shuttle'), findsOneWidget);
  });
}
