import 'dart:io';

import 'package:carlton/components/loyalty/loyalty_activity_section.dart';
import 'package:carlton/components/loyalty/loyalty_points_card.dart';
import 'package:carlton/components/loyalty/loyalty_stats_grid.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:intl/date_symbol_data_local.dart';

/// Renders the Loyalty blocks with what the API returns.
///
/// What is worth guarding is that real figures reach the tree formatted — a
/// balance that renders as an unformatted `12000`, a ledger row whose sign is
/// lost, or one that prints `null` for a movement with no booking — are the
/// regressions a smoke pump catches.
void main() {
  late Directory tempDir;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    await initializeDateFormatting();
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
    try {
      tempDir.deleteSync(recursive: true);
    } on FileSystemException {
      // ignored — Windows keeps the storage file open.
    }
  });

  const account = LoyaltyAccount(
    availablePoints: 12000,
    lifetimeEarnedPoints: 20000,
    lifetimeRedeemedPoints: 7000,
    program: LoyaltyProgram(earning: true, pointsDiscount: true),
    redeemValueUsd: 0.01,
  );

  Future<void> pump(WidgetTester tester, Widget child) async {
    await tester.binding.setSurfaceSize(const Size(430, 1100));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      GetMaterialApp(
        translations: Local(),
        locale: const Locale('en'),
        theme: Themes.theme,
        home: Scaffold(body: SingleChildScrollView(child: child)),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets(
    'the balance card groups the balance and shows what it is worth',
    (tester) async {
      await pump(tester, const LoyaltyPointsCard(account: account));

      expect(find.text('12,000'), findsOneWidget);
      // 12,000 points at $0.01 each.
      expect(find.textContaining('Worth about'), findsOneWidget);
    },
  );

  testWidgets('without a set point value the card shows no worth line', (
    tester,
  ) async {
    await pump(
      tester,
      const LoyaltyPointsCard(account: LoyaltyAccount(availablePoints: 500)),
    );

    expect(find.text('500'), findsOneWidget);
    expect(find.textContaining('Worth about'), findsNothing);
  });

  testWidgets('the stats grid reports lifetime earned and redeemed', (
    tester,
  ) async {
    await pump(tester, const LoyaltyStatsGrid(account: account));

    expect(find.text('20,000'), findsOneWidget);
    expect(find.text('7,000'), findsOneWidget);
  });

  testWidgets('every ledger row carries its sign and names its booking', (
    tester,
  ) async {
    await pump(
      tester,
      LoyaltyActivitySection(
        entries: [
          LoyaltyLedgerEntry(
            uuid: '1',
            type: LoyaltyEntryType.earn,
            label: 'Points earned',
            source: LoyaltyEntrySource.stay,
            points: 620,
            occurredAt: DateTime(2026, 9, 12),
            bookingCode: 'CARL-AAA11111',
          ),
          LoyaltyLedgerEntry(
            uuid: '2',
            type: LoyaltyEntryType.redeem,
            label: 'Points redeemed',
            points: -5000,
            occurredAt: DateTime(2026, 9, 14),
            bookingCode: 'CARL-BBB22222',
          ),
          LoyaltyLedgerEntry(
            uuid: '3',
            type: LoyaltyEntryType.adjust,
            label: 'Adjustment',
            source: LoyaltyEntrySource.manual,
            sourceLabel: 'Manual adjustment',
            points: -100,
            occurredAt: DateTime(2026, 9, 15),
          ),
        ],
      ),
    );

    expect(find.text('+620'), findsOneWidget);
    expect(find.text('-5,000'), findsOneWidget);
    expect(find.text('-100'), findsOneWidget);
    expect(find.textContaining('CARL-AAA11111'), findsOneWidget);
    // No booking: the source label stands in, and nothing prints "null".
    expect(find.textContaining('Manual adjustment'), findsOneWidget);
    expect(find.textContaining('null'), findsNothing);
  });

  testWidgets('an empty ledger shows the empty state', (tester) async {
    await pump(tester, const LoyaltyActivitySection(entries: []));

    expect(find.text('No points activity yet'), findsOneWidget);
  });
}
