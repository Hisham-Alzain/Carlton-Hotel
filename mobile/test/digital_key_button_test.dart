import 'package:carlton/components/check_in/digital_key_button.dart';
import 'package:carlton/l10n/local.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

Future<void> _pump(
  WidgetTester tester,
  DigitalKeyStatus status, {
  VoidCallback? onPressed,
}) {
  return tester.pumpWidget(
    GetMaterialApp(
      // Without these, `.tr` returns the raw key and every text assertion
      // below would be asserting on 'checkIn.addDigitalKey'.
      translations: Local(),
      locale: const Locale('en'),
      home: Scaffold(
        body: DigitalKeyButton(status: status, onPressed: onPressed ?? () {}),
      ),
    ),
  );
}

/// Guards the three-state key button. Collapsing it to a plain button would
/// lose the activating and activated states the design specifies.
void main() {
  testWidgets('renders a distinct label per state', (tester) async {
    await _pump(tester, DigitalKeyStatus.idle);
    expect(find.text('Add Digital Key to phone'), findsOneWidget);

    await _pump(tester, DigitalKeyStatus.activating);
    expect(find.text('Activating Digital Key…'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    await _pump(tester, DigitalKeyStatus.activated);
    expect(find.text('Activated Digital Key'), findsOneWidget);
  });

  testWidgets('activated state uses forestGreen', (tester) async {
    await _pump(tester, DigitalKeyStatus.activated);
    final card = tester.widget<Card>(find.byType(Card));
    expect(card.color, AppColors.forestGreen);
  });

  testWidgets('only the idle state is tappable', (tester) async {
    var taps = 0;
    await _pump(tester, DigitalKeyStatus.idle, onPressed: () => taps++);
    await tester.tap(find.byType(DigitalKeyButton));
    await tester.pump();
    expect(taps, 1);

    await _pump(tester, DigitalKeyStatus.activated, onPressed: () => taps++);
    await tester.tap(find.byType(DigitalKeyButton));
    await tester.pump();
    expect(taps, 1, reason: 'activated must swallow the tap');
  });

  testWidgets('service walks idle -> activating -> activated', (tester) async {
    Get.reset();
    Get.put(CheckInService());
    final service = CheckInService.find;

    expect(service.key.value, DigitalKeyStatus.idle);
    final pending = service.activateDigitalKey();
    await tester.pump();
    expect(service.key.value, DigitalKeyStatus.activating);

    await tester.pump(const Duration(seconds: 2));
    await pending;
    expect(service.key.value, DigitalKeyStatus.activated);
    Get.reset();
  });
}
