import 'package:cached_network_image/cached_network_image.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Guards the empty-source short-circuit in [CustomImage]. Seeded menu items
/// (and venues/rooms without photos) arrive as `photo: null`, which callers pass
/// as `''`; without the guard the widget builds the bare storage host
/// `.../storage/` and 404s. Would fail if the `source.trim().isEmpty` branch is
/// reverted (a CachedNetworkImage would be built instead of the placeholder).
void main() {
  testWidgets('empty source renders placeholder, fires no network request', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: CustomImage(source: '', width: 70, height: 85)),
      ),
    );

    expect(find.byIcon(Icons.image_not_supported_outlined), findsOneWidget);
    expect(find.byType(CachedNetworkImage), findsNothing);
  });

  testWidgets('blank (whitespace) source is also treated as empty', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: CustomImage(source: '   ')),
      ),
    );

    expect(find.byIcon(Icons.image_not_supported_outlined), findsOneWidget);
    expect(find.byType(CachedNetworkImage), findsNothing);
  });
}
