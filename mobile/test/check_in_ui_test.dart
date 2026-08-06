import 'package:carlton/components/check_in/check_in_tab_bar.dart';
import 'package:carlton/components/custom_toggle_tile.dart';
import 'package:carlton/controllers/check_in/check_in_controller.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/views/check_in/tabs/identity_tab.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

/// Widget-level guards for the wizard's two load-bearing UI rules: the Identity
/// CTA stays inert until the guest is verified, and locked tabs swallow taps.
void main() {
  setUp(() {
    Get.reset();
    Get.put(CheckInService());
    Get.put(CheckInController());
  });

  tearDown(Get.reset);

  /// Without translations wired, `.tr` returns the raw key and every text
  /// assertion here would be checking 'checkIn.tabRoomKey' rather than English.
  Widget wrap(Widget child) => GetMaterialApp(
    translations: Local(),
    locale: const Locale('en'),
    home: Scaffold(body: child),
  );

  testWidgets('Identity Continue is disabled until verified', (tester) async {
    await tester.pumpWidget(wrap(const IdentityTab()));
    await tester.pump();

    final gate = tester.widget<AbsorbPointer>(
      find.byKey(const Key('identity-continue-gate')),
    );
    expect(gate.absorbing, isTrue, reason: 'CTA must be inert unverified');

    CheckInService.find.markIdentityVerified('SY-20480831');
    await tester.pump();

    final unlocked = tester.widget<AbsorbPointer>(
      find.byKey(const Key('identity-continue-gate')),
    );
    expect(unlocked.absorbing, isFalse);
    expect(find.textContaining('SY-20480831'), findsOneWidget);
  });

  testWidgets('locked tabs do not fire onTap', (tester) async {
    var tapped = -1;
    await tester.pumpWidget(
      wrap(
        CheckInTabBar(
          activeIndex: 0,
          furthestIndex: 0,
          onTap: (i) => tapped = i,
        ),
      ),
    );

    await tester.tap(find.text('Room Key'));
    await tester.pump();
    expect(tapped, -1, reason: 'locked tab must be inert');

    await tester.tap(find.text('Identity'));
    await tester.pump();
    expect(tapped, 0);
  });

  testWidgets('a completed tab is tappable for back navigation', (
    tester,
  ) async {
    var tapped = -1;
    // Guest has reached Room Key; Identity and Preferences are behind them.
    await tester.pumpWidget(
      wrap(
        CheckInTabBar(
          activeIndex: 2,
          furthestIndex: 2,
          onTap: (i) => tapped = i,
        ),
      ),
    );

    await tester.tap(find.text('Identity'));
    await tester.pump();
    expect(tapped, 0, reason: 'tapping a completed tab must navigate back');

    await tester.tap(find.text('Preferences'));
    await tester.pump();
    expect(tapped, 1);
  });

  testWidgets('going back keeps the furthest tab unlocked', (tester) async {
    final controller = Get.find<CheckInController>();
    controller.advanceTo(1);
    controller.advanceTo(2);
    expect(controller.furthestTab.value, 2);

    controller.goToTab(0);
    expect(controller.activeTab.value, 0);
    expect(
      controller.furthestTab.value,
      2,
      reason: 'furthestTab is a high-water mark, not the current position',
    );
    expect(controller.isTabUnlocked(2), isTrue);

    // Forward again still works.
    controller.goToTab(2);
    expect(controller.activeTab.value, 2);
  });

  testWidgets('CustomToggleTile reports changes and shows both lines', (
    tester,
  ) async {
    bool? received;
    await tester.pumpWidget(
      wrap(
        CustomToggleTile(
          title: 'Early Check-in',
          subtitle: 'Request early check-in when available',
          value: false,
          onChanged: (v) => received = v,
        ),
      ),
    );

    expect(find.text('Early Check-in'), findsOneWidget);
    expect(find.text('Request early check-in when available'), findsOneWidget);

    await tester.tap(find.byType(Switch));
    await tester.pump();
    expect(received, isTrue);
  });

  testWidgets('advanceTo unlocks the tab it moves to', (tester) async {
    final controller = Get.find<CheckInController>();
    expect(controller.isTabUnlocked(1), isFalse);

    controller.advanceTo(1);
    expect(controller.isTabUnlocked(1), isTrue);
    expect(controller.activeTab.value, 1);
    expect(controller.isTabUnlocked(2), isFalse);
  });
}
