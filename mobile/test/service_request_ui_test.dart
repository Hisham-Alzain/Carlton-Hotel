import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/models/service_models.dart';
import 'package:carlton/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Pumps [child] in a viewport of a known width so a widget's laid-out size can
/// be compared against it. Uses the real app theme: [CustomFilledButton] layers
/// every one of its params onto `filledButtonTheme.style`, so under a bare
/// `ThemeData()` the whole style resolves to null and height is ignored.
Future<void> pumpInBox(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(
      theme: Themes().theme,
      home: Scaffold(
        body: SizedBox(width: 400, child: Column(children: [child])),
      ),
    ),
  );
}

void main() {
  group('CustomFilledButton width', () {
    testWidgets(
      'width: double.infinity fills its parent (regression: fixedSize drops '
      'an infinite width, so the CTA rendered at text width)',
      (tester) async {
        await pumpInBox(
          tester,
          CustomFilledButton(
            width: double.infinity,
            height: 52,
            onPressed: () {},
            child: const Text('Send Request'),
          ),
        );

        final size = tester.getSize(find.byType(FilledButton));
        expect(size.width, 400);
        expect(size.height, 52);
      },
    );

    testWidgets('a null width still sizes to the child, so two fit in a Row', (
      tester,
    ) async {
      await pumpInBox(
        tester,
        Row(
          children: [
            CustomFilledButton(onPressed: () {}, child: const Text('One')),
            CustomFilledButton(onPressed: () {}, child: const Text('Two')),
          ],
        ),
      );

      expect(tester.takeException(), isNull);
      final first = tester.getSize(find.byType(FilledButton).first);
      expect(first.width, lessThan(400));
    });
  });

  group('ServiceOption', () {
    test('etaLabel drops the "ETA: " prefix for use in prose', () {
      const option = ServiceOption(
        iconPath: 'assets/icons/svc_clean.svg',
        title: 'Room Cleaning',
        description: 'Full room service and tidying',
        eta: 'ETA: 45 min',
      );

      expect(option.etaLabel, '45 min');
      expect(option.eta, 'ETA: 45 min');
    });
  });
}
